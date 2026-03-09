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

		// "Build with Naano AI" in the admin bar.
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_item' ], 100 );
	}

	/**
	 * Add "Build with Naano AI" to the Pages list row actions.
	 *
	 * @param string[]  $actions Current row action links.
	 * @param \WP_Post  $post    Current post object.
	 * @return string[]
	 */
	public function add_page_row_action( array $actions, \WP_Post $post ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$url = add_query_arg(
				[
					'page'    => 'naano-ai-builder',
					'page_id' => $post->ID,
				],
				admin_url( 'admin.php' )
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
	 * Add "Naano AI" quick-access node to the WordPress admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 * @return void
	 */
	public function add_admin_bar_item( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin() ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'naano-ai-builder',
			'title' => '<span class="ab-icon dashicons dashicons-admin-site-alt3"></span>'
						. __( 'Naano AI', 'naano-ai-website-builder' ),
			'href'  => admin_url( 'admin.php?page=naano-new-page' ),
			'meta'  => [ 'title' => __( 'Create a new page with Naano AI', 'naano-ai-website-builder' ) ],
		] );

		$wp_admin_bar->add_node( [
			'parent' => 'naano-ai-builder',
			'id'     => 'naano-ai-new-page',
			'title'  => __( 'Create New Page', 'naano-ai-website-builder' ),
			'href'   => admin_url( 'admin.php?page=naano-new-page' ),
		] );

		$wp_admin_bar->add_node( [
			'parent' => 'naano-ai-builder',
			'id'     => 'naano-ai-builder-main',
			'title'  => __( 'Builder', 'naano-ai-website-builder' ),
			'href'   => admin_url( 'admin.php?page=naano-ai-builder' ),
		] );
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
			[ $this, 'render_builder_page' ],
			'dashicons-admin-site-alt3',
			30
		);

		$this->page_hooks[] = add_submenu_page(
			'naano-ai-builder',
			__( 'Builder', 'naano-ai-website-builder' ),
			__( 'Builder', 'naano-ai-website-builder' ),
			'manage_options',
			'naano-ai-builder',
			[ $this, 'render_builder_page' ]
		);

		$this->page_hooks[] = add_submenu_page(
			'naano-ai-builder',
			__( 'New Page', 'naano-ai-website-builder' ),
			__( 'New Page', 'naano-ai-website-builder' ),
			'manage_options',
			'naano-new-page',
			[ $this, 'render_new_page' ]
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
			'naano-ai-builder_page_naano-new-page',
			'naano-ai-builder_page_naano-settings',
		];

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		// CSS.
		wp_enqueue_style(
			'naano-builder',
			NAANO_PLUGIN_URL . 'assets/css/builder.css',
			[],
			NAANO_VERSION
		);

		// WordPress media uploader.
		wp_enqueue_media();

		// Builder JS.
		wp_enqueue_script(
			'naano-builder',
			NAANO_PLUGIN_URL . 'assets/js/builder.js',
			[ 'jquery', 'wp-util' ],
			NAANO_VERSION,
			true
		);

		// Preview JS.
		wp_enqueue_script(
			'naano-preview',
			NAANO_PLUGIN_URL . 'assets/js/preview.js',
			[ 'naano-builder' ],
			NAANO_VERSION,
			true
		);

		$page_id  = isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : 0;
		$sections = [];
		$references = [];

		if ( $page_id ) {
			$sm       = new Naano_Section_Manager();
			$rm       = new Naano_Reference_Manager();
			$sections = $sm->get_sections( $page_id );
			foreach ( $sections as $sec ) {
				$references[ $sec['id'] ] = $rm->get_references( $page_id, $sec['id'] );
			}
		}

		wp_localize_script( 'naano-builder', 'naanoBuilderData', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'naano_builder_nonce' ),
			'pageId'     => $page_id,
			'sections'   => $sections,
			'references' => $references,
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
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the main builder page.
	 *
	 * @return void
	 */
	public function render_builder_page(): void {
		require NAANO_PLUGIN_DIR . 'templates/builder-page.php';
	}

	/**
	 * Render the "New Page" page (builder without a page_id pre-set).
	 *
	 * @return void
	 */
	public function render_new_page(): void {
		require NAANO_PLUGIN_DIR . 'templates/builder-page.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		require NAANO_PLUGIN_DIR . 'templates/settings-page.php';
	}
}
