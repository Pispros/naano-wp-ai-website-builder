<?php
/**
 * Admin Pages List — shows all pages built with Naano AI.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Fetch all pages that have Naano AI sections saved.
// We legitimately need to filter pages by the presence of _naano_sections
// meta — there's no higher-level WP API for this.
$naano_ai_pages = get_posts( [
	'post_type'      => 'page',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'meta_key'       => '_naano_sections',
	'orderby'        => 'modified',
	'order'          => 'DESC',
] );

// Translation languages configured in settings.
$naano_languages      = get_option( 'naano_languages', [] );
if ( ! is_array( $naano_languages ) ) { $naano_languages = []; }
$naano_default_lang_label  = get_option( 'naano_default_lang_label', '' );
$naano_lang_map       = [ 'default' => $naano_default_lang_label !== '' ? $naano_default_lang_label : __( 'Default', 'naano-ai-website-builder' ) ];
foreach ( $naano_languages as $naano_lentry ) {
	if ( ! empty( $naano_lentry['code'] ) ) {
		$naano_lang_map[ $naano_lentry['code'] ] = $naano_lentry['label'] ?? strtoupper( $naano_lentry['code'] );
	}
}

// Maintenance state — shown as a banner above the table when active.
$naano_maint_on   = (bool) get_option( 'naano_maintenance_enabled', '' );
$naano_maint_pid  = (int) get_option( 'naano_maintenance_page_id', 0 );

// Localize for the inline-permalink-editor JS in admin-pages-list.js.
// Done via wp_add_inline_script so the data is available before the
// listener fires, even though the script handle is registered elsewhere.
wp_register_script(
	'naano-admin-pages-list-data',
	false,
	[],
	NAANO_VERSION
);
wp_enqueue_script( 'naano-admin-pages-list-data' );
wp_add_inline_script(
	'naano-admin-pages-list-data',
	'window.naanoPagesListData = ' . wp_json_encode( [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'naano_builder_nonce' ),
		'i18n'    => [
			'saving'      => __( 'Saving…', 'naano-ai-website-builder' ),
			'saved'       => __( 'Permalink saved.', 'naano-ai-website-builder' ),
			'save_failed' => __( 'Save failed.', 'naano-ai-website-builder' ),
		],
	] ) . ';',
	'before'
);
// And enqueue admin-pages-list.js itself (it already exists in the
// plugin's JS folder but wasn't being loaded from this template).
wp_enqueue_script(
	'naano-admin-pages-list',
	NAANO_PLUGIN_URL . 'assets/js/admin-pages-list.js',
	[ 'jquery', 'naano-admin-pages-list-data' ],
	NAANO_VERSION,
	true
);
?>
<style>
/* Inline permalink editor in the AI Pages table. Keeps the slug input
   visually attached to the home URL prefix so the user always sees the
   final URL shape while editing. */
