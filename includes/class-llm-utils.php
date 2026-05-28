<?php
/**
 * LLM Utility helpers.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Naano_LLM_Utils {

	/**
	 * Disable PHP timeouts for long-running LLM requests.
	 * Must be called at the start of each adapter's send() method only —
	 * NOT in a constructor, on init, or in any globally-bound hook. Each
	 * LLM call needs the extended runtime; nothing else does.
	 *
	 * LLM completion calls routinely take 60–600 seconds (token-by-token
	 * generation of multi-thousand-token HTML payloads). Without raising
	 * the limit below, the request would die mid-stream and leave a
	 * partial response we can't recover.
	 *
	 * Only set_time_limit(0) is used here. We previously also called
	 * ini_set() for max_execution_time / max_input_time / default_socket_timeout,
	 * but:
	 *   - set_time_limit(0) already covers wall-clock execution time;
	 *   - max_input_time is only consulted during request parsing
	 *     (before this code ever runs), so setting it at runtime is a
	 *     no-op;
	 *   - default_socket_timeout affects PHP stream functions only, not
	 *     wp_remote_post() / cURL which is what our adapters use.
	 * Removing the ini_set() calls keeps this plugin compliant with the
	 * WordPress.org guideline against altering PHP runtime defaults.
	 */
	public static function prepare_long_running_request(): void {
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		@set_time_limit( 0 );

		if ( function_exists( 'ignore_user_abort' ) ) {
			@ignore_user_abort( true );
		}

		if ( session_id() ) {
			session_write_close();
		}
	}

	/**
	 * Default arguments shared by every wp_remote_post() call to an LLM
	 * provider. Adapter classes merge their endpoint-specific headers
	 * and bodies on top of this.
	 *
	 * @param int $timeout Per-request timeout in seconds.
	 * @return array<string,mixed>
	 */
	public static function default_request_args( int $timeout = 600 ): array {
		return [
			'method'      => 'POST',
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'sslverify'   => true,
			'data_format' => 'body',
		];
	}
}
