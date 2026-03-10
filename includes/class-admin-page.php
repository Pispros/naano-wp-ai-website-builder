<?php
/**
 * Admin Page – registers menus, settings, and enqueues assets.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up the WordPress admin menus and settings for Naano AI Website Builder.
 */
class Naano_Admin_Page {

	/** @var string[] Admin page hook suffixes registered by this class. */
	private array $page_hooks = [];

	/**
	 * Constructor – wire up hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// "Build with Naano AI" in the Pages list row actions.
		add_filter( 'page_row_actions', [ $this, 'add_page_row_action' ], 10, 2 );

		// Frontend builder: intercept ?naano_builder=1 on frontend pages.
		add_action( 'template_redirect', [ $this, 'maybe_render_frontend_builder' ] );

		// Hide the WordPress admin bar when the frontend builder is active.
		add_filter( 'show_admin_bar', [ $this, 'maybe_hide_admin_bar' ] );

		// Serve standalone Naano pages as raw HTML (no theme wrapping).
		add_action( 'template_redirect', [ $this, 'maybe_render_standalone_page' ] );
	}

	/**
	 * Suppress the WP admin bar when the frontend builder overlay is active.
	 *
	 * @param bool $show
	 * @return bool
	 */
	public function maybe_hide_admin_bar( bool $show ): bool {
		if ( ! empty( $_GET['naano_builder'] ) && current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Add "Build with Naano AI" to the Pages list row actions.
	 * Links to the frontend page URL with the builder overlay activated.
	 *
	 * @param string[]  $actions Current row action links.
	 * @param \WP_Post  $post    Current post object.
	 * @return string[]
	 */
	public function add_page_row_action( array $actions, \WP_Post $post ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$url = add_query_arg(
				[ 'naano_builder' => '1' ],
				get_permalink( $post->ID )
			);

			$actions['naano_build'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Build with Naano AI', 'naano-ai-website-builder' )
			);
		}

		return $actions;
	}

	/**
	 * Register top-level and sub-menus.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		$this->page_hooks[] = add_menu_page(
			__( 'Naano AI Builder', 'naano-ai-website-builder' ),
			__( 'Naano AI Builder', 'naano-ai-website-builder' ),
			'manage_options',
			'naano-ai-builder',
			[ $this, 'render_pages_list' ],
			'dashicons-admin-site-alt3',
			30
		);

		$this->page_hooks[] = add_submenu_page(
			'naano-ai-builder',
			__( 'AI Pages', 'naano-ai-website-builder' ),
			__( 'AI Pages', 'naano-ai-website-builder' ),
			'manage_options',
			'naano-ai-builder',
			[ $this, 'render_pages_list' ]
		);

		$this->page_hooks[] = add_submenu_page(
			'naano-ai-builder',
			__( 'Settings', 'naano-ai-website-builder' ),
			__( 'Settings', 'naano-ai-website-builder' ),
			'manage_options',
			'naano-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings with WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'naano_settings_group',
			'naano_provider',
			[
				'sanitize_callback' => static function ( $v ) {
					return in_array( $v, [ 'claude', 'gemini', 'kimi' ], true ) ? $v : 'claude';
				},
				'default'           => 'claude',
			]
		);

		register_setting(
			'naano_settings_group',
			'naano_api_key',
			[
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		register_setting(
			'naano_settings_group',
			'naano_model',
			[
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		register_setting(
			'naano_settings_group',
			'naano_variables',
			[
				'sanitize_callback' => [ $this, 'sanitize_variables' ],
				'default'           => [],
			]
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
	public function sanitize_variables( $input ): array {
		$keys   = array_map( 'sanitize_text_field', (array) ( $_POST['naano_vars_keys'] ?? [] ) );
		$values = array_map( 'sanitize_text_field', (array) ( $_POST['naano_vars_values'] ?? [] ) );

		$result = [];
		foreach ( $keys as $i => $key ) {
			$key = trim( $key );
			if ( $key !== '' ) {
				$result[ $key ] = $values[ $i ] ?? '';
			}
		}
		return $result;
	}

	/**
	 * Enqueue plugin CSS/JS only on plugin admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$plugin_pages = [
			'toplevel_page_naano-ai-builder',
			'naano-ai-builder_page_naano-settings',
		];

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		// CSS for the admin pages list / settings.
		wp_enqueue_style(
			'naano-builder',
			NAANO_PLUGIN_URL . 'assets/css/builder.css',
			[],
			NAANO_VERSION
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
	public function render_pages_list(): void {
		require NAANO_PLUGIN_DIR . 'templates/admin-pages-list.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		require NAANO_PLUGIN_DIR . 'templates/settings-page.php';
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
	public function maybe_render_standalone_page(): void {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$page_id = get_queried_object_id();
		if ( ! $page_id || ! get_post_meta( $page_id, '_naano_standalone', true ) ) {
			return;
		}

		// The raw HTML is stored in _naano_page_html meta to avoid being
		// mangled by WordPress content filters on post_content.
		$html = get_post_meta( $page_id, '_naano_page_html', true );

		if ( ! $html ) {
			return; // Nothing to render; let WP fall through normally.
		}

		header( 'Content-Type: text/html; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		exit;
	}

	/**
	 * Intercept frontend requests with ?naano_builder=1 and render the
	 * full visual builder instead of the normal page template.
	 *
	 * Only accessible to logged-in users with manage_options capability.
	 *
	 * @return void
	 */
	public function maybe_render_frontend_builder(): void {
		if ( empty( $_GET['naano_builder'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Determine the page ID from the queried object (e.g. /my-page/?naano_builder=1).
		$page_id = get_queried_object_id() ?: 0;

		// Enqueue all required assets for the builder.
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'naano-builder',
			NAANO_PLUGIN_URL . 'assets/css/builder.css',
			[ 'dashicons' ],
			NAANO_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'naano-builder',
			NAANO_PLUGIN_URL . 'assets/js/builder.js',
			[ 'jquery', 'wp-util' ],
			NAANO_VERSION,
			true
		);

		wp_enqueue_script(
			'naano-preview',
			NAANO_PLUGIN_URL . 'assets/js/preview.js',
			[ 'naano-builder' ],
			NAANO_VERSION,
			true
		);

		$sections   = [];
		$references = [];

		if ( $page_id ) {
			$sm = new Naano_Section_Manager();
			$rm = new Naano_Reference_Manager();
			$sections = $sm->get_sections( $page_id );
			foreach ( $sections as $sec ) {
				$references[ $sec['id'] ] = $rm->get_references( $page_id, $sec['id'] );
			}
		}

		$_mlp        = get_option( 'naano_provider', 'claude' );
		$_mlm        = get_option( 'naano_model', '' );
		$_mld        = [ 'claude' => 'claude-sonnet-4-20250514', 'gemini' => 'gemini-2.5-flash', 'kimi' => 'kimi-k2-0711-preview' ];
		$model_label = $_mlm ?: ( $_mld[ $_mlp ] ?? $_mlp );

		wp_localize_script( 'naano-builder', 'naanoBuilderData', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'naano_builder_nonce' ),
			'pageId'     => $page_id,
			'sections'   => $sections,
			'references' => $references,
			'modelLabel' => $model_label,
			'strings'    => [
				'confirm_delete'    => __( 'Are you sure you want to delete this section?', 'naano-ai-website-builder' ),
				'generating'        => __( 'Generating…', 'naano-ai-website-builder' ),
				'updating'          => __( 'Updating…', 'naano-ai-website-builder' ),
				'error_generic'     => __( 'An error occurred. Please try again.', 'naano-ai-website-builder' ),
				'select_section'    => __( 'Please select a section first.', 'naano-ai-website-builder' ),
				'enter_instruction' => __( 'Please enter an instruction.', 'naano-ai-website-builder' ),
				'new_section_name'  => __( 'New section name (e.g. "Team", "Gallery"):', 'naano-ai-website-builder' ),
				'click_section'     => __( '— click a section in the preview —', 'naano-ai-website-builder' ),
			],
		] );

		// Output a standalone full-page builder and stop WP from rendering anything else.
		require NAANO_PLUGIN_DIR . 'templates/frontend-builder.php';
		exit;
	}
}
