<?php
/**
 * Job Runner – executes ONE LLM call per WP-Cron tick. Each tick runs in its
 * own fresh PHP worker, so LSAPI_MAX_PROCESS_TIME (typically 60-300s on shared
 * hosts) cannot kill a long multi-section job mid-flight: each tick performs
 * exactly one LLM round-trip (~30-90s) and exits, then the next tick is
 * scheduled in a brand new worker.
 *
 *   START handler     ──► create job, schedule first cron tick   ──► HTTP 200 {job_id}
 *   wp-cron tick #1   ──► step 0: setup (URL fetches, no LLM)    ──► EXIT
 *   wp-cron tick #2   ──► section 1: initial generation (1 LLM)  ──► EXIT
 *   wp-cron tick #3   ──► section 1: refinement pass 1 (1 LLM)   ──► EXIT
 *   wp-cron tick #4   ──► section 1: persist (extract + save)    ──► EXIT
 *   wp-cron tick #5   ──► section 2: initial generation (1 LLM)  ──► EXIT
 *   ...
 *   wp-cron tick #N   ──► finalize, mark_done                    ──► EXIT
 *
 *   poll endpoint     ──► reads transient, returns status (+log) to client
 *
 * Why split init and each refinement pass into their own tick: a single LLM
 * call can take 60s+, so two sequential calls in one worker can exceed a
 * 120s LSAPI ceiling. By making each tick exactly one LLM call, the worker
 * wall time is bounded by a single round-trip plus minor WP/DB overhead.
 *
 * WP-Cron is fired by:
 *   1. spawn_cron() (best-effort, called right after each schedule)
 *   2. cPanel cron OS hitting wp-cron.php every minute (backstop)
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

class Naano_Job_Runner
{
    /**
     * WP-Cron hook name that drives multi-step job execution. Each cron
     * tick fires this action which calls run($job_id), executing exactly
     * one step (one LLM call typically). After the step, run() schedules
     * the next tick if the job isn't terminal yet.
     */
    public const CRON_HOOK = "naano_run_job_cron";

    /**
     * Sentinel telling the registered shutdown handler that the worker is
     * exiting INTENTIONALLY (we either reached terminal status or scheduled
     * the next cron tick) — so it must NOT classify the exit as a host kill.
     *
     * Without this flag, every clean exit between two cron ticks would be
     * misread as "Worker terminated unexpectedly" and the job would get
     * mark_error()'d after its very first successful step. That was the
     * exact bug observed when a 5s setup tick ended with shutdown_unexpected
     * even though the host budget was 360s.
     *
     * Reset to false at the beginning of every run(), set to true once run()
     * has finished its bookkeeping (mark_done / mark_error / schedule_next).
     */
    private static bool $expected_exit = false;

    /**
     * Wire up the cron action. Called from the plugin bootstrap.
     */
    public static function register_hooks(): void
    {
        add_action(self::CRON_HOOK, [__CLASS__, "cron_handler"], 10, 2);
    }

    /**
     * Cron tick entry point. Receives the job_id from the scheduled event
     * args and runs exactly one step.
     *
     * @param string $job_id
     * @param string $tick_id Unique per-tick id (microtime-based) used to
     *                        bypass WP-Cron's 10-minute dedup on identical
     *                        hook+args combos.
     * @return void
     */
    public static function cron_handler(
        string $job_id,
        string $tick_id = "",
    ): void {
        Naano_Job_Manager::log($job_id, [
            "phase" => "cron_tick",
            "tick_id" => $tick_id,
        ]);
        self::run($job_id);
    }

    /**
     * Schedule the next step's cron tick. Uses time() - 1 so the event
     * is "due" immediately, and a microtime-based tick_id to dodge
     * WordPress's dedup of identical scheduled events within 10 minutes.
     *
     * Calls spawn_cron() in best-effort mode — this fires a non-blocking
     * wp_remote_post to wp-cron.php so the cron queue runs RIGHT NOW
     * instead of waiting for the next visitor or the OS-level cron tick.
     * If spawn_cron silently fails (which can happen on hardened hosts),
     * the cPanel cron OS configured to hit wp-cron.php every minute
     * will still pick up the pending event.
     *
     * @param string $job_id
     * @return void
     */
    public static function schedule_next_step(string $job_id): void
    {
        $tick_id = (string) microtime(true);
        $scheduled = wp_schedule_single_event(time() - 1, self::CRON_HOOK, [
            $job_id,
            $tick_id,
        ]);

        Naano_Job_Manager::log($job_id, [
            "phase" => "schedule_next_step",
            "tick_id" => $tick_id,
            "scheduled" => $scheduled !== false,
        ]);

        // Best-effort kick of the cron queue. Doesn't block if it fails.
        if (function_exists("spawn_cron")) {
            @spawn_cron();
        }
    }

    /**
     * Run ONE step of the job. Invoked by cron_handler() each time a
     * scheduled WP-Cron tick fires. Each step typically performs one
     * LLM call (~30-90s) which fits comfortably inside any reasonable
     * LSAPI_MAX_PROCESS_TIME budget (60-300s).
     *
     * After the step completes:
     *   - If the job is terminal (done/error), nothing more to do.
     *   - Otherwise, schedule the next cron tick to continue. The next
     *     tick runs in a fresh PHP worker with a fresh time budget.
     *
     * A registered shutdown handler captures any "silent death" of the
     * worker (LSAPI_MAX_PROCESS_TIME, memory_limit, fatal, etc.) and
     * marks the job as errored so the polling client sees a real error
     * instead of a frozen spinner.
     *
     * @param string $job_id
     * @return void
     */
    public static function run(string $job_id): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        // Reset sentinel for THIS run. The shutdown handler closure below
        // checks self::$expected_exit at PHP shutdown time — if we set it
        // to true before returning normally, the handler treats the exit
        // as intentional (terminal status or next-tick scheduled).
        self::$expected_exit = false;

        $start_ts = microtime(true);
        register_shutdown_function(static function () use ($job_id, $start_ts) {
            // Path A — clean exit. run() finished its bookkeeping
            // (mark_done / mark_error / schedule_next_step) and flipped
            // the sentinel. Nothing to do here.
            if (self::$expected_exit) {
                return;
            }

            $job = Naano_Job_Manager::get($job_id);
            if (
                !$job ||
                in_array($job["status"] ?? "", ["done", "error"], true)
            ) {
                return;
            }

            $err = error_get_last();
            $elapsed = round(microtime(true) - $start_ts, 2);

            // Ignore non-fatal warnings/notices that may have happened
            // earlier in the worker's life (e.g. ini_set warnings) —
            // those don't kill the process.
            $is_fatal =
                is_array($err) &&
                in_array(
                    $err["type"] ?? 0,
                    [
                        E_ERROR,
                        E_PARSE,
                        E_CORE_ERROR,
                        E_COMPILE_ERROR,
                        E_USER_ERROR,
                        E_RECOVERABLE_ERROR,
                    ],
                    true,
                );

            $reason = $is_fatal
                ? "PHP " .
                    self::php_error_label((int) $err["type"]) .
                    ": " .
                    ($err["message"] ?? "(no message)") .
                    " in " .
                    basename($err["file"] ?? "?") .
                    ":" .
                    ($err["line"] ?? "?") .
                    " after " .
                    $elapsed .
                    "s."
                : "Worker terminated unexpectedly after " .
                    $elapsed .
                    "s (LSAPI_MAX_PROCESS_TIME, memory_limit, or other host-imposed kill).";

            // Tiered recovery policy. We may be here because:
            //   1. LSAPI killed an LLM call that hit 120s (transient variance)
            //   2. The section content reliably triggers a model/host crash
            //   3. Hard fatal (PHP error, OOM, etc.)
            //
            // For (1) a retry usually succeeds — the killed worker doesn't
            // taint the next one. For (2) retrying loops forever, so after
            // one retry we skip to the next section instead of failing the
            // whole job. For (3) we still retry-then-skip; if the same hard
            // fatal hits us again on retry, we skip rather than block the
            // user's entire run on one bad section.
            //
            // Only generate_site supports skip — its work is naturally
            // section-by-section. update_section and enhance_prompt are
            // single-shot jobs with nothing to skip TO, so they fall through
            // to mark_error like before.
            self::handle_unexpected_shutdown($job_id, $reason);
        });

        $job = Naano_Job_Manager::get($job_id);
        if (!$job) {
            self::$expected_exit = true;
            return;
        }

        // Defense in depth: a previous tick may have legitimately reached a
        // terminal state (or even been marked error by an old buggy build).
        // Don't waste an LLM call re-running a step on a terminal job.
        if (in_array($job["status"] ?? "", ["done", "error"], true)) {
            self::$expected_exit = true;
            return;
        }

        // First tick → flip pending to running.
        if (($job["status"] ?? "") === "pending") {
            Naano_Job_Manager::update_status($job_id, "running");
        }

        $type = (string) ($job["type"] ?? "");
        $payload = (array) ($job["payload"] ?? []);
        $state = (array) ($job["state"] ?? []);

        try {
            switch ($type) {
                case "generate_site":
                    self::step_generate_site($job_id, $payload, $state);
                    break;
                case "update_section":
                    self::step_update_section($job_id, $payload, $state);
                    break;
                case "enhance_prompt":
                    self::step_enhance_prompt($job_id, $payload);
                    break;
                default:
                    throw new RuntimeException("Unknown job type: " . $type);
            }
        } catch (\Throwable $e) {
            $reason =
                "Exception: " .
                $e->getMessage() .
                " (" .
                basename($e->getFile()) .
                ":" .
                $e->getLine() .
                ")";
            Naano_Job_Manager::log($job_id, [
                "phase" => "exception",
                "message" => $e->getMessage(),
                "file" => basename($e->getFile()),
                "line" => $e->getLine(),
            ]);
            // Same skip policy as for shutdown kills: for generate_site we
            // try to keep the job moving instead of failing it on a single
            // bad section. For other job types, mark_error.
            self::handle_unexpected_shutdown($job_id, $reason);
            self::$expected_exit = true;
            return;
        }

        // After the step, see if more work remains. If yes, schedule
        // the next cron tick in a fresh worker.
        $job = Naano_Job_Manager::get($job_id);
        $status = (string) ($job["status"] ?? "");
        if ($status === "running" || $status === "pending") {
            self::schedule_next_step($job_id);
        }

        // Bookkeeping done — flip the sentinel BEFORE PHP starts its
        // shutdown phase so the registered handler treats this exit as
        // intentional. This is the fix for the "shutdown_unexpected after
        // 5s" false positive that was killing every job at its first tick.
        self::$expected_exit = true;
    }

    /**
     * Decide what to do when the registered shutdown handler detected an
     * unexpected worker termination. NEVER retries — the policy is to skip
     * past the failing step so the rest of the job can complete.
     *
     * Behaviour by job type and current state:
     *
     *   generate_site, cursor=0 (setup)
     *     → mark_error. There's nothing to skip TO; URL fetches and sibling
     *       page lookups must succeed before any section can be generated.
     *
     *   generate_site, cursor>=1, no pending_section
     *     → init was killed. The section has no HTML at all; record it as
     *       failed, advance cursor, schedule next tick to start the next
     *       section.
     *
     *   generate_site, cursor>=1, pending_section.sub == 'init'
     *     → init COMPLETED but a refinement pass was killed. The init HTML
     *       is intact in state — persist it as-is (without further polish),
     *       advance cursor, schedule next tick.
     *
     *   generate_site, cursor>=1, pending_section.sub == 'refine'
     *     → a later refinement pass was killed. We still have the most
     *       recent good HTML (either initial or a previous refine output).
     *       Persist it as-is, advance cursor, schedule next tick.
     *
     *   update_section, enhance_prompt
     *     → mark_error. Single-section jobs with nothing to skip toward.
     *
     * The skipped section IDs are accumulated in state.failed_sections so
     * the UI can mention which parts of the page didn't get the polish
     * they would have under a normal run.
     */
    private static function handle_unexpected_shutdown(
        string $job_id,
        string $reason,
    ): void {
        $job = Naano_Job_Manager::get($job_id);
        if (!$job || in_array($job["status"] ?? "", ["done", "error"], true)) {
            return;
        }

        $type = (string) ($job["type"] ?? "");

        // Only generate_site has a meaningful "next step" to skip toward.
        // Other job types are single-shot and must mark_error like before.
        if ($type !== "generate_site") {
            Naano_Job_Manager::mark_error($job_id, $reason);
            return;
        }

        $state = (array) ($job["state"] ?? []);
        $cursor = (int) ($state["cursor"] ?? 0);

        // cursor=0 means setup never finished. We can't skip to a section
        // when the prerequisites (URL fetches, sibling pages, nav menu)
        // aren't in state yet.
        if ($cursor === 0) {
            Naano_Job_Manager::mark_error($job_id, $reason);
            return;
        }

        $payload = (array) ($job["payload"] ?? []);
        $sections = (array) ($payload["sections"] ?? []);
        $section_count = count($sections);
        $section_idx = $cursor - 1;
        $section_type =
            $section_idx >= 0 && $section_idx < $section_count
                ? sanitize_text_field($sections[$section_idx])
                : "";
        $section_id = $section_type ? sanitize_title($section_type) : "";

        $pending = $state["pending_section"] ?? null;
        $has_pending_html =
            is_array($pending) &&
            ($pending["section_id"] ?? "") === $section_id &&
            trim((string) ($pending["html"] ?? "")) !== "";

        $failed_sections = (array) ($state["failed_sections"] ?? []);

        if ($has_pending_html) {
            // A refinement pass died but we have good (or at least usable)
            // init/intermediate HTML. Persist it as-is so the user gets a
            // section instead of a hole.
            $current_html = (string) $pending["html"];
            $page_id = (int) ($payload["page_id"] ?? 0);

            try {
                $section_html = Naano_HTML_Sanitizer::extract_section(
                    $current_html,
                    $section_id,
                );
                if (!$section_html) {
                    $section_html = $current_html;
                }
                if ($page_id > 0 && $section_id !== "") {
                    $section_manager = new Naano_Section_Manager();
                    $section_manager->update_section(
                        $page_id,
                        $section_id,
                        $section_html,
                        $section_type,
                    );
                }
            } catch (\Throwable $e) {
                // If even persisting fails, log it but still advance —
                // we'd rather lose one section than block the whole job.
                Naano_Job_Manager::log($job_id, [
                    "phase" => "skip_persist_failed",
                    "section_id" => $section_id,
                    "message" => $e->getMessage(),
                ]);
            }

            Naano_Job_Manager::log($job_id, [
                "phase" => "skip_refine_killed",
                "section_id" => $section_id,
                "reason" => $reason,
                "note" =>
                    "Refinement worker was killed; persisted the latest pre-refine HTML and moved on.",
            ]);
        } else {
            // Init was killed, no HTML to salvage. Record the section as
            // failed and move on.
            $failed_sections[] = [
                "section_id" => $section_id,
                "section_type" => $section_type,
                "reason" => $reason,
            ];
            Naano_Job_Manager::log($job_id, [
                "phase" => "skip_init_killed",
                "section_id" => $section_id,
                "reason" => $reason,
                "note" =>
                    "Initial generation worker was killed; section skipped (no HTML produced).",
            ]);
        }

        // Advance past the failing section regardless of which sub-step
        // died. The next tick starts cleanly on the next section.
        $state["cursor"] = $cursor + 1;
        $state["pending_section"] = null;
        $state["failed_sections"] = $failed_sections;
        Naano_Job_Manager::set_state($job_id, $state);

        // Schedule the next tick. The job stays in "running" status —
        // it's not an error, we just lost one section.
        self::schedule_next_step($job_id);
    }

    /**
     * Map a PHP error level constant to a human-readable label.
     */
    private static function php_error_label(int $type): string
    {
        $map = [
            E_ERROR => "E_ERROR",
            E_WARNING => "E_WARNING",
            E_PARSE => "E_PARSE",
            E_NOTICE => "E_NOTICE",
            E_CORE_ERROR => "E_CORE_ERROR",
            E_CORE_WARNING => "E_CORE_WARNING",
            E_COMPILE_ERROR => "E_COMPILE_ERROR",
            E_COMPILE_WARNING => "E_COMPILE_WARNING",
            E_USER_ERROR => "E_USER_ERROR",
            E_USER_WARNING => "E_USER_WARNING",
            E_USER_NOTICE => "E_USER_NOTICE",
            E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
            E_DEPRECATED => "E_DEPRECATED",
            E_USER_DEPRECATED => "E_USER_DEPRECATED",
        ];
        return $map[$type] ?? "E_UNKNOWN(" . $type . ")";
    }

    // -------------------------------------------------------------------------
    // generate_site – multi-step
    // -------------------------------------------------------------------------

    /**
     * State shape:
     *   [
     *     'cursor'          => int,     // 0 = setup, 1..N = section index, N+1 = finalize
     *     'desc_refs'       => array,   // URL refs fetched during setup, reused per section
     *     'site_pages'      => array,   // sibling Naano pages
     *     'nav_menu'        => string,  // rendered WP nav menu HTML
     *     'pending_section' => array|null,
     *         //   [
     *         //     'section_type' => string,    // raw section name from payload
     *         //     'section_id'   => string,    // sanitized id used in markers
     *         //     'sub'          => 'init' | 'refine' | null,
     *         //     'pass'         => int,       // 0 before any refine; 1..P after each refine
     *         //     'html'         => string,    // current best HTML (raw, pre-extract)
     *         //   ]
     *         // Persists between ticks WITHIN the same section so each tick
     *         // does exactly ONE LLM call (init OR a single refinement pass).
     *   ]
     *
     * Why split init and refinement across ticks: a host with a 120s LSAPI
     * ceiling cannot host 2 sequential ~60s LLM calls in one worker. By
     * making each tick = exactly one LLM call, the worker's wall time is
     * bounded by the time of a single LLM call (typically 30-90s) plus a
     * few hundred ms of WP/DB overhead, which fits comfortably under any
     * reasonable host limit.
     */
    private static function step_generate_site(
        string $job_id,
        array $payload,
        array $state,
    ): void {
        $page_id = (int) ($payload["page_id"] ?? 0);
        $description = (string) ($payload["description"] ?? "");
        $sections = (array) ($payload["sections"] ?? []);
        $initial_refs = (array) ($payload["initial_references"] ?? []);
        $wp_menu_id = (int) ($payload["wp_menu_id"] ?? 0);

        if (!$page_id || !$description || empty($sections)) {
            throw new RuntimeException("Invalid generate_site payload.");
        }

        $cursor = (int) ($state["cursor"] ?? 0);
        $section_count = count($sections);
        $refine_passes = max(
            0,
            (int) get_option("naano_initial_refinement_passes", 1),
        );

        // ── Step 0: setup (URL fetches, sibling pages, nav menu) ────────────
        if ($cursor === 0) {
            Naano_Job_Manager::log($job_id, [
                "phase" => "generate_site:setup",
                "page_id" => $page_id,
                "section_count" => $section_count,
                "sections" => $sections,
                "refine_passes" => $refine_passes,
            ]);

            $desc_refs = [];

            preg_match_all(
                '/https?:\/\/[^\s,"\'<>]+/i',
                $description,
                $url_matches,
            );
            foreach (array_unique($url_matches[0] ?? []) as $desc_url) {
                $desc_url = rtrim($desc_url, '.,;)\'"');
                $desc_refs[] = [
                    "url" => $desc_url,
                    "notes" => "mentioned in site description",
                    "content" => Naano_Reference_Manager::fetch_url_text(
                        $desc_url,
                    ),
                ];
            }

            foreach ($initial_refs as $ref) {
                $ref_url = esc_url_raw($ref["url"] ?? "");
                if (!$ref_url) {
                    continue;
                }
                $desc_refs[] = [
                    "url" => $ref_url,
                    "notes" => sanitize_text_field($ref["notes"] ?? ""),
                    "content" => Naano_Reference_Manager::fetch_url_text(
                        $ref_url,
                    ),
                ];
            }

            $naano_pages = get_posts([
                "post_type" => "page",
                "post_status" => ["publish", "draft"],
                "posts_per_page" => -1,
                "meta_key" => "_naano_sections",
                "exclude" => [$page_id],
            ]);
            $site_pages = [];
            foreach ($naano_pages as $np) {
                $site_pages[] = [
                    "title" => $np->post_title,
                    "url" => get_permalink($np->ID),
                ];
            }

            $nav_menu = $wp_menu_id ? self::render_nav_menu($wp_menu_id) : "";

            Naano_Job_Manager::set_state($job_id, [
                "cursor" => 1,
                "desc_refs" => $desc_refs,
                "site_pages" => $site_pages,
                "nav_menu" => $nav_menu,
                "pending_section" => null,
            ]);

            return;
        }

        // ── Steps 1..N: one LLM call per worker (init OR one refine pass) ───
        $section_idx = $cursor - 1;
        if ($section_idx < $section_count) {
            $section_type = sanitize_text_field($sections[$section_idx]);
            if (!$section_type) {
                // Skip empty entries cleanly.
                $state["cursor"] = $cursor + 1;
                $state["pending_section"] = null;
                Naano_Job_Manager::set_state($job_id, $state);
                return;
            }
            $section_id = sanitize_title($section_type);

            // Rebuild the Prompt Builder + system prompt fresh on every tick
            // (these are cheap; the expensive part is the LLM call, which is
            // the only thing we ever do once per tick).
            $router = self::build_router();
            $builder = new Naano_Prompt_Builder();
            $vars = get_option("naano_variables", []);
            $builder->set_variables(is_array($vars) ? $vars : []);

            $desc_refs = (array) ($state["desc_refs"] ?? []);
            if (!empty($desc_refs)) {
                $builder->set_references($desc_refs);
            }
            $builder->set_site_pages((array) ($state["site_pages"] ?? []));
            $nav_menu = (string) ($state["nav_menu"] ?? "");
            if ($nav_menu !== "") {
                $builder->set_nav_menu($nav_menu);
            }

            $system = self::augment_system_with_design_rules(
                $builder->build_system_prompt(),
            );
            $user_message = $builder->build_single_section_message(
                $description,
                $section_type,
            );

            $pending = $state["pending_section"] ?? null;
            // Defensive: if state was carried over from a partially-done
            // previous section (cursor mismatch), drop it and restart fresh.
            if (
                is_array($pending) &&
                ($pending["section_id"] ?? "") !== $section_id
            ) {
                $pending = null;
            }

            // ── Sub-step A: initial generation (1 LLM call) ─────────────────
            if (!is_array($pending)) {
                Naano_Job_Manager::log($job_id, [
                    "phase" => "generate_site:section_init",
                    "index" => $section_idx + 1,
                    "of" => $section_count,
                    "section_type" => $section_type,
                    "section_id" => $section_id,
                ]);

                $messages = [["role" => "user", "content" => $user_message]];
                $llm_start = microtime(true);
                $html = $router->generate($system, $messages, []);
                Naano_Job_Manager::log($job_id, [
                    "phase" => "generate_site:section_init_done",
                    "section_id" => $section_id,
                    "elapsed_s" => round(microtime(true) - $llm_start, 2),
                    "length" => strlen((string) $html),
                ]);

                $state["pending_section"] = [
                    "section_type" => $section_type,
                    "section_id" => $section_id,
                    "sub" => "init",
                    "pass" => 0,
                    "html" => (string) $html,
                ];
                Naano_Job_Manager::set_state($job_id, $state);
                return;
            }

            // ── Sub-step B: refinement passes (1 LLM call per tick) ─────────
            $current_pass = (int) ($pending["pass"] ?? 0);
            $current_html = (string) ($pending["html"] ?? "");

            if ($current_pass < $refine_passes && trim($current_html) !== "") {
                $next_pass = $current_pass + 1;
                Naano_Job_Manager::log($job_id, [
                    "phase" => "generate_site:section_refine",
                    "section_id" => $section_id,
                    "pass" => $next_pass,
                    "of" => $refine_passes,
                ]);

                $history = [
                    ["role" => "user", "content" => $user_message],
                    ["role" => "assistant", "content" => $current_html],
                    [
                        "role" => "user",
                        "content" => self::build_refinement_prompt(
                            $section_id,
                            $next_pass,
                            $refine_passes,
                        ),
                    ],
                ];

                $llm_start = microtime(true);
                $refined = $router->generate($system, $history, []);
                Naano_Job_Manager::log($job_id, [
                    "phase" => "generate_site:section_refine_done",
                    "section_id" => $section_id,
                    "pass" => $next_pass,
                    "elapsed_s" => round(microtime(true) - $llm_start, 2),
                    "length" => strlen((string) $refined),
                ]);

                $refined_str = (string) $refined;
                if (trim($refined_str) !== "") {
                    $current_html = $refined_str;
                }

                $state["pending_section"] = [
                    "section_type" => $section_type,
                    "section_id" => $section_id,
                    "sub" => "refine",
                    "pass" => $next_pass,
                    "html" => $current_html,
                ];
                Naano_Job_Manager::set_state($job_id, $state);
                return;
            }

            // ── Sub-step C: persist (no LLM call) ───────────────────────────
            $section_html = Naano_HTML_Sanitizer::extract_section(
                $current_html,
                $section_id,
            );
            if (!$section_html) {
                $section_html = $current_html;
            }

            $section_manager = new Naano_Section_Manager();
            $section_manager->update_section(
                $page_id,
                $section_id,
                $section_html,
                $section_type,
            );

            Naano_Job_Manager::log($job_id, [
                "phase" => "generate_site:section_persisted",
                "section_id" => $section_id,
                "html_length" => strlen($section_html),
            ]);

            // Advance to the next section + clear pending.
            $state["cursor"] = $cursor + 1;
            $state["pending_section"] = null;
            Naano_Job_Manager::set_state($job_id, $state);
            return;
        }

        // ── Step N+1: finalize ──────────────────────────────────────────────
        $section_manager = new Naano_Section_Manager();
        $failed_sections = (array) ($state["failed_sections"] ?? []);

        // Persist failed sections to a per-page meta so the builder can
        // surface them as a "Failed sections" list with retry buttons even
        // if the user refreshes the page hours later. mark_section_recovered
        // is called from the update_section job to remove individual entries
        // when they're successfully regenerated.
        $section_manager->set_failed_sections($page_id, $failed_sections);

        $result = [
            "page_id" => $page_id,
            "sections" => $section_manager->get_sections($page_id),
            "html" => $section_manager->get_assembled_html($page_id),
            // List of sections that were skipped due to LSAPI kills /
            // exceptions during their generation. Empty on a clean run.
            "failed_sections" => $failed_sections,
        ];

        Naano_Job_Manager::log($job_id, [
            "phase" => "generate_site:done",
            "section_count" => count($result["sections"]),
            "failed_count" => count($failed_sections),
        ]);

        Naano_Job_Manager::mark_done($job_id, $result);
    }

    // -------------------------------------------------------------------------
    // update_section – single-step (one LLM call + 1 refinement = ~30-60s, fits
    // in a fresh worker's budget)
    // -------------------------------------------------------------------------

    /**
     * State shape (this job type only ever updates ONE section):
     *   [
     *     'phase'        => 'init' | 'refine' | null,
     *     'pass'         => int,    // 0 before any refine; 1..P after each refine
     *     'html'         => string, // current best HTML (raw, pre-extract)
     *     'message'      => string, // user message (cached, for refine prompts)
     *     'history'      => array,  // initial history (cached, for refine prompts)
     *     'system'       => string, // assembled system prompt (cached)
     *   ]
     *
     * Each tick performs exactly ONE LLM call (init or one refinement pass)
     * so the worker wall time stays well under any LSAPI ceiling.
     */
    private static function step_update_section(
        string $job_id,
        array $payload,
        array $state,
    ): void {
        $page_id = (int) ($payload["page_id"] ?? 0);
        $section_id = (string) ($payload["section_id"] ?? "");
        $instruction = (string) ($payload["instruction"] ?? "");
        $assets = (array) ($payload["assets"] ?? []);
        $redirects = (array) ($payload["redirects"] ?? []);
        $client_refs = (array) ($payload["client_refs"] ?? []);
        $wp_menu_id = (int) ($payload["wp_menu_id"] ?? 0);

        if (!$page_id || !$section_id || !$instruction) {
            throw new RuntimeException("Invalid update_section payload.");
        }

        $refine_passes = max(
            0,
            (int) get_option("naano_update_refinement_passes", 1),
        );

        $phase = (string) ($state["phase"] ?? "");

        // ── Sub-step A: initial generation (1 LLM call) ─────────────────────
        if ($phase === "") {
            Naano_Job_Manager::log($job_id, [
                "phase" => "update_section:start",
                "page_id" => $page_id,
                "section_id" => $section_id,
                "refine_passes" => $refine_passes,
            ]);

            $section_manager = new Naano_Section_Manager();
            $ref_manager = new Naano_Reference_Manager();

            $all_sections = $section_manager->get_sections($page_id);
            $context = Naano_Payload_Compressor::compress_context(
                $all_sections,
                $section_id,
            );

            $images = $ref_manager->prepare_images_for_llm(
                $page_id,
                $section_id,
            );

            if (!empty($client_refs)) {
                $url_refs = array_values(
                    array_filter(
                        $client_refs,
                        static fn($r) => ($r["type"] ?? "") === "url" &&
                            !empty($r["url"]),
                    ),
                );
                foreach ($url_refs as &$ref) {
                    $ref["url"] = esc_url_raw($ref["url"]);
                    $ref["content"] = Naano_Reference_Manager::fetch_url_text(
                        $ref["url"],
                    );
                }
                unset($ref);
            } else {
                $url_refs = $ref_manager->prepare_url_references(
                    $page_id,
                    $section_id,
                );
            }

            $builder = new Naano_Prompt_Builder();
            $vars = get_option("naano_variables", []);
            $builder->set_variables(is_array($vars) ? $vars : []);
            $builder->set_references($url_refs);
            $builder->set_assets($assets);
            $builder->set_redirects($redirects);
            if ($wp_menu_id) {
                $builder->set_nav_menu(self::render_nav_menu($wp_menu_id));
            }

            $conversation = new Naano_Conversation();
            $system = self::augment_system_with_design_rules(
                $builder->build_system_prompt(),
            );
            $history = $conversation->get_trimmed($page_id);
            $message = $builder->build_section_message(
                $section_id,
                $instruction,
                $context,
            );

            $messages = $history;
            $messages[] = ["role" => "user", "content" => $message];

            $router = self::build_router();
            $llm_start = microtime(true);
            $html = $router->generate($system, $messages, $images);
            Naano_Job_Manager::log($job_id, [
                "phase" => "update_section:init_done",
                "elapsed_s" => round(microtime(true) - $llm_start, 2),
                "length" => strlen((string) $html),
            ]);

            Naano_Job_Manager::set_state($job_id, [
                "phase" => "init",
                "pass" => 0,
                "html" => (string) $html,
                "message" => $message,
                "history" => $history,
                "system" => $system,
            ]);
            return;
        }

        // ── Sub-step B: refinement passes (1 LLM call per tick) ─────────────
        $current_html = (string) ($state["html"] ?? "");
        $current_pass = (int) ($state["pass"] ?? 0);
        $message = (string) ($state["message"] ?? "");
        $history = (array) ($state["history"] ?? []);
        $system = (string) ($state["system"] ?? "");

        if ($current_pass < $refine_passes && trim($current_html) !== "") {
            $next_pass = $current_pass + 1;
            Naano_Job_Manager::log($job_id, [
                "phase" => "update_section:refine",
                "section_id" => $section_id,
                "pass" => $next_pass,
                "of" => $refine_passes,
            ]);

            $messages = $history;
            $messages[] = ["role" => "user", "content" => $message];
            $messages[] = ["role" => "assistant", "content" => $current_html];
            $messages[] = [
                "role" => "user",
                "content" => self::build_refinement_prompt(
                    $section_id,
                    $next_pass,
                    $refine_passes,
                ),
            ];

            $router = self::build_router();
            $llm_start = microtime(true);
            $refined = $router->generate($system, $messages, []);
            Naano_Job_Manager::log($job_id, [
                "phase" => "update_section:refine_done",
                "section_id" => $section_id,
                "pass" => $next_pass,
                "elapsed_s" => round(microtime(true) - $llm_start, 2),
                "length" => strlen((string) $refined),
            ]);

            $refined_str = (string) $refined;
            if (trim($refined_str) !== "") {
                $current_html = $refined_str;
            }

            $state["phase"] = "refine";
            $state["pass"] = $next_pass;
            $state["html"] = $current_html;
            Naano_Job_Manager::set_state($job_id, $state);
            return;
        }

        // ── Sub-step C: persist + mark_done (no LLM call) ───────────────────
        $section_html = Naano_HTML_Sanitizer::extract_section(
            $current_html,
            $section_id,
        );
        if (!$section_html) {
            $section_html = $current_html;
        }

        $section_manager = new Naano_Section_Manager();
        $section_manager->update_section($page_id, $section_id, $section_html);

        // If this section was previously in the failed list (e.g. a host
        // kill during the original generate_site run), mark it as recovered
        // so it disappears from the "Failed sections" list in the builder.
        $section_manager->mark_section_recovered($page_id, $section_id);

        $conversation = new Naano_Conversation();
        $conversation->add_message($page_id, "user", $message);
        $conversation->add_message($page_id, "assistant", $section_html);

        Naano_Job_Manager::log($job_id, [
            "phase" => "update_section:done",
            "html_length" => strlen($section_html),
        ]);

        Naano_Job_Manager::mark_done($job_id, [
            "section_id" => $section_id,
            "section_html" => $section_html,
        ]);
    }

    // -------------------------------------------------------------------------
    // enhance_prompt – single-step (one LLM call, very fast)
    // -------------------------------------------------------------------------

    private static function step_enhance_prompt(
        string $job_id,
        array $payload,
    ): void {
        $raw_text = (string) ($payload["raw_text"] ?? "");
        $context = (string) ($payload["context"] ?? "initial");
        $page_name = (string) ($payload["page_name"] ?? "");

        Naano_Job_Manager::log($job_id, [
            "phase" => "enhance_prompt:start",
            "context" => $context,
            "raw_text_length" => strlen($raw_text),
        ]);

        if (!$raw_text) {
            throw new RuntimeException("Empty raw_text in enhance_prompt.");
        }

        $available_sections = [
            "header",
            "hero",
            "features",
            "about",
            "services",
            "pricing",
            "testimonials",
            "contact",
            "footer",
        ];

        if ($context === "initial") {
            $system =
                "LANGUAGE RULE (CRITICAL):\n" .
                "- You MUST respond in the SAME language as the user's input.\n" .
                "- Never switch language unless the user explicitly requests it.\n\n" .
                "You are a senior website strategist and AI website planning assistant.\n" .
                ($page_name
                    ? "The website/page name is \"" . $page_name . "\".\n"
                    : "") .
                "The user will provide a rough website idea, incomplete notes, or a short business description.\n\n" .
                "Your job is to transform the user's input into a PROFESSIONAL website generation brief optimized for:\n" .
                "- AI website builders\n" .
                "- web designers\n" .
                "- UI/UX planning\n" .
                "- landing page generation\n\n" .
                "The rewritten brief MUST:\n" .
                "- Preserve ALL user requirements\n" .
                "- Expand vague ideas into realistic website expectations\n" .
                "- Be clear, structured, and actionable\n" .
                "- Focus on usability, hierarchy, clarity, trust, and conversion\n" .
                "- Mention the target audience\n" .
                "- Mention the visual style and brand tone when relevant\n" .
                "- Mention important sections and content\n" .
                "- Mention key calls-to-action when relevant\n" .
                "- Mention useful features and functionality\n" .
                "- Mention responsive/mobile-friendly expectations\n" .
                "- Mention SEO or trust-building elements if relevant\n" .
                "- Avoid generic marketing buzzwords and filler language\n\n" .
                "IMPORTANT:\n" .
                "- Do NOT invent fake business details\n" .
                "- Do NOT generate HTML, CSS, or code\n" .
                "- Do NOT explain your reasoning\n" .
                "- Do NOT use markdown\n" .
                "- The output will be directly used by an AI website generation system\n\n" .
                "You must also suggest the BEST website sections for this project.\n" .
                "Available default sections:\n" .
                implode(", ", $available_sections) .
                "\n\n" .
                "You MAY suggest custom sections if they are highly relevant.\n\n" .
                "SECTION RULES:\n" .
                "- Only suggest sections that improve user experience and conversion flow\n" .
                "- Avoid redundant or unnecessary sections\n" .
                "- Keep the section order logical\n\n" .
                "Respond ONLY with valid JSON using this exact schema:\n\n" .
                "{\n" .
                '  "enhanced_prompt": "string",' .
                "\n" .
                '  "suggested_sections": ["section1", "section2"]' .
                "\n" .
                "}";
        } else {
            $system =
                "LANGUAGE RULE (CRITICAL):\n" .
                "- You MUST respond in the SAME language as the user's input.\n" .
                "- Never switch language unless explicitly requested.\n\n" .
                "You are a senior UI/UX website editing assistant.\n" .
                ($page_name
                    ? "The website/page name is \"" . $page_name . "\".\n"
                    : "") .
                "The user will provide a rough instruction for modifying an existing website section or page.\n\n" .
                "Your job is to rewrite the request into a PROFESSIONAL and PRECISE website editing instruction suitable for:\n" .
                "- AI website editors\n" .
                "- frontend designers\n" .
                "- UI/UX systems\n" .
                "- HTML/CSS generation pipelines\n\n" .
                "The rewritten instruction MUST:\n" .
                "- Preserve the user's intent\n" .
                "- Clarify ambiguous requests\n" .
                "- Be visually specific and actionable\n" .
                "- Mention layout expectations when relevant\n" .
                "- Mention spacing, alignment, or hierarchy when relevant\n" .
                "- Mention typography, colors, or styling when relevant\n" .
                "- Mention responsiveness/mobile behavior when relevant\n" .
                "- Mention animations or interactions only if useful\n" .
                "- Improve clarity, usability, accessibility, and conversion when appropriate\n" .
                "- Avoid generic filler language\n\n" .
                "IMPORTANT:\n" .
                "- Do NOT generate HTML, CSS, or code\n" .
                "- Do NOT explain your reasoning\n" .
                "- Do NOT use markdown\n" .
                "- Do NOT modify unrelated parts of the page\n" .
                "- The output will be directly used by an AI website editing system\n\n" .
                "Respond ONLY with the rewritten editing instruction.";
        }

        $messages = [["role" => "user", "content" => $raw_text]];
        $router = self::build_router();

        Naano_Job_Manager::log($job_id, [
            "phase" => "enhance_prompt:llm_call_begin",
            "system_prompt_length" => strlen($system),
            "messages_count" => count($messages),
        ]);

        $llm_start = microtime(true);
        $result = $router->generate($system, $messages);
        $llm_elapsed = round(microtime(true) - $llm_start, 2);

        Naano_Job_Manager::log($job_id, [
            "phase" => "enhance_prompt:llm_response",
            "elapsed_s" => $llm_elapsed,
            "length" => strlen($result),
            "preview" => substr($result, 0, 300),
        ]);

        if ($context === "initial") {
            $enhanced = $raw_text;
            $suggested = $available_sections;

            $cleaned = trim($result);

            // The system prompt asks for valid JSON; the LLM occasionally
            // wraps it in ```json fences. Strip those before decoding.
            $json_candidate = preg_replace(
                '/^```(?:json)?\s*|\s*```$/i',
                "",
                $cleaned,
            );

            $decoded = json_decode((string) $json_candidate, true);
            if (is_array($decoded)) {
                $maybe_enhanced =
                    $decoded["enhanced_prompt"] ??
                    ($decoded["enhanced_text"] ?? null);
                if (
                    is_string($maybe_enhanced) &&
                    trim($maybe_enhanced) !== ""
                ) {
                    $enhanced = trim($maybe_enhanced);
                }
                if (
                    isset($decoded["suggested_sections"]) &&
                    is_array($decoded["suggested_sections"])
                ) {
                    $clean_sections = [];
                    foreach ($decoded["suggested_sections"] as $s) {
                        if (is_string($s)) {
                            $s = trim(strtolower($s));
                            if ($s !== "") {
                                $clean_sections[] = $s;
                            }
                        }
                    }
                    if (!empty($clean_sections)) {
                        $suggested = array_values($clean_sections);
                    }
                }
            } elseif (
                preg_match(
                    '/---ENHANCED_PROMPT---\s*([\s\S]*?)\s*---SUGGESTED_SECTIONS---\s*([\s\S]*)$/i',
                    $cleaned,
                    $m,
                )
            ) {
                // Legacy delimited format kept as fallback.
                $enhanced = trim($m[1]);
                $suggested = array_values(
                    array_filter(
                        array_map(
                            "trim",
                            explode(",", strtolower(trim($m[2]))),
                        ),
                        static fn($s) => $s !== "",
                    ),
                );
            }
            // Otherwise: keep $enhanced = $raw_text. We never dump a raw,
            // unparsed LLM response into the user's textarea.

            Naano_Job_Manager::mark_done($job_id, [
                "enhanced_text" => $enhanced,
                "suggested_sections" => $suggested,
            ]);
            return;
        }

        Naano_Job_Manager::mark_done($job_id, [
            "enhanced_text" => trim($result) ?: $raw_text,
        ]);
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Append the design-quality / output-format directives to a system prompt
     * built by Naano_Prompt_Builder. Pure string transformation — no LLM call,
     * no I/O. Called identically from every tick that talks to the LLM, so
     * generation and refinement always share the exact same system prompt.
     *
     * Why a helper instead of the old generate_and_refine() call site: each
     * tick now performs a single LLM call, so we no longer want a function
     * that does (init + N refines) inside one PHP request. We split that
     * loop across cron ticks (see step_generate_site / step_update_section)
     * and call this helper at the start of each one to assemble the prompt.
     */
    private static function augment_system_with_design_rules(
        string $system,
    ): string {
        return $system .
            "\n\n" .
            "LANGUAGE RULE (CRITICAL):\n" .
            "- Any visible text content MUST be written in the SAME language as the user's request.\n" .
            "- Never switch language unless explicitly requested.\n\n" .
            "You are an elite senior frontend engineer, UI designer, and AI website generation system.\n\n" .
            "Your task is to generate PRODUCTION-QUALITY HTML and CSS for a modern website section.\n\n" .
            "The generated result MUST feel:\n" .
            "- premium\n" .
            "- modern\n" .
            "- visually polished\n" .
            "- conversion-focused\n" .
            "- agency-quality\n" .
            "- fully responsive\n\n" .
            "CRITICAL OUTPUT RULES:\n" .
            "- Return ONLY HTML\n" .
            "- No markdown\n" .
            "- No explanations\n" .
            "- No code fences\n" .
            "- No JavaScript\n" .
            "- No React/Vue/Angular syntax\n" .
            "- No inline styles\n" .
            "- No external libraries\n" .
            "- No placeholder comments\n" .
            "- ALWAYS keep the BEGIN/END section markers exactly as instructed earlier in this prompt.\n\n" .
            "HTML + CSS REQUIREMENTS:\n" .
            "- All CSS MUST be fully scoped to the section root class\n" .
            "- NEVER use global selectors\n" .
            "- NEVER style body, html, *, or generic tags globally\n" .
            "- The platform already resets the document with html,body{margin:0;padding:0} and box-sizing:border-box. Do NOT add any margin or padding on the section root to compensate for browser defaults — the section will sit flush against the page edges by design. If you want internal spacing, use padding on inner containers, not on the section root.\n" .
            "- Use semantic HTML5 structure\n" .
            "- Include accessible alt text for images\n" .
            "- Preserve clean DOM hierarchy\n" .
            "- Avoid unnecessary wrapper divs\n" .
            "- Use consistent naming conventions\n\n" .
            "DESIGN REQUIREMENTS:\n" .
            "- Use a premium modern SaaS/startup visual style unless the user specifies otherwise\n" .
            "- Strong typography hierarchy\n" .
            "- Clear visual contrast\n" .
            "- Proper spacing rhythm using an 8px spacing system\n" .
            "- Modern rounded corners and subtle depth when relevant\n" .
            "- Strong CTA visibility\n" .
            "- Balanced whitespace\n" .
            "- Mobile-first responsive design\n" .
            "- Excellent visual hierarchy\n" .
            "- Elegant hover and focus states for interactive elements\n" .
            "- Layout must remain visually strong from 375px to 1440px+\n\n" .
            "RESPONSIVE RULES:\n" .
            "- Ensure layouts never overflow horizontally\n" .
            "- Ensure text remains readable on small screens\n" .
            "- Stack grids intelligently on mobile\n" .
            "- Ensure buttons remain easily tappable\n" .
            "- Optimize spacing and alignment across breakpoints\n\n" .
            "CONTENT RULES:\n" .
            "- Avoid generic marketing buzzwords\n" .
            "- Avoid lorem ipsum\n" .
            "- Use realistic professional website copy\n" .
            "- Keep content concise and visually scannable\n" .
            "- Prioritize conversion and clarity\n\n" .
            "IMAGE RULES:\n" .
            "- Use images only if visually useful\n" .
            "- Images must feel modern and premium\n" .
            "- Never break layout consistency\n\n" .
            "IMPORTANT:\n" .
            "- The output will be directly rendered by an AI website builder\n" .
            "- Optimize for visual polish and production readiness\n" .
            "- Think like a top-tier frontend designer from a premium digital agency\n";
    }

    /**
     * Build the user message used to drive a single refinement pass. Returns
     * a deterministic string given (section_id, current pass, total passes).
     * Identical wording to the old generate_and_refine loop body so prompt
     * behaviour is preserved.
     */
    private static function build_refinement_prompt(
        string $section_id,
        int $pass,
        int $total_passes,
    ): string {
        $marker = $section_id
            ? "Return ONLY the improved section wrapped exactly like this:\n<!-- BEGIN:{$section_id} -->\n...HTML...\n<!-- END:{$section_id} -->"
            : "Return ONLY the improved HTML using the exact same BEGIN/END markers.";

        return "Refinement pass {$pass}/{$total_passes}.\n\n" .
            "Carefully self-review and improve the HTML you just generated.\n\n" .
            "VALIDATION CHECKLIST:\n" .
            "1. SCOPING\n" .
            "- Verify ALL CSS is perfectly scoped\n" .
            "- Verify zero global leakage\n" .
            "- Verify no unsafe selectors exist\n\n" .
            "2. RESPONSIVENESS\n" .
            "- Mentally validate every layout from 375px to 1440px+\n" .
            "- Ensure no overflow issues exist\n" .
            "- Ensure grids collapse correctly\n" .
            "- Ensure spacing remains balanced on mobile\n\n" .
            "3. VISUAL QUALITY\n" .
            "- Improve hierarchy, polish, contrast, and alignment\n" .
            "- Improve spacing consistency\n" .
            "- Improve CTA prominence\n" .
            "- Improve premium feel\n" .
            "- Improve readability and scanning\n\n" .
            "4. ACCESSIBILITY\n" .
            "- Verify sufficient contrast\n" .
            "- Verify focus states are visible\n" .
            "- Verify buttons and links remain accessible\n" .
            "- Verify alt attributes exist\n\n" .
            "5. CLEANLINESS\n" .
            "- Remove unnecessary wrappers\n" .
            "- Remove redundant classes\n" .
            "- Remove weak or repetitive copy\n" .
            "- Remove visual inconsistencies\n\n" .
            "6. PRODUCTION READINESS\n" .
            "- Ensure the section looks deploy-ready\n" .
            "- Ensure the section feels agency-quality\n" .
            "- Ensure the section looks intentionally designed\n\n" .
            $marker;
    }

    /**
     * Render a WordPress nav menu as a flat HTML list.
     */
    private static function render_nav_menu(int $menu_id): string
    {
        $items = wp_get_nav_menu_items($menu_id);
        if (empty($items) || !is_array($items)) {
            return "";
        }

        $lines = ["<ul>"];
        foreach ($items as $item) {
            $lines[] =
                '<li><a href="' .
                esc_url($item->url) .
                '">' .
                esc_html($item->title) .
                "</a></li>";
        }
        $lines[] = "</ul>";

        return implode("\n", $lines);
    }

    /**
     * Build an LLM router from saved settings.
     */
    private static function build_router(): Naano_LLM_Router
    {
        $provider = get_option("naano_provider", "claude");
        $api_key = get_option("naano_api_key", "");
        $model = (string) get_option("naano_model", "");

        if (!$api_key) {
            throw new RuntimeException(
                __(
                    "No API key configured. Please visit Naano AI Builder → Settings.",
                    "naano-ai-website-builder",
                ),
            );
        }

        return new Naano_LLM_Router($provider, $api_key, ["model" => $model]);
    }
}
