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
			'naano_save_api_key',
			'naano_save_global_config',
			'naano_save_assets',
			'naano_save_redirects',
			'naano_add_reference',
			'naano_remove_reference',
			'naano_delete_section',
			'naano_reorder_sections',
			'naano_export_html',
			'naano_save_as_page',
			'naano_set_homepage',
		];

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, 'handle_' . $action ] );
		}
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Generate a full website, one LLM call per section.
	 *
	 * POST: page_id, description, sections[] (section type names)
	 */
	public static function handle_naano_generate_site(): void {
		set_time_limit( 0 );
		ignore_user_abort( true );
		self::verify_nonce();

		$page_id     = self::get_int( 'page_id' );
		$page_name   = sanitize_text_field( wp_unslash( $_POST['page_name'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$sections    = array_map( 'sanitize_text_field', (array) ( $_POST['sections'] ?? [] ) );

		// Parse imported sections (header/footer cloned from other pages, no LLM needed).
		// NOTE: wp_unslash only — sanitize_text_field would strip HTML tags from the JSON.
		$imported_json = wp_unslash( $_POST['imported_sections'] ?? '' );
		$imported_data = [];
		if ( $imported_json ) {
			$decoded = json_decode( $imported_json, true );
			if ( is_array( $decoded ) ) {
				$imported_data = $decoded;
			}
		}

		if ( ! $description || ( empty( $sections ) && empty( $imported_data ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'naano-ai-website-builder' ) ] );
		}

		// Create a new WordPress page when none exists yet.
		if ( ! $page_id ) {
			if ( ! current_user_can( 'edit_pages' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions to create pages.', 'naano-ai-website-builder' ) ] );
			}

			$new_id = wp_insert_post( [
				'post_title'  => $page_name ?: __( 'Untitled', 'naano-ai-website-builder' ),
				'post_status' => 'draft',
				'post_type'   => 'page',
			] );

			if ( is_wp_error( $new_id ) ) {
				wp_send_json_error( [ 'message' => $new_id->get_error_message() ] );
			}

			$page_id = $new_id;
		}

		// Save any imported sections — fetch HTML from DB using the source page reference
		// so we never trust client-supplied HTML (avoids sanitization stripping CSS/styles).
		if ( ! empty( $imported_data ) ) {
			$imp_sm    = new Naano_Section_Manager();
			$src_cache = [];
			foreach ( $imported_data as $imp ) {
				$imp_id  = sanitize_key( $imp['id']         ?? '' );
				$imp_type = sanitize_key( $imp['type']       ?? '' );
				$src_pid = (int) ( $imp['sourcePageId']      ?? 0 );
				if ( ! $imp_id || ! $src_pid ) { continue; }
				if ( ! isset( $src_cache[ $src_pid ] ) ) {
					$src_cache[ $src_pid ] = $imp_sm->get_sections( $src_pid );
				}
				$imp_html = '';
				foreach ( $src_cache[ $src_pid ] as $src_sec ) {
					if ( ( $src_sec['id'] ?? '' ) === $imp_id ) {
						$imp_html = $src_sec['html'] ?? '';
						break;
					}
				}
				if ( trim( $imp_html ) ) {
					$imp_sm->update_section( $page_id, $imp_id, $imp_html, $imp_type );
				}
			}
		}

		// Imported-only request — no LLM needed.
		if ( empty( $sections ) ) {
			$sm = new Naano_Section_Manager();
			wp_send_json_success( [
				'page_id'  => $page_id,
				'sections' => $sm->get_sections( $page_id ),
				'html'     => $sm->get_assembled_html( $page_id ),
			] );
			return;
		}

		try {
			$router  = self::build_router();
			$builder = new Naano_Prompt_Builder();
			$vars    = get_option( 'naano_variables', [] );
			$builder->set_variables( is_array( $vars ) ? $vars : [] );

			// Fetch any URLs mentioned in the description once, reuse for every section.
			preg_match_all( '/https?:\/\/[^\s,"\'<>]+/i', $description, $url_matches );
			$desc_refs = [];
			foreach ( array_unique( $url_matches[0] ?? [] ) as $desc_url ) {
				$desc_url    = rtrim( $desc_url, '.,;)\'"' );
				$desc_refs[] = [
					'url'     => $desc_url,
					'notes'   => 'mentioned in site description',
					'content' => Naano_Reference_Manager::fetch_url_text( $desc_url ),
				];
			}
			if ( ! empty( $desc_refs ) ) {
				$builder->set_references( $desc_refs );
			}

			// Collect other Naano pages for navigation links.
			$naano_pages = get_posts( [
				'post_type'      => 'page',
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => -1,
				'meta_key'       => '_naano_sections',
				'exclude'        => [ $page_id ],
			] );
			$site_pages = [];
			foreach ( $naano_pages as $np ) {
				$site_pages[] = [
					'title' => $np->post_title,
					'url'   => get_permalink( $np->ID ),
				];
			}
			$builder->set_site_pages( $site_pages );

			$system          = $builder->build_system_prompt();
			$section_manager = new Naano_Section_Manager();

			// Generate each section with its own focused LLM call + 1 refinement pass.
			foreach ( $sections as $section_type ) {
				$section_id   = sanitize_title( $section_type );
				$message      = $builder->build_single_section_message( $description, $section_type );
				$raw_html     = self::generate_and_refine( $router, $system, [ [ 'role' => 'user', 'content' => $message ] ], [], $section_id, (int) get_option( 'naano_initial_refinement_passes', 1 ) );
				$section_html = Naano_HTML_Sanitizer::extract_section( $raw_html, $section_id );

				if ( ! $section_html ) {
					$section_html = $raw_html;
				}

				$section_manager->update_section( $page_id, $section_id, $section_html, $section_type );
			}

			wp_send_json_success( [
				'page_id'  => $page_id,
				'sections' => $section_manager->get_sections( $page_id ),
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
		set_time_limit( 0 );
		ignore_user_abort( true );
		self::verify_nonce();

		$page_id    = self::get_int( 'page_id' );
		$section_id = sanitize_text_field( wp_unslash( $_POST['section_id'] ?? '' ) );
		$instruction = sanitize_textarea_field( wp_unslash( $_POST['instruction'] ?? '' ) );

		$assets_raw    = sanitize_text_field( wp_unslash( $_POST['assets'] ?? '[]' ) );
		$redirects_raw = sanitize_text_field( wp_unslash( $_POST['redirects'] ?? '[]' ) );
		$refs_raw      = sanitize_text_field( wp_unslash( $_POST['references'] ?? '[]' ) );
		$assets        = json_decode( $assets_raw, true );
		$redirects     = json_decode( $redirects_raw, true );
		$client_refs   = json_decode( $refs_raw, true );
		$assets        = is_array( $assets )      ? $assets      : [];
		$redirects     = is_array( $redirects )   ? $redirects   : [];
		$client_refs   = is_array( $client_refs ) ? $client_refs : [];

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

			// Use URL refs sent from the client (live UI state). Fall back to DB if empty.
			if ( ! empty( $client_refs ) ) {
				$url_refs = array_values( array_filter(
					$client_refs,
					static fn( $r ) => ( $r['type'] ?? '' ) === 'url' && ! empty( $r['url'] )
				) );
				foreach ( $url_refs as &$ref ) {
					$ref['url']     = esc_url_raw( $ref['url'] );
					$ref['content'] = Naano_Reference_Manager::fetch_url_text( $ref['url'] );
				}
				unset( $ref );
			} else {
				$url_refs = $ref_manager->prepare_url_references( $page_id, $section_id );
			}

			$builder = new ²();
			$vars    = get_option( 'naano_variables', [] );
			$builder->set_variables( is_array( $vars ) ? $vars : [] );
			$builder->set_references( $url_refs );
			$builder->set_assets( $assets );
			$builder->set_redirects( $redirects );

			$system  = $builder->build_system_prompt();
			$history = $conversation->get_trimmed( $page_id );
			$message = $builder->build_section_message( $section_id, $instruction, $context );

			$history[] = [ 'role' => 'user', 'content' => $message ];

			$raw_html     = self::generate_and_refine( $router, $system, $history, $images, $section_id, (int) get_option( 'naano_update_refinement_passes', 1 ) );
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
	 * Save the API key independently via AJAX.
	 */
	public static function handle_naano_save_api_key(): void {
		self::verify_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'naano-ai-website-builder' ) ] );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'API key cannot be empty.', 'naano-ai-website-builder' ) ] );
		}

		update_option( 'naano_api_key', $api_key );

		wp_send_json_success( [ 'message' => __( 'API key saved.', 'naano-ai-website-builder' ) ] );
	}

	/**
	 * Save global configuration via AJAX.
	 */
	public static function handle_naano_save_global_config(): void {
		self::verify_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'naano-ai-website-builder' ) ] );
		}

		$initial = absint( $_POST['initial_refinement_passes'] ?? 1 );
		$update  = absint( $_POST['update_refinement_passes']  ?? 3 );

		if ( $initial > 10 ) { $initial = 10; }
		if ( $update  > 10 ) { $update  = 10; }

		update_option( 'naano_initial_refinement_passes', $initial );
		update_option( 'naano_update_refinement_passes', $update );

		wp_send_json_success( [ 'message' => __( 'Global config saved.', 'naano-ai-website-builder' ) ] );
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
	 * Save page-level assets.
	 *
	 * POST: page_id, assets (JSON)
	 */
	public static function handle_naano_save_assets(): void {
		self::verify_nonce();

		$page_id = self::get_int( 'page_id' );
		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing page ID.', 'naano-ai-website-builder' ) ] );
		}

		$raw    = wp_unslash( $_POST['assets'] ?? '[]' );
		$assets = json_decode( $raw, true );
		if ( ! is_array( $assets ) ) {
			$assets = [];
		}

		// Sanitise each entry.
		$clean = [];
		foreach ( $assets as $a ) {
			if ( ! is_array( $a ) || empty( $a['url'] ) ) { continue; }
			$clean[] = [
				'url'  => esc_url_raw( $a['url'] ),
				'desc' => sanitize_text_field( $a['desc'] ?? '' ),
			];
		}

		update_post_meta( $page_id, '_naano_assets', $clean );
		wp_send_json_success();
	}

	/**
	 * Save page-level redirects.
	 *
	 * POST: page_id, redirects (JSON)
	 */
	public static function handle_naano_save_redirects(): void {
		self::verify_nonce();

		$page_id = self::get_int( 'page_id' );
		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing page ID.', 'naano-ai-website-builder' ) ] );
		}

		$raw       = wp_unslash( $_POST['redirects'] ?? '[]' );
		$redirects = json_decode( $raw, true );
		if ( ! is_array( $redirects ) ) {
			$redirects = [];
		}

		$clean = [];
		foreach ( $redirects as $r ) {
			if ( ! is_array( $r ) || empty( $r['label'] ) || empty( $r['url'] ) ) { continue; }
			$clean[] = [
				'label' => sanitize_text_field( $r['label'] ),
				'url'   => esc_url_raw( $r['url'] ),
			];
		}

		update_post_meta( $page_id, '_naano_redirects', $clean );
		wp_send_json_success();
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
		$title   = $title ?: ( get_the_title( $page_id ) ?: __( 'AI Generated Page', 'naano-ai-website-builder' ) );

		// The client sends the assembled HTML directly (built from in-memory
		// sectionsData) so we never rely on a server-side DB re-assembly which
		// may be empty if sections meta is on a different page_id.
		$raw_html = wp_unslash( $_POST['html'] ?? '' );

		// Strip scripts as a defence-in-depth measure (content was already
		// sanitized by Naano_HTML_Sanitizer during generation, but this
		// ensures nothing slips through if the payload is tampered).
		$html = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $raw_html ) ?? '';

		// Fall back to server-side assembly if the client sent nothing.
		if ( ! trim( $html ) ) {
			$html = $manager->get_assembled_html( $page_id );
		}

		// Publish / update the SAME page that was edited in the builder.
		// This avoids creating a duplicate and keeps sections + standalone HTML
		// on a single post.
		$result = wp_update_post( [
			'ID'           => $page_id,
			'post_title'   => $title,
			'post_content' => __( 'This page was generated by Naano AI Website Builder.', 'naano-ai-website-builder' ),
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// Store the raw assembled HTML in a dedicated meta key so it is never
		// touched by WordPress content filters (wpautop, wptexturize, etc.).
		update_post_meta( $page_id, '_naano_page_html', $html );

		// Mark this page as a Naano standalone page so template_redirect
		// can serve the raw HTML without any theme wrapping.
		update_post_meta( $page_id, '_naano_standalone', '1' );

		wp_send_json_success( [
			'page_id'   => $page_id,
			'edit_url'  => get_edit_post_link( $page_id, 'raw' ),
			'view_url'  => get_permalink( $page_id ),
			'title'     => $title,
		] );
	}

	/**
	 * Set a Naano page as the WordPress static front page.
	 *
	 * POST: page_id
	 */
	public static function handle_naano_set_homepage(): void {
		self::verify_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'naano-ai-website-builder' ) ] );
		}

		$page_id = self::get_int( 'page_id' );

		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing page_id.', 'naano-ai-website-builder' ) ] );
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Run the initial LLM generation then refine the result a given number of times.
	 *
	 * Each refinement pass appends the previous assistant response to the
	 * conversation and asks the LLM to self-review against the design standards
	 * in the system prompt. Stops early if a pass returns an empty response.
	 *
	 * Images are passed only on the first call – the LLM already has that
	 * context captured in its initial response for subsequent refinement turns.
	 *
	 * @param Naano_LLM_Router $router
	 * @param string           $system     Assembled system prompt.
	 * @param array            $messages   Full message history including the initial user message.
	 * @param array            $images     Optional image references (first call only).
	 * @param string           $section_id Section ID used to build the BEGIN/END marker hint.
	 * @param int              $passes     Number of refinement passes to run.
	 * @return string Final HTML after all passes.
	 */
	private static function generate_and_refine(
		Naano_LLM_Router $router,
		string $system,
		array $messages,
		array $images = [],
		string $section_id = '',
		int $passes = 1
	): string {
		$html    = $router->generate( $system, $messages, $images );
		$history = $messages;

		$marker = $section_id
			? "Return ONLY the improved section wrapped in its required markers:\n<!-- BEGIN:{$section_id} -->\n…improved HTML here…\n<!-- END:{$section_id} -->"
			: 'Return ONLY the improved HTML using the same <!-- BEGIN --> / <!-- END --> markers as before.';

		for ( $pass = 1; $pass <= $passes; $pass++ ) {
			$history[] = [ 'role' => 'assistant', 'content' => $html ];
			$history[] = [
				'role'    => 'user',
				'content' => "Refinement pass {$pass}/{$passes} — self-review the section you just produced and improve it:\n\n"
					. "✓ CSS fully scoped to the section root class — zero leaking or global selectors\n"
					. "✓ Responsive from 375 px → 1280 px+ — verify every breakpoint mentally\n"
					. "✓ Typography scale and 8 px spacing grid enforced throughout\n"
					. "✓ Every interactive element has a clearly visible hover and focus state\n"
					. "✓ Visual quality matches a premium agency — improve polish, contrast, and depth\n"
					. "✓ No placeholder text, no missing alt attributes, no JavaScript, no inline styles\n\n"
					. $marker,
			];

			$refined = $router->generate( $system, $history );
			if ( ! trim( $refined ) ) {
				break; // stop early if a pass produces nothing
			}
			$html = $refined;
		}

		return $html;
	}

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
		$model    = (string) get_option( 'naano_model', '' );

		if ( ! $api_key ) {
			throw new RuntimeException(
				__( 'No API key configured. Please visit Naano AI Builder → Settings.', 'naano-ai-website-builder' )
			);
		}

		return new Naano_LLM_Router( $provider, $api_key, [ 'model' => $model ] );
	}
}
