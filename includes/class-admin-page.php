<?php
/**
 * Admin Page – registers menus, settings, and enqueues assets.
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Sets up the WordPress admin menus and settings for Naano AI Website Builder.
 */
class Naano_Admin_Page
{
    /** @var string[] Admin page hook suffixes registered by this class. */
    private array $page_hooks = [];

    /**
     * Constructor – wire up hooks.
     */
    public function __construct()
    {
        add_action("admin_menu", [$this, "register_menus"]);
        add_action("admin_init", [$this, "register_settings"]);
        add_action("admin_enqueue_scripts", [$this, "enqueue_assets"]);
        add_action("admin_enqueue_scripts", [$this, "enqueue_admin_common"]);

        // "Build with Naano AI" in the Pages list row actions.
        add_filter("page_row_actions", [$this, "add_page_row_action"], 10, 2);

        // Delete page from the Naano pages list.
        add_action("admin_post_naano_delete_page", [
            $this,
            "handle_delete_page",
        ]);

        // Duplicate a page for a new language translation.
        add_action("admin_post_naano_duplicate_for_translation", [
            $this,
            "handle_duplicate_for_translation",
        ]);

        // Frontend builder: intercept ?naano_builder=1 on frontend pages.
        add_action("template_redirect", [
            $this,
            "maybe_render_frontend_builder",
        ]);

        // Hide the WordPress admin bar when the frontend builder is active.
        add_filter("show_admin_bar", [$this, "maybe_hide_admin_bar"]);

        // Serve standalone Naano pages as raw HTML (no theme wrapping).
        add_action("template_redirect", [
            $this,
            "maybe_render_standalone_page",
        ]);

        // Add "Edit with Naano AI" to the WP admin bar on standalone Naano pages.
        add_action("admin_bar_menu", [$this, "add_admin_bar_edit_link"], 80);

        // ── Site Configuration: favicon + maintenance mode ────────────────
        //
        // Maintenance gate. Runs EARLY on template_redirect (priority 0)
        // so it fires before maybe_render_standalone_page / the theme
        // pipeline. When the admin has toggled maintenance mode on and
        // picked a maintenance page, every front-end URL (except wp-admin,
        // wp-login, AJAX, REST, cron, and the builder/preview itself) is
        // served the maintenance page's HTML with a 503 Service
        // Unavailable status so search engines don't index the placeholder
        // as the real content.
        add_action(
            "template_redirect",
            [$this, "maybe_render_maintenance_page"],
            0,
        );

        // Inject the favicon link tag into <head> on every front-end
        // request — including standalone Naano pages, where we still
        // capture wp_head() output. Hooking wp_head means anything that
        // calls it gets the favicon (theme pages AND our raw HTML
        // injection path via the wp_head capture inside
        // maybe_render_standalone_page).
        add_action("wp_head", [$this, "render_favicon_meta"], 2);
        // Same favicon link in the admin so the back-office tabs are
        // visually consistent with the front-end site identity.
        add_action("admin_head", [$this, "render_favicon_meta"], 2);

        // Server-side action for the "Create maintenance page" button on
        // the Site Configuration screen. Bootstraps a draft + opens the
        // builder so the admin can generate the maintenance content.
        add_action("admin_post_naano_create_maintenance_page", [
            $this,
            "handle_create_maintenance_page",
        ]);
    }

    /**
     * Enqueue inline CSS used across every admin page (menu icon sizing
     * and the small "page title with SVG" rule used by our top-level
     * admin pages).
     *
     * Uses wp_register_style() + wp_add_inline_style() so the styles flow
     * through the WordPress asset pipeline instead of being echoed in
     * admin_head, satisfying the WordPress.org enqueue guideline.
     *
     * @return void
     */
    public function enqueue_admin_common(): void
    {
        // Register an empty handle just to give wp_add_inline_style()
        // somewhere to attach. Passing `false` as the src tells WP not
        // to print a <link> tag — only the inline CSS will be emitted.
        wp_register_style(
            "naano-admin-common",
            false,
            [],
            NAANO_VERSION,
        );
        wp_enqueue_style("naano-admin-common");

        $css =
            // Size and align the custom SVG menu icon exactly like WP dashicons.
            "#adminmenu .toplevel_page_naano-ai-builder .wp-menu-image img{" .
                "width:20px !important;height:20px !important;" .
                "padding:0 !important;margin:0 !important;" .
                "opacity:1 !important;filter:none !important;display:block;" .
            "}" .
            "#adminmenu .toplevel_page_naano-ai-builder .wp-menu-image{" .
                "display:flex !important;align-items:center;justify-content:center;" .
            "}" .
            // Vertically centre icon + text in our page headings.
            ".naano-page-title{display:flex;align-items:center;gap:10px;line-height:1;}" .
            ".naano-page-title svg{width:28px;height:28px;flex-shrink:0;}";

        wp_add_inline_style("naano-admin-common", $css);
    }

    /**
     * Handle admin-post.php delete page request.
     *
     * Moves the page to trash (reversible). Only allowed for users who can
     * delete the specific post.
     *
     * @return void
     */
    public function handle_delete_page(): void
    {
        // The page_id is read first so we can build the per-page nonce action
        // (naano_delete_page_<id>) that check_admin_referer verifies below.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $page_id = isset($_POST["page_id"])
            ? (int) sanitize_text_field(wp_unslash($_POST["page_id"]))
            : 0;

        check_admin_referer("naano_delete_page_" . $page_id);

        if (!$page_id || !current_user_can("delete_post", $page_id)) {
            wp_die(
                esc_html__(
                    "You do not have permission to delete this page.",
                    "naano-ai-website-builder",
                ),
            );
        }

        wp_trash_post($page_id);

        wp_safe_redirect(
            add_query_arg(
                ["page" => "naano-ai-builder", "deleted" => "1"],
                admin_url("admin.php"),
            ),
        );
        exit();
    }

    /**
     * Suppress the WP admin bar when the frontend builder overlay is active.
     *
     * @param bool $show
     * @return bool
     */
    public function maybe_hide_admin_bar(bool $show): bool
    {
        // Read-only check of an unauthenticated query parameter — there is
        // no form submission to verify here. The actual builder access is
        // gated by current_user_can('manage_options') below.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (
            ! empty( $_GET["naano_builder"] ) &&
            current_user_can( "manage_options" )
        ) {
            return false;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        return $show;
    }

    /**
     * Add an "Edit with Naano AI" link to the WP admin bar on standalone pages.
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function add_admin_bar_edit_link(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (
            is_admin() ||
            !is_singular("page") ||
            !current_user_can("manage_options")
        ) {
            return;
        }

        $page_id = get_queried_object_id();
        if (!$page_id || !get_post_meta($page_id, "_naano_standalone", true)) {
            return;
        }

        $builder_url = add_query_arg(
            "naano_builder",
            "1",
            get_permalink($page_id),
        );

        $icon =
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 340 340" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;fill:none;stroke:currentColor;stroke-width:18"><rect x="54" y="54" width="232" height="232" rx="26" ry="26"/><line x1="115" y1="54" x2="115" y2="26" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="54" x2="170" y2="26" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="54" x2="225" y2="26" stroke-width="17" stroke-linecap="round"/><line x1="115" y1="286" x2="115" y2="314" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="286" x2="170" y2="314" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="286" x2="225" y2="314" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="115" x2="26" y2="115" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="170" x2="26" y2="170" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="225" x2="26" y2="225" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="115" x2="314" y2="115" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="170" x2="314" y2="170" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="225" x2="314" y2="225" stroke-width="17" stroke-linecap="round"/><line x1="106" y1="106" x2="106" y2="234" stroke-width="22" stroke-linecap="round"/><line x1="234" y1="106" x2="234" y2="234" stroke-width="22" stroke-linecap="round"/><line x1="106" y1="106" x2="234" y2="234" stroke-width="22" stroke-linecap="round"/></svg>';

        $wp_admin_bar->add_node([
            "id" => "naano-edit-page",
            "title" =>
                $icon . __("Edit with Naano AI", "naano-ai-website-builder"),
            "href" => $builder_url,
            "meta" => ["class" => "naano-ab-edit"],
        ]);
    }

    /**
     * Add "Build with Naano AI" to the Pages list row actions.
     * Links to the frontend page URL with the builder overlay activated.
     *
     * @param string[]  $actions Current row action links.
     * @param \WP_Post  $post    Current post object.
     * @return string[]
     */
    public function add_page_row_action(array $actions, \WP_Post $post): array
    {
        if (current_user_can("manage_options")) {
            $url = add_query_arg(
                ["naano_builder" => "1"],
                get_permalink($post->ID),
            );

            $actions["naano_build"] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($url),
                esc_html__("Build with Naano AI", "naano-ai-website-builder"),
            );
        }

