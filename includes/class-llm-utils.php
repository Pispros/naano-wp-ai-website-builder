<?php
class Naano_LLM_Utils
{
    /**
     * Disable PHP timeouts for long-running LLM requests.
     * Must be called at the start of each adapter's send() method.
     *
     * NOTE: the zlib.output_compression / output_buffering / implicit_flush
     * tweaks that used to live here are leftovers from the SSE streaming
     * era. They cannot be changed after headers are sent (which is the
     * case once we're inside a WP-Cron tick or after litespeed_finish_request),
     * so they would silently emit an E_WARNING that polluted error_get_last
     * and confused the runner's shutdown handler. They have been removed.
     */
    public static function prepare_long_running_request(): void
    {
        @set_time_limit(0);
        @ini_set("max_execution_time", "0");
        @ini_set("default_socket_timeout", "600");
        @ini_set("max_input_time", "-1");

        if (function_exists("apache_setenv")) {
            @apache_setenv("noabort", "1");
            @apache_setenv("noconntimeout", "1");
        }

        if (function_exists("ignore_user_abort")) {
            @ignore_user_abort(true);
        }

        if (session_id()) {
            session_write_close();
        }
    }

    /**
     * Options cURL standard pour requêtes LLM longues.
     * À fusionner avec les options spécifiques de chaque adapter.
     */
    public static function default_curl_opts(): array
    {
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_POST => true,
        ];

        if (defined("CURLOPT_TCP_KEEPALIVE")) {
            $opts[CURLOPT_TCP_KEEPALIVE] = 1;
        }
        if (defined("CURLOPT_TCP_KEEPIDLE")) {
            $opts[CURLOPT_TCP_KEEPIDLE] = 30;
        }
        if (defined("CURLOPT_TCP_KEEPINTVL")) {
            $opts[CURLOPT_TCP_KEEPINTVL] = 15;
        }

        return $opts;
    }
}
?>
