<?php
/**
 * AJAX Handler – registers and handles all plugin AJAX endpoints.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress AJAX actions for the builder.
 */
class Naano_Ajax_Handler {

	/**
	 * Register all wp_ajax_* hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		$actions = [
			'naano_generate_site',
			'naano_update_section',
			'naano_test_connection',
			'naano_add_reference',
			'naano_remove_reference',
			'naano_delete_section',
			'naano_reorder_sections',
			'naano_export_html',
			'naano_save_as_page',
		];

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, 'handle_' . $action ] );
		}
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Generate a full website section-by-section.
	 *
	 * POST: page_id, description, sections[] (section type names)
	 */
	public static function handle_naano_generate_site(): void {
		self::verify_nonce();

		$page_id     = self::get_int( 'page_id' );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$sections    = array_map( 'sanitize_text_field', (array) ( $_POST['sections'] ?? [] ) );

		if ( ! $page_id || ! $description || empty( $sections ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		try {
			$router  = self::build_router();
			$builder = new Naano_Prompt_Builder();
			$vars    = get_option( 'naano_variables', [] );
			$builder->set_variables( is_array( $vars ) ? $vars : [] );

			$system  = $builder->build_system_prompt();
			$message = $builder->build_initial_message( $description, $sections );

			$raw_html = $router->generate( $system, [ [ 'role' => 'user', 'content' => $message ] ] );

			// Parse sections from LLM response.
			$section_manager = new Naano_Section_Manager();
			$conversation    = new Naano_Conversation();

			$parsed = [];
			foreach ( $sections as $section_type ) {
				$section_id  = sanitize_title( $section_type );
				$section_html = Naano_HTML_Sanitizer::extract_section( $raw_html, $section_id );

				if ( $section_html ) {
					$section_manager->update_section( $page_id, $section_id, $section_html, $section_type );
					$parsed[ $section_id ] = $section_html;
				}
			}

			// If no markers found, treat entire response as single block.
			if ( empty( $parsed ) ) {
				$section_id = 'main';
				$section_manager->update_section( $page_id, $section_id, $raw_html, 'main' );
				$parsed[ $section_id ] = $raw_html;
			}

			$conversation->add_message( $page_id, 'user', $message );
			$conversation->add_message( $page_id, 'assistant', $raw_html );

			wp_send_json_success( [
				'sections' => $parsed,
				'html'     => $section_manager->get_assembled_html( $page_id ),
			] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Update a single section.
	 *
	 * POST: page_id, section_id, instruction
	 */
	public static function handle_naano_update_section(): void {
		self::verify_nonce();

		$page_id    = self::get_int( 'page_id' );
		$section_id = sanitize_text_field( wp_unslash( $_POST['section_id'] ?? '' ) );
		$instruction = sanitize_textarea_field( wp_unslash( $_POST['instruction'] ?? '' ) );

		if ( ! $page_id || ! $section_id || ! $instruction ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		try {
			$section_manager  = new Naano_Section_Manager();
			$conversation     = new Naano_Conversation();
			$ref_manager      = new Naano_Reference_Manager();
			$router           = self::build_router();

			$all_sections = $section_manager->get_sections( $page_id );
			$context      = Naano_Payload_Compressor::compress_context( $all_sections, $section_id );

			$images  = $ref_manager->prepare_images_for_llm( $page_id, $section_id );
			$url_refs = $ref_manager->prepare_url_references( $page_id, $section_id );

			$builder = new Naano_Prompt_Builder();
			$vars    = get_option( 'naano_variables', [] );
			$builder->set_variables( is_array( $vars ) ? $vars : [] );
			$builder->set_references( $url_refs );

			$system  = $builder->build_system_prompt();
			$history = $conversation->get_trimmed( $page_id );
			$message = $builder->build_section_message( $section_id, $instruction, $context );

			$history[] = [ 'role' => 'user', 'content' => $message ];

			$raw_html     = $router->generate( $system, $history, $images );
			$section_html = Naano_HTML_Sanitizer::extract_section( $raw_html, $section_id );

			if ( ! $section_html ) {
				// Fallback: use entire sanitized response as section HTML.
				$section_html = $raw_html;
			}

			$section_manager->update_section( $page_id, $section_id, $section_html );

			$conversation->add_message( $page_id, 'user', $message );
			$conversation->add_message( $page_id, 'assistant', $section_html );

			wp_send_json_success( [
				'section_id'   => $section_id,
				'section_html' => $section_html,
			] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Test LLM provider connection.
	 *
	 * POST: provider, api_key
	 */
	public static function handle_naano_test_connection(): void {
		self::verify_nonce();

		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );
		$api_key  = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		$model    = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );

		if ( ! $provider || ! $api_key ) {
			wp_send_json_error( [ 'message' => __( 'Provider and API key are required.', 'naano-ai-website-builder' ) ] );
		}

		try {
			$router  = new Naano_LLM_Router( $provider, $api_key, [ 'model' => $model ] );
			$result  = $router->get_adapter()->test_connection();
			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Add a reference to a section.
	 *
	 * POST: page_id, section_id, type, url, attachment_id, notes
	 */
	public static function handle_naano_add_reference(): void {
		self::verify_nonce();

		$page_id    = self::get_int( 'page_id' );
		$section_id = sanitize_text_field( wp_unslash( $_POST['section_id'] ?? '' ) );
		$type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'url' ) );
		$url        = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		$attach_id  = self::get_int( 'attachment_id' );
		$notes      = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		if ( ! $page_id || ! $section_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		$manager = new Naano_Reference_Manager();
		$manager->add_reference( $page_id, $section_id, [
			'type'          => $type,
			'url'           => $url,
			'attachment_id' => $attach_id,
			'notes'         => $notes,
		] );

		wp_send_json_success( [
			'references' => $manager->get_references( $page_id, $section_id ),
		] );
	}

	/**
	 * Remove a reference.
	 *
	 * POST: page_id, section_id, index
	 */
	public static function handle_naano_remove_reference(): void {
		self::verify_nonce();

		$page_id    = self::get_int( 'page_id' );
		$section_id = sanitize_text_field( wp_unslash( $_POST['section_id'] ?? '' ) );
		$index      = self::get_int( 'index' );

		if ( ! $page_id || ! $section_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		$manager = new Naano_Reference_Manager();
		$manager->remove_reference( $page_id, $section_id, $index );

		wp_send_json_success( [
			'references' => $manager->get_references( $page_id, $section_id ),
		] );
	}

	/**
	 * Delete a section.
	 *
	 * POST: page_id, section_id
	 */
	public static function handle_naano_delete_section(): void {
		self::verify_nonce();

		$page_id    = self::get_int( 'page_id' );
		$section_id = sanitize_text_field( wp_unslash( $_POST['section_id'] ?? '' ) );

		if ( ! $page_id || ! $section_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		$manager = new Naano_Section_Manager();
		$manager->delete_section( $page_id, $section_id );

		wp_send_json_success();
	}

	/**
	 * Reorder sections.
	 *
	 * POST: page_id, order[] (array of section IDs in new order)
	 */
	public static function handle_naano_reorder_sections(): void {
		self::verify_nonce();

		$page_id = self::get_int( 'page_id' );
		$order   = array_map( 'sanitize_text_field', (array) ( $_POST['order'] ?? [] ) );

		if ( ! $page_id || empty( $order ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		$manager = new Naano_Section_Manager();
		$manager->reorder_sections( $page_id, $order );

		wp_send_json_success();
	}

	/**
	 * Export assembled HTML.
	 *
	 * POST: page_id
	 */
	public static function handle_naano_export_html(): void {
		self::verify_nonce();

		$page_id = self::get_int( 'page_id' );

		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing page_id.', 'naano-ai-website-builder' ) ] );
		}

		$manager = new Naano_Section_Manager();
		$html    = $manager->get_assembled_html( $page_id );

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Save assembled HTML as a real WordPress page.
	 *
	 * POST: page_id, title
	 */
	public static function handle_naano_save_as_page(): void {
		self::verify_nonce();

		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'naano-ai-website-builder' ) ] );
		}

		$page_id = self::get_int( 'page_id' );
		$title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );

		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing page_id.', 'naano-ai-website-builder' ) ] );
		}

		$manager = new Naano_Section_Manager();
		$html    = $manager->get_assembled_html( $page_id );
		$title   = $title ?: ( get_the_title( $page_id ) ?: __( 'AI Generated Page', 'naano-ai-website-builder' ) );

		$new_page_id = wp_insert_post( [
			'post_title'   => $title,
			'post_content' => $html,
			'post_status'  => 'draft',
			'post_type'    => 'page',
		] );

		if ( is_wp_error( $new_page_id ) ) {
			wp_send_json_error( [ 'message' => $new_page_id->get_error_message() ] );
		}

		wp_send_json_success( [
			'page_id'   => $new_page_id,
			'edit_url'  => get_edit_post_link( $new_page_id, 'raw' ),
			'view_url'  => get_permalink( $new_page_id ),
		] );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify AJAX nonce, die on failure.
	 *
	 * @return void
	 */
	private static function verify_nonce(): void {
		check_ajax_referer( 'naano_builder_nonce', 'nonce' );
	}

	/**
	 * Get an integer POST field.
	 *
	 * @param string $key POST field name.
	 * @return int
	 */
	private static function get_int( string $key ): int {
		return (int) ( $_POST[ $key ] ?? 0 );
	}

	/**
	 * Build an LLM router from saved settings.
	 *
	 * @return Naano_LLM_Router
	 * @throws RuntimeException If no API key is configured.
	 */
	private static function build_router(): Naano_LLM_Router {
		$provider = get_option( 'naano_provider', 'claude' );
		$api_key  = get_option( 'naano_api_key', '' );
		$model    = get_option( 'naano_model', '' );

		if ( ! $api_key ) {
			throw new RuntimeException(
				__( 'No API key configured. Please visit Naano AI Builder → Settings.', 'naano-ai-website-builder' )
			);
		}

		return new Naano_LLM_Router( $provider, $api_key, [ 'model' => $model ] );
	}
}
