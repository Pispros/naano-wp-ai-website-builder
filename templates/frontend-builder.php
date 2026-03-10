<?php
/**
 * Frontend Builder Template — standalone full-page visual builder.
 *
 * Rendered when a logged-in admin visits any page with ?naano_builder=1.
 * Outputs a complete HTML document; WordPress template rendering is aborted
 * after this file via exit() in maybe_render_frontend_builder().
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_id  = empty( $_GET['naano_new'] ) ? ( get_queried_object_id() ?: 0 ) : 0;
$has_page = $page_id > 0;

$section_types = [
	'header'       => __( 'Header / Navigation', 'naano-ai-website-builder' ),
	'hero'         => __( 'Hero / Banner', 'naano-ai-website-builder' ),
	'features'     => __( 'Features', 'naano-ai-website-builder' ),
	'about'        => __( 'About', 'naano-ai-website-builder' ),
	'services'     => __( 'Services', 'naano-ai-website-builder' ),
	'pricing'      => __( 'Pricing', 'naano-ai-website-builder' ),
	'testimonials' => __( 'Testimonials', 'naano-ai-website-builder' ),
	'contact'      => __( 'Contact', 'naano-ai-website-builder' ),
	'footer'       => __( 'Footer', 'naano-ai-website-builder' ),
];

// Back-to-admin URL.
$admin_pages_url = admin_url( 'admin.php?page=naano-ai-builder' );

// Translation data is injected by maybe_render_frontend_builder() via
// variable scope; fall back to empty arrays when accessed directly.
$current_lang = $current_lang ?? '';
$translations = $translations ?? [];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Naano AI Builder', 'naano-ai-website-builder' ); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Reset any theme styles that may bleed into the builder. */
		html, body {
			margin: 0 !important;
			padding: 0 !important;
			overflow: hidden !important;
			background: #1d2327 !important;
		}
		/* Override naano-vb height: fills the full viewport (no WP admin bar). */
		.naano-vb {
			height: 100vh !important;
		}
	</style>
</head>
<body class="naano-frontend-builder">

