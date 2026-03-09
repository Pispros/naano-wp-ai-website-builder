<?php
/**
 * Conversation Manager – stores and trims LLM conversation history per page.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages per-page conversation history for multi-turn LLM interactions.
 */
class Naano_Conversation {

	private const META_KEY = '_naano_conversation';

	/**
	 * Get the full conversation history for a page.
	 *
	 * @param int $page_id WordPress post ID.
	 * @return array Array of ['role' => 'user|assistant', 'content' => '…'].
	 */
	public function get_history( int $page_id ): array {
		$data = get_post_meta( $page_id, self::META_KEY, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Append a message to the conversation history.
	 *
	 * @param int    $page_id WordPress post ID.
	 * @param string $role    'user' or 'assistant'.
	 * @param string $content Message text.
	 * @return void
	 */
	public function add_message( int $page_id, string $role, string $content ): void {
		$history   = $this->get_history( $page_id );
		$history[] = [
			'role'      => $role,
			'content'   => $content,
			'timestamp' => time(),
		];
		$this->save( $page_id, $history );
	}

	/**
	 * Get a trimmed view of the conversation (last N exchanges).
	 *
	 * An "exchange" = 1 user message + 1 assistant reply.
	 *
	 * @param int $page_id      WordPress post ID.
	 * @param int $max_exchanges Maximum number of exchanges to keep.
	 * @return array Trimmed messages suitable for the LLM messages array.
	 */
	public function get_trimmed( int $page_id, int $max_exchanges = 3 ): array {
		$history = $this->get_history( $page_id );

		// Strip timestamp field before passing to LLM.
		$messages = array_map(
			static fn( $m ) => [ 'role' => $m['role'], 'content' => $m['content'] ],
			$history
		);

		// Keep only the last max_exchanges * 2 messages.
		$keep = $max_exchanges * 2;
		if ( count( $messages ) > $keep ) {
			$messages = array_slice( $messages, - $keep );
		}

		return $messages;
	}

	/**
	 * Clear all conversation history for a page.
	 *
	 * @param int $page_id WordPress post ID.
	 * @return void
	 */
	public function clear( int $page_id ): void {
		delete_post_meta( $page_id, self::META_KEY );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Persist conversation history.
	 *
	 * @param int   $page_id WordPress post ID.
	 * @param array $history Full history array.
	 * @return void
	 */
	private function save( int $page_id, array $history ): void {
		update_post_meta( $page_id, self::META_KEY, $history );
	}
}
