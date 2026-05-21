<?php
/**
 * Section Card Template
 *
 * Expected variables:
 *   $section_id   (string) – section identifier
 *   $section_html (string) – raw HTML for this section
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Derive a display name from the ID.
$naano_display_name = ucwords( str_replace( [ '-', '_' ], ' ', $section_id ) );

// Count references if we have page context.
$naano_screenshot_count = 0;
$naano_url_count        = 0;
if ( isset( $page_id ) && $page_id ) {
	$naano_rm               = new Naano_Reference_Manager();
	$naano_refs             = $naano_rm->get_references( (int) $page_id, $section_id );
	$naano_screenshot_count = count( array_filter( $naano_refs, static fn( $r ) => ( $r['type'] ?? '' ) === 'screenshot' ) );
	$naano_url_count        = count( array_filter( $naano_refs, static fn( $r ) => ( $r['type'] ?? '' ) === 'url' ) );
}
?>
<div class="naano-section-card"
	 id="naano-card-<?php echo esc_attr( $section_id ); ?>"
	 data-section-id="<?php echo esc_attr( $section_id ); ?>"
	 draggable="true">

	<div class="naano-section-card__header">
		<span class="naano-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'naano-ai-website-builder' ); ?>">⠿</span>
		<span class="naano-section-card__name"><?php echo esc_html( $naano_display_name ); ?></span>

		<div class="naano-section-card__badges">
			<?php if ( $naano_screenshot_count > 0 ) : ?>
			<span class="naano-badge naano-badge--screenshots" title="<?php esc_attr_e( 'Screenshot references', 'naano-ai-website-builder' ); ?>">
				🖼️ <?php echo esc_html( $naano_screenshot_count ); ?>
			</span>
			<?php endif; ?>
			<?php if ( $naano_url_count > 0 ) : ?>
			<span class="naano-badge naano-badge--urls" title="<?php esc_attr_e( 'URL references', 'naano-ai-website-builder' ); ?>">
				🔗 <?php echo esc_html( $naano_url_count ); ?>
			</span>
			<?php endif; ?>
		</div>

		<div class="naano-section-card__actions">
			<button type="button"
					class="naano-btn-icon naano-btn-edit"
					data-section-id="<?php echo esc_attr( $section_id ); ?>"
					title="<?php esc_attr_e( 'Edit section', 'naano-ai-website-builder' ); ?>">
				✏️
			</button>
			<button type="button"
					class="naano-btn-icon naano-btn-screenshot"
					data-section-id="<?php echo esc_attr( $section_id ); ?>"
					title="<?php esc_attr_e( 'Add screenshot reference', 'naano-ai-website-builder' ); ?>">
				🖼️
			</button>
			<button type="button"
					class="naano-btn-icon naano-btn-url-ref"
					data-section-id="<?php echo esc_attr( $section_id ); ?>"
					title="<?php esc_attr_e( 'Add URL reference', 'naano-ai-website-builder' ); ?>">
				🔗
			</button>
			<button type="button"
					class="naano-btn-icon naano-btn-delete"
					data-section-id="<?php echo esc_attr( $section_id ); ?>"
					title="<?php esc_attr_e( 'Delete section', 'naano-ai-website-builder' ); ?>">
				🗑️
			</button>
		</div>
	</div>

	<div class="naano-section-card__preview">
		<iframe
			class="naano-section-iframe"
			srcdoc="<?php echo esc_attr( $section_html ); ?>"
			sandbox="allow-same-origin"
			loading="lazy"
			title="<?php
				/* translators: %s: human-readable section name (e.g. "Hero", "Pricing") */
				echo esc_attr( sprintf( __( 'Preview of %s section', 'naano-ai-website-builder' ), $naano_display_name ) );
			?>">
		</iframe>
	</div>

</div>