<div class="naano-vb" id="naano-vb">

	<!-- ===================================================================
	     TOP TOOLBAR
	     =================================================================== -->
	<div class="naano-vb__toolbar" id="naano-vb-toolbar">

		<div class="naano-vb__toolbar-left">
			<a href="<?php echo esc_url( $admin_pages_url ); ?>"
			   class="naano-drawer-toggle"
			   title="<?php esc_attr_e( 'Back to Dashboard', 'naano-ai-website-builder' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt"></span>
			</a>
			<button type="button" class="naano-drawer-toggle" id="naano-drawer-toggle"
					title="<?php esc_attr_e( 'Toggle Panel', 'naano-ai-website-builder' ); ?>">
				<span class="dashicons dashicons-menu-alt"></span>
			</button>
			<span class="naano-vb__brand">
				<span class="dashicons dashicons-admin-site-alt3"></span>
				<?php esc_html_e( 'Naano AI', 'naano-ai-website-builder' ); ?>
			</span>
			<span class="naano-vb__separator"></span>
			<span class="naano-vb__page-name" id="naano-current-page-name">
				<?php echo $has_page ? esc_html( get_the_title( $page_id ) ) : esc_html__( 'New Page', 'naano-ai-website-builder' ); ?>
			</span>
			<?php if ( count( $translations ) > 1 ) : ?>
			<span class="naano-vb__separator"></span>
			<div class="naano-lang-switcher-wrap">
				<span class="dashicons dashicons-translation naano-lang-icon" title="<?php esc_attr_e( 'Language', 'naano-ai-website-builder' ); ?>"></span>
				<select id="naano-lang-switcher" class="naano-lang-select">
					<?php foreach ( $translations as $tr ) : ?>
					<option value="<?php echo esc_attr( $tr['builderUrl'] ); ?>"
						<?php selected( $tr['current'] ); ?>>
						<?php echo esc_html( strtoupper( $tr['lang'] ) ); ?>
						<?php if ( ! empty( $tr['label'] ) && $tr['label'] !== strtoupper( $tr['lang'] ) ) :
							echo ' — ' . esc_html( $tr['label'] );
						endif; ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>
		</div>

		<div class="naano-vb__toolbar-center">
			<div class="naano-viewport-group" id="naano-viewport-group">
				<button type="button" class="naano-viewport-btn naano-viewport-btn--active"
						data-width="100%" title="<?php esc_attr_e( 'Desktop', 'naano-ai-website-builder' ); ?>">
					<span class="dashicons dashicons-desktop"></span>
				</button>
				<button type="button" class="naano-viewport-btn"
						data-width="768px" title="<?php esc_attr_e( 'Tablet', 'naano-ai-website-builder' ); ?>">
					<span class="dashicons dashicons-tablet"></span>
				</button>
				<button type="button" class="naano-viewport-btn"
						data-width="375px" title="<?php esc_attr_e( 'Mobile', 'naano-ai-website-builder' ); ?>">
					<span class="dashicons dashicons-smartphone"></span>
				</button>
			</div>
		</div>

		<div class="naano-vb__toolbar-right">
			<button type="button" class="naano-tb-btn" id="naano-preview-btn"
					title="<?php esc_attr_e( 'Preview', 'naano-ai-website-builder' ); ?>">
				<span class="dashicons dashicons-visibility"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e( 'Preview', 'naano-ai-website-builder' ); ?></span>
			</button>
			<button type="button" class="naano-tb-btn" id="naano-export-btn"
					title="<?php esc_attr_e( 'Export HTML', 'naano-ai-website-builder' ); ?>">
				<span class="dashicons dashicons-download"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e( 'Export', 'naano-ai-website-builder' ); ?></span>
			</button>
			<button type="button" class="naano-tb-btn" id="naano-copy-btn"
					title="<?php esc_attr_e( 'Copy HTML', 'naano-ai-website-builder' ); ?>">
				<span class="dashicons dashicons-admin-page"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e( 'Copy', 'naano-ai-website-builder' ); ?></span>
			</button>
			<button type="button" class="naano-tb-btn naano-tb-btn--primary" id="naano-save-page-btn"
					title="<?php esc_attr_e( 'Save as WP Page', 'naano-ai-website-builder' ); ?>">
				<span class="dashicons dashicons-saved"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e( 'Save', 'naano-ai-website-builder' ); ?></span>
			</button>
		</div>

	</div><!-- .naano-vb__toolbar -->

	<!-- ===================================================================
	     BODY  (DRAWER + CANVAS)
	     =================================================================== -->
	<div class="naano-vb__body">

		<!-- ===============================================================
		     LEFT DRAWER
		     =============================================================== -->
		<div class="naano-vb__drawer" id="naano-drawer">
			<div class="naano-drawer__inner">

				<!-- STATE 1 : Initial generation -->
				<div class="naano-drawer-panel" id="naano-drawer-generate"
					 <?php echo $has_page ? 'style="display:none;"' : ''; ?>>

					<div class="naano-drawer__header">
						<h3><?php esc_html_e( 'Create New Page', 'naano-ai-website-builder' ); ?></h3>
						<p><?php esc_html_e( 'Describe your site and choose which sections to generate.', 'naano-ai-website-builder' ); ?></p>
					</div>

					<div class="naano-drawer__field">
						<label for="naano-page-name">
							<?php esc_html_e( 'Page Name', 'naano-ai-website-builder' ); ?>
						</label>
						<input type="text" id="naano-page-name" class="naano-input"
							   placeholder="<?php esc_attr_e( 'e.g. My Awesome Product', 'naano-ai-website-builder' ); ?>">
					</div>

					<div class="naano-drawer__field">
						<label for="naano-description">
							<?php esc_html_e( 'Site Description', 'naano-ai-website-builder' ); ?>
						</label>
						<textarea id="naano-description" class="naano-textarea" rows="6"
								  placeholder="<?php esc_attr_e( 'Describe your business, product, service, tone, target audience…', 'naano-ai-website-builder' ); ?>"></textarea>
						<p id="naano-description-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;">
							<?php esc_html_e( 'Please enter a site description.', 'naano-ai-website-builder' ); ?>
						</p>
					</div>

					<div class="naano-drawer__field">
						<label><?php esc_html_e( 'Sections to Generate', 'naano-ai-website-builder' ); ?></label>
						<div class="naano-section-checkboxes" id="naano-section-checkboxes">
							<?php foreach ( $section_types as $type => $label ) : ?>
							<label class="naano-checkbox-label">
								<input type="checkbox" name="sections[]"
									   value="<?php echo esc_attr( $type ); ?>" checked>
								<?php echo esc_html( $label ); ?>
							</label>
							<?php endforeach; ?>
						</div>
						<div class="naano-custom-section-row">
							<input type="text" id="naano-custom-section-input" class="naano-input"
								   placeholder="<?php esc_attr_e( 'Custom section name…', 'naano-ai-website-builder' ); ?>">
							<button type="button" class="naano-btn-secondary" id="naano-add-custom-section">
								<?php esc_html_e( '+ Add', 'naano-ai-website-builder' ); ?>
							</button>
						</div>
						<p id="naano-custom-section-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;">
							<?php esc_html_e( 'Please enter a section name.', 'naano-ai-website-builder' ); ?>
						</p>
						<p id="naano-sections-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;">
							<?php esc_html_e( 'Please select at least one section.', 'naano-ai-website-builder' ); ?>
						</p>
					</div>
				<?php if ( ! empty( $existing_components ) ) : ?>
				<div class="naano-drawer__field naano-import-components-field">
					<label class="naano-import-label">
						<?php esc_html_e( 'Import from Existing Pages', 'naano-ai-website-builder' ); ?>
						<button type="button" class="naano-link-btn" id="naano-import-toggle"><?php esc_html_e( 'Show', 'naano-ai-website-builder' ); ?></button>
					</label>
					<p class="naano-field-hint"><?php esc_html_e( 'Reuse the header or footer from an existing page instead of regenerating it.', 'naano-ai-website-builder' ); ?></p>
					<div id="naano-import-list" style="display:none;">
						<?php foreach ( $existing_components as $ep ) : ?>
						<div class="naano-import-page">
							<span class="naano-import-page-name"><?php echo esc_html( $ep['pageTitle'] ); ?></span>
							<div class="naano-import-btns">
								<?php foreach ( $ep['sections'] as $sec ) :
									$sec_type  = sanitize_key( $sec['type'] ?? $sec['id'] ?? '' );
									$sec_label = ucfirst( str_replace( [ '_', '-' ], ' ', $sec['id'] ?? $sec_type ) );
								?>
								<button type="button"
										class="naano-import-section-btn"
										data-section-id="<?php echo esc_attr( $sec['id'] ); ?>"
										data-section-type="<?php echo esc_attr( $sec_type ); ?>">
									<span class="naano-import-check dashicons dashicons-yes" style="display:none;"></span>
									<?php echo esc_html( $sec_label ); ?>
								</button>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div><!-- .naano-import-components-field -->
				<?php endif; ?>
					<div class="naano-drawer__actions">
						<button type="button" class="naano-btn-generate" id="naano-generate-btn">
							<span class="dashicons dashicons-superhero-alt"></span>
							<?php esc_html_e( 'Generate Full Website', 'naano-ai-website-builder' ); ?>
						</button>
						<div class="naano-loading" id="naano-generate-loading" style="display:none;">
							<span class="spinner is-active"></span>
							<?php esc_html_e( 'Generating your website…', 'naano-ai-website-builder' ); ?>
						</div>
					</div>

				</div><!-- #naano-drawer-generate -->

				<!-- STATE 2 : Section editing -->
				<div class="naano-drawer-panel" id="naano-drawer-edit"
					 <?php echo $has_page ? '' : 'style="display:none;"'; ?>>

					<div class="naano-drawer__header">
						<h3><?php esc_html_e( 'Edit Section', 'naano-ai-website-builder' ); ?></h3>
						<div class="naano-editing-section-badge" id="naano-editing-section-name">
							<?php esc_html_e( '— click a section in the preview —', 'naano-ai-website-builder' ); ?>
						</div>
					</div>

					<!-- Sections list (quick-select) -->
					<div class="naano-drawer__field naano-sections-list-wrap" id="naano-sections-list-wrap">
						<label><?php esc_html_e( 'Page Sections', 'naano-ai-website-builder' ); ?></label>
						<ul class="naano-sections-list" id="naano-sections-list"></ul>
					</div>

					<!-- Inspect mode toggle -->
					<div class="naano-drawer__field">
						<button type="button" class="naano-btn-secondary" id="naano-inspect-toggle-btn">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Inspect Elements', 'naano-ai-website-builder' ); ?>
						</button>
					</div>

					<!-- Element style panel — hidden until user clicks an element in inspect mode -->
					<div id="naano-element-style-panel" class="naano-element-style-panel naano-drawer__field" style="display:none;">

						<div class="naano-esp-label">
							<span><?php esc_html_e( 'Element', 'naano-ai-website-builder' ); ?></span>
							<span id="naano-esp-breadcrumb" class="naano-esp-breadcrumb"></span>
						</div>

						<div class="naano-esp-tabs">
							<button type="button" class="naano-esp-tab naano-esp-tab--active" data-tab="style"><?php esc_html_e( 'Style', 'naano-ai-website-builder' ); ?></button>
							<button type="button" class="naano-esp-tab" data-tab="custom"><?php esc_html_e( 'Custom CSS', 'naano-ai-website-builder' ); ?></button>
						</div>

						<!-- Style pane -->
						<div class="naano-esp-tab-pane" id="naano-esp-pane-style">
							<div class="naano-esp-grid">

								<div class="naano-esp-group">
									<div class="naano-esp-group-label"><?php esc_html_e( 'Typography', 'naano-ai-website-builder' ); ?></div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Color', 'naano-ai-website-builder' ); ?></label>
										<input type="color" class="naano-esp-color-input" data-prop="color" value="#000000">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Size', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="fontSize" placeholder="16px">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Weight', 'naano-ai-website-builder' ); ?></label>
										<select class="naano-esp-select" data-prop="fontWeight">
											<option value="">—</option>
											<option value="300"><?php esc_html_e( 'Light', 'naano-ai-website-builder' ); ?></option>
											<option value="400"><?php esc_html_e( 'Normal', 'naano-ai-website-builder' ); ?></option>
											<option value="500"><?php esc_html_e( 'Medium', 'naano-ai-website-builder' ); ?></option>
											<option value="600"><?php esc_html_e( 'Semi-bold', 'naano-ai-website-builder' ); ?></option>
											<option value="700"><?php esc_html_e( 'Bold', 'naano-ai-website-builder' ); ?></option>
											<option value="800"><?php esc_html_e( 'Extra-bold', 'naano-ai-website-builder' ); ?></option>
										</select>
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Align', 'naano-ai-website-builder' ); ?></label>
										<select class="naano-esp-select" data-prop="textAlign">
											<option value="">—</option>
											<option value="left"><?php esc_html_e( 'Left', 'naano-ai-website-builder' ); ?></option>
											<option value="center"><?php esc_html_e( 'Center', 'naano-ai-website-builder' ); ?></option>
											<option value="right"><?php esc_html_e( 'Right', 'naano-ai-website-builder' ); ?></option>
											<option value="justify"><?php esc_html_e( 'Justify', 'naano-ai-website-builder' ); ?></option>
										</select>
									</div>
								</div>

								<div class="naano-esp-group">
									<div class="naano-esp-group-label"><?php esc_html_e( 'Background', 'naano-ai-website-builder' ); ?></div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Color', 'naano-ai-website-builder' ); ?></label>
										<input type="color" class="naano-esp-color-input" data-prop="backgroundColor" value="#ffffff">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Image', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="backgroundImage" placeholder="url(…)">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Size', 'naano-ai-website-builder' ); ?></label>
										<select class="naano-esp-select" data-prop="backgroundSize">
											<option value="">—</option>
											<option value="cover"><?php esc_html_e( 'Cover', 'naano-ai-website-builder' ); ?></option>
											<option value="contain"><?php esc_html_e( 'Contain', 'naano-ai-website-builder' ); ?></option>
											<option value="auto"><?php esc_html_e( 'Auto', 'naano-ai-website-builder' ); ?></option>
										</select>
									</div>
								</div>

								<div class="naano-esp-group">
									<div class="naano-esp-group-label"><?php esc_html_e( 'Size', 'naano-ai-website-builder' ); ?></div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Width', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="width" placeholder="100%">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Height', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="height" placeholder="auto">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Max width', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="maxWidth" placeholder="1200px">
									</div>
								</div>

								<div class="naano-esp-group">
									<div class="naano-esp-group-label"><?php esc_html_e( 'Padding', 'naano-ai-website-builder' ); ?></div>
									<div class="naano-esp-4col">
										<input type="text" class="naano-esp-text-input" data-prop="paddingTop" placeholder="T">
										<input type="text" class="naano-esp-text-input" data-prop="paddingRight" placeholder="R">
										<input type="text" class="naano-esp-text-input" data-prop="paddingBottom" placeholder="B">
										<input type="text" class="naano-esp-text-input" data-prop="paddingLeft" placeholder="L">
									</div>
								</div>

								<div class="naano-esp-group">
									<div class="naano-esp-group-label"><?php esc_html_e( 'Margin', 'naano-ai-website-builder' ); ?></div>
									<div class="naano-esp-4col">
										<input type="text" class="naano-esp-text-input" data-prop="marginTop" placeholder="T">
										<input type="text" class="naano-esp-text-input" data-prop="marginRight" placeholder="R">
										<input type="text" class="naano-esp-text-input" data-prop="marginBottom" placeholder="B">
										<input type="text" class="naano-esp-text-input" data-prop="marginLeft" placeholder="L">
									</div>
								</div>

								<div class="naano-esp-group">
									<div class="naano-esp-group-label"><?php esc_html_e( 'Border', 'naano-ai-website-builder' ); ?></div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Border', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="border" placeholder="1px solid #000">
									</div>
									<div class="naano-esp-row">
										<label><?php esc_html_e( 'Radius', 'naano-ai-website-builder' ); ?></label>
										<input type="text" class="naano-esp-text-input" data-prop="borderRadius" placeholder="8px">
									</div>
								</div>

							</div><!-- .naano-esp-grid -->
						</div><!-- #naano-esp-pane-style -->

						<!-- Custom CSS pane -->
						<div class="naano-esp-tab-pane" id="naano-esp-pane-custom" style="display:none;">
							<textarea id="naano-esp-custom-css" class="naano-textarea" rows="7"
									  placeholder="<?php esc_attr_e( 'color: red;\nfont-size: 18px;', 'naano-ai-website-builder' ); ?>"></textarea>
						</div>

						<div class="naano-esp-actions">
							<button type="button" class="naano-btn-ghost" id="naano-esp-deselect-btn">
								<?php esc_html_e( 'Deselect', 'naano-ai-website-builder' ); ?>
							</button>
							<button type="button" class="naano-btn-generate" id="naano-esp-apply-btn" style="width:auto;padding:8px 16px;">
								<?php esc_html_e( 'Apply', 'naano-ai-website-builder' ); ?>
							</button>
						</div>

					</div><!-- #naano-element-style-panel -->

					<div class="naano-drawer__field">
						<label for="naano-instruction">
							<?php esc_html_e( 'Instruction', 'naano-ai-website-builder' ); ?>
						</label>
						<textarea id="naano-instruction" class="naano-textarea" rows="5"
								  placeholder="<?php esc_attr_e( 'Describe what you want to change…', 'naano-ai-website-builder' ); ?>"></textarea>
					</div>
					<!-- Page Assets -->
					<div class="naano-drawer__field naano-assets-section">
						<label><?php esc_html_e( 'Page Assets', 'naano-ai-website-builder' ); ?></label>
						<p class="naano-field-hint"><?php esc_html_e( 'Add numbered assets to reference in your instructions (e.g. "use asset #1 as hero image").', 'naano-ai-website-builder' ); ?></p>
						<ul class="naano-reference-list" id="naano-asset-list"></ul>
						<div class="naano-add-url-form" id="naano-add-asset-form" style="display:none;">
							<input type="url" id="naano-asset-url" class="naano-input"
								   placeholder="https://example.com/image.jpg">
							<input type="text" id="naano-asset-desc" class="naano-input"
								   placeholder="<?php esc_attr_e( 'Description (optional)', 'naano-ai-website-builder' ); ?>">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-secondary" id="naano-save-asset-btn">
									<?php esc_html_e( 'Add', 'naano-ai-website-builder' ); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-asset-btn">
									<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
								</button>
							</div>
						</div>
						<div class="naano-asset-picker" id="naano-asset-picker">
							<button type="button" class="naano-btn-secondary" id="naano-add-asset-media-btn">
								<span class="dashicons dashicons-admin-media"></span>
								<?php esc_html_e( 'Media Library', 'naano-ai-website-builder' ); ?>
							</button>
							<button type="button" class="naano-btn-secondary" id="naano-add-asset-btn">
								<span class="dashicons dashicons-admin-links"></span>
								<?php esc_html_e( 'From URL', 'naano-ai-website-builder' ); ?>
							</button>
						</div>
					</div>

					<!-- URL Redirections -->
					<div class="naano-drawer__field naano-redirects-section">
						<label><?php esc_html_e( 'URL Redirections', 'naano-ai-website-builder' ); ?></label>
						<p class="naano-field-hint"><?php esc_html_e( 'Define named links to use in your instructions (e.g. "CTA should link to Contact").', 'naano-ai-website-builder' ); ?></p>
						<ul class="naano-reference-list" id="naano-redirect-list"></ul>
						<div class="naano-add-url-form" id="naano-add-redirect-form" style="display:none;">
							<input type="text" id="naano-redirect-label" class="naano-input"
								   placeholder="<?php esc_attr_e( 'Label (e.g. Contact)', 'naano-ai-website-builder' ); ?>">
							<input type="url" id="naano-redirect-url" class="naano-input"
								   placeholder="https://example.com/contact">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-secondary" id="naano-save-redirect-btn">
									<?php esc_html_e( 'Add', 'naano-ai-website-builder' ); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-redirect-btn">
									<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
								</button>
							</div>
						</div>
						<button type="button" class="naano-btn-secondary" id="naano-add-redirect-btn">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'Add Redirect', 'naano-ai-website-builder' ); ?>
						</button>
					</div>
					<!-- Screenshot References -->
					<div class="naano-drawer__field naano-reference-section">
						<label><?php esc_html_e( 'Screenshot References', 'naano-ai-website-builder' ); ?></label>
						<ul class="naano-reference-list" id="naano-screenshot-list"></ul>
						<button type="button" class="naano-btn-secondary" id="naano-add-screenshot-btn">
							<span class="dashicons dashicons-format-image"></span>
							<?php esc_html_e( 'Add Screenshot', 'naano-ai-website-builder' ); ?>
						</button>
					</div>

					<!-- URL References -->
					<div class="naano-drawer__field naano-reference-section">
						<label><?php esc_html_e( 'URL References', 'naano-ai-website-builder' ); ?></label>
						<ul class="naano-reference-list" id="naano-url-list"></ul>
						<div class="naano-add-url-form" id="naano-add-url-form" style="display:none;">
							<input type="url" id="naano-ref-url" class="naano-input"
								   placeholder="https://example.com">
							<input type="text" id="naano-ref-notes" class="naano-input"
								   placeholder="<?php esc_attr_e( 'Notes (optional)', 'naano-ai-website-builder' ); ?>">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-secondary" id="naano-save-url-btn">
									<?php esc_html_e( 'Add', 'naano-ai-website-builder' ); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-url-btn">
									<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
								</button>
							</div>
						</div>
						<button type="button" class="naano-btn-secondary" id="naano-add-url-btn">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'Add URL', 'naano-ai-website-builder' ); ?>
						</button>
					</div>

					<div class="naano-drawer__actions">
						<button type="button" class="naano-btn-generate" id="naano-update-section-btn" disabled>
							<span class="dashicons dashicons-superhero-alt"></span>
							<?php esc_html_e( 'Update Section', 'naano-ai-website-builder' ); ?>
						</button>
						<div class="naano-loading" id="naano-update-loading" style="display:none;">
							<span class="spinner is-active"></span>
							<?php esc_html_e( 'Updating…', 'naano-ai-website-builder' ); ?>
						</div>
						<button type="button" class="naano-btn-secondary naano-mt-8" id="naano-add-new-section-btn">
							+ <?php esc_html_e( 'Add New Section', 'naano-ai-website-builder' ); ?>
						</button>
						<div id="naano-new-section-form" style="display:none;margin-top:8px;">
							<input type="text" id="naano-new-section-name" class="naano-input"
								   placeholder="<?php esc_attr_e( 'Section name, e.g. Team', 'naano-ai-website-builder' ); ?>">
							<p id="naano-new-section-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;"></p>
							<div class="naano-add-url-form__btns" style="margin-top:6px;">
								<button type="button" class="naano-btn-secondary" id="naano-confirm-new-section-btn">
									<?php esc_html_e( 'Add', 'naano-ai-website-builder' ); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-new-section-btn">
									<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
								</button>
							</div>
						</div>
					</div>

				</div><!-- #naano-drawer-edit -->

			</div><!-- .naano-drawer__inner -->
		</div><!-- .naano-vb__drawer -->

		<!-- ===============================================================
		     LIVE PREVIEW CANVAS
		     =============================================================== -->
		<div class="naano-vb__canvas" id="naano-vb-canvas">

			<!-- Empty state placeholder -->
			<div class="naano-canvas-placeholder" id="naano-canvas-placeholder"
				 <?php echo $has_page ? 'style="display:none;"' : ''; ?>>
				<div class="naano-canvas-placeholder__inner">
					<span class="dashicons dashicons-admin-site-alt3 naano-canvas-placeholder__icon"></span>
					<h2><?php esc_html_e( 'Your page preview will appear here', 'naano-ai-website-builder' ); ?></h2>
					<p><?php esc_html_e( 'Fill in the form on the left and click "Generate Full Website" to get started.', 'naano-ai-website-builder' ); ?></p>
				</div>
			</div>

			<!-- Live preview iframe -->
			<div class="naano-live-iframe-wrap" id="naano-live-iframe-wrap"
				 <?php echo $has_page ? '' : 'style="display:none;"'; ?>>
				<iframe
					id="naano-live-preview"
					class="naano-live-iframe"
					sandbox="allow-scripts"
					title="<?php esc_attr_e( 'Live page preview', 'naano-ai-website-builder' ); ?>"
				></iframe>
			</div>

		</div><!-- .naano-vb__canvas -->

	</div><!-- .naano-vb__body -->

