<?php
/**
 * Builder Admin Page Template
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_id  = isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : 0;
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
?>
<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<span class="dashicons dashicons-admin-site-alt3"></span>
		<?php esc_html_e( 'Naano AI Website Builder', 'naano-ai-website-builder' ); ?>
	</h1>

	<?php if ( ! $has_page ) : ?>
	<!-- ============================================================
	     GENERATION FORM (no page selected yet)
	     ============================================================ -->
	<div class="naano-generation-form" id="naano-generation-form">
		<div class="naano-card">
			<h2><?php esc_html_e( 'Create a New AI Website', 'naano-ai-website-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Describe your site and select the sections you want. The AI will generate a complete website section-by-section.', 'naano-ai-website-builder' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano-page-name"><?php esc_html_e( 'Page Name', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="naano-page-name"
							   class="regular-text"
							   placeholder="<?php esc_attr_e( 'e.g. My Awesome Product', 'naano-ai-website-builder' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano-description"><?php esc_html_e( 'Site Description', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<textarea id="naano-description"
								  class="large-text"
								  rows="5"
								  placeholder="<?php esc_attr_e( 'Describe your business, product, service, tone, target audience…', 'naano-ai-website-builder' ); ?>"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sections', 'naano-ai-website-builder' ); ?></th>
					<td>
						<div class="naano-section-checkboxes" id="naano-section-checkboxes">
							<?php foreach ( $section_types as $type => $label ) : ?>
							<label class="naano-checkbox-label">
								<input type="checkbox"
									   name="sections[]"
									   value="<?php echo esc_attr( $type ); ?>"
									   checked>
								<?php echo esc_html( $label ); ?>
							</label>
							<?php endforeach; ?>
						</div>
						<div class="naano-custom-section-row" style="margin-top:8px;">
							<input type="text"
								   id="naano-custom-section-input"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'Custom section name…', 'naano-ai-website-builder' ); ?>">
							<button type="button" class="button" id="naano-add-custom-section">
								<?php esc_html_e( '+ Add Section', 'naano-ai-website-builder' ); ?>
							</button>
						</div>
					</td>
				</tr>
			</table>

			<div class="naano-form-actions">
				<button type="button" class="button button-primary button-hero" id="naano-generate-btn">
					<span class="dashicons dashicons-superhero-alt" style="margin-top:3px;"></span>
					<?php esc_html_e( 'Generate Full Website', 'naano-ai-website-builder' ); ?>
				</button>
				<span class="naano-loading" id="naano-generate-loading" style="display:none;">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Generating your website…', 'naano-ai-website-builder' ); ?>
				</span>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION CARDS AREA (shown after generation or when editing)
	     ============================================================ -->
	<div class="naano-builder-main" id="naano-builder-main" <?php echo $has_page ? '' : 'style="display:none;"'; ?>>

		<!-- Action Bar -->
		<div class="naano-action-bar" id="naano-action-bar">
			<div class="naano-action-bar__left">
				<strong><?php esc_html_e( 'Page:', 'naano-ai-website-builder' ); ?></strong>
				<span id="naano-current-page-name"><?php echo $has_page ? esc_html( get_the_title( $page_id ) ) : ''; ?></span>
			</div>
			<div class="naano-action-bar__right">
				<button type="button" class="button naano-btn-preview" id="naano-preview-btn">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Preview', 'naano-ai-website-builder' ); ?>
				</button>
				<button type="button" class="button naano-btn-export" id="naano-export-btn">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export HTML', 'naano-ai-website-builder' ); ?>
				</button>
				<button type="button" class="button naano-btn-copy" id="naano-copy-btn">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Copy HTML', 'naano-ai-website-builder' ); ?>
				</button>
				<button type="button" class="button button-primary naano-btn-save-page" id="naano-save-page-btn">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save as WP Page', 'naano-ai-website-builder' ); ?>
				</button>
			</div>
		</div>

		<!-- Section Cards Container -->
		<div class="naano-section-cards" id="naano-section-cards">
			<?php if ( $has_page ) :
				$sm = new Naano_Section_Manager();
				foreach ( $sm->get_sections( $page_id ) as $section ) :
					$section_id   = $section['id'];
					$section_html = $section['html'];
					include NAANO_PLUGIN_DIR . 'templates/section-card.php';
				endforeach;
			endif; ?>
		</div>

		<!-- Edit Panel (hidden, shown when editing a section) -->
		<div class="naano-edit-panel" id="naano-edit-panel" style="display:none;">
			<div class="naano-edit-panel__inner">
				<h3 class="naano-edit-panel__title">
					<?php esc_html_e( 'Edit Section', 'naano-ai-website-builder' ); ?>:
					<span id="naano-editing-section-name"></span>
				</h3>

				<label for="naano-instruction">
					<strong><?php esc_html_e( 'Instruction', 'naano-ai-website-builder' ); ?></strong>
				</label>
				<textarea id="naano-instruction"
						  class="large-text"
						  rows="4"
						  placeholder="<?php esc_attr_e( 'Describe what you want to change…', 'naano-ai-website-builder' ); ?>"></textarea>

				<!-- Screenshot References -->
				<div class="naano-reference-section">
					<h4><?php esc_html_e( 'Screenshot References', 'naano-ai-website-builder' ); ?></h4>
					<ul class="naano-reference-list" id="naano-screenshot-list"></ul>
					<button type="button" class="button" id="naano-add-screenshot-btn">
						<span class="dashicons dashicons-format-image"></span>
						<?php esc_html_e( 'Add Screenshot', 'naano-ai-website-builder' ); ?>
					</button>
				</div>

				<!-- URL References -->
				<div class="naano-reference-section">
					<h4><?php esc_html_e( 'URL References', 'naano-ai-website-builder' ); ?></h4>
					<ul class="naano-reference-list" id="naano-url-list"></ul>
					<div class="naano-add-url-form" id="naano-add-url-form" style="display:none;">
						<input type="url" id="naano-ref-url" class="regular-text"
							   placeholder="https://example.com">
						<input type="text" id="naano-ref-notes" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Notes (optional)', 'naano-ai-website-builder' ); ?>">
						<button type="button" class="button" id="naano-save-url-btn">
							<?php esc_html_e( 'Add', 'naano-ai-website-builder' ); ?>
						</button>
						<button type="button" class="button" id="naano-cancel-url-btn">
							<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
						</button>
					</div>
					<button type="button" class="button" id="naano-add-url-btn">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'Add URL Reference', 'naano-ai-website-builder' ); ?>
					</button>
				</div>

				<div class="naano-edit-panel__actions">
					<button type="button" class="button button-primary" id="naano-update-section-btn">
						<span class="dashicons dashicons-superhero-alt" style="margin-top:3px;"></span>
						<?php esc_html_e( 'Update Section', 'naano-ai-website-builder' ); ?>
					</button>
					<button type="button" class="button" id="naano-cancel-edit-btn">
						<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
					</button>
					<span class="naano-loading" id="naano-update-loading" style="display:none;">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Updating…', 'naano-ai-website-builder' ); ?>
					</span>
				</div>
			</div>
		</div>

		<!-- Add New Section -->
		<div class="naano-add-section-bar">
			<button type="button" class="button naano-add-section-btn" id="naano-add-new-section-btn">
				+ <?php esc_html_e( 'Add New Section', 'naano-ai-website-builder' ); ?>
			</button>
		</div>
	</div>

</div><!-- .naano-builder-wrap -->
