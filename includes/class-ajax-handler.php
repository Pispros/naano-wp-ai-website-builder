<?php
/**
 * AJAX Handler – registers and handles all plugin AJAX endpoints.
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Registers WordPress AJAX actions for the builder.
 */
class Naano_Ajax_Handler
{
    /**
     * Register all wp_ajax_* hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        $actions = [
            "naano_generate_site",
            "naano_update_section",
            "naano_enhance_prompt",
            "naano_poll_job",
            "naano_test_connection",
            "naano_save_api_key",
            "naano_save_global_config",
            "naano_save_assets",
            "naano_save_redirects",
            "naano_add_reference",
            "naano_remove_reference",
            "naano_delete_section",
            "naano_reorder_sections",
            "naano_export_html",
            "naano_save_as_page",
            "naano_set_homepage",
            "naano_save_firecrawl_key",
            "naano_test_firecrawl",
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_" . $action, [__CLASS__, "handle_" . $action]);
        }
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    /**
     * Generate a full website. Thin start handler: validates input,
     * creates the page (if needed), saves any imported sections, then
     * either returns immediately (imported-only request) or kicks off
     * a background job and returns its job_id.
     *
     * The slow work (URL fetches, per-section LLM calls) runs in
     * Naano_Job_Runner::run() inside a fresh PHP worker spawned via a
     * non-blocking wp_remote_post() loopback to admin-ajax.php. Each
     * section runs in its own worker, so LiteSpeed's LSAPI_MAX_PROCESS_TIME
     * (typically 60-300 s) never has a chance to kill mid-job.
     *
     * POST: page_id, page_name, description, sections[], imported_sections,
     *       initial_references, wp_menu
     */
    public static function handle_naano_generate_site(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        $page_name = sanitize_text_field(wp_unslash($_POST["page_name"] ?? ""));
        $description = sanitize_textarea_field(
            wp_unslash($_POST["description"] ?? ""),
        );
        $sections = array_map(
            "sanitize_text_field",
            (array) ($_POST["sections"] ?? []),
        );

        // Parse imported sections (header/footer cloned from other pages, no LLM needed).
        // NOTE: wp_unslash only — sanitize_text_field would strip HTML tags from the JSON.
        $imported_json = wp_unslash($_POST["imported_sections"] ?? "");
        $imported_data = [];
        if ($imported_json) {
            $decoded = json_decode($imported_json, true);
            if (is_array($decoded)) {
                $imported_data = $decoded;
            }
        }

        if (!$description || (empty($sections) && empty($imported_data))) {
            wp_send_json_error([
                "message" => __(
                    "Missing required fields.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        // Create a new WordPress page when none exists yet.
        if (!$page_id) {
            if (!current_user_can("edit_pages")) {
                wp_send_json_error([
                    "message" => __(
                        "Insufficient permissions to create pages.",
                        "naano-ai-website-builder",
                    ),
                ]);
            }

            $new_id = wp_insert_post([
                "post_title" =>
                    $page_name ?: __("Untitled", "naano-ai-website-builder"),
                "post_status" => "draft",
                "post_type" => "page",
            ]);

            if (is_wp_error($new_id)) {
                wp_send_json_error(["message" => $new_id->get_error_message()]);
            }

            $page_id = $new_id;
        }

        // Save imported sections (sync, fast – just DB copies, no LLM).
        if (!empty($imported_data)) {
            $imp_sm = new Naano_Section_Manager();
            $src_cache = [];
            foreach ($imported_data as $imp) {
                $imp_id = sanitize_key($imp["id"] ?? "");
                $imp_type = sanitize_key($imp["type"] ?? "");
                $src_pid = (int) ($imp["sourcePageId"] ?? 0);
                if (!$imp_id || !$src_pid) {
                    continue;
                }
                if (!isset($src_cache[$src_pid])) {
                    $src_cache[$src_pid] = $imp_sm->get_sections($src_pid);
                }
                $imp_html = "";
                foreach ($src_cache[$src_pid] as $src_sec) {
                    if (($src_sec["id"] ?? "") === $imp_id) {
                        $imp_html = $src_sec["html"] ?? "";
                        break;
                    }
                }
                if (trim($imp_html)) {
                    $imp_sm->update_section(
                        $page_id,
                        $imp_id,
                        $imp_html,
                        $imp_type,
                    );
                }
            }
        }

        // Imported-only request — no LLM needed, return synchronously.
        if (empty($sections)) {
            $sm = new Naano_Section_Manager();
            wp_send_json_success([
                "page_id" => $page_id,
                "sections" => $sm->get_sections($page_id),
                "html" => $sm->get_assembled_html($page_id),
            ]);
            return;
        }

        // Parse initial references (URLs+notes only — content is fetched in the runner).
        $initial_refs = [];
        $initial_refs_json = wp_unslash($_POST["initial_references"] ?? "");
        if ($initial_refs_json) {
            $decoded_refs = json_decode($initial_refs_json, true);
            if (is_array($decoded_refs)) {
                foreach ($decoded_refs as $ref) {
                    if (empty($ref["url"])) {
                        continue;
                    }
                    $initial_refs[] = [
                        "url" => esc_url_raw($ref["url"]),
                        "notes" => sanitize_text_field($ref["notes"] ?? ""),
                    ];
                }
            }
        }

        $payload = [
            "page_id" => $page_id,
            "description" => $description,
            "sections" => $sections,
            "initial_references" => $initial_refs,
            "wp_menu_id" => (int) ($_POST["wp_menu"] ?? 0),
        ];

        $job_id = Naano_Job_Manager::create("generate_site", $payload);

        // Each step (one section LLM call) runs in its own fresh PHP
        // worker via WP-Cron. This is the only reliable pattern on
        // LiteSpeed shared hosting where:
        //   - LSAPI_MAX_PROCESS_TIME (~120s) caps any single worker
        //   - non-blocking loopback workers are killed after ~10s once
        //     the dispatching worker exits
        // The cPanel cron OS configured by the user kicks wp-cron.php
        // every minute as a backstop in case spawn_cron is throttled.
        Naano_Job_Runner::schedule_next_step($job_id);

        wp_send_json_success(["job_id" => $job_id]);
    }

    /**
     * Update a single section. Thin start handler.
     *
     * POST: page_id, section_id, instruction, assets, redirects, references, wp_menu
     */
    public static function handle_naano_update_section(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        $section_id = sanitize_text_field(
            wp_unslash($_POST["section_id"] ?? ""),
        );
        $instruction = sanitize_textarea_field(
            wp_unslash($_POST["instruction"] ?? ""),
        );

        $assets_raw = sanitize_text_field(wp_unslash($_POST["assets"] ?? "[]"));
        $redirects_raw = sanitize_text_field(
            wp_unslash($_POST["redirects"] ?? "[]"),
        );
        $refs_raw = sanitize_text_field(
            wp_unslash($_POST["references"] ?? "[]"),
        );
        $assets = json_decode($assets_raw, true);
        $redirects = json_decode($redirects_raw, true);
        $client_refs = json_decode($refs_raw, true);
        $assets = is_array($assets) ? $assets : [];
        $redirects = is_array($redirects) ? $redirects : [];
        $client_refs = is_array($client_refs) ? $client_refs : [];

        if (!$page_id || !$section_id || !$instruction) {
            wp_send_json_error([
                "message" => __(
                    "Missing required fields.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $payload = [
            "page_id" => $page_id,
            "section_id" => $section_id,
            "instruction" => $instruction,
            "assets" => $assets,
            "redirects" => $redirects,
            "client_refs" => $client_refs,
            "wp_menu_id" => (int) ($_POST["wp_menu"] ?? 0),
        ];

        $job_id = Naano_Job_Manager::create("update_section", $payload);

        Naano_Job_Runner::schedule_next_step($job_id);

        wp_send_json_success(["job_id" => $job_id]);
    }

    /**
     * Poll a job's status. Used by the JS layer to drive the UI loading state.
     *
     * POST: job_id
     */
    public static function handle_naano_poll_job(): void
    {
        self::verify_nonce();

        $job_id = sanitize_text_field(wp_unslash($_POST["job_id"] ?? ""));
        if (!$job_id) {
            wp_send_json_error([
                "message" => __("Missing job_id.", "naano-ai-website-builder"),
            ]);
        }

        $job = Naano_Job_Manager::get($job_id);
        if (!$job) {
            wp_send_json_error([
                "message" => __(
                    "Job not found or expired.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        if ((int) ($job["user_id"] ?? 0) !== get_current_user_id()) {
            wp_send_json_error([
                "message" => __("Forbidden.", "naano-ai-website-builder"),
            ]);
        }

        // Don't leak the original payload nor the auth token back to the client.
        unset($job["payload"], $job["token"]);

        wp_send_json_success($job);

        // We intentionally do NOT delete the job on terminal status here.
        // The JS poller may make one more poll due to its backoff timing,
        // and deleting too eagerly would cause a spurious "Job not found"
        // error. Jobs are cleaned up automatically by the transient TTL
        // (HOUR_IN_SECONDS).
    }

    /**
     * Save the API key independently via AJAX.
     */
    public static function handle_naano_save_api_key(): void
    {
        self::verify_nonce();

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __(
                    "Permission denied.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $api_key = sanitize_text_field(wp_unslash($_POST["api_key"] ?? ""));

        if (empty($api_key)) {
            wp_send_json_error([
                "message" => __(
                    "API key cannot be empty.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        update_option("naano_api_key", $api_key);

        wp_send_json_success([
            "message" => __("API key saved.", "naano-ai-website-builder"),
        ]);
    }

    /**
     * Save Firecrawl API key via AJAX.
     */
    public static function handle_naano_save_firecrawl_key(): void
    {
        self::verify_nonce();

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __(
                    "Permission denied.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $api_key = sanitize_text_field(wp_unslash($_POST["api_key"] ?? ""));

        if (empty($api_key)) {
            wp_send_json_error([
                "message" => __(
                    "API key cannot be empty.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        update_option("naano_firecrawl_api_key", $api_key);

        wp_send_json_success([
            "message" => __(
                "Firecrawl API key saved.",
                "naano-ai-website-builder",
            ),
        ]);
    }

    /**
     * Test Firecrawl API key by scraping a lightweight page.
     */
    public static function handle_naano_test_firecrawl(): void
    {
        self::verify_nonce();

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __(
                    "Permission denied.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $api_key = sanitize_text_field(wp_unslash($_POST["api_key"] ?? ""));
        if (empty($api_key)) {
            wp_send_json_error([
                "message" => __(
                    "Please enter an API key first.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $start = microtime(true);

        $body = wp_json_encode([
            "url" => "https://example.com",
            "formats" => ["html"],
            "onlyMainContent" => true,
        ]);

        $response = wp_remote_post("https://api.firecrawl.dev/v2/scrape", [
            "timeout" => 1200,
            "headers" => [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $api_key,
            ],
            "body" => $body,
        ]);

        $latency = round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_error(["message" => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 401 || $code === 403) {
            wp_send_json_error([
                "message" => __("Invalid API key.", "naano-ai-website-builder"),
            ]);
        }

        if ($code < 200 || $code >= 300) {
            $msg = $data["error"] ?? "HTTP " . $code;
            wp_send_json_error(["message" => $msg]);
        }

        if (empty($data["success"])) {
            wp_send_json_error([
                "message" =>
                    $data["error"] ??
                    __("Unknown error.", "naano-ai-website-builder"),
            ]);
        }

        wp_send_json_success([
            "message" => __("Connected!", "naano-ai-website-builder"),
            "latency_ms" => $latency . "ms",
        ]);
    }

    /**
     * Save global configuration via AJAX.
     */
    public static function handle_naano_save_global_config(): void
    {
        self::verify_nonce();

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __(
                    "Permission denied.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $initial = absint($_POST["initial_refinement_passes"] ?? 1);
        $update = absint($_POST["update_refinement_passes"] ?? 3);

        if ($initial > 10) {
            $initial = 10;
        }
        if ($update > 10) {
            $update = 10;
        }

        update_option("naano_initial_refinement_passes", $initial);
        update_option("naano_update_refinement_passes", $update);

        wp_send_json_success([
            "message" => __("Global config saved.", "naano-ai-website-builder"),
        ]);
    }

    /**
     * Enhance a user prompt using the LLM. Thin start handler.
     *
     * POST: raw_text, context ('initial' or 'edit'), page_name
     * Returns: { job_id } — final result is delivered through the poll endpoint.
     */
    public static function handle_naano_enhance_prompt(): void
    {
        self::verify_nonce();

        $raw_text = sanitize_textarea_field(
            wp_unslash($_POST["raw_text"] ?? ""),
        );
        $context = sanitize_text_field(
            wp_unslash($_POST["context"] ?? "initial"),
        );
        $page_name = sanitize_text_field(wp_unslash($_POST["page_name"] ?? ""));

        if (!$raw_text) {
            wp_send_json_error([
                "message" => __(
                    "Please enter some text to enhance.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $payload = [
            "raw_text" => $raw_text,
            "context" => $context,
            "page_name" => $page_name,
        ];

        $job_id = Naano_Job_Manager::create("enhance_prompt", $payload);

        Naano_Job_Runner::schedule_next_step($job_id);

        wp_send_json_success(["job_id" => $job_id]);
    }

    /**
     * Test LLM provider connection.
     *
     * POST: provider, api_key
     */
    public static function handle_naano_test_connection(): void
    {
        self::verify_nonce();

        $provider = sanitize_text_field(wp_unslash($_POST["provider"] ?? ""));
        $api_key = sanitize_text_field(wp_unslash($_POST["api_key"] ?? ""));
        $model = sanitize_text_field(wp_unslash($_POST["model"] ?? ""));

        if (!$provider || !$api_key) {
            wp_send_json_error([
                "message" => __(
                    "Provider and API key are required.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        try {
            $router = new Naano_LLM_Router($provider, $api_key, [
                "model" => $model,
            ]);
            $result = $router->get_adapter()->test_connection();
            if ($result["success"]) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(["message" => $e->getMessage()]);
        }
    }

    /**
     * Save page-level assets.
     *
     * POST: page_id, assets (JSON)
     */
    public static function handle_naano_save_assets(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        if (!$page_id) {
            wp_send_json_error([
                "message" => __("Missing page ID.", "naano-ai-website-builder"),
            ]);
        }

        $raw = wp_unslash($_POST["assets"] ?? "[]");
        $assets = json_decode($raw, true);
        if (!is_array($assets)) {
            $assets = [];
        }

        // Sanitise each entry.
        $clean = [];
        foreach ($assets as $a) {
            if (!is_array($a) || empty($a["url"])) {
                continue;
            }
            $clean[] = [
                "url" => esc_url_raw($a["url"]),
                "desc" => sanitize_text_field($a["desc"] ?? ""),
            ];
        }

        update_post_meta($page_id, "_naano_assets", $clean);
        wp_send_json_success();
    }

    /**
     * Save page-level redirects.
     *
     * POST: page_id, redirects (JSON)
     */
    public static function handle_naano_save_redirects(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        if (!$page_id) {
            wp_send_json_error([
                "message" => __("Missing page ID.", "naano-ai-website-builder"),
            ]);
        }

        $raw = wp_unslash($_POST["redirects"] ?? "[]");
        $redirects = json_decode($raw, true);
        if (!is_array($redirects)) {
            $redirects = [];
        }

        $clean = [];
        foreach ($redirects as $r) {
            if (!is_array($r) || empty($r["label"]) || empty($r["url"])) {
                continue;
            }
            $clean[] = [
                "label" => sanitize_text_field($r["label"]),
                "url" => esc_url_raw($r["url"]),
            ];
        }

        update_post_meta($page_id, "_naano_redirects", $clean);
        wp_send_json_success();
    }

    /**
     * Add a reference to a section.
     *
     * POST: page_id, section_id, type, url, attachment_id, notes
     */
    public static function handle_naano_add_reference(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        $section_id = sanitize_text_field(
            wp_unslash($_POST["section_id"] ?? ""),
        );
        $type = sanitize_text_field(wp_unslash($_POST["type"] ?? "url"));
        $url = esc_url_raw(wp_unslash($_POST["url"] ?? ""));
        $attach_id = self::get_int("attachment_id");
        $notes = sanitize_textarea_field(wp_unslash($_POST["notes"] ?? ""));

        if (!$page_id || !$section_id) {
            wp_send_json_error([
                "message" => __(
                    "Missing required fields.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $manager = new Naano_Reference_Manager();
        $manager->add_reference($page_id, $section_id, [
            "type" => $type,
            "url" => $url,
            "attachment_id" => $attach_id,
            "notes" => $notes,
        ]);

        wp_send_json_success([
            "references" => $manager->get_references($page_id, $section_id),
        ]);
    }

    /**
     * Remove a reference.
     *
     * POST: page_id, section_id, index
     */
    public static function handle_naano_remove_reference(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        $section_id = sanitize_text_field(
            wp_unslash($_POST["section_id"] ?? ""),
        );
        $index = self::get_int("index");

        if (!$page_id || !$section_id) {
            wp_send_json_error([
                "message" => __(
                    "Missing required fields.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $manager = new Naano_Reference_Manager();
        $manager->remove_reference($page_id, $section_id, $index);

        wp_send_json_success([
            "references" => $manager->get_references($page_id, $section_id),
        ]);
    }

    /**
     * Delete a section.
     *
     * POST: page_id, section_id
     */
    public static function handle_naano_delete_section(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        $section_id = sanitize_text_field(
            wp_unslash($_POST["section_id"] ?? ""),
        );

        if (!$page_id || !$section_id) {
            wp_send_json_error([
                "message" => __(
                    "Missing required fields.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $manager = new Naano_Section_Manager();
        $manager->delete_section($page_id, $section_id);

        wp_send_json_success();
    }

    /**
     * Reorder sections.
     *
     * POST: page_id, order[] (array of section IDs in new order)
     */
    public static function handle_naano_reorder_sections(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");
        $order = array_map(
            "sanitize_text_field",
            (array) ($_POST["order"] ?? []),
        );

        if (!$page_id || empty($order)) {
            wp_send_json_error([
                "message" => __(
                    "Missing required fields.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $manager = new Naano_Section_Manager();
        $manager->reorder_sections($page_id, $order);

        wp_send_json_success();
    }

    /**
     * Export assembled HTML.
     *
     * POST: page_id
     */
    public static function handle_naano_export_html(): void
    {
        self::verify_nonce();

        $page_id = self::get_int("page_id");

        if (!$page_id) {
            wp_send_json_error([
                "message" => __("Missing page_id.", "naano-ai-website-builder"),
            ]);
        }

        $manager = new Naano_Section_Manager();
        $html = $manager->get_assembled_html($page_id);

        wp_send_json_success(["html" => $html]);
    }

    /**
     * Save assembled HTML as a real WordPress page.
     *
     * POST: page_id, title
     */
    public static function handle_naano_save_as_page(): void
    {
        self::verify_nonce();

        if (!current_user_can("publish_pages")) {
            wp_send_json_error([
                "message" => __(
                    "Insufficient permissions.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $page_id = self::get_int("page_id");
        $title = sanitize_text_field(wp_unslash($_POST["title"] ?? ""));

        if (!$page_id) {
            wp_send_json_error([
                "message" => __("Missing page_id.", "naano-ai-website-builder"),
            ]);
        }

        $manager = new Naano_Section_Manager();
        $title =
            $title ?:
            (get_the_title($page_id) ?:
            __("AI Generated Page", "naano-ai-website-builder"));

        // The client sends the assembled HTML directly (built from in-memory
        // sectionsData) so we never rely on a server-side DB re-assembly which
        // may be empty if sections meta is on a different page_id.
        $raw_html = wp_unslash($_POST["html"] ?? "");

        // Strip scripts as a defence-in-depth measure (content was already
        // sanitized by Naano_HTML_Sanitizer during generation, but this
        // ensures nothing slips through if the payload is tampered).
        $html =
            preg_replace(
                "/<script\b[^>]*>[\s\S]*?<\/script>/i",
                "",
                $raw_html,
            ) ?? "";

        // Fall back to server-side assembly if the client sent nothing.
        if (!trim($html)) {
            $html = $manager->get_assembled_html($page_id);
        }

        // Publish / update the SAME page that was edited in the builder.
        // This avoids creating a duplicate and keeps sections + standalone HTML
        // on a single post.
        $result = wp_update_post(
            [
                "ID" => $page_id,
                "post_title" => $title,
                "post_content" => __(
                    "This page was generated by Naano AI Website Builder.",
                    "naano-ai-website-builder",
                ),
                "post_status" => "publish",
            ],
            true,
        );

        if (is_wp_error($result)) {
            wp_send_json_error(["message" => $result->get_error_message()]);
        }

        // Store the raw assembled HTML in a dedicated meta key so it is never
        // touched by WordPress content filters (wpautop, wptexturize, etc.).
        update_post_meta($page_id, "_naano_page_html", $html);

        // Mark this page as a Naano standalone page so template_redirect
        // can serve the raw HTML without any theme wrapping.
        update_post_meta($page_id, "_naano_standalone", "1");

        wp_send_json_success([
            "page_id" => $page_id,
            "edit_url" => get_edit_post_link($page_id, "raw"),
            "view_url" => get_permalink($page_id),
            "title" => $title,
        ]);
    }

    /**
     * Set a Naano page as the WordPress static front page.
     *
     * POST: page_id
     */
    public static function handle_naano_set_homepage(): void
    {
        self::verify_nonce();

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __(
                    "Insufficient permissions.",
                    "naano-ai-website-builder",
                ),
            ]);
        }

        $page_id = self::get_int("page_id");

        if (!$page_id) {
            wp_send_json_error([
                "message" => __("Missing page_id.", "naano-ai-website-builder"),
            ]);
        }

        update_option("show_on_front", "page");
        update_option("page_on_front", $page_id);

        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Verify AJAX nonce, die on failure.
     *
     * @return void
     */
    private static function verify_nonce(): void
    {
        check_ajax_referer("naano_builder_nonce", "nonce");
    }

    /**
     * Get an integer POST field.
     *
     * @param string $key POST field name.
     * @return int
     */
    private static function get_int(string $key): int
    {
        return (int) ($_POST[$key] ?? 0);
    }
}