</div><!-- .naano-vb -->

<!-- ===== Publish Page Modal ===== -->
<div class="naano-modal-backdrop" id="naano-save-page-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="naano-save-page-modal-title">
	<div class="naano-modal">
		<h3 class="naano-modal__title" id="naano-save-page-modal-title"><?php esc_html_e( 'Publish Page', 'naano-ai-website-builder' ); ?></h3>
		<p class="naano-modal__desc"><?php esc_html_e( 'Your page will be published as a standalone WordPress page with no theme wrapping.', 'naano-ai-website-builder' ); ?></p>
		<div class="naano-drawer__field">
			<label for="naano-save-page-title"><?php esc_html_e( 'Page Title', 'naano-ai-website-builder' ); ?></label>
			<input type="text" id="naano-save-page-title" class="naano-input" placeholder="<?php esc_attr_e( 'Enter a page title&hellip;', 'naano-ai-website-builder' ); ?>" />
			<p class="naano-error-msg" id="naano-save-page-error" style="display:none;"></p>
		</div>
		<label class="naano-checkbox-label" style="margin-bottom:16px;">
			<input type="checkbox" id="naano-set-homepage-chk" />
			<?php esc_html_e( 'Set as WordPress homepage', 'naano-ai-website-builder' ); ?>
		</label>
		<div class="naano-modal__actions">
			<button type="button" class="naano-btn-secondary" id="naano-save-page-cancel-btn"><?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?></button>
			<button type="button" class="naano-btn-generate" id="naano-save-page-confirm-btn" style="width:auto;padding:8px 20px;"><?php esc_html_e( 'Publish →', 'naano-ai-website-builder' ); ?></button>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
