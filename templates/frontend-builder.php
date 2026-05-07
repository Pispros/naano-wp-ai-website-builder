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

if (!defined("ABSPATH")) {
    exit();
}

$page_id = empty($_GET["naano_new"]) ? (get_queried_object_id() ?: 0) : 0;
$has_page = $page_id > 0;

$section_types = [
    "header" => __("Header / Navigation", "naano-ai-website-builder"),
    "hero" => __("Hero / Banner", "naano-ai-website-builder"),
    "features" => __("Features", "naano-ai-website-builder"),
    "about" => __("About", "naano-ai-website-builder"),
    "services" => __("Services", "naano-ai-website-builder"),
    "pricing" => __("Pricing", "naano-ai-website-builder"),
    "testimonials" => __("Testimonials", "naano-ai-website-builder"),
    "contact" => __("Contact", "naano-ai-website-builder"),
    "footer" => __("Footer", "naano-ai-website-builder"),
];

// Back-to-admin URL.
$admin_pages_url = admin_url("admin.php?page=naano-ai-builder");

// Translation data is injected by maybe_render_frontend_builder() via
// variable scope; fall back to empty arrays when accessed directly.
$current_lang = $current_lang ?? "";
$translations = $translations ?? [];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo("charset"); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e(
     "Naano AI Builder",
     "naano-ai-website-builder",
 ); ?></title>
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
			<a href="<?php echo esc_url($admin_pages_url); ?>"
			   class="naano-drawer-toggle"
			   title="<?php esc_attr_e(
          "Back to Dashboard",
          "naano-ai-website-builder",
      ); ?>">
				<span class="dashicons dashicons-arrow-left-alt"></span>
			</a>
			<button type="button" class="naano-drawer-toggle" id="naano-drawer-toggle"
					title="<?php esc_attr_e("Toggle Panel", "naano-ai-website-builder"); ?>">
				<span class="dashicons dashicons-menu-alt"></span>
			</button>
			<span class="naano-vb__brand">
				<span class="dashicons dashicons-admin-site-alt3"></span>
				<?php esc_html_e("Naano AI", "naano-ai-website-builder"); ?>
			</span>
			<span class="naano-vb__separator"></span>
			<span class="naano-vb__page-name" id="naano-current-page-name">
				<?php echo $has_page
        ? esc_html(get_the_title($page_id))
        : esc_html__("New Page", "naano-ai-website-builder"); ?>
			</span>
			<?php if (count($translations) > 1): ?>
			<span class="naano-vb__separator"></span>
			<div class="naano-lang-switcher-wrap">
				<span class="dashicons dashicons-translation naano-lang-icon" title="<?php esc_attr_e(
        "Language",
        "naano-ai-website-builder",
    ); ?>"></span>
				<select id="naano-lang-switcher" class="naano-lang-select">
					<?php foreach ($translations as $tr): ?>
					<option value="<?php echo esc_attr($tr["builderUrl"]); ?>"
						<?php selected($tr["current"]); ?>>
						<?php echo esc_html(strtoupper($tr["lang"])); ?>
						<?php if (!empty($tr["label"]) && $tr["label"] !== strtoupper($tr["lang"])):
          echo " — " . esc_html($tr["label"]);
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
						data-width="100%" title="<?php esc_attr_e(
          "Desktop",
          "naano-ai-website-builder",
      ); ?>">
					<span class="dashicons dashicons-desktop"></span>
				</button>
				<button type="button" class="naano-viewport-btn"
						data-width="768px" title="<?php esc_attr_e(
          "Tablet",
          "naano-ai-website-builder",
      ); ?>">
					<span class="dashicons dashicons-tablet"></span>
				</button>
				<button type="button" class="naano-viewport-btn"
						data-width="375px" title="<?php esc_attr_e(
          "Mobile",
          "naano-ai-website-builder",
      ); ?>">
					<span class="dashicons dashicons-smartphone"></span>
				</button>
			</div>
		</div>

		<div class="naano-vb__toolbar-right">
			<button type="button" class="naano-tb-btn" id="naano-preview-btn"
					title="<?php esc_attr_e("Preview", "naano-ai-website-builder"); ?>">
				<span class="dashicons dashicons-visibility"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e(
        "Preview",
        "naano-ai-website-builder",
    ); ?></span>
			</button>
			<button type="button" class="naano-tb-btn" id="naano-export-btn"
					title="<?php esc_attr_e("Export HTML", "naano-ai-website-builder"); ?>">
				<span class="dashicons dashicons-download"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e(
        "Export",
        "naano-ai-website-builder",
    ); ?></span>
			</button>
			<button type="button" class="naano-tb-btn" id="naano-copy-btn"
					title="<?php esc_attr_e("Copy HTML", "naano-ai-website-builder"); ?>">
				<span class="dashicons dashicons-admin-page"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e(
        "Copy",
        "naano-ai-website-builder",
    ); ?></span>
			</button>
			<!-- Save manual edits (text, styles, classes, deletions) to DB.
			     This is SEPARATE from "Save as WP Page" below — that one
			     publishes the assembled HTML to the live page. This one
			     only persists section-level edits to the meta store so
			     they survive a refresh and are present next time the user
			     hits Publish. -->
			<button type="button" class="naano-tb-btn naano-tb-btn--save-changes" id="naano-save-changes-btn"
					title="<?php esc_attr_e(
         "Save manual edits (text, styles, classes) to draft. Does not publish.",
         "naano-ai-website-builder",
     ); ?>"
					style="display:none;">
				<span class="dashicons dashicons-cloud-upload"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e(
        "Save changes",
        "naano-ai-website-builder",
    ); ?></span>
				<span class="naano-tb-btn__counter" id="naano-save-changes-counter" style="display:none;">0</span>
			</button>
			<button type="button" class="naano-tb-btn naano-tb-btn--primary" id="naano-save-page-btn"
					title="<?php esc_attr_e("Save as WP Page", "naano-ai-website-builder"); ?>">
				<span class="dashicons dashicons-saved"></span>
				<span class="naano-tb-btn__label"><?php esc_html_e(
        "Publish",
        "naano-ai-website-builder",
    ); ?></span>
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
					 <?php echo $has_page ? 'style="display:none;"' : ""; ?>>

					<div class="naano-drawer__header">
						<h3><?php esc_html_e("Create New Page", "naano-ai-website-builder"); ?></h3>
						<p><?php esc_html_e(
          "Describe your site and choose which sections to generate.",
          "naano-ai-website-builder",
      ); ?></p>
					</div>

					<div class="naano-drawer__field">
						<label for="naano-page-name">
							<?php esc_html_e("Page Name", "naano-ai-website-builder"); ?>
						</label>
						<input type="text" id="naano-page-name" class="naano-input"
							   placeholder="<?php esc_attr_e(
              "e.g. My Awesome Product",
              "naano-ai-website-builder",
          ); ?>">
					</div>

					<div class="naano-drawer__field">
						<label for="naano-description">
							<?php esc_html_e("Site Description", "naano-ai-website-builder"); ?>
						</label>
						<textarea id="naano-description" class="naano-textarea" rows="6"
								  placeholder="<?php esc_attr_e(
              "Describe your business, product, service, tone, target audience…",
              "naano-ai-website-builder",
          ); ?>"></textarea>
						<p id="naano-description-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;">
							<?php esc_html_e(
           "Please enter a site description.",
           "naano-ai-website-builder",
       ); ?>
						</p>
						<div class="naano-enhance-row">
							<button type="button" class="naano-btn-enhance" id="naano-enhance-description-btn">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e("Enhance with AI", "naano-ai-website-builder"); ?>
							</button>
							<div class="naano-loading" id="naano-enhance-description-loading" style="display:none;">
								<span class="spinner is-active"></span>
								<?php esc_html_e("Enhancing…", "naano-ai-website-builder"); ?>
							</div>
						</div>
					</div>

					<div class="naano-drawer__field">
						<label><?php esc_html_e(
          "Sections to Generate",
          "naano-ai-website-builder",
      ); ?></label>
						<div class="naano-section-checkboxes" id="naano-section-checkboxes">
							<?php foreach ($section_types as $type => $label): ?>
							<label class="naano-checkbox-label">
								<input type="checkbox" name="sections[]"
									   value="<?php echo esc_attr($type); ?>" checked>
								<?php echo esc_html($label); ?>
							</label>
							<?php endforeach; ?>
						</div>
						<div class="naano-custom-section-row">
							<input type="text" id="naano-custom-section-input" class="naano-input"
								   placeholder="<?php esc_attr_e(
               "Custom section name…",
               "naano-ai-website-builder",
           ); ?>">
							<button type="button" class="naano-btn-secondary" id="naano-add-custom-section">
								<?php esc_html_e("+ Add", "naano-ai-website-builder"); ?>
							</button>
						</div>
						<p id="naano-custom-section-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;">
							<?php esc_html_e("Please enter a section name.", "naano-ai-website-builder"); ?>
						</p>
						<p id="naano-sections-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;">
							<?php esc_html_e(
           "Please select at least one section.",
           "naano-ai-website-builder",
       ); ?>
						</p>
					</div>

					<div class="naano-drawer__field">
						<label for="naano-initial-wp-menu"><?php esc_html_e(
          "Navigation Menu",
          "naano-ai-website-builder",
      ); ?></label>
						<p class="naano-field-hint"><?php esc_html_e(
          "Select a WordPress menu to inject into header/footer sections.",
          "naano-ai-website-builder",
      ); ?></p>
						<select id="naano-initial-wp-menu" class="naano-input">
							<option value=""><?php esc_html_e(
           "— None —",
           "naano-ai-website-builder",
       ); ?></option>
						</select>
					</div>

					<div class="naano-drawer__field naano-reference-section">
						<label><?php esc_html_e(
          "URL References",
          "naano-ai-website-builder",
      ); ?></label>
						<p class="naano-field-hint"><?php esc_html_e(
          "Add reference websites for the AI to study and replicate their design patterns.",
          "naano-ai-website-builder",
      ); ?></p>
						<p class="naano-field-hint naano-field-hint--warn"><?php esc_html_e(
          "Scraping may fail for ThemeForest previews and similar protected platforms — use screenshots instead for best results.",
          "naano-ai-website-builder",
      ); ?></p>
						<ul class="naano-reference-list" id="naano-initial-url-list"></ul>
						<div class="naano-add-url-form" id="naano-initial-add-url-form" style="display:none;">
							<input type="url" id="naano-initial-ref-url" class="naano-input"
								   placeholder="https://example.com">
							<input type="text" id="naano-initial-ref-notes" class="naano-input"
								   placeholder="<?php esc_attr_e(
               "Notes (optional)",
               "naano-ai-website-builder",
           ); ?>">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-primary" id="naano-initial-save-url-btn">
									<?php esc_html_e("Add", "naano-ai-website-builder"); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-initial-cancel-url-btn">
									<?php esc_html_e("Cancel", "naano-ai-website-builder"); ?>
								</button>
							</div>
						</div>
						<button type="button" class="naano-btn-secondary" id="naano-initial-add-url-btn">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e("Add URL", "naano-ai-website-builder"); ?>
						</button>
					</div>

					<!-- Global CSS for the entire page. Persisted via the
					     "Save changes" button alongside section edits. The
					     CSS is injected into the assembled HTML after the
					     base reset so the user can override anything. -->
					<div class="naano-drawer__field naano-global-css-field">
						<label for="naano-global-css">
							<?php esc_html_e("Global CSS", "naano-ai-website-builder"); ?>
						</label>
						<p class="naano-field-hint">
							<?php esc_html_e(
           'Page-level CSS that applies to the whole site. Persists between AI regenerations. Click "Save changes" in the toolbar to commit.',
           "naano-ai-website-builder",
       ); ?>
						</p>
						<textarea id="naano-global-css" class="naano-textarea naano-global-css-textarea" rows="8"
								  placeholder="<?php esc_attr_e(
              "/* Example */&#10;:root { --brand: #f59e0b; }&#10;a:hover { opacity: 0.8; }",
              "naano-ai-website-builder",
          ); ?>"></textarea>
					</div>

					<!-- Failed sections list — sections that didn't make it
					     during the original generate_site run (e.g. host
					     timeout, exception). Each row has a Retry button
					     that triggers a fresh update_section job for that
					     section only. Auto-clears as sections are recovered. -->
					<div class="naano-drawer__field naano-failed-sections-field" id="naano-failed-sections-wrap" style="display:none;">
						<label>
							<span class="dashicons dashicons-warning" style="color:#d97706;"></span>
							<?php esc_html_e("Failed sections", "naano-ai-website-builder"); ?>
							<span class="naano-failed-count" id="naano-failed-count">0</span>
						</label>
						<p class="naano-field-hint">
							<?php esc_html_e(
           'These sections were skipped during generation due to host timeouts or errors. Click "Retry" to regenerate one. They disappear from this list as they succeed.',
           "naano-ai-website-builder",
       ); ?>
						</p>
						<ul class="naano-failed-list" id="naano-failed-list"></ul>
					</div>

				<?php if (!empty($existing_components)): ?>
				<div class="naano-drawer__field naano-import-components-field">
					<label class="naano-import-label">
						<?php esc_html_e("Import from Existing Pages", "naano-ai-website-builder"); ?>
						<button type="button" class="naano-link-btn" id="naano-import-toggle"><?php esc_html_e(
          "Show",
          "naano-ai-website-builder",
      ); ?></button>
					</label>
					<p class="naano-field-hint"><?php esc_html_e(
         "Reuse the header or footer from an existing page instead of regenerating it.",
         "naano-ai-website-builder",
     ); ?></p>
					<div id="naano-import-list" style="display:none;">
						<?php foreach ($existing_components as $ep): ?>
						<div class="naano-import-page">
							<span class="naano-import-page-name"><?php echo esc_html(
           $ep["pageTitle"],
       ); ?></span>
							<div class="naano-import-btns">
								<?php foreach ($ep["sections"] as $sec):

            $sec_type = sanitize_key($sec["type"] ?? ($sec["id"] ?? ""));
            $sec_label = ucfirst(
                str_replace(["_", "-"], " ", $sec["id"] ?? $sec_type),
            );
            ?>
								<button type="button"
										class="naano-import-section-btn"
										data-section-id="<?php echo esc_attr($sec["id"]); ?>"
										data-section-type="<?php echo esc_attr($sec_type); ?>">
									<span class="naano-import-check dashicons dashicons-yes" style="display:none;"></span>
									<?php echo esc_html($sec_label); ?>
								</button>
								<?php
        endforeach; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div><!-- .naano-import-components-field -->
				<?php endif; ?>
					<div class="naano-drawer__actions">
						<p class="naano-generation-warning">
							<span class="dashicons dashicons-info-outline"></span>
							<?php esc_html_e(
           "Initial generation may take a while depending on the number of sections and refinement passes configured in settings.",
           "naano-ai-website-builder",
       ); ?>
						</p>
						<button type="button" class="naano-btn-generate" id="naano-generate-btn">
							<span class="dashicons dashicons-superhero-alt"></span>
							<?php esc_html_e("Generate Full Website", "naano-ai-website-builder"); ?>
						</button>
						<div class="naano-loading" id="naano-generate-loading" style="display:none;">
							<span class="spinner is-active"></span>
							<?php esc_html_e("Generating your website…", "naano-ai-website-builder"); ?>
						</div>
					</div>

				</div><!-- #naano-drawer-generate -->

				<!-- STATE 2 : Section editing -->
				<div class="naano-drawer-panel" id="naano-drawer-edit"
					 <?php echo $has_page ? "" : 'style="display:none;"'; ?>>

					<div class="naano-drawer__header">
						<h3><?php esc_html_e("Edit Section", "naano-ai-website-builder"); ?></h3>
						<div class="naano-editing-section-badge" id="naano-editing-section-name">
							<?php esc_html_e(
           "— click a section in the preview —",
           "naano-ai-website-builder",
       ); ?>
						</div>
					</div>

					<!-- Sections list (quick-select) -->
					<div class="naano-drawer__field naano-sections-list-wrap" id="naano-sections-list-wrap">
						<label><?php esc_html_e("Page Sections", "naano-ai-website-builder"); ?></label>
						<ul class="naano-sections-list" id="naano-sections-list"></ul>
					</div>

					<!-- Element style panel was moved out of the drawer and now lives
					     as a floating top-right overlay (#naano-element-style-panel)
					     for an Elementor-style UX. See bottom of this template. -->


					<div class="naano-drawer__field">
						<label for="naano-instruction">
							<?php esc_html_e("Instruction", "naano-ai-website-builder"); ?>
						</label>
						<textarea id="naano-instruction" class="naano-textarea" rows="5"
								  placeholder="<?php esc_attr_e(
              "Describe what you want to change…",
              "naano-ai-website-builder",
          ); ?>"></textarea>
						<div class="naano-enhance-row">
							<button type="button" class="naano-btn-enhance" id="naano-enhance-instruction-btn">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e("Enhance with AI", "naano-ai-website-builder"); ?>
							</button>
							<div class="naano-loading" id="naano-enhance-instruction-loading" style="display:none;">
								<span class="spinner is-active"></span>
								<?php esc_html_e("Enhancing…", "naano-ai-website-builder"); ?>
							</div>
						</div>
					</div>
					<!-- Navigation Menu -->
					<div class="naano-drawer__field">
						<label for="naano-edit-wp-menu"><?php esc_html_e(
          "Navigation Menu",
          "naano-ai-website-builder",
      ); ?></label>
						<p class="naano-field-hint"><?php esc_html_e(
          "Select a WordPress menu to inject into header/footer sections.",
          "naano-ai-website-builder",
      ); ?></p>
						<select id="naano-edit-wp-menu" class="naano-input">
							<option value=""><?php esc_html_e(
           "— None —",
           "naano-ai-website-builder",
       ); ?></option>
						</select>
					</div>

					<!-- Page Assets -->
					<div class="naano-drawer__field naano-assets-section">
						<label><?php esc_html_e("Page Assets", "naano-ai-website-builder"); ?></label>
						<p class="naano-field-hint"><?php esc_html_e(
          'Add numbered assets to reference in your instructions (e.g. "use asset #1 as hero image").',
          "naano-ai-website-builder",
      ); ?></p>
						<ul class="naano-reference-list" id="naano-asset-list"></ul>
						<div class="naano-add-url-form" id="naano-add-asset-form" style="display:none;">
							<input type="url" id="naano-asset-url" class="naano-input"
								   placeholder="https://example.com/image.jpg">
							<input type="text" id="naano-asset-desc" class="naano-input"
								   placeholder="<?php esc_attr_e(
               "Description (optional)",
               "naano-ai-website-builder",
           ); ?>">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-secondary" id="naano-save-asset-btn">
									<?php esc_html_e("Add", "naano-ai-website-builder"); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-asset-btn">
									<?php esc_html_e("Cancel", "naano-ai-website-builder"); ?>
								</button>
							</div>
						</div>
						<div class="naano-asset-picker" id="naano-asset-picker">
							<button type="button" class="naano-btn-secondary" id="naano-add-asset-media-btn">
								<span class="dashicons dashicons-admin-media"></span>
								<?php esc_html_e("Media Library", "naano-ai-website-builder"); ?>
							</button>
							<button type="button" class="naano-btn-secondary" id="naano-add-asset-btn">
								<span class="dashicons dashicons-admin-links"></span>
								<?php esc_html_e("From URL", "naano-ai-website-builder"); ?>
							</button>
						</div>
					</div>

					<!-- URL Redirections -->
					<div class="naano-drawer__field naano-redirects-section">
						<label><?php esc_html_e(
          "URL Redirections",
          "naano-ai-website-builder",
      ); ?></label>
						<p class="naano-field-hint"><?php esc_html_e(
          'Define named links to use in your instructions (e.g. "CTA should link to Contact").',
          "naano-ai-website-builder",
      ); ?></p>
						<ul class="naano-reference-list" id="naano-redirect-list"></ul>
						<div class="naano-add-url-form" id="naano-add-redirect-form" style="display:none;">
							<input type="text" id="naano-redirect-label" class="naano-input"
								   placeholder="<?php esc_attr_e(
               "Label (e.g. Contact)",
               "naano-ai-website-builder",
           ); ?>">
							<input type="url" id="naano-redirect-url" class="naano-input"
								   placeholder="https://example.com/contact">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-secondary" id="naano-save-redirect-btn">
									<?php esc_html_e("Add", "naano-ai-website-builder"); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-redirect-btn">
									<?php esc_html_e("Cancel", "naano-ai-website-builder"); ?>
								</button>
							</div>
						</div>
						<button type="button" class="naano-btn-secondary" id="naano-add-redirect-btn">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e("Add Redirect", "naano-ai-website-builder"); ?>
						</button>
					</div>
					<!-- Screenshot References -->
					<div class="naano-drawer__field naano-reference-section">
						<label><?php esc_html_e(
          "Screenshot References",
          "naano-ai-website-builder",
      ); ?></label>
						<ul class="naano-reference-list" id="naano-screenshot-list"></ul>
						<button type="button" class="naano-btn-secondary" id="naano-add-screenshot-btn">
							<span class="dashicons dashicons-format-image"></span>
							<?php esc_html_e("Add Screenshot", "naano-ai-website-builder"); ?>
						</button>
					</div>

					<!-- URL References -->
					<div class="naano-drawer__field naano-reference-section">
						<label><?php esc_html_e(
          "URL References",
          "naano-ai-website-builder",
      ); ?></label>
						<p class="naano-field-hint naano-field-hint--warn"><?php esc_html_e(
          "Scraping may fail for ThemeForest previews and similar protected platforms — use screenshots instead for best results.",
          "naano-ai-website-builder",
      ); ?></p>
						<ul class="naano-reference-list" id="naano-url-list"></ul>
						<div class="naano-add-url-form" id="naano-add-url-form" style="display:none;">
							<input type="url" id="naano-ref-url" class="naano-input"
								   placeholder="https://example.com">
							<input type="text" id="naano-ref-notes" class="naano-input"
								   placeholder="<?php esc_attr_e(
               "Notes (optional)",
               "naano-ai-website-builder",
           ); ?>">
							<div class="naano-add-url-form__btns">
								<button type="button" class="naano-btn-secondary" id="naano-save-url-btn">
									<?php esc_html_e("Add", "naano-ai-website-builder"); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-url-btn">
									<?php esc_html_e("Cancel", "naano-ai-website-builder"); ?>
								</button>
							</div>
						</div>
						<button type="button" class="naano-btn-secondary" id="naano-add-url-btn">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e("Add URL", "naano-ai-website-builder"); ?>
						</button>
					</div>

					<!-- Global CSS for the entire page (also available in the
					     create panel). Persisted via the "Save changes" button
					     alongside section edits. The CSS is injected into the
					     assembled HTML after the base reset so the user can
					     override anything. Uses a unique ID so the DOM stays
					     valid even though the create-panel field is also in
					     the document; the JS keeps both textareas in sync via
					     the shared .naano-global-css-textarea class. -->
					<div class="naano-drawer__field naano-global-css-field">
						<label for="naano-global-css-edit">
							<?php esc_html_e("Global CSS", "naano-ai-website-builder"); ?>
						</label>
						<p class="naano-field-hint">
							<?php esc_html_e(
           'Page-level CSS that applies to the whole site. Persists between AI regenerations. Click "Save changes" in the toolbar to commit.',
           "naano-ai-website-builder",
       ); ?>
						</p>
						<textarea id="naano-global-css-edit" class="naano-textarea naano-global-css-textarea" rows="8"
								  placeholder="<?php esc_attr_e(
              "/* Example */&#10;:root { --brand: #f59e0b; }&#10;a:hover { opacity: 0.8; }",
              "naano-ai-website-builder",
          ); ?>"></textarea>
					</div>

					<div class="naano-drawer__actions">
						<button type="button" class="naano-btn-generate" id="naano-update-section-btn" disabled>
							<span class="naano-update-btn-label"></span>
							<span class="dashicons dashicons-superhero-alt"></span>
							<?php esc_html_e("Update Section", "naano-ai-website-builder"); ?>
						</button>
						<div class="naano-loading" id="naano-update-loading" style="display:none;">
							<span class="spinner is-active"></span>
							<?php esc_html_e("Updating…", "naano-ai-website-builder"); ?>
						</div>
						<button type="button" class="naano-btn-secondary naano-mt-8" id="naano-add-new-section-btn">
							+ <?php esc_html_e("Add New Section", "naano-ai-website-builder"); ?>
						</button>
						<div id="naano-new-section-form" style="display:none;margin-top:8px;">
							<input type="text" id="naano-new-section-name" class="naano-input"
								   placeholder="<?php esc_attr_e(
               "Section name, e.g. Team",
               "naano-ai-website-builder",
           ); ?>">
							<p id="naano-new-section-error" style="display:none;color:#f87171;font-size:12px;margin:4px 0 0;"></p>
							<div class="naano-add-url-form__btns" style="margin-top:6px;">
								<button type="button" class="naano-btn-secondary" id="naano-confirm-new-section-btn">
									<?php esc_html_e("Add", "naano-ai-website-builder"); ?>
								</button>
								<button type="button" class="naano-btn-ghost" id="naano-cancel-new-section-btn">
									<?php esc_html_e("Cancel", "naano-ai-website-builder"); ?>
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
				 <?php echo $has_page ? 'style="display:none;"' : ""; ?>>
				<div class="naano-canvas-placeholder__inner">
					<span class="dashicons dashicons-admin-site-alt3 naano-canvas-placeholder__icon"></span>
					<h2><?php esc_html_e(
         "Your page preview will appear here",
         "naano-ai-website-builder",
     ); ?></h2>
					<p><?php esc_html_e(
         'Fill in the form on the left and click "Generate Full Website" to get started.',
         "naano-ai-website-builder",
     ); ?></p>
				</div>
			</div>

			<!-- Live preview iframe -->
			<div class="naano-live-iframe-wrap" id="naano-live-iframe-wrap"
				 <?php echo $has_page ? "" : 'style="display:none;"'; ?>>
				<iframe
					id="naano-live-preview"
					class="naano-live-iframe"
					sandbox="allow-scripts"
					title="<?php esc_attr_e("Live page preview", "naano-ai-website-builder"); ?>"
				></iframe>
			</div>

		</div><!-- .naano-vb__canvas -->

	</div><!-- .naano-vb__body -->

	<!-- ===================================================================
	     ELEMENT STYLE PANEL (floating, top-right of canvas — Elementor style)

	     Hidden by default. Shown when the user clicks any element while
	     "Edit content manually" mode is active. Lets the user edit text
	     inline (the element becomes contenteditable in the iframe), tweak
	     styles, spacing, CSS classes, or delete the element.

	     Edits are kept in memory until the user clicks "Save changes" in
	     the toolbar — that pushes them to the DB without publishing.
	     =================================================================== -->
	<div id="naano-element-style-panel" class="naano-element-style-panel naano-esp--floating" style="display:none;" role="dialog" aria-label="<?php esc_attr_e(
     "Element editor",
     "naano-ai-website-builder",
 ); ?>">

		<div class="naano-esp__header">
			<div class="naano-esp__header-info">
				<span class="naano-esp__header-tag" id="naano-esp-tag">div</span>
				<span class="naano-esp-breadcrumb" id="naano-esp-breadcrumb"></span>
			</div>
			<button type="button" class="naano-esp__close" id="naano-esp-close-btn"
					title="<?php esc_attr_e("Close (Esc)", "naano-ai-website-builder"); ?>"
					aria-label="<?php esc_attr_e(
         "Close element editor",
         "naano-ai-website-builder",
     ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="naano-esp__hint">
			<span class="dashicons dashicons-edit"></span>
			<?php esc_html_e(
       "Tip: click directly on text in the preview to edit it inline.",
       "naano-ai-website-builder",
   ); ?>
		</div>

		<div class="naano-esp-tabs">
			<button type="button" class="naano-esp-tab naano-esp-tab--active" data-tab="style"><?php esc_html_e(
       "Style",
       "naano-ai-website-builder",
   ); ?></button>
			<button type="button" class="naano-esp-tab" data-tab="spacing"><?php esc_html_e(
       "Spacing",
       "naano-ai-website-builder",
   ); ?></button>
			<button type="button" class="naano-esp-tab" data-tab="classes"><?php esc_html_e(
       "Classes",
       "naano-ai-website-builder",
   ); ?></button>
			<!-- Link tab: shown only when the selected element is an <a>.
			     Hidden by default; the JS unhides it on selection if applicable. -->
			<button type="button" class="naano-esp-tab naano-esp-tab--link-only" data-tab="link" style="display:none;"><?php esc_html_e(
       "Link",
       "naano-ai-website-builder",
   ); ?></button>
			<button type="button" class="naano-esp-tab" data-tab="custom"><?php esc_html_e(
       "Custom CSS",
       "naano-ai-website-builder",
   ); ?></button>
		</div>

		<div class="naano-esp__body">

			<!-- Style pane: typography + background + border -->
			<div class="naano-esp-tab-pane" id="naano-esp-pane-style">
				<div class="naano-esp-grid">

					<div class="naano-esp-group">
						<div class="naano-esp-group-label"><?php esc_html_e(
          "Typography",
          "naano-ai-website-builder",
      ); ?></div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Color", "naano-ai-website-builder"); ?></label>
							<input type="color" class="naano-esp-color-input" data-prop="color" value="#000000">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Size", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="fontSize" placeholder="16px">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Weight", "naano-ai-website-builder"); ?></label>
							<select class="naano-esp-select" data-prop="fontWeight">
								<option value="">—</option>
								<option value="300"><?php esc_html_e(
            "Light",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="400"><?php esc_html_e(
            "Normal",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="500"><?php esc_html_e(
            "Medium",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="600"><?php esc_html_e(
            "Semi-bold",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="700"><?php esc_html_e(
            "Bold",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="800"><?php esc_html_e(
            "Extra-bold",
            "naano-ai-website-builder",
        ); ?></option>
							</select>
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Align", "naano-ai-website-builder"); ?></label>
							<select class="naano-esp-select" data-prop="textAlign">
								<option value="">—</option>
								<option value="left"><?php esc_html_e(
            "Left",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="center"><?php esc_html_e(
            "Center",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="right"><?php esc_html_e(
            "Right",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="justify"><?php esc_html_e(
            "Justify",
            "naano-ai-website-builder",
        ); ?></option>
							</select>
						</div>
					</div>

					<div class="naano-esp-group">
						<div class="naano-esp-group-label"><?php esc_html_e(
          "Background",
          "naano-ai-website-builder",
      ); ?></div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Color", "naano-ai-website-builder"); ?></label>
							<input type="color" class="naano-esp-color-input" data-prop="backgroundColor" value="#ffffff">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Image", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="backgroundImage" placeholder="url(…)">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Size", "naano-ai-website-builder"); ?></label>
							<select class="naano-esp-select" data-prop="backgroundSize">
								<option value="">—</option>
								<option value="cover"><?php esc_html_e(
            "Cover",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="contain"><?php esc_html_e(
            "Contain",
            "naano-ai-website-builder",
        ); ?></option>
								<option value="auto"><?php esc_html_e(
            "Auto",
            "naano-ai-website-builder",
        ); ?></option>
							</select>
						</div>
					</div>

					<div class="naano-esp-group">
						<div class="naano-esp-group-label"><?php esc_html_e(
          "Border",
          "naano-ai-website-builder",
      ); ?></div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Border", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="border" placeholder="1px solid #000">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Radius", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="borderRadius" placeholder="8px">
						</div>
					</div>

				</div><!-- .naano-esp-grid -->
			</div>

			<!-- Spacing pane: width / height + padding + margin -->
			<div class="naano-esp-tab-pane" id="naano-esp-pane-spacing" style="display:none;">
				<div class="naano-esp-grid">

					<div class="naano-esp-group">
						<div class="naano-esp-group-label"><?php esc_html_e(
          "Size",
          "naano-ai-website-builder",
      ); ?></div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Width", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="width" placeholder="100%">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Height", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="height" placeholder="auto">
						</div>
						<div class="naano-esp-row">
							<label><?php esc_html_e("Max width", "naano-ai-website-builder"); ?></label>
							<input type="text" class="naano-esp-text-input" data-prop="maxWidth" placeholder="1200px">
						</div>
					</div>

					<div class="naano-esp-group">
						<div class="naano-esp-group-label"><?php esc_html_e(
          "Padding",
          "naano-ai-website-builder",
      ); ?></div>
						<div class="naano-esp-4col">
							<input type="text" class="naano-esp-text-input" data-prop="paddingTop" placeholder="T">
							<input type="text" class="naano-esp-text-input" data-prop="paddingRight" placeholder="R">
							<input type="text" class="naano-esp-text-input" data-prop="paddingBottom" placeholder="B">
							<input type="text" class="naano-esp-text-input" data-prop="paddingLeft" placeholder="L">
						</div>
					</div>

					<div class="naano-esp-group">
						<div class="naano-esp-group-label"><?php esc_html_e(
          "Margin",
          "naano-ai-website-builder",
      ); ?></div>
						<div class="naano-esp-4col">
							<input type="text" class="naano-esp-text-input" data-prop="marginTop" placeholder="T">
							<input type="text" class="naano-esp-text-input" data-prop="marginRight" placeholder="R">
							<input type="text" class="naano-esp-text-input" data-prop="marginBottom" placeholder="B">
							<input type="text" class="naano-esp-text-input" data-prop="marginLeft" placeholder="L">
						</div>
					</div>

				</div>
			</div>

			<!-- Classes pane: arbitrary CSS class names -->
			<div class="naano-esp-tab-pane" id="naano-esp-pane-classes" style="display:none;">
				<div class="naano-esp-group">
					<div class="naano-esp-group-label"><?php esc_html_e(
         "CSS classes",
         "naano-ai-website-builder",
     ); ?></div>
					<p class="naano-field-help" style="margin-top:0;">
						<?php esc_html_e(
          "Space-separated class names. They will be applied to the selected element. Existing classes generated by the AI are preserved.",
          "naano-ai-website-builder",
      ); ?>
					</p>
					<input type="text" id="naano-esp-classes-input" class="naano-input" placeholder="my-custom-class another-class">
				</div>
			</div>

			<!-- Link pane: only visible when an <a> element is selected.
			     Lets the user edit href + target, OR pick an in-page anchor
			     from the auto-detected list of section ids. -->
			<div class="naano-esp-tab-pane" id="naano-esp-pane-link" style="display:none;">
				<div class="naano-esp-group">
					<div class="naano-esp-group-label"><?php esc_html_e(
         "Link target",
         "naano-ai-website-builder",
     ); ?></div>
					<p class="naano-field-help" style="margin-top:0;">
						<?php esc_html_e(
          "Pick an in-page anchor or type any URL. Anchors jump the visitor to a section without leaving the page.",
          "naano-ai-website-builder",
      ); ?>
					</p>
					<div class="naano-esp-row">
						<label><?php esc_html_e("Anchor", "naano-ai-website-builder"); ?></label>
						<select id="naano-esp-anchor-select" class="naano-esp-select">
							<option value="">— <?php esc_html_e(
           "pick a section…",
           "naano-ai-website-builder",
       ); ?> —</option>
						</select>
					</div>
					<div class="naano-esp-row">
						<label><?php esc_html_e("URL", "naano-ai-website-builder"); ?></label>
						<input type="text" id="naano-esp-href-input" class="naano-esp-text-input" placeholder="https://example.com or #header">
					</div>
					<div class="naano-esp-row">
						<label><?php esc_html_e("Open in", "naano-ai-website-builder"); ?></label>
						<select id="naano-esp-target-select" class="naano-esp-select">
							<option value="_self"><?php esc_html_e(
           "Same tab",
           "naano-ai-website-builder",
       ); ?></option>
							<option value="_blank"><?php esc_html_e(
           "New tab",
           "naano-ai-website-builder",
       ); ?></option>
						</select>
					</div>
					<div class="naano-esp-row">
						<label><?php esc_html_e("Rel", "naano-ai-website-builder"); ?></label>
						<input type="text" id="naano-esp-rel-input" class="naano-esp-text-input" placeholder="noopener noreferrer">
					</div>
				</div>
			</div>

			<!-- Custom CSS pane -->
			<div class="naano-esp-tab-pane" id="naano-esp-pane-custom" style="display:none;">
				<p class="naano-field-help" style="margin-top:0;">
					<?php esc_html_e(
         "Raw CSS scoped to this element only.",
         "naano-ai-website-builder",
     ); ?>
				</p>
				<textarea id="naano-esp-custom-css" class="naano-textarea" rows="6"
						  placeholder="<?php esc_attr_e(
            'color: red;\nfont-size: 18px;',
            "naano-ai-website-builder",
        ); ?>"></textarea>
			</div>

		</div><!-- .naano-esp__body -->

		<div class="naano-esp__footer">
			<div class="naano-esp__footer-left">
				<button type="button" class="naano-btn-ghost naano-esp__danger" id="naano-esp-delete-btn"
						title="<?php esc_attr_e("Delete this element", "naano-ai-website-builder"); ?>">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e("Delete", "naano-ai-website-builder"); ?>
				</button>
				<button type="button" class="naano-btn-ghost" id="naano-esp-edit-section-btn"
						title="<?php esc_attr_e(
          "Open this section in the AI editor",
          "naano-ai-website-builder",
      ); ?>">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e("Edit section with AI", "naano-ai-website-builder"); ?>
				</button>
			</div>
			<div class="naano-esp__footer-right">
				<button type="button" class="naano-btn-ghost" id="naano-esp-deselect-btn">
					<?php esc_html_e("Done", "naano-ai-website-builder"); ?>
				</button>
				<button type="button" class="naano-btn-generate" id="naano-esp-apply-btn" style="width:auto;padding:8px 16px;">
					<?php esc_html_e("Apply", "naano-ai-website-builder"); ?>
				</button>
			</div>
		</div>

	</div><!-- #naano-element-style-panel -->

</div><!-- .naano-vb -->

<!-- ===== Publish Page Modal ===== -->
<div class="naano-modal-backdrop" id="naano-save-page-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="naano-save-page-modal-title">
	<div class="naano-modal">
		<h3 class="naano-modal__title" id="naano-save-page-modal-title"><?php esc_html_e(
      "Publish Page",
      "naano-ai-website-builder",
  ); ?></h3>
		<p class="naano-modal__desc"><?php esc_html_e(
      "Your page will be published as a standalone WordPress page with no theme wrapping.",
      "naano-ai-website-builder",
  ); ?></p>
		<div class="naano-drawer__field">
			<label for="naano-save-page-title"><?php esc_html_e(
       "Page Title",
       "naano-ai-website-builder",
   ); ?></label>
			<input type="text" id="naano-save-page-title" class="naano-input" placeholder="<?php esc_attr_e(
       "Enter a page title&hellip;",
       "naano-ai-website-builder",
   ); ?>" />
			<p class="naano-error-msg" id="naano-save-page-error" style="display:none;"></p>
		</div>
		<label class="naano-checkbox-label" style="margin-bottom:16px;">
			<input type="checkbox" id="naano-set-homepage-chk" />
			<?php esc_html_e("Set as WordPress homepage", "naano-ai-website-builder"); ?>
		</label>
		<div class="naano-modal__actions">
			<button type="button" class="naano-btn-secondary" id="naano-save-page-cancel-btn"><?php esc_html_e(
       "Cancel",
       "naano-ai-website-builder",
   ); ?></button>
			<button type="button" class="naano-btn-generate" id="naano-save-page-confirm-btn" style="width:auto;padding:8px 20px;"><?php esc_html_e(
       "Publish →",
       "naano-ai-website-builder",
   ); ?></button>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
