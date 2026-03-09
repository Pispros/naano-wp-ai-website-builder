<?php
/**
 * LLM Provider Interface
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract that every LLM adapter must fulfill.
 */
interface Naano_LLM_Provider_Interface {

	/**
	 * Send a conversation to the LLM and return the raw text response.
	 *
	 * @param string  $system_prompt The system/context prompt.
	 * @param array   $messages      Array of ['role' => 'user|assistant', 'content' => '…'].
	 * @param array   $images        Optional array of ['data' => base64, 'mime_type' => 'image/jpeg'].
	 * @return string                LLM text response.
	 * @throws RuntimeException      On cURL or API errors.
	 */
	public function send( string $system_prompt, array $messages, array $images = [] ): string;

	/**
	 * Test the API connection.
	 *
	 * @return array{success: bool, model: string, latency_ms: int, error?: string}
	 */
	public function test_connection(): array;
}
