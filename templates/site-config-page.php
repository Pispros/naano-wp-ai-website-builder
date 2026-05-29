<?php
/**
 * Site Configuration page — slogan, favicon, maintenance mode.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$naano_slogan        = (string) get_option( 'naano_site_slogan', '' );
$naano_favicon_url   = (string) get_option( 'naano_site_favicon_url', '' );
$naano_favicon_aid   = (int)    get_option( 'naano_site_favicon_attachment_id', 0 );
$naano_maint_enabled = (bool)   get_option( 'naano_maintenance_enabled', '' );
$naano_maint_pid     = (int)    get_option( 'naano_maintenance_page_id', 0 );

// Build the list of candidate maintenance pages: any page tagged with
// _naano_sections meta (i.e. built by Naano AI). The currently-flagged
// _naano_maintenance page is always included even if it has no sections
// yet, so a brand-new draft created via "Create maintenance page" is
// selectable immediately.
$naano_candidate_pages = get_posts( [
	'post_type'      => 'page',
	'post_status'    => [ 'publish', 'draft', 'private', 'pending' ],
	'posts_per_page' => -1,
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'meta_key'       => '_naano_sections',
	'orderby'        => 'modified',
	'order'          => 'DESC',
] );

// Make sure the configured maintenance page is in the list even if it
// has no sections yet (newly created draft, etc.).
if ( $naano_maint_pid ) {
	$naano_has_pid_in_list = false;
	foreach ( $naano_candidate_pages as $cp ) {
		if ( (int) $cp->ID === $naano_maint_pid ) { $naano_has_pid_in_list = true; break; }
	}
	if ( ! $naano_has_pid_in_list ) {
		$naano_extra = get_post( $naano_maint_pid );
		if ( $naano_extra && $naano_extra->post_status !== 'trash' ) {
			array_unshift( $naano_candidate_pages, $naano_extra );
		}
	}
}

$naano_maint_url     = $naano_maint_pid ? get_permalink( $naano_maint_pid ) : '';
$naano_maint_builder = $naano_maint_pid ? add_query_arg( 'naano_builder', '1', $naano_maint_url ) : '';
$naano_maint_preview = $naano_maint_pid ? add_query_arg( 'naano_maintenance_preview', '1', $naano_maint_url ) : '';
?>
<style>
/* Page-scoped styles. Compact, native-feeling layout that matches the
   rest of the Naano admin look (white card, light borders). */
