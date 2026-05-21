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
	 * Must be called at the start of each adapter's send() method.
	 *
	 * LLM completion calls routinely take 60–600 seconds (token-by-token
	 * generation of multi-thousand-token HTML payloads). Without raising
	 * the limits below, the request would die mid-stream and leave a
	 * partial response we can't recover. The Squiz "discouraged"
	 * warnings are acknowledged but unavoidable for this workload.
	 */
	public static function prepare_long_running_request(): void {
		// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged
		@set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );
		@ini_set( 'default_socket_timeout', '600' );
		@ini_set( 'max_input_time', '-1' );
		// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'noabort', '1' );
			@apache_setenv( 'noconntimeout', '1' );
		}

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
