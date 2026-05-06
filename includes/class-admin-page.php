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
        add_action("admin_head", [$this, "admin_icon_styles"]);

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
    }

    /**
     * Inject admin CSS: fix the SVG menu icon opacity and align page headings.
     *
     * @return void
     */
    public function admin_icon_styles(): void
    {
        ?>
		<style>
			/* Size and align the custom SVG menu icon exactly like WP dashicons. */
			#adminmenu .toplevel_page_naano-ai-builder .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				padding: 0 !important;
				margin: 0 !important;
				opacity: 1 !important;
				filter: none !important;
				display: block;
			}
			#adminmenu .toplevel_page_naano-ai-builder .wp-menu-image {
				display: flex !important;
				align-items: center;
				justify-content: center;
			}
			/* Vertically centre icon + text in page headings. */
			.naano-page-title {
				display: flex;
				align-items: center;
				gap: 10px;
				line-height: 1;
			}
			.naano-page-title svg {
				width: 28px;
				height: 28px;
				flex-shrink: 0;
			}
		</style>
		<?php
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
        $page_id = (int) ($_POST["page_id"] ?? 0);

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
        if (
            !empty($_GET["naano_builder"]) &&
            current_user_can("manage_options")
        ) {
            return false;
        }
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
                    ["claude", "gemini", "kimi", "openai"],
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
        $keys = array_map(
            "sanitize_text_field",
            (array) ($_POST["naano_vars_keys"] ?? []),
        );
        $values = array_map(
            "sanitize_text_field",
            (array) ($_POST["naano_vars_values"] ?? []),
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
        $codes = array_map(
            "sanitize_key",
            (array) ($_POST["naano_lang_codes"] ?? []),
        );
        $labels = array_map(
            "sanitize_text_field",
            (array) ($_POST["naano_lang_labels"] ?? []),
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
        $page_id = (int) ($_POST["page_id"] ?? 0);
        $lang = sanitize_key($_POST["lang"] ?? "");

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
        $existing = get_posts([
            "post_type" => "page",
            "post_status" => "any",
            "posts_per_page" => 1,
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

        // CSS for the admin pages list / settings.
        wp_enqueue_style(
            "naano-builder",
            NAANO_PLUGIN_URL . "assets/css/builder.css",
            [],
            NAANO_VERSION,
        );
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

            // Inject CSS + JS to offset fixed/sticky headers below the WP admin bar.
            // WP core sets html { margin-top: 32px } for static content.  For
            // position:fixed / position:sticky elements we must add a matching
            // top offset.  Because scoped <style> blocks set position via CSS
            // (not inline), we use a small script that inspects computedStyle.
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

        // Translation children.
        $trans_pages = get_posts([
            "post_type" => "page",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "meta_key" => "_naano_translation_of",
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

        // phpcs:disable
        return <<<HTML
        <style id="naano-ls-css">
        #naano-ls{position:fixed;bottom:24px;right:24px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:13px}
        #naano-ls *{box-sizing:border-box}
        .naano-ls__trigger{display:flex;align-items:center;gap:6px;background:#1e293b;color:#f1f5f9;border:1px solid #334155;padding:7px 12px;border-radius:8px;cursor:pointer;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.35);transition:background .15s}
        .naano-ls__trigger:hover{background:#334155}
        .naano-ls__globe{width:16px;height:16px;fill:none;stroke:#94a3b8;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
        .naano-ls__caret{width:10px;height:10px;fill:none;stroke:#94a3b8;stroke-width:2;transition:transform .2s;flex-shrink:0}
        .naano-ls__caret--open{transform:rotate(180deg)}
        .naano-ls__menu{display:none;position:absolute;bottom:calc(100% + 6px);right:0;background:#1e293b;border:1px solid #334155;border-radius:8px;overflow:hidden;min-width:140px;box-shadow:0 4px 16px rgba(0,0,0,.4)}
        .naano-ls__menu--open{display:block}
        .naano-ls__menu ul{list-style:none;margin:0;padding:4px 0}
        .naano-ls__item{display:block;padding:8px 14px;color:#cbd5e1;text-decoration:none;white-space:nowrap;transition:background .12s,color .12s}
        .naano-ls__item:hover{background:#334155;color:#f1f5f9}
        .naano-ls__item--active{color:#60a5fa;font-weight:600;pointer-events:none;background:#1e3a5a}
        </style>
        <div id="naano-ls" role="navigation" aria-label="Language">
          <div class="naano-ls__trigger" id="naano-ls-trigger" aria-haspopup="true" aria-expanded="false" tabindex="0">
            <svg class="naano-ls__globe" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span id="naano-ls-label">{$current_label}</span>
            <svg class="naano-ls__caret" id="naano-ls-caret" viewBox="0 0 10 10" aria-hidden="true"><polyline points="1,3 5,7 9,3"/></svg>
          </div>
          <div class="naano-ls__menu" id="naano-ls-menu" role="menu">
            <ul>{$items_html}</ul>
          </div>
        </div>
        <script id="naano-ls-js">
        (function(){
          var t=document.getElementById('naano-ls-trigger'),
              m=document.getElementById('naano-ls-menu'),
              c=document.getElementById('naano-ls-caret');
          function open(){m.classList.add('naano-ls__menu--open');c.classList.add('naano-ls__caret--open');t.setAttribute('aria-expanded','true');}
          function close(){m.classList.remove('naano-ls__menu--open');c.classList.remove('naano-ls__caret--open');t.setAttribute('aria-expanded','false');}
          t.addEventListener('click',function(){m.classList.contains('naano-ls__menu--open')?close():open();});
          t.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();m.classList.contains('naano-ls__menu--open')?close():open();}if(e.key==='Escape'){close();}});
          document.addEventListener('click',function(e){if(!document.getElementById('naano-ls').contains(e.target)){close();}});
        })();
        </script>
        HTML;
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
        if (
            empty($_GET["naano_builder"]) ||
            !current_user_can("manage_options")
        ) {
            return;
        }

        // Determine the page ID from the queried object (e.g. /my-page/?naano_builder=1).
        // When naano_new=1 is present the user wants a blank new page — ignore the queried object.
        $page_id = empty($_GET["naano_new"])
            ? (get_queried_object_id() ?:
            0)
            : 0;

        // Enqueue all required assets for the builder.
        wp_enqueue_style("dashicons");
        wp_enqueue_style(
            "naano-builder",
            NAANO_PLUGIN_URL . "assets/css/builder.css",
            ["dashicons"],
            NAANO_VERSION,
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

        if ($page_id) {
            $sm = new Naano_Section_Manager();
            $rm = new Naano_Reference_Manager();
            $sections = $sm->get_sections($page_id);
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
            "kimi" => "kimi-k2-0711-preview",
            "openai" => "gpt-5.5",
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
                "meta_key" => "_naano_translation_of",
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
                "sections_selected" => __(
                    "%d sections selected",
                    "naano-ai-website-builder",
                ),
                "update_section" => __(
                    "Update Section",
                    "naano-ai-website-builder",
                ),
                "update_n_sections" => __(
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
                "save_failed" => __(
                    "Save failed.",
                    "naano-ai-website-builder",
                ),
                "save_failed_network" => __(
                    "Save failed (network).",
                    "naano-ai-website-builder",
                ),
                "saved_n_sections" => __(
                    "Saved %d section",
                    "naano-ai-website-builder",
                ),
                "saved_n_sections_plural" => __(
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
                "n_sections_updated" => __(
                    "%d sections updated! ✨",
                    "naano-ai-website-builder",
                ),
                "section_added" => __(
                    'Section "%s" added! ✨',
                    "naano-ai-website-builder",
                ),
                "site_generated" => __(
                    "Website generated successfully! 🎉",
                    "naano-ai-website-builder",
                ),
                "site_generated_partial" => __(
                    "%1\$d of %2\$d sections generated. ⚠️",
                    "naano-ai-website-builder",
                ),
                "generation_failed" => __(
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
                "site_generated_with_skips" => __(
                    "Website generated, but %1\$d section(s) were skipped due to server timeouts: %2\$s. You can regenerate them individually from the builder.",
                    "naano-ai-website-builder",
                ),
                "generation_in_progress" => __(
                    "Generation is still in progress on the server. Showing %d section(s) generated so far — refresh in a moment to see more.",
                    "naano-ai-website-builder",
                ),
                "generation_interrupted" => __(
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
                "page_published_html" => __(
                    'Page published! <a href="%1$s" target="_blank">View it</a> · <a href="%2$s" target="_blank">Edit in WP</a>',
                    "naano-ai-website-builder",
                ),
                "retrying_section" => __(
                    "Retrying section: %s…",
                    "naano-ai-website-builder",
                ),
                "section_recovered" => __(
                    "Section recovered: %s ✓",
                    "naano-ai-website-builder",
                ),
                "retry_failed" => __(
                    "Retry failed: %s",
                    "naano-ai-website-builder",
                ),
                "retry" => __("Retry", "naano-ai-website-builder"),
                "failed_label" => __("(failed)", "naano-ai-website-builder"),
                "asset_added" => __(
                    "Asset added!",
                    "naano-ai-website-builder",
                ),
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