.naano-sc-card {
	background:#fff;border:1px solid #c3c4c7;padding:18px 22px;margin-bottom:18px;
	border-radius:6px;max-width:920px;
}
.naano-sc-card h2 {
	margin:0 0 6px;font-size:15px;line-height:1.3;
}
.naano-sc-card p.description {
	margin:0 0 14px;color:#50575e;
}
.naano-sc-row { display:flex;gap:14px;align-items:flex-start;margin-bottom:14px;flex-wrap:wrap; }
.naano-sc-row > label { width:160px;flex-shrink:0;font-weight:600;padding-top:6px; }
.naano-sc-row > .naano-sc-field { flex:1;min-width:260px; }
.naano-sc-field input[type="text"],
.naano-sc-field select { width:100%;max-width:480px; }
.naano-sc-favicon-preview {
	width:48px;height:48px;border:1px solid #c3c4c7;border-radius:4px;
	background:#f6f7f7 center/contain no-repeat;display:inline-block;vertical-align:middle;
	margin-right:10px;
}
.naano-sc-favicon-actions { display:inline-flex;gap:6px;align-items:center; }
.naano-sc-status-row {
	display:flex;gap:8px;align-items:center;padding:10px 14px;border-radius:6px;
	font-size:13px;margin-bottom:14px;
}
.naano-sc-status-row.is-off { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }
.naano-sc-status-row.is-on  { background:#fef2f2;border:1px solid #fecaca;color:#991b1b; }
.naano-sc-save-bar {
	display:flex;align-items:center;gap:10px;
	background:#f0f6fc;border:1px solid #c5d9ed;padding:10px 14px;border-radius:6px;
	max-width:920px;
}
#naano-sc-save-msg { font-size:13px; }
#naano-sc-save-msg.is-success { color:#166534; }
#naano-sc-save-msg.is-error   { color:#991b1b; }
</style>

<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<span class="dashicons dashicons-admin-site-alt3" style="font-size:28px;width:28px;height:28px;color:#2060F0;"></span>
		<?php esc_html_e( 'Site Configuration', 'naano-ai-website-builder' ); ?>
	</h1>

	<p class="description" style="max-width:920px;">
		<?php esc_html_e( 'Site identity (slogan, favicon) and maintenance-mode controls. Changes apply to the entire site, not just AI-generated pages.', 'naano-ai-website-builder' ); ?>
	</p>

	<div id="naano-sc-form">

		<!-- ── Identity card ──────────────────────────────────────── -->
		<div class="naano-sc-card">
			<h2><?php esc_html_e( 'Site identity', 'naano-ai-website-builder' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Used in browser tabs, social-share previews, and AI prompts when generating new pages.', 'naano-ai-website-builder' ); ?></p>

			<div class="naano-sc-row">
				<label for="naano-sc-slogan"><?php esc_html_e( 'Slogan / tagline', 'naano-ai-website-builder' ); ?></label>
				<div class="naano-sc-field">
					<input type="text" id="naano-sc-slogan"
						value="<?php echo esc_attr( $naano_slogan ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. AI-powered websites in minutes', 'naano-ai-website-builder' ); ?>">
					<p class="description" style="margin-top:4px;">
						<?php esc_html_e( 'A short phrase describing what your site is about.', 'naano-ai-website-builder' ); ?>
					</p>
				</div>
			</div>

			<div class="naano-sc-row">
				<label><?php esc_html_e( 'Favicon', 'naano-ai-website-builder' ); ?></label>
				<div class="naano-sc-field">
					<span class="naano-sc-favicon-preview" id="naano-sc-favicon-preview"
						<?php if ( $naano_favicon_url ) : ?>style="background-image:url('<?php echo esc_url( $naano_favicon_url ); ?>');"<?php endif; ?>></span>
					<span class="naano-sc-favicon-actions">
						<button type="button" class="button" id="naano-sc-favicon-pick">
							<span class="dashicons dashicons-format-image" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
							<?php esc_html_e( 'Pick from media library', 'naano-ai-website-builder' ); ?>
						</button>
						<button type="button" class="button-link" id="naano-sc-favicon-clear"
							<?php if ( ! $naano_favicon_url ) : ?>style="display:none;"<?php endif; ?>>
							<?php esc_html_e( 'Remove', 'naano-ai-website-builder' ); ?>
						</button>
					</span>
					<input type="text" id="naano-sc-favicon-url" style="margin-top:6px;"
						value="<?php echo esc_attr( $naano_favicon_url ); ?>"
						placeholder="https://…">
					<input type="hidden" id="naano-sc-favicon-aid" value="<?php echo esc_attr( $naano_favicon_aid ); ?>">
					<p class="description" style="margin-top:4px;">
						<?php esc_html_e( 'PNG, ICO, or SVG. 32×32 or larger recommended. Paste a URL or pick from your media library.', 'naano-ai-website-builder' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- ── Maintenance mode card ──────────────────────────────── -->
		<div class="naano-sc-card">
			<h2><?php esc_html_e( 'Maintenance mode', 'naano-ai-website-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'When enabled, every visitor sees the maintenance page instead of your normal content. Admins still see the real site (so you can keep working on it).', 'naano-ai-website-builder' ); ?>
			</p>

			<div class="naano-sc-status-row <?php echo $naano_maint_enabled ? 'is-on' : 'is-off'; ?>" id="naano-sc-status-row">
				<span class="dashicons dashicons-<?php echo $naano_maint_enabled ? 'warning' : 'yes-alt'; ?>"></span>
				<span id="naano-sc-status-text">
					<?php echo $naano_maint_enabled
						? esc_html__( 'Maintenance mode is ON — visitors see the maintenance page.', 'naano-ai-website-builder' )
						: esc_html__( 'Maintenance mode is OFF — your site is live.', 'naano-ai-website-builder' );
					?>
				</span>
			</div>

			<div class="naano-sc-row">
				<label for="naano-sc-maint-toggle"><?php esc_html_e( 'Enable', 'naano-ai-website-builder' ); ?></label>
				<div class="naano-sc-field">
					<label style="display:inline-flex;gap:8px;align-items:center;cursor:pointer;font-weight:400;">
						<input type="checkbox" id="naano-sc-maint-toggle" <?php checked( $naano_maint_enabled ); ?>>
						<?php esc_html_e( 'Turn maintenance mode ON', 'naano-ai-website-builder' ); ?>
					</label>
					<p class="description" style="margin-top:4px;">
						<?php esc_html_e( 'Visitors get an HTTP 503 status while maintenance is on so search engines don\'t index the placeholder as your real content.', 'naano-ai-website-builder' ); ?>
					</p>
				</div>
			</div>

			<div class="naano-sc-row">
				<label for="naano-sc-maint-page"><?php esc_html_e( 'Maintenance page', 'naano-ai-website-builder' ); ?></label>
				<div class="naano-sc-field">
					<select id="naano-sc-maint-page">
						<option value="0">— <?php esc_html_e( 'pick a page', 'naano-ai-website-builder' ); ?> —</option>
						<?php foreach ( $naano_candidate_pages as $cp ) :
							$cp_lang = get_post_meta( $cp->ID, '_naano_lang', true );
							$cp_label = ( $cp->post_title ?: __( '(no title)', 'naano-ai-website-builder' ) )
								. ' — ' . esc_html( get_post_status_object( $cp->post_status )->label ?? $cp->post_status )
								. ( $cp_lang ? ' (' . strtoupper( $cp_lang ) . ')' : '' );
						?>
							<option value="<?php echo esc_attr( $cp->ID ); ?>" <?php selected( $naano_maint_pid, $cp->ID ); ?>>
								<?php echo esc_html( $cp_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description" style="margin-top:4px;">
						<?php esc_html_e( 'Any AI-built page works. You can also generate a dedicated one:', 'naano-ai-website-builder' ); ?>
					</p>

					<div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="naano_create_maintenance_page">
							<?php wp_nonce_field( 'naano_create_maintenance_page' ); ?>
							<button type="submit" class="button">
								<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
								<?php esc_html_e( 'Create / open maintenance page with AI', 'naano-ai-website-builder' ); ?>
							</button>
						</form>

						<?php if ( $naano_maint_pid ) : ?>
							<a href="<?php echo esc_url( $naano_maint_builder ); ?>" class="button" target="_blank">
								<span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
								<?php esc_html_e( 'Edit current maintenance page', 'naano-ai-website-builder' ); ?>
							</a>
							<a href="<?php echo esc_url( $naano_maint_preview ); ?>" class="button" target="_blank">
								<span class="dashicons dashicons-visibility" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
								<?php esc_html_e( 'Preview', 'naano-ai-website-builder' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- ── Save bar ───────────────────────────────────────────── -->
		<div class="naano-sc-save-bar">
			<button type="button" class="button button-primary" id="naano-sc-save-btn">
				<?php esc_html_e( 'Save configuration', 'naano-ai-website-builder' ); ?>
			</button>
			<span id="naano-sc-save-msg"></span>
		</div>

	</div>
</div>