.naano-perma-wrap {
	display:flex;align-items:center;gap:0;font-size:12px;line-height:1.2;
}
.naano-perma-prefix {
	color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
	max-width:160px;
}
.naano-perma-input {
	font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;
	padding:2px 6px;height:24px;line-height:1.2;min-width:0;width:auto;
	flex:1;max-width:180px;
}
.naano-perma-msg.is-success { color:#15803d; }
.naano-perma-msg.is-error   { color:#b91c1c; }
.naano-perma-msg.is-saving  { color:#6b7280;font-style:italic; }
</style>
<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 340 340" aria-hidden="true" focusable="false"><rect x="54" y="54" width="232" height="232" rx="26" ry="26" fill="none" stroke="#2060F0" stroke-width="18"/><line x1="115" y1="54" x2="115" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="54" x2="170" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="54" x2="225" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="115" y1="286" x2="115" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="286" x2="170" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="286" x2="225" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="115" x2="26" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="170" x2="26" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="225" x2="26" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="115" x2="314" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="170" x2="314" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="225" x2="314" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="106" y1="106" x2="106" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="234" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="106" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/></svg>
		<?php esc_html_e( 'Naano AI Builder — Pages', 'naano-ai-website-builder' ); ?>
	</h1>

	<?php
	// Read-only "deleted=1" success flag after the admin-post trash flow.
	// The actual delete is nonce-verified in handle_delete_page(); this is
	// just the post-redirect display.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Page moved to trash.', 'naano-ai-website-builder' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $naano_maint_on ) : ?>
		<div class="notice notice-warning" style="border-left-color:#d63638;">
			<p>
				<strong><?php esc_html_e( '⚠ Maintenance mode is ACTIVE.', 'naano-ai-website-builder' ); ?></strong>
				<?php esc_html_e( 'Visitors are seeing the maintenance page. Admins (you) still see the real site.', 'naano-ai-website-builder' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=naano-site-config' ) ); ?>">
					<?php esc_html_e( 'Manage in Site Configuration →', 'naano-ai-website-builder' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="naano-admin-actions" style="margin-bottom:20px;">
		<a href="<?php echo esc_url( add_query_arg( [ 'naano_builder' => '1', 'naano_new' => '1' ], home_url( '/' ) ) ); ?>"
		   class="button button-primary" target="_blank">
			<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:-2px;"></span>
			<?php esc_html_e( 'Create New Page with AI', 'naano-ai-website-builder' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=naano-site-config' ) ); ?>"
		   class="button" style="margin-left:6px;">
			<span class="dashicons dashicons-admin-site-alt3" style="vertical-align:middle;margin-top:-2px;"></span>
			<?php esc_html_e( 'Site Configuration', 'naano-ai-website-builder' ); ?>
		</a>
	</div>

	<?php if ( empty( $naano_ai_pages ) ) : ?>
		<div class="naano-card" style="padding:30px;text-align:center;">
			<span class="dashicons dashicons-admin-page" style="font-size:48px;width:48px;height:48px;color:#a7aaad;display:block;margin:0 auto 12px;"></span>
			<p style="font-size:15px;color:#50575e;">
				<?php esc_html_e( 'No pages built with Naano AI yet.', 'naano-ai-website-builder' ); ?>
			</p>
		<a href="<?php echo esc_url( add_query_arg( [ 'naano_builder' => '1', 'naano_new' => '1' ], home_url( '/' ) ) ); ?>"
			   class="button button-primary" target="_blank" style="margin-top:10px;">
				<?php esc_html_e( 'Build Your First Page', 'naano-ai-website-builder' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="naano-card">
			<table class="wp-list-table widefat fixed striped pages">
				<thead>
					<tr>
						<th scope="col" class="column-title column-primary"><?php esc_html_e( 'Page', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Permalink', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Language', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sections', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Modified', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'naano-ai-website-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $naano_ai_pages as $page ) :
					$naano_sections      = get_post_meta( $page->ID, '_naano_sections', true );
					$naano_section_count = is_array( $naano_sections ) ? count( $naano_sections ) : 0;
					$naano_builder_url   = add_query_arg( 'naano_builder', '1', get_permalink( $page->ID ) );
					$naano_page_lang     = get_post_meta( $page->ID, '_naano_lang', true );
					$naano_is_translation = (bool) get_post_meta( $page->ID, '_naano_translation_of', true );
					$naano_root_id        = $naano_is_translation ? (int) get_post_meta( $page->ID, '_naano_translation_of', true ) : $page->ID;
					// Sibling translations (only needed on originals). We
					// legitimately need to filter by _naano_translation_of
					// pointing at this page's ID.
					$naano_sibling_translations = $naano_is_translation ? [] : get_posts( [
						'post_type'      => 'page',
						'post_status'    => 'any',
						'posts_per_page' => -1,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_key'       => '_naano_translation_of',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'meta_value'     => $page->ID,
					] );
					// Language codes already translated (to disable those options in the picker).
					$naano_translated_langs = [];
					foreach ( $naano_sibling_translations as $naano_st ) {
						$naano_tl = get_post_meta( $naano_st->ID, '_naano_lang', true );
						if ( $naano_tl ) { $naano_translated_langs[] = $naano_tl; }
					}
					$naano_is_maint = (bool) get_post_meta( $page->ID, '_naano_maintenance', true );
					$naano_slug     = $page->post_name ?: '';
					// home_url() gives us "https://site.tld" — append a trailing slash
					// so the slug field appears next to a properly-terminated prefix.
					$naano_home     = trailingslashit( home_url( '/' ) );
					?>
					<tr data-page-id="<?php echo esc_attr( $page->ID ); ?>">
						<td class="column-title column-primary">
							<strong>
							<a href="<?php echo esc_url( $naano_builder_url ); ?>" target="_blank">
									<?php echo esc_html( $page->post_title ?: __( '(no title)', 'naano-ai-website-builder' ) ); ?>
								</a>
							</strong>
							<?php if ( $naano_is_maint ) : ?>
								<span class="naano-status-badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;margin-left:6px;">
									<span class="dashicons dashicons-warning" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-top:-2px;"></span>
									<?php esc_html_e( 'Maintenance', 'naano-ai-website-builder' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="naano-status-badge naano-status-<?php echo esc_attr( $page->post_status ); ?>">
								<?php echo esc_html( get_post_status_object( $page->post_status )->label ?? $page->post_status ); ?>
							</span>
						</td>
						<!-- Permalink column: inline editor.
						     Submitting saves the slug via AJAX (handle_naano_update_permalink).
						     We display the home URL as a static prefix so the user sees the
						     final URL shape while editing just the slug part. -->
						<td class="naano-perma-cell">
							<div class="naano-perma-wrap" data-page-id="<?php echo esc_attr( $page->ID ); ?>">
								<span class="naano-perma-prefix"><?php echo esc_html( $naano_home ); ?></span><input
									type="text"
									class="naano-perma-input"
									value="<?php echo esc_attr( $naano_slug ); ?>"
									data-original="<?php echo esc_attr( $naano_slug ); ?>"
									aria-label="<?php esc_attr_e( 'Page slug', 'naano-ai-website-builder' ); ?>">
								<button type="button" class="button button-small naano-perma-save" style="display:none;margin-left:4px;">
									<?php esc_html_e( 'Save', 'naano-ai-website-builder' ); ?>
								</button>
								<span class="naano-perma-msg" style="margin-left:6px;font-size:11px;"></span>
							</div>
						</td>
						<!-- Language column -->
					<td>
						<?php if ( $naano_page_lang ) : ?>
							<span class="naano-status-badge naano-status-lang"><?php echo esc_html( strtoupper( $naano_page_lang ) ); ?></span>
						<?php endif; ?>
						<?php if ( $naano_is_translation ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'naano_builder', '1', get_permalink( $naano_root_id ) ) ); ?>"
							   class="naano-lang-orig-link" title="<?php esc_attr_e( 'Go to original page', 'naano-ai-website-builder' ); ?>">
								&larr; <?php esc_html_e( 'Original', 'naano-ai-website-builder' ); ?>
							</a>
						<?php else : ?>
							<?php foreach ( $naano_sibling_translations as $naano_st ) :
								$naano_st_lang = get_post_meta( $naano_st->ID, '_naano_lang', true );
							?>
								<a href="<?php echo esc_url( add_query_arg( 'naano_builder', '1', get_permalink( $naano_st->ID ) ) ); ?>"
								   class="naano-status-badge naano-status-lang naano-lang-badge--link"
								   title="<?php echo esc_attr( $naano_lang_map[ $naano_st_lang ] ?? strtoupper( $naano_st_lang ) ); ?>">
									<?php echo esc_html( strtoupper( $naano_st_lang ) ); ?>
								</a>
							<?php endforeach; ?>
							<?php if ( ! empty( $naano_languages ) ) : ?>
								<button type="button"
										class="button button-small naano-translate-btn"
										data-page-id="<?php echo esc_attr( $page->ID ); ?>"
										style="margin-left:2px;">
									<span class="dashicons dashicons-translation" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
									<?php esc_html_e( 'Translate', 'naano-ai-website-builder' ); ?>
								</button>
								<div class="naano-translate-form" id="naano-translate-form-<?php echo esc_attr( $page->ID ); ?>" style="display:none;margin-top:6px;">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action"  value="naano_duplicate_for_translation">
										<input type="hidden" name="page_id" value="<?php echo esc_attr( $page->ID ); ?>">
										<?php wp_nonce_field( 'naano_duplicate_translation_' . $page->ID ); ?>
										<select name="lang" class="naano-lang-select-admin" style="margin-right:4px;">
											<?php foreach ( $naano_languages as $naano_lentry ) :
												$naano_lcode  = $naano_lentry['code']  ?? '';
												$naano_llabel = $naano_lentry['label'] ?? strtoupper( $naano_lcode );
												$naano_ldone  = in_array( $naano_lcode, $naano_translated_langs, true );
											?>
											<option value="<?php echo esc_attr( $naano_lcode ); ?>" <?php disabled( $naano_ldone ); ?>>
												<?php echo esc_html( $naano_llabel . ' (' . strtoupper( $naano_lcode ) . ')' . ( $naano_ldone ? ' ✓' : '' ) ); ?>
											</option>
											<?php endforeach; ?>
										</select>
										<button type="submit" class="button button-small button-primary">
											<?php esc_html_e( 'Duplicate &amp; Translate', 'naano-ai-website-builder' ); ?>
										</button>
										<button type="button" class="button button-small naano-translate-cancel"
												data-page-id="<?php echo esc_attr( $page->ID ); ?>">
											<?php esc_html_e( 'Cancel', 'naano-ai-website-builder' ); ?>
										</button>
									</form>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</td>						<td><?php echo (int) $naano_section_count; ?></td>
						<td><?php echo esc_html( get_the_modified_date( 'Y-m-d H:i', $page ) ); ?></td>
						<td class="naano-row-actions">
							<a href="<?php echo esc_url( $naano_builder_url ); ?>"
							   class="button button-small" target="_blank">
								<span class="dashicons dashicons-superhero-alt" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
								<?php esc_html_e( 'Open Builder', 'naano-ai-website-builder' ); ?>
							</a>
						<?php if ( $page->post_status === 'publish' ) : ?>
						<a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>"
						   class="button button-small" target="_blank" style="margin-left:4px;">
							<?php esc_html_e( 'View', 'naano-ai-website-builder' ); ?>
						</a>
						<?php
						$naano_home_page_id = (int) get_option( 'page_on_front' );
						if ( get_option( 'show_on_front' ) === 'page' && $naano_home_page_id === $page->ID ) :
						?>
							<span class="naano-status-badge naano-status-homepage" style="margin-left:6px;">
								<?php esc_html_e( 'Homepage', 'naano-ai-website-builder' ); ?>
							</span>
						<?php endif; ?>
						<?php endif; ?>
						<form method="post"
						      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						      style="display:inline;margin-left:4px;"
						      onsubmit="return confirm('<?php echo esc_js( __( 'Move this page to trash?', 'naano-ai-website-builder' ) ); ?>')"
						>
							<input type="hidden" name="action"  value="naano_delete_page">
							<input type="hidden" name="page_id" value="<?php echo esc_attr( $page->ID ); ?>">
							<?php wp_nonce_field( 'naano_delete_page_' . $page->ID ); ?>
							<button type="submit" class="button button-small button-link-delete" style="height:26px;line-height:24px;padding:0 8px;">
								<span class="dashicons dashicons-trash" style="vertical-align:middle;margin-top:-2px;font-size:14px;width:14px;height:14px;"></span>
								<?php esc_html_e( 'Delete', 'naano-ai-website-builder' ); ?>
							</button>
						</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<p style="margin-top:20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=naano-settings' ) ); ?>">
			<?php esc_html_e( '⚙ Settings', 'naano-ai-website-builder' ); ?>
		</a>
	</p>
</div>
