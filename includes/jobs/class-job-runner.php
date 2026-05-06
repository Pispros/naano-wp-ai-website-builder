<?php
/**
 * Job Runner – executes ONE step of a job per WP-Cron tick. Each tick
 * runs in its own fresh PHP worker, so LSAPI_MAX_PROCESS_TIME (typically
 * 60-300s) cannot kill a long multi-section job mid-flight: each step
 * (one LLM call ≈ 30-90s) finishes well within a single worker's budget,
 * then the runner schedules the next tick and exits.
 *
 *   START handler     ──► create job, schedule first cron tick   ──► HTTP 200 {job_id}
 *   wp-cron tick #1   ──► step 0 (setup/fetch URLs), schedule #2 ──► EXIT
 *   wp-cron tick #2   ──► step 1 (section 1 LLM)   , schedule #3 ──► EXIT
 *   wp-cron tick #N   ──► finalize, mark_done                    ──► EXIT
 *
 *   poll endpoint     ──► reads transient, returns status (+log) to client
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

            Naano_Job_Manager::log($job_id, [
                "phase" => "shutdown_unexpected",
                "elapsed_s" => $elapsed,
                "last_error" => $err,
                "reason" => $reason,
            ]);
            Naano_Job_Manager::mark_error($job_id, $reason);
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
                    self::step_update_section($job_id, $payload);
                    break;
                case "enhance_prompt":
                    self::step_enhance_prompt($job_id, $payload);
                    break;
                default:
                    throw new RuntimeException("Unknown job type: " . $type);
            }
        } catch (\Throwable $e) {
            Naano_Job_Manager::log($job_id, [
                "phase" => "exception",
                "message" => $e->getMessage(),
                "file" => basename($e->getFile()),
                "line" => $e->getLine(),
            ]);
            Naano_Job_Manager::mark_error($job_id, $e->getMessage());
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
     *     'cursor'     => int,          // 0 = setup, 1..N = section index, N+1 = finalize
     *     'desc_refs'  => array,        // URL refs fetched during setup, reused per section
     *     'site_pages' => array,        // sibling Naano pages
     *     'nav_menu'   => string,       // rendered WP nav menu HTML
     *   ]
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

        // ── Step 0: setup (URL fetches, sibling pages, nav menu) ────────────
        if ($cursor === 0) {
            Naano_Job_Manager::log($job_id, [
                "phase" => "generate_site:setup",
                "page_id" => $page_id,
                "section_count" => $section_count,
                "sections" => $sections,
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
            ]);

            return;
        }

        // ── Steps 1..N: one section per worker ──────────────────────────────
        $section_idx = $cursor - 1;
        if ($section_idx < $section_count) {
            $section_type = sanitize_text_field($sections[$section_idx]);
            if (!$section_type) {
                // Skip empty entries cleanly.
                $state["cursor"] = $cursor + 1;
                Naano_Job_Manager::set_state($job_id, $state);
                return;
            }
            $section_id = sanitize_title($section_type);

            Naano_Job_Manager::log($job_id, [
                "phase" => "generate_site:section",
                "index" => $section_idx + 1,
                "of" => $section_count,
                "section_type" => $section_type,
                "section_id" => $section_id,
            ]);

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

            $system = $builder->build_system_prompt();
            $message = $builder->build_single_section_message(
                $description,
                $section_type,
            );

            $raw_html = self::generate_and_refine(
                $router,
                $system,
                [["role" => "user", "content" => $message]],
                [],
                $section_id,
                (int) get_option("naano_initial_refinement_passes", 1),
            );

            $section_html = Naano_HTML_Sanitizer::extract_section(
                $raw_html,
                $section_id,
            );
            if (!$section_html) {
                $section_html = $raw_html;
            }

            $section_manager = new Naano_Section_Manager();
            $section_manager->update_section(
                $page_id,
                $section_id,
                $section_html,
                $section_type,
            );

            // Advance cursor + dispatch next worker.
            $state["cursor"] = $cursor + 1;
            Naano_Job_Manager::set_state($job_id, $state);
            return;
        }

        // ── Step N+1: finalize ──────────────────────────────────────────────
        $section_manager = new Naano_Section_Manager();
        $result = [
            "page_id" => $page_id,
            "sections" => $section_manager->get_sections($page_id),
            "html" => $section_manager->get_assembled_html($page_id),
        ];

        Naano_Job_Manager::log($job_id, [
            "phase" => "generate_site:done",
            "section_count" => count($result["sections"]),
        ]);

        Naano_Job_Manager::mark_done($job_id, $result);
    }

    // -------------------------------------------------------------------------
    // update_section – single-step (one LLM call + 1 refinement = ~30-60s, fits
    // in a fresh worker's budget)
    // -------------------------------------------------------------------------

    private static function step_update_section(
        string $job_id,
        array $payload,
    ): void {
        $page_id = (int) ($payload["page_id"] ?? 0);
        $section_id = (string) ($payload["section_id"] ?? "");
        $instruction = (string) ($payload["instruction"] ?? "");
        $assets = (array) ($payload["assets"] ?? []);
        $redirects = (array) ($payload["redirects"] ?? []);
        $client_refs = (array) ($payload["client_refs"] ?? []);
        $wp_menu_id = (int) ($payload["wp_menu_id"] ?? 0);

        Naano_Job_Manager::log($job_id, [
            "phase" => "update_section:start",
            "page_id" => $page_id,
            "section_id" => $section_id,
        ]);

        if (!$page_id || !$section_id || !$instruction) {
            throw new RuntimeException("Invalid update_section payload.");
        }

        $section_manager = new Naano_Section_Manager();
        $conversation = new Naano_Conversation();
        $ref_manager = new Naano_Reference_Manager();
        $router = self::build_router();

        $all_sections = $section_manager->get_sections($page_id);
        $context = Naano_Payload_Compressor::compress_context(
            $all_sections,
            $section_id,
        );

        $images = $ref_manager->prepare_images_for_llm($page_id, $section_id);

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

        $system = $builder->build_system_prompt();
        $history = $conversation->get_trimmed($page_id);
        $message = $builder->build_section_message(
            $section_id,
            $instruction,
            $context,
        );
        $history[] = ["role" => "user", "content" => $message];

        $raw_html = self::generate_and_refine(
            $router,
            $system,
            $history,
            $images,
            $section_id,
            (int) get_option("naano_update_refinement_passes", 1),
        );
        $section_html = Naano_HTML_Sanitizer::extract_section(
            $raw_html,
            $section_id,
        );
        if (!$section_html) {
            $section_html = $raw_html;
        }

        $section_manager->update_section($page_id, $section_id, $section_html);
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
     * Run the initial LLM generation then refine the result a number of times.
     *
     * IMPORTANT: $system is the prompt assembled by Naano_Prompt_Builder
     * (containing variables, URL refs with their fetched content, assets,
     * redirects, the WP menu, sibling pages, and the BEGIN:{section_id} /
     * END:{section_id} marker contract used by Naano_HTML_Sanitizer). The
     * design-quality block below is APPENDED with `.=`, never replaces it.
     */
    private static function generate_and_refine(
        Naano_LLM_Router $router,
        string $system,
        array $messages,
        array $images = [],
        string $section_id = "",
        int $passes = 1,
    ): string {
        $system .=
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

        $html = $router->generate($system, $messages, $images);
        $history = $messages;

        $marker = $section_id
            ? "Return ONLY the improved section wrapped exactly like this:\n<!-- BEGIN:{$section_id} -->\n...HTML...\n<!-- END:{$section_id} -->"
            : "Return ONLY the improved HTML using the exact same BEGIN/END markers.";

        for ($pass = 1; $pass <= $passes; $pass++) {
            $history[] = ["role" => "assistant", "content" => $html];
            $history[] = [
                "role" => "user",
                "content" =>
                    "Refinement pass {$pass}/{$passes}.\n\n" .
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
                    $marker,
            ];

            $refined = $router->generate($system, $history);
            if (!trim($refined)) {
                break;
            }
            $html = $refined;
        }

        return $html;
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
