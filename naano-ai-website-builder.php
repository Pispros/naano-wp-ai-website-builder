<?php
/**
 * Plugin Name: Naano AI Website Builder
 * Plugin URI:  https://wordpress.org/plugins/naano-ai-website-builder/
 * Description: AI-powered section-by-section website builder using Claude, Gemini, OpenAI, or Kimi. Pure PHP — no external backend needed.
 * Version:     2.1.3
 * Author:      Naano
 * Author URI:  https://github.com/Pispros
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: naano-ai-website-builder
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (!defined("ABSPATH")) {
    exit();
}

define("NAANO_VERSION", "2.1.3");
define("NAANO_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("NAANO_PLUGIN_URL", plugin_dir_url(__FILE__));
define("NAANO_PLUGIN_BASENAME", plugin_basename(__FILE__));

/**
 * Activation hook – verify PHP/WP/extension requirements.
 */
function naano_activate(): void
{
    $errors = [];

    if (version_compare(PHP_VERSION, "8.1", "<")) {
        $errors[] = sprintf(
            /* translators: %s: current PHP version */
            __(
                "Naano AI Website Builder requires PHP 8.1 or higher. You are running PHP %s.",
                "naano-ai-website-builder",
            ),
            PHP_VERSION,
        );
    }

    global $wp_version;
    if (version_compare($wp_version, "6.0", "<")) {
        $errors[] = sprintf(
            /* translators: %s: current WP version */
            __(
                "Naano AI Website Builder requires WordPress 6.0 or higher. You are running WordPress %s.",
                "naano-ai-website-builder",
            ),
            $wp_version,
        );
    }

    $required_extensions = ["curl", "json", "gd", "dom", "mbstring"];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = sprintf(
                /* translators: %s: PHP extension name */
                __(
                    "Naano AI Website Builder requires the PHP extension: %s.",
                    "naano-ai-website-builder",
                ),
                $ext,
            );
        }
    }

    if (!empty($errors)) {
        set_transient("naano_activation_errors", $errors, 30);
    }
}
register_activation_hook(__FILE__, "naano_activate");

/**
 * Deactivation hook – clean up transients.
 */
function naano_deactivate(): void
{
    delete_transient("naano_activation_errors");
    // Remove any per-request caches.
    global $wpdb;
    // Bulk transient cleanup on deactivation: a single LIKE delete is
    // dramatically faster than iterating delete_transient() across hundreds
    // of rows. This runs exactly once per deactivation, so the lack of
    // caching is intentional — there is nothing to cache for a teardown
    // query, and the rows are being deleted anyway.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_naano_%'",
    );
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_naano_%'",
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    wp_cache_flush_group("options");
}
register_deactivation_hook(__FILE__, "naano_deactivate");

/**
 * Show admin notice if activation checks failed.
 */
function naano_admin_notices(): void
{
    $errors = get_transient("naano_activation_errors");
    if (!empty($errors)) {
        delete_transient("naano_activation_errors");
        echo '<div class="notice notice-error"><p>';
        echo "<strong>" .
            esc_html__(
                "Naano AI Website Builder could not be activated:",
                "naano-ai-website-builder",
            ) .
            "</strong><br>";
        foreach ($errors as $error) {
            echo esc_html($error) . "<br>";
        }
        echo "</p></div>";
    }
}
add_action("admin_notices", "naano_admin_notices");

/**
 * Load all includes.
 */
require_once NAANO_PLUGIN_DIR . "includes/interface-llm-provider.php";
require_once NAANO_PLUGIN_DIR . "includes/class-llm-claude.php";
require_once NAANO_PLUGIN_DIR . "includes/class-llm-gemini.php";
require_once NAANO_PLUGIN_DIR . "includes/class-llm-kimi.php";
require_once NAANO_PLUGIN_DIR . "includes/class-llm-openai.php";
require_once NAANO_PLUGIN_DIR . "includes/class-llm-router.php";
require_once NAANO_PLUGIN_DIR . "includes/class-llm-utils.php";
require_once NAANO_PLUGIN_DIR . "includes/class-payload-compressor.php";
require_once NAANO_PLUGIN_DIR . "includes/class-html-sanitizer.php";
require_once NAANO_PLUGIN_DIR . "includes/class-prompt-builder.php";
require_once NAANO_PLUGIN_DIR . "includes/class-reference-manager.php";
require_once NAANO_PLUGIN_DIR . "includes/class-firecrawl.php";
require_once NAANO_PLUGIN_DIR . "includes/class-section-manager.php";
require_once NAANO_PLUGIN_DIR . "includes/class-conversation.php";
require_once NAANO_PLUGIN_DIR . "includes/jobs/class-job-manager.php";
require_once NAANO_PLUGIN_DIR . "includes/jobs/class-job-runner.php";
require_once NAANO_PLUGIN_DIR . "includes/class-ajax-handler.php";
require_once NAANO_PLUGIN_DIR . "includes/class-admin-page.php";

/**
 * Translation loading note:
 *
 * Since WordPress 4.6, translations for plugins hosted on WordPress.org are
 * loaded automatically. Plugin-bundled translation files placed in
 * /languages/ (e.g. naano-ai-website-builder-fr_FR.mo) are picked up by
 * WordPress without needing load_plugin_textdomain(). We therefore do not
 * call it here — the function is no longer required and would only delay
 * translation loading.
 */

/**
 * Bootstrap the plugin.
 */
function naano_init(): void
{
    new Naano_Admin_Page();
    Naano_Ajax_Handler::register();

    // Wire the WP-Cron action that drives multi-step job execution.
    // Each tick runs one step of one job; the runner re-schedules
    // itself between steps until the job is terminal.
    Naano_Job_Runner::register_hooks();
}
add_action("plugins_loaded", "naano_init");