        return $actions;
    }

    /**
     * Register top-level and sub-menus.
     *
     * @return void
     */
    public function register_menus(): void
    {
        $this->page_hooks[] = add_menu_page(
            __("Naano AI Builder", "naano-ai-website-builder"),
            __("Naano AI Builder", "naano-ai-website-builder"),
            "manage_options",
            "naano-ai-builder",
            [$this, "render_pages_list"],
            NAANO_PLUGIN_URL . "assets/images/naano-icon.svg",
            30,
        );

        $this->page_hooks[] = add_submenu_page(
            "naano-ai-builder",
            __("AI Pages", "naano-ai-website-builder"),
            __("AI Pages", "naano-ai-website-builder"),
            "manage_options",
            "naano-ai-builder",
            [$this, "render_pages_list"],
        );

        $this->page_hooks[] = add_submenu_page(
            "naano-ai-builder",
            __("Site Configuration", "naano-ai-website-builder"),
            __("Site Configuration", "naano-ai-website-builder"),
            "manage_options",
            "naano-site-config",
            [$this, "render_site_config_page"],
        );

        $this->page_hooks[] = add_submenu_page(
            "naano-ai-builder",
            __("Settings", "naano-ai-website-builder"),
            __("Settings", "naano-ai-website-builder"),
            "manage_options",
            "naano-settings",
            [$this, "render_settings_page"],
        );
    }

    /**
     * Register plugin settings with WordPress Settings API.
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting("naano_settings_group", "naano_provider", [
            "sanitize_callback" => static function ($v) {
                return in_array(
                    $v,
                    ["claude", "gemini", "kimi", "openai", "deepseek"],
                    true,
                )
                    ? $v
                    : "claude";
            },
            "default" => "claude",
        ]);

        register_setting("naano_settings_group", "naano_api_key", [
            "sanitize_callback" => "sanitize_text_field",
            "default" => "",
        ]);

        register_setting("naano_settings_group", "naano_model", [
            "sanitize_callback" => "sanitize_text_field",
            "default" => "",
        ]);

        register_setting("naano_settings_group", "naano_custom_prompt", [
            "sanitize_callback" => "sanitize_textarea_field",
            "default" => "",
        ]);

        register_setting("naano_settings_group", "naano_variables", [
            "sanitize_callback" => [$this, "sanitize_variables"],
            "default" => [],
        ]);

        register_setting("naano_settings_group", "naano_languages", [
            "sanitize_callback" => [$this, "sanitize_languages"],
            "default" => [],
        ]);

        register_setting("naano_settings_group", "naano_default_lang_label", [
            "sanitize_callback" => "sanitize_text_field",
            "default" => "",
        ]);

        register_setting(
            "naano_settings_group",
            "naano_initial_refinement_passes",
            [
                "sanitize_callback" => "absint",
                "default" => 1,
            ],
        );

        register_setting(
            "naano_settings_group",
            "naano_update_refinement_passes",
            [
                "sanitize_callback" => "absint",
                "default" => 1,
            ],
        );

        register_setting("naano_settings_group", "naano_firecrawl_api_key", [
            "sanitize_callback" => "sanitize_text_field",
            "default" => "",
        ]);

        // ── Site Configuration options ────────────────────────────────────
        //
        // These options drive the new "Site Configuration" submenu:
        // tagline/slogan (used as both a meta tag and an AI variable),
        // favicon (URL string — we accept any URL, not just an attachment
        // ID, so the admin can paste a CDN-hosted favicon if they want),
        // and the maintenance-mode toggle + maintenance page id. The
        // options live in their own group `naano_site_config_group` so
        // a save on the Site Configuration page doesn't accidentally
        // wipe the unrelated LLM/translation settings.
        register_setting("naano_site_config_group", "naano_site_slogan", [
            "sanitize_callback" => "sanitize_text_field",
            "default" => "",
        ]);
        register_setting("naano_site_config_group", "naano_site_favicon_url", [
            "sanitize_callback" => "esc_url_raw",
            "default" => "",
        ]);
        register_setting(
            "naano_site_config_group",
            "naano_site_favicon_attachment_id",
            [
                "sanitize_callback" => "absint",
                "default" => 0,
            ],
        );
        register_setting(
            "naano_site_config_group",
            "naano_maintenance_enabled",
            [
                "sanitize_callback" => static function ($v) {
                    return $v ? "1" : "";
                },
                "default" => "",
            ],
        );
        register_setting(
            "naano_site_config_group",
            "naano_maintenance_page_id",
            [
                "sanitize_callback" => "absint",
                "default" => 0,
            ],
        );
    }

    /**
     * Sanitize design variables from multi-field form input.
     *
     * Combines naano_vars_keys[] and naano_vars_values[] POST arrays into
     * an associative array.
     *
     * @param mixed $input Ignored (uses $_POST directly for multi-field).
     * @return array
     */
    public function sanitize_variables($input): array
    {
        // This is a register_setting() sanitize callback. options.php
        // verifies the nonce before calling sanitize callbacks, but we
        // re-check it explicitly here so the dependency is visible in
        // the source and Plugin Check is satisfied. We also gate on the
        // user capability that options.php itself requires.
        if (
            !current_user_can("manage_options") ||
            !isset($_POST["_wpnonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST["_wpnonce"])),
                "naano_settings_group-options",
            )
        ) {
            // Fall back to the previously-stored value so we never wipe
            // saved settings when the nonce fails.
            $existing = get_option("naano_variables", []);
            return is_array($existing) ? $existing : [];
        }

        $keys = array_map(
            "sanitize_text_field",
            (array) wp_unslash($_POST["naano_vars_keys"] ?? []),
        );
        $values = array_map(
            "sanitize_text_field",
            (array) wp_unslash($_POST["naano_vars_values"] ?? []),
        );

        $result = [];
        foreach ($keys as $i => $key) {
            $key = trim($key);
            if ($key !== "") {
                $result[$key] = $values[$i] ?? "";
            }
        }
        return $result;
    }

    /**
     * Sanitize language list from multi-field form input.
     *
     * Combines naano_lang_codes[] and naano_lang_labels[] POST arrays into
     * an indexed array of {code, label} objects.
     *
     * @param mixed $input Ignored (uses $_POST directly for multi-field).
     * @return array
     */
    public function sanitize_languages($input): array
    {
        // This is a register_setting() sanitize callback. options.php
        // verifies the nonce before calling sanitize callbacks, but we
        // re-check it explicitly here so the dependency is visible in
        // the source and Plugin Check is satisfied. We also gate on the
        // user capability that options.php itself requires.
        if (
            !current_user_can("manage_options") ||
            !isset($_POST["_wpnonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST["_wpnonce"])),
                "naano_settings_group-options",
            )
        ) {
            // Fall back to the previously-stored value so we never wipe
            // saved settings when the nonce fails.
            $existing = get_option("naano_languages", []);
            return is_array($existing) ? $existing : [];
        }

        $codes = array_map(
            "sanitize_key",
            (array) wp_unslash($_POST["naano_lang_codes"] ?? []),
        );
        $labels = array_map(
            "sanitize_text_field",
            (array) wp_unslash($_POST["naano_lang_labels"] ?? []),
        );

        $result = [];
        foreach ($codes as $i => $code) {
            $code = trim($code);
            if ($code !== "") {
                $result[] = [
                    "code" => $code,
                    "label" => trim($labels[$i] ?? "") ?: strtoupper($code),
                ];
            }
        }
        return $result;
    }

    /**
     * Duplicate a Naano page as a translation into a new language.
     *
     * Creates a child page with slug = language code and copies all sections
     * and HTML from the root page. On success, redirects to the new page's
     * builder. Accessible via admin-post.php.
     *
     * @return void
     */
    public function handle_duplicate_for_translation(): void
    {
        // The page_id is read first so we can build the per-page nonce action
        // (naano_duplicate_translation_<id>) that check_admin_referer verifies.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $page_id = isset($_POST["page_id"])
            ? (int) sanitize_text_field(wp_unslash($_POST["page_id"]))
            : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $lang = isset($_POST["lang"])
            ? sanitize_key(wp_unslash($_POST["lang"]))
            : "";

        check_admin_referer("naano_duplicate_translation_" . $page_id);

        if (!$page_id || !$lang) {
            wp_die(
                esc_html__("Missing parameters.", "naano-ai-website-builder"),
            );
        }

        if (!current_user_can("edit_pages")) {
            wp_die(
                esc_html__(
                    "You do not have permission to create pages.",
                    "naano-ai-website-builder",
                ),
            );
        }

        // Resolve the root (non-translated) page.
        $root_id =
            (int) get_post_meta($page_id, "_naano_translation_of", true) ?:
            $page_id;
        $root = get_post($root_id);

        if (!$root) {
            wp_die(
                esc_html__(
                    "Original page not found.",
                    "naano-ai-website-builder",
                ),
            );
        }

        // If a translation for this language already exists, open it instead of creating a duplicate.
        // Meta_query is the right WP_Query primitive here — we genuinely
        // need to filter on two post-meta fields.
        $existing = get_posts([
            "post_type" => "page",
            "post_status" => "any",
            "posts_per_page" => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            "meta_query" => [
                ["key" => "_naano_translation_of", "value" => $root_id],
                ["key" => "_naano_lang", "value" => $lang],
            ],
        ]);

        if (!empty($existing)) {
            wp_safe_redirect(
                add_query_arg(
                    "naano_builder",
                    "1",
                    get_permalink($existing[0]->ID),
                ),
            );
            exit();
        }

        // Create a child page whose slug equals the language code.
        // WordPress will resolve the URL as /{root-slug}/{lang}/ automatically.
        $new_id = wp_insert_post([
            "post_title" => $root->post_title . " (" . strtoupper($lang) . ")",
            "post_name" => $lang,
            "post_parent" => $root_id,
            "post_status" => "draft",
            "post_type" => "page",
            "post_content" => "",
        ]);

        if (is_wp_error($new_id)) {
            wp_die(esc_html($new_id->get_error_message()));
        }

        // Copy sections from root.
        $sections = get_post_meta($root_id, "_naano_sections", true);
        if ($sections) {
            update_post_meta($new_id, "_naano_sections", $sections);
        }

        // Copy standalone HTML.
        $html = get_post_meta($root_id, "_naano_page_html", true);
        if ($html) {
            update_post_meta($new_id, "_naano_page_html", $html);
            update_post_meta($new_id, "_naano_standalone", "1");
        }

        // Store translation relationship.
        update_post_meta($new_id, "_naano_translation_of", $root_id);
        update_post_meta($new_id, "_naano_lang", $lang);

        // Ensure the root page is tagged with its own language.
        if (!get_post_meta($root_id, "_naano_lang", true)) {
            update_post_meta($root_id, "_naano_lang", "default");
        }

        wp_safe_redirect(
            add_query_arg("naano_builder", "1", get_permalink($new_id)),
        );
        exit();
    }

    /**
     * Enqueue plugin CSS/JS only on plugin admin pages.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook_suffix): void
    {
        $plugin_pages = [
            "toplevel_page_naano-ai-builder",
            "naano-ai-builder_page_naano-settings",
        ];

        if (!in_array($hook_suffix, $plugin_pages, true)) {
            return;
        }

        // Shared CSS for the admin pages list / settings.
        wp_enqueue_style(
            "naano-builder",
            NAANO_PLUGIN_URL . "assets/css/builder.css",
            [],
            NAANO_VERSION,
        );

        // The "Pages" list screen (toplevel_page_naano-ai-builder) also
        // ships its own status-badge / row-action CSS and a small jQuery
        // helper that toggles the per-row translate form. Both used to
        // be echoed inline by templates/admin-pages-list.php — they are
        // now real, enqueued assets so the WordPress.org "use wp_enqueue"
        // guideline is satisfied.
        if ($hook_suffix === "toplevel_page_naano-ai-builder") {
            wp_enqueue_style(
                "naano-admin-pages-list",
                NAANO_PLUGIN_URL . "assets/css/admin-pages-list.css",
                ["naano-builder"],
                NAANO_VERSION,
            );
            wp_enqueue_script(
                "naano-admin-pages-list",
                NAANO_PLUGIN_URL . "assets/js/admin-pages-list.js",
                ["jquery"],
                NAANO_VERSION,
                true,
            );
        }

        // The Settings screen used to inline a ~290-line <script> block
        // for tab switching, variable/language row management, and the
        // AJAX save/test buttons. That JS is now shipped as a real file
        // (assets/js/settings-page.js) and reads its ajax URL, nonce,
        // and translatable strings from window.naanoSettingsData, set
        // by wp_localize_script() below.
        if ($hook_suffix === "naano-ai-builder_page_naano-settings") {
            wp_enqueue_script(
                "naano-settings-page",
                NAANO_PLUGIN_URL . "assets/js/settings-page.js",
                ["jquery"],
                NAANO_VERSION,
                true,
            );

            wp_localize_script("naano-settings-page", "naanoSettingsData", [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce("naano_builder_nonce"),
                "i18n" => [
                    "varKeyPlaceholder" => __(
                        "e.g. primary_color",
                        "naano-ai-website-builder",
                    ),
                    "varValuePlaceholder" => __(
                        "e.g. #3B82F6",
                        "naano-ai-website-builder",
                    ),
                    "langCodePlaceholder" => __(
                        "e.g. es",
                        "naano-ai-website-builder",
                    ),
                    "langLabelPlaceholder" => __(
                        "e.g. Spanish",
                        "naano-ai-website-builder",
                    ),
                    "remove" => __("Remove", "naano-ai-website-builder"),
                    "requestFailed" => __(
                        "Request failed.",
                        "naano-ai-website-builder",
                    ),
                    "enterApiKey" => __(
                        "Please enter an API key.",
                        "naano-ai-website-builder",
                    ),
                    "enterApiKeyFirst" => __(
                        "Please enter an API key first.",
                        "naano-ai-website-builder",
                    ),
                    "enterModel" => __(
                        "Please enter a Model Override before testing.",
                        "naano-ai-website-builder",
                    ),
                    "connected" => __(
                        "Connected!",
                        "naano-ai-website-builder",
                    ),
                ],
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    /**
     * Render the admin pages list (backoffice dashboard).
     *
     * @return void
     */
    public function render_pages_list(): void
    {
        require NAANO_PLUGIN_DIR . "templates/admin-pages-list.php";
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        require NAANO_PLUGIN_DIR . "templates/settings-page.php";
    }

    /**
     * Render the Site Configuration page (slogan, favicon, maintenance).
     *
     * @return void
     */
    public function render_site_config_page(): void
    {
        // Enqueue the site-config JS (handles the AJAX save, the
        // wp.media() favicon picker, and the "Create maintenance page"
        // shortcut). The handle name follows the rest of the plugin
        // (naano-*) for consistency.
        wp_enqueue_media();
        wp_enqueue_script(
            "naano-site-config",
            NAANO_PLUGIN_URL . "assets/js/site-config.js",
            ["jquery", "wp-util"],
            NAANO_VERSION,
            true,
        );
        wp_localize_script("naano-site-config", "naanoSiteConfig", [
            "ajaxUrl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("naano_builder_nonce"),
            "i18n" => [
                "saved" => __("Configuration saved.", "naano-ai-website-builder"),
                "save_failed" => __(
                    "Save failed — please retry.",
                    "naano-ai-website-builder",
                ),
                "pick_favicon" => __(
                    "Choose favicon",
                    "naano-ai-website-builder",
                ),
                "use_this" => __("Use this image", "naano-ai-website-builder"),
                // Live status banner text shown when the user toggles
                // the maintenance-mode checkbox. Must match the strings
                // rendered server-side in templates/site-config-page.php
                // so the banner doesn't flicker on save.
                "maint_on" => __(
                    "Maintenance mode is ON — visitors see the maintenance page.",
                    "naano-ai-website-builder",
                ),
                "maint_off" => __(
                    "Maintenance mode is OFF — your site is live.",
                    "naano-ai-website-builder",
                ),
            ],
        ]);
        require NAANO_PLUGIN_DIR . "templates/site-config-page.php";
    }

    /**
     * Output a <link rel="icon"> tag in the document <head> when a
     * favicon URL has been configured under Site Configuration.
     *
     * The same callback runs on both wp_head and admin_head so the
     * favicon shows in browser tabs for the public site, the WP admin,
     * and the Naano builder overlay. We also emit an apple-touch-icon
     * for iOS home-screen bookmarks.
     *
     * Skipped silently when no favicon is configured so the active
     * theme's site_icon (if any) can take over.
     *
     * @return void
     */
    public function render_favicon_meta(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->build_favicon_meta_html();
    }

    /**
     * Build the <link> tag(s) for the configured favicon. Returns an
     * empty string when no favicon URL is set so the caller can
     * concatenate it without conditional guards.
     *
     * Shared by render_favicon_meta (wp_head/admin_head printer) AND
     * the standalone page renderer, which doesn't go through wp_head
     * for anonymous visitors and so needs to inject the tag directly.
     *
     * @return string
     */
    private function build_favicon_meta_html(): string
    {
        $favicon = get_option("naano_site_favicon_url", "");
        if (!$favicon) {
            return "";
        }
        // Guess the MIME type from the extension. PNG, ICO and SVG are
        // the only three the major browsers actually care about — anything
        // else falls back to a bare type attribute which all browsers
        // tolerate (they sniff the bytes anyway).
        $ext = strtolower(
            pathinfo(wp_parse_url($favicon, PHP_URL_PATH) ?: "", PATHINFO_EXTENSION),
        );
        $type = "image/x-icon";
        if ($ext === "png") {
            $type = "image/png";
        } elseif ($ext === "svg") {
            $type = "image/svg+xml";
        } elseif ($ext === "jpg" || $ext === "jpeg") {
            $type = "image/jpeg";
        } elseif ($ext === "webp") {
            $type = "image/webp";
        }
        return '<link rel="icon" type="' .
            esc_attr($type) .
            '" href="' .
            esc_url($favicon) .
            '">' .
            "\n" .
            '<link rel="apple-touch-icon" href="' .
            esc_url($favicon) .
            '">' .
            "\n";
    }

    /**
     * Intercept every front-end request when maintenance mode is on and
     * serve the configured maintenance page in place of the requested
     * URL. Hooked on `template_redirect` priority 0 so we run before
     * maybe_render_standalone_page.
     *
     * Bypass conditions (in order):
     *   1. Maintenance toggle is off → return normally.
     *   2. No maintenance page configured / page missing → return.
     *   3. Caller is an admin (manage_options) → preview the site
     *      normally so they can still see / edit pages while maintenance
     *      is active. They can still hit the maintenance URL directly to
     *      preview it.
     *   4. ?naano_builder=1 / ?naano_new=1 → preserve the builder
     *      overlay flow.
     *   5. ?naano_maintenance_preview=1 → explicit preview link from
     *      the Site Configuration page.
     *   6. Request is for the maintenance page itself → render it
     *      normally with a 200 status (no 503 — the admin is looking
     *      at it on purpose).
     *
     * Everything else gets the maintenance page HTML + a 503 status +
     * a Retry-After header set to 1 hour (a reasonable default; the
     * admin can override via filter `naano_maintenance_retry_after`).
     *
     * @return void
     */
    public function maybe_render_maintenance_page(): void
    {
        // 1. Toggle.
        if (!get_option("naano_maintenance_enabled", "")) {
            return;
        }
        // 2. Configured page.
        $maint_id = (int) get_option("naano_maintenance_page_id", 0);
        if (!$maint_id) {
            return;
        }
        $maint_post = get_post($maint_id);
        if (!$maint_post || $maint_post->post_status === "trash") {
            return;
        }

        // 3. Admins bypass — they can still browse the site while it's
        // closed to the public.
        if (current_user_can("manage_options")) {
            return;
        }

        // 4. Builder/preview overlay.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET["naano_builder"]) || !empty($_GET["naano_new"])) {
            return;
        }
        // 5. Explicit preview link.
        $is_preview = !empty($_GET["naano_maintenance_preview"]);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // 6. The maintenance page itself.
        $queried_id = get_queried_object_id();
        $is_maint_url = ($queried_id === $maint_id);

        $html = get_post_meta($maint_id, "_naano_page_html", true);
        if (!$html) {
            // Maintenance page has no assembled HTML yet — fall through
            // to normal rendering so the admin doesn't accidentally
            // lock out the whole site with a blank screen.
            return;
        }

        if (!$is_maint_url && !$is_preview) {
            $retry_after = (int) apply_filters(
                "naano_maintenance_retry_after",
                3600,
            );
            status_header(503);
            nocache_headers();
            header("Retry-After: " . max(0, $retry_after));
        }

        header("Content-Type: text/html; charset=UTF-8");
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $html;
        exit();
    }

    /**
     * Handle the "Create maintenance page" form on Site Configuration.
     *
     * Creates a fresh WordPress page tagged with _naano_maintenance=1
     * meta (so the pages list can show the badge) and assigns it as the
     * configured maintenance page. The user is then redirected straight
     * into the builder to generate / edit the content.
     *
     * If a maintenance page already exists, the existing one is reused
     * (no duplicate is created) and the user is redirected to its
     * builder.
     *
     * @return void
     */
    public function handle_create_maintenance_page(): void
    {
        if (!current_user_can("manage_options")) {
            wp_die(
                esc_html__(
                    "Insufficient permissions.",
                    "naano-ai-website-builder",
                ),
            );
        }
        check_admin_referer("naano_create_maintenance_page");

        $existing_id = (int) get_option("naano_maintenance_page_id", 0);
        $existing = $existing_id ? get_post($existing_id) : null;
        if ($existing && $existing->post_status !== "trash") {
            // Already exists — go straight to the builder for it.
            wp_safe_redirect(
                add_query_arg(
                    "naano_builder",
                    "1",
                    get_permalink($existing->ID),
                ),
            );
            exit();
        }

        // Create a draft page. We use status='private' so it's
        // accessible at a clean URL but never shows up in front-end
        // listings or search results before the admin is ready.
        $new_id = wp_insert_post(
            [
                "post_type" => "page",
                "post_status" => "private",
                "post_title" => __(
                    "Site Maintenance",
                    "naano-ai-website-builder",
                ),
                "post_name" => "maintenance",
                "post_content" => __(
                    "This page is rendered by Naano AI Website Builder during maintenance mode.",
                    "naano-ai-website-builder",
                ),
            ],
            true,
        );

        if (is_wp_error($new_id) || !$new_id) {
            wp_die(
                esc_html__(
                    "Could not create maintenance page.",
                    "naano-ai-website-builder",
                ),
            );
        }

        update_post_meta($new_id, "_naano_maintenance", "1");
        // Mark it as a Naano page so it shows up in the AI Pages list
        // (the list filters on the presence of _naano_sections meta).
        update_post_meta($new_id, "_naano_sections", []);
        update_post_meta($new_id, "_naano_standalone", "1");

        update_option("naano_maintenance_page_id", (int) $new_id);

        // Redirect straight into the builder so the admin can generate
        // the maintenance content right away.
        wp_safe_redirect(
            add_query_arg("naano_builder", "1", get_permalink($new_id)),
        );
        exit();
    }

    // -------------------------------------------------------------------------
    // Frontend builder
    // -------------------------------------------------------------------------

    /**
     * Intercept standalone Naano pages (tagged with _naano_standalone meta)
     * and output the assembled HTML directly, bypassing the WordPress theme.
     *
     * @return void
     */
    public function maybe_render_standalone_page(): void
    {
        if (!is_singular("page")) {
            return;
        }

        $page_id = get_queried_object_id();
        if (!$page_id || !get_post_meta($page_id, "_naano_standalone", true)) {
            return;
        }

        // The raw HTML is stored in _naano_page_html meta to avoid being
        // mangled by WordPress content filters on post_content.
        $html = get_post_meta($page_id, "_naano_page_html", true);

        if (!$html) {
            return; // Nothing to render; let WP fall through normally.
        }

        // Inject the configured favicon into <head> directly. For logged-in
        // visitors the wp_head capture below will ALSO emit a favicon tag —
        // duplicate link rels are harmless and browsers just pick one.
        // For anonymous visitors this is the only place the favicon link
        // can land because wp_head is not run on the standalone HTML path.
        $favicon_html = $this->build_favicon_meta_html();
        if ($favicon_html && stripos($html, "</head>") !== false) {
            $html = str_ireplace("</head>", $favicon_html . "</head>", $html);
        }

        // Inject a floating language switcher when this page has translation variants.
        $switcher = $this->build_frontend_lang_switcher($page_id);
        if ($switcher) {
            // Insert just before the closing </body> tag; fall back to appending.
            if (stripos($html, "</body>") !== false) {
                $html = str_ireplace("</body>", $switcher . "</body>", $html);
            } else {
                $html .= $switcher;
            }
        }

        // Inject the WordPress admin bar for logged-in users.
        if (is_user_logged_in() && is_admin_bar_showing()) {
            // Capture wp_head output (admin bar CSS + scripts).
            ob_start();
            wp_head();
            $head_assets = ob_get_clean();

            // Capture wp_footer output (admin bar HTML + scripts).
            ob_start();
            wp_footer();
            $footer_assets = ob_get_clean();

            // Add admin-bar body class and offset the page content.
            if (stripos($html, "<body") !== false) {
                $html = preg_replace(
                    '/(<body[^>]*class=["\'])/',
                    '$1admin-bar ',
                    $html,
                    1,
                    $count,
                );
                if (!$count) {
                    $html = preg_replace(
                        "/(<body)/",
                        '$1 class="admin-bar"',
                        $html,
                        1,
                    );
                }
            }

            // CSS + JS to offset fixed/sticky headers below the WP admin
            // bar on the standalone Naano page. These blocks are part of
            // the page CONTENT we are serving directly, not enqueued
            // theme assets: this code path runs after `template_redirect`
            // and outputs a complete HTML document with `exit()`, which
            // means wp_enqueue_style() / wp_enqueue_script() (which write
            // into wp_head / wp_footer of the active theme) would have
            // no effect here. The "use wp_enqueue commands" guideline
            // applies to WordPress runtime assets — these strings are
            // page content woven into the served HTML document, which
            // is why they're concatenated rather than enqueued.
            $admin_bar_css =
                '<style id="naano-admin-bar-fix">' .
                ".naano-abfix { top: 32px !important; }" .
                "@media screen and (max-width:782px){ .naano-abfix { top: 46px !important; } }" .
                "#wpadminbar .naano-ab-edit > .ab-item { display: flex; align-items: center; }" .
                "</style>";

            $admin_bar_js =
                '<script id="naano-admin-bar-fix-js">' .
                "(function(){" .
                "function f(){" .
                'document.querySelectorAll("header,nav,section,[class*=header],[class*=nav]").forEach(function(el){' .
                "var s=getComputedStyle(el).position;" .
                'if(s==="fixed"||s==="sticky")el.classList.add("naano-abfix");' .
                "});" .
                "}" .
                "f();" .
                'window.addEventListener("load",f);' .
                "})();" .
                "</script>";

            // Inject head assets before </head>.
            if (stripos($html, "</head>") !== false) {
                $html = str_ireplace(
                    "</head>",
                    $admin_bar_css . $head_assets . "</head>",
                    $html,
                );
            }

            // Inject footer assets + admin bar fix script before </body>.
            $footer_all = $footer_assets . $admin_bar_js;
            if (stripos($html, "</body>") !== false) {
                $html = str_ireplace("</body>", $footer_all . "</body>", $html);
            } else {
                $html .= $footer_all;
            }
        }

        header("Content-Type: text/html; charset=UTF-8");
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $html;
        exit();
    }

    /**
     * Build a self-contained floating language switcher widget for the frontend.
     *
     * Returns a fully-styled HTML/CSS/JS snippet that can be injected straight
     * into a standalone page's raw HTML, or an empty string when there are no
     * translation variants to show.
     *
     * @param int $page_id Current page ID being served.
     * @return string HTML snippet, or empty string.
     */
    private function build_frontend_lang_switcher(int $page_id): string
    {
        // Resolve root page and current language.
        $root_id =
            (int) get_post_meta($page_id, "_naano_translation_of", true) ?:
            $page_id;
        $current_lang = get_post_meta($page_id, "_naano_lang", true) ?: "";

        // Build the language map from saved settings.
        $all_languages = get_option("naano_languages", []);
        $default_label = get_option("naano_default_lang_label", "");
        $lang_map = [
            "default" =>
                $default_label !== ""
                    ? $default_label
                    : __("Default", "naano-ai-website-builder"),
        ];
        foreach ((array) $all_languages as $lentry) {
            if (!empty($lentry["code"])) {
                $lang_map[$lentry["code"]] =
                    $lentry["label"] ?? strtoupper($lentry["code"]);
            }
        }

        // Gather all variants: original + every translation.
        $variants = [];

        // Original page.
        $orig_lang = get_post_meta($root_id, "_naano_lang", true) ?: "";
        $orig_status = get_post_status($root_id);
        if ($orig_lang && $orig_status === "publish") {
            $variants[] = [
                "lang" => $orig_lang,
                "label" => $lang_map[$orig_lang] ?? strtoupper($orig_lang),
                "url" => get_permalink($root_id),
                "current" => $page_id === $root_id,
            ];
        }

        // Translation children. We legitimately need to filter pages by
        // _naano_translation_of pointing at this root, and there's no
        // higher-level WP API for that one-to-many relationship.
        $trans_pages = get_posts([
            "post_type" => "page",
            "post_status" => "publish",
            "posts_per_page" => -1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            "meta_key" => "_naano_translation_of",
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            "meta_value" => $root_id,
        ]);
        foreach ($trans_pages as $tp) {
            $tl = get_post_meta($tp->ID, "_naano_lang", true);
            if (!$tl) {
                continue;
            }
            $variants[] = [
                "lang" => $tl,
                "label" => $lang_map[$tl] ?? strtoupper($tl),
                "url" => get_permalink($tp->ID),
                "current" => $page_id === $tp->ID,
            ];
        }

        // Only render when there are at least two published variants.
        if (count($variants) < 2) {
            return "";
        }

        // Build the <li> items.
        $items_html = "";
        foreach ($variants as $v) {
            $code = esc_attr($v["lang"]);
            $label = esc_html($v["label"]);
            $url = esc_url($v["url"]);
            $active = $v["current"] ? " naano-ls__item--active" : "";
            $aria = $v["current"] ? ' aria-current="page"' : "";
            $items_html .= "<li><a href=\"{$url}\" class=\"naano-ls__item{$active}\" hreflang=\"{$code}\"{$aria}>{$label}</a></li>";
        }

        $current_label = esc_html(
            $lang_map[$current_lang] ?? strtoupper($current_lang),
        );

        // Build the floating language-switcher widget. The CSS and JS
        // below are inlined deliberately: the caller (maybe_render_standalone_page)
        // serves a *complete* HTML document directly via `echo $html; exit;`
        // after `template_redirect`, bypassing the WordPress theme. There
        // is no wp_head/wp_footer pipeline for wp_enqueue_style/script to
        // hook into in that code path, so the widget ships as a single
        // self-contained string that is woven into the document just
        // before </body>. The CSS/JS here are part of the served page's
        // CONTENT, not separately-managed theme assets — the WordPress.org
        // "use wp_enqueue commands" guideline does not apply.
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        $html = "<style id=\"naano-ls-css\">\n";
        $html .=
            "#naano-ls{position:fixed;bottom:24px;right:24px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:13px}\n";
        $html .= "#naano-ls *{box-sizing:border-box}\n";
        $html .=
            ".naano-ls__trigger{display:flex;align-items:center;gap:6px;background:#1e293b;color:#f1f5f9;border:1px solid #334155;padding:7px 12px;border-radius:8px;cursor:pointer;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.35);transition:background .15s}\n";
        $html .= ".naano-ls__trigger:hover{background:#334155}\n";
        $html .=
            ".naano-ls__globe{width:16px;height:16px;fill:none;stroke:#94a3b8;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}\n";
        $html .=
            ".naano-ls__caret{width:10px;height:10px;fill:none;stroke:#94a3b8;stroke-width:2;transition:transform .2s;flex-shrink:0}\n";
        $html .= ".naano-ls__caret--open{transform:rotate(180deg)}\n";
        $html .=
            ".naano-ls__menu{display:none;position:absolute;bottom:calc(100% + 6px);right:0;background:#1e293b;border:1px solid #334155;border-radius:8px;overflow:hidden;min-width:140px;box-shadow:0 4px 16px rgba(0,0,0,.4)}\n";
        $html .= ".naano-ls__menu--open{display:block}\n";
        $html .= ".naano-ls__menu ul{list-style:none;margin:0;padding:4px 0}\n";
        $html .=
            ".naano-ls__item{display:block;padding:8px 14px;color:#cbd5e1;text-decoration:none;white-space:nowrap;transition:background .12s,color .12s}\n";
        $html .= ".naano-ls__item:hover{background:#334155;color:#f1f5f9}\n";
        $html .=
            ".naano-ls__item--active{color:#60a5fa;font-weight:600;pointer-events:none;background:#1e3a5a}\n";
        $html .= "</style>\n";
        $html .=
            "<div id=\"naano-ls\" role=\"navigation\" aria-label=\"Language\">\n";
        $html .=
            "  <div class=\"naano-ls__trigger\" id=\"naano-ls-trigger\" aria-haspopup=\"true\" aria-expanded=\"false\" tabindex=\"0\">\n";
        $html .=
            '    <svg class="naano-ls__globe" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>' .
            "\n";
        $html .= "    <span id=\"naano-ls-label\">{$current_label}</span>\n";
        $html .=
            '    <svg class="naano-ls__caret" id="naano-ls-caret" viewBox="0 0 10 10" aria-hidden="true"><polyline points="1,3 5,7 9,3"/></svg>' .
            "\n";
        $html .= "  </div>\n";
        $html .=
            "  <div class=\"naano-ls__menu\" id=\"naano-ls-menu\" role=\"menu\">\n";
        $html .= "    <ul>{$items_html}</ul>\n";
        $html .= "  </div>\n";
        $html .= "</div>\n";
        $html .= "<script id=\"naano-ls-js\">\n";
        $html .= "(function(){\n";
        $html .= "  var t=document.getElementById('naano-ls-trigger'),\n";
        $html .= "      m=document.getElementById('naano-ls-menu'),\n";
        $html .= "      c=document.getElementById('naano-ls-caret');\n";
        $html .=
            "  function open(){m.classList.add('naano-ls__menu--open');c.classList.add('naano-ls__caret--open');t.setAttribute('aria-expanded','true');}\n";
        $html .=
            "  function close(){m.classList.remove('naano-ls__menu--open');c.classList.remove('naano-ls__caret--open');t.setAttribute('aria-expanded','false');}\n";
        $html .=
            "  t.addEventListener('click',function(){m.classList.contains('naano-ls__menu--open')?close():open();});\n";
        $html .=
            "  t.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();m.classList.contains('naano-ls__menu--open')?close():open();}if(e.key==='Escape'){close();}});\n";
        $html .=
            "  document.addEventListener('click',function(e){if(!document.getElementById('naano-ls').contains(e.target)){close();}});\n";
        $html .= "})();\n";
        $html .= "</script>\n";

        return $html;
        // phpcs:enable
    }

    /**
     * Intercept frontend requests with ?naano_builder=1 and render the
     * full visual builder instead of the normal page template.
     *
     * Only accessible to logged-in users with manage_options capability.
     *
     * @return void
     */
    public function maybe_render_frontend_builder(): void
    {
        // Read-only check of unauthenticated query parameters — this is
        // a frontend route gate, not a form submission. Access is protected
        // by current_user_can('manage_options') below.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (
            empty( $_GET["naano_builder"] ) ||
            ! current_user_can( "manage_options" )
        ) {
            return;
        }

        // Determine the page ID from the queried object (e.g. /my-page/?naano_builder=1).
        // When naano_new=1 is present the user wants a blank new page — ignore the queried object.
        $page_id = empty( $_GET["naano_new"] )
            ? ( get_queried_object_id() ?: 0 )
            : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Enqueue all required assets for the builder.
        wp_enqueue_style("dashicons");
        wp_enqueue_style(
            "naano-builder",
            NAANO_PLUGIN_URL . "assets/css/builder.css",
            ["dashicons"],
            NAANO_VERSION,
        );

        // Reset rules that suppress any theme styles leaking into the
        // builder overlay. Previously printed as an inline <style> block
        // inside templates/frontend-builder.php — moved here so the rules
        // flow through wp_add_inline_style() per the WP enqueue guideline.
        wp_add_inline_style(
            "naano-builder",
            "html,body{margin:0 !important;padding:0 !important;" .
                "overflow:hidden !important;background:#1d2327 !important;}" .
                ".naano-vb{height:100vh !important;}",
        );

        wp_enqueue_media();

        wp_enqueue_script(
            "naano-builder",
            NAANO_PLUGIN_URL . "assets/js/builder.js",
            ["jquery", "wp-util"],
            NAANO_VERSION,
            true,
        );

        wp_enqueue_script(
            "naano-preview",
            NAANO_PLUGIN_URL . "assets/js/preview.js",
            ["naano-builder"],
            NAANO_VERSION,
            true,
        );

        $sections = [];
        $references = [];
        $global_css = "";

        if ($page_id) {
            $sm = new Naano_Section_Manager();
            $rm = new Naano_Reference_Manager();
            $sections = $sm->get_sections($page_id);
            // Load the saved page-level "Global CSS" so the builder can
            // pre-fill the textareas AND inject it into the very first
            // live-preview render — otherwise the editor opens with the
            // page un-styled until the user touches the CSS field.
            $global_css = $sm->get_global_css($page_id);
            foreach ($sections as $sec) {
                $references[$sec["id"]] = $rm->get_references(
                    $page_id,
                    $sec["id"],
                );
            }
        }

        // Fetch header/footer sections from other Naano pages for the "Import Components" UI
        // (only needed on the new-page screen where $page_id === 0).
        $existing_components = [];
        if (!$page_id) {
            $comp_sm = new Naano_Section_Manager();
            $comp_pages = get_posts([
                "post_type" => "page",
                "post_status" => "any",
                "posts_per_page" => 20,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                "meta_key" => "_naano_sections",
                "orderby" => "modified",
                "order" => "DESC",
            ]);
            foreach ($comp_pages as $cp) {
                $cp_sections = $comp_sm->get_sections($cp->ID);
                $comp_filtered = array_values(
                    array_filter($cp_sections, static function ($s) {
                        $t = $s["type"] ?? "";
                        $i = $s["id"] ?? "";
                        return in_array($t, ["header", "footer"], true) ||
                            strpos($i, "header") !== false ||
                            strpos($i, "footer") !== false;
                    }),
                );
                // Strip full HTML — client will reference by sourcePageId+id; server fetches from DB.
                $comps = array_map(static function ($s) use ($cp) {
                    return [
                        "id" => $s["id"] ?? "",
                        "type" => $s["type"] ?? "",
                        "sourcePageId" => $cp->ID,
                    ];
                }, $comp_filtered);
                if (!empty($comps)) {
                    $existing_components[] = [
                        "pageId" => $cp->ID,
                        "pageTitle" =>
                            $cp->post_title ?:
                            __("(no title)", "naano-ai-website-builder"),
                        "sections" => $comps,
                    ];
                }
            }
        }

        $_mlp = get_option("naano_provider", "claude");
        $_mlm = get_option("naano_model", "");
        $_mld = [
            "claude" => "claude-sonnet-4-6",
            "gemini" => "gemini-2.5-flash",
            "kimi" => "kimi-k2.6",
            "openai" => "gpt-5.5",
            "deepseek" => "deepseek-v4-flash",
        ];
        $model_label = $_mlm ?: $_mld[$_mlp] ?? $_mlp;

        // Collect translation variants so the toolbar language switcher can be rendered.
        $current_lang = "";
        $translations = [];
        $all_languages = get_option("naano_languages", []);
        if ($page_id) {
            $default_label = get_option("naano_default_lang_label", "");
            $lang_map = [
                "default" =>
                    $default_label !== ""
                        ? $default_label
                        : __("Default", "naano-ai-website-builder"),
            ];
            foreach ((array) $all_languages as $lentry) {
                if (!empty($lentry["code"])) {
                    $lang_map[$lentry["code"]] =
                        $lentry["label"] ?? strtoupper($lentry["code"]);
                }
            }

            $root_id =
                (int) get_post_meta($page_id, "_naano_translation_of", true) ?:
                $page_id;
            $current_lang = get_post_meta($page_id, "_naano_lang", true) ?: "";

            // Original page entry.
            $orig_lang = get_post_meta($root_id, "_naano_lang", true) ?: "";
            if ($orig_lang) {
                $translations[] = [
                    "lang" => $orig_lang,
                    "label" => $lang_map[$orig_lang] ?? strtoupper($orig_lang),
                    "pageId" => $root_id,
                    "builderUrl" => add_query_arg(
                        "naano_builder",
                        "1",
                        get_permalink($root_id),
                    ),
                    "current" => $page_id === $root_id,
                ];
            }

            // All translated variants.
            $trans_pages = get_posts([
                "post_type" => "page",
                "post_status" => "any",
                "posts_per_page" => -1,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                "meta_key" => "_naano_translation_of",
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                "meta_value" => $root_id,
            ]);
            foreach ($trans_pages as $tp) {
                $tl = get_post_meta($tp->ID, "_naano_lang", true);
                if (!$tl) {
                    continue;
                }
                $translations[] = [
                    "lang" => $tl,
                    "label" => $lang_map[$tl] ?? strtoupper($tl),
                    "pageId" => $tp->ID,
                    "builderUrl" => add_query_arg(
                        "naano_builder",
                        "1",
                        get_permalink($tp->ID),
                    ),
                    "current" => $page_id === $tp->ID,
                ];
            }
        }

        $saved_assets = $page_id
            ? get_post_meta($page_id, "_naano_assets", true)
            : [];
        $saved_redirects = $page_id
            ? get_post_meta($page_id, "_naano_redirects", true)
            : [];
        if (!is_array($saved_assets)) {
            $saved_assets = [];
        }
        if (!is_array($saved_redirects)) {
            $saved_redirects = [];
        }

        // Collect WordPress navigation menus for the builder.
        $wp_menus = [];
        foreach (wp_get_nav_menus() as $menu) {
            $wp_menus[] = [
                "id" => $menu->term_id,
                "name" => $menu->name,
            ];
        }

        wp_localize_script("naano-builder", "naanoBuilderData", [
            "ajaxUrl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("naano_builder_nonce"),
            "pageId" => $page_id,
            "sections" => $sections,
            "globalCss" => $global_css,
            "references" => $references,
            "assets" => $saved_assets,
            "redirects" => $saved_redirects,
            "modelLabel" => $model_label,
            "existingComponents" => $existing_components,
            "currentLang" => $current_lang,
            "translations" => $translations,
            "wpMenus" => $wp_menus,
            "strings" => [
                "confirm_delete" => __(
                    "Are you sure you want to delete this section?",
                    "naano-ai-website-builder",
                ),
                "generating" => __("Generating…", "naano-ai-website-builder"),
                "updating" => __("Updating…", "naano-ai-website-builder"),
                "error_generic" => __(
                    "An error occurred. Please try again.",
                    "naano-ai-website-builder",
                ),
                "select_section" => __(
                    "Please select a section first.",
                    "naano-ai-website-builder",
                ),
                "enter_description" => __(
                    "Please enter a site description.",
                    "naano-ai-website-builder",
                ),
                "enter_instruction" => __(
                    "Please enter an instruction.",
                    "naano-ai-website-builder",
                ),
                "new_section_name" => __(
                    'New section name (e.g. "Team", "Gallery"):',
                    "naano-ai-website-builder",
                ),
                "click_section" => __(
                    "— click a section in the preview —",
                    "naano-ai-website-builder",
                ),

                // — Strings used dynamically inside builder.js. Anything the
                // user can see at runtime that isn't already in the templates
                // belongs here so it goes through the WP translation
                // pipeline instead of being hard-coded English.
                "sections_selected" =>
                /* translators: %d: number of sections currently selected by the user */
                __(
                    "%d sections selected",
                    "naano-ai-website-builder",
                ),
                "update_section" => __(
                    "Update Section",
                    "naano-ai-website-builder",
                ),
                "update_n_sections" =>
                /* translators: %d: number of sections that will be updated by a bulk action */
                __(
                    "Update %d Sections",
                    "naano-ai-website-builder",
                ),
                "show" => __("Show", "naano-ai-website-builder"),
                "hide" => __("Hide", "naano-ai-website-builder"),
                "edit" => __("Edit", "naano-ai-website-builder"),
                "delete" => __("Delete", "naano-ai-website-builder"),
                "remove" => __("Remove", "naano-ai-website-builder"),
                "drag_to_reorder" => __(
                    "Drag to reorder",
                    "naano-ai-website-builder",
                ),
                "applied" => __("Applied", "naano-ai-website-builder"),
                "js_syntax_error" => __(
                    "⚠ JavaScript syntax error — saved, but the button won't run until fixed:",
                    "naano-ai-website-builder",
                ),
                "section_deleted" => __(
                    "Section deleted.",
                    "naano-ai-website-builder",
                ),
                "screenshot_added" => __(
                    "Screenshot added! 🖼️",
                    "naano-ai-website-builder",
                ),
                "url_added" => __(
                    "URL reference added! 🔗",
                    "naano-ai-website-builder",
                ),
                "html_copied" => __(
                    "HTML copied to clipboard! 📋",
                    "naano-ai-website-builder",
                ),
                "set_homepage" => __(
                    "Set as homepage!",
                    "naano-ai-website-builder",
                ),
                "enter_url" => __(
                    "Please enter a URL.",
                    "naano-ai-website-builder",
                ),
                "enter_page_title" => __(
                    "Please enter a page title.",
                    "naano-ai-website-builder",
                ),
                "enter_section_name" => __(
                    "Please enter a section name.",
                    "naano-ai-website-builder",
                ),
                "save_failed" => __("Save failed.", "naano-ai-website-builder"),
                "save_failed_network" => __(
                    "Save failed (network).",
                    "naano-ai-website-builder",
                ),
                "saved_n_sections" =>
                /* translators: %d: number of sections saved (singular form, used when count is 1) */
                __(
                    "Saved %d section",
                    "naano-ai-website-builder",
                ),
                "saved_n_sections_plural" =>
                /* translators: %d: number of sections saved (plural form, used when count > 1) */
                __(
                    "Saved %d sections",
                    "naano-ai-website-builder",
                ),
                "and_global_css" => __(
                    " + global CSS",
                    "naano-ai-website-builder",
                ),
                "section_updated" => __(
                    "Section updated! ✨",
                    "naano-ai-website-builder",
                ),
                "n_sections_updated" =>
                /* translators: %d: number of sections that were successfully updated */
                __(
                    "%d sections updated! ✨",
                    "naano-ai-website-builder",
                ),
                "section_added" =>
                /* translators: %s: name of the newly added section (e.g. "Hero", "Pricing") */
                __(
                    'Section "%s" added! ✨',
                    "naano-ai-website-builder",
                ),
                "site_generated" => __(
                    "Website generated successfully! 🎉",
                    "naano-ai-website-builder",
                ),
                "site_generated_partial" =>
                /* translators: 1: number of sections successfully generated, 2: total number of sections that were requested */
                __(
                    "%1\$d of %2\$d sections generated. ⚠️",
                    "naano-ai-website-builder",
                ),
                "generation_failed" =>
                /* translators: %s: error message returned by the LLM provider or server */
                __(
                    "Generation failed: %s",
                    "naano-ai-website-builder",
                ),
                "unknown_error_check_settings" => __(
                    "unknown error — check API settings.",
                    "naano-ai-website-builder",
                ),
                "unknown_error" => __(
                    "unknown error",
                    "naano-ai-website-builder",
                ),
                "site_generated_with_skips" =>
                /* translators: 1: number of sections skipped due to timeout, 2: comma-separated list of skipped section names */
                __(
                    "Website generated, but %1\$d section(s) were skipped due to server timeouts: %2\$s. You can regenerate them individually from the builder.",
                    "naano-ai-website-builder",
                ),
                "generation_in_progress" =>
                /* translators: %d: number of sections that have been generated so far while the job is still running */
                __(
                    "Generation is still in progress on the server. Showing %d section(s) generated so far — refresh in a moment to see more.",
                    "naano-ai-website-builder",
                ),
                "generation_interrupted" =>
                /* translators: 1: error message describing why generation stopped, 2: number of sections successfully saved before the interruption */
                __(
                    "Generation interrupted: %1\$s Showing %2\$d section(s) that were saved before the error.",
                    "naano-ai-website-builder",
                ),
                "prompt_enhanced_sections" => __(
                    "Prompt enhanced & sections suggested!",
                    "naano-ai-website-builder",
                ),
                "instruction_enhanced" => __(
                    "Instruction enhanced!",
                    "naano-ai-website-builder",
                ),
                "page_published_html" =>
                /* translators: 1: URL to the published page on the public site, 2: URL to the page editor in wp-admin */
                __(
                    'Page published! <a href="%1$s" target="_blank">View it</a> · <a href="%2$s" target="_blank">Edit in WP</a>',
                    "naano-ai-website-builder",
                ),
                "retrying_section" =>
                /* translators: %s: section identifier currently being retried after a failure */
                __(
                    "Retrying section: %s…",
                    "naano-ai-website-builder",
                ),
                "section_recovered" =>
                /* translators: %s: section identifier that has been successfully regenerated */
                __(
                    "Section recovered: %s ✓",
                    "naano-ai-website-builder",
                ),
                "retry_failed" =>
                /* translators: %s: error message returned by the LLM provider when the retry attempt failed */
                __(
                    "Retry failed: %s",
                    "naano-ai-website-builder",
                ),
                "retry" => __("Retry", "naano-ai-website-builder"),
                "failed_label" => __("(failed)", "naano-ai-website-builder"),
                "custom_html_inserted" => __(
                    "Custom HTML inserted! 🧱",
                    "naano-ai-website-builder",
                ),
                "custom_html_updated" => __(
                    "Custom HTML updated! ✨",
                    "naano-ai-website-builder",
                ),
                "custom_html_empty_hint" => __(
                    "Click to edit HTML",
                    "naano-ai-website-builder",
                ),
                "enter_html" => __(
                    "Please enter some HTML.",
                    "naano-ai-website-builder",
                ),
                "edit_custom_html" => __(
                    "Edit custom HTML",
                    "naano-ai-website-builder",
                ),
                "save" => __("Save", "naano-ai-website-builder"),
                "insert" => __("Insert", "naano-ai-website-builder"),
                "remove_bg_color" => __(
                    "Remove background color",
                    "naano-ai-website-builder",
                ),
                "asset_added" => __("Asset added!", "naano-ai-website-builder"),
                "select_asset" => __(
                    "Select Asset",
                    "naano-ai-website-builder",
                ),
                "use_this_file" => __(
                    "Use this file",
                    "naano-ai-website-builder",
                ),
                "delete_element_confirm" => __(
                    "Delete this element? You can undo by hitting Ctrl+Z in the iframe (or by regenerating the section).",
                    "naano-ai-website-builder",
                ),
                "unsaved_warning" => __(
                    "You have unsaved manual edits. Click \u201cSave changes\u201d before leaving.",
                    "naano-ai-website-builder",
                ),
            ],
        ]);

        // Output a standalone full-page builder and stop WP from rendering anything else.
        require NAANO_PLUGIN_DIR . "templates/frontend-builder.php";
        exit();
    }
}
