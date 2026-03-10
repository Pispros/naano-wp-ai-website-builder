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
$ai_pages = get_posts( [
	'post_type'      => 'page',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'meta_key'       => '_naano_sections',
	'orderby'        => 'modified',
	'order'          => 'DESC',
] );

// Translation languages configured in settings.
$naano_languages      = get_option( 'naano_languages', [] );
if ( ! is_array( $naano_languages ) ) { $naano_languages = []; }
$_default_lang_label  = get_option( 'naano_default_lang_label', '' );
$naano_lang_map       = [ 'default' => $_default_lang_label !== '' ? $_default_lang_label : __( 'Default', 'naano-ai-website-builder' ) ];
foreach ( $naano_languages as $lentry ) {
	if ( ! empty( $lentry['code'] ) ) {
		$naano_lang_map[ $lentry['code'] ] = $lentry['label'] ?? strtoupper( $lentry['code'] );
	}
}
?>
<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 340 340" aria-hidden="true" focusable="false"><rect x="54" y="54" width="232" height="232" rx="26" ry="26" fill="none" stroke="#2060F0" stroke-width="18"/><line x1="115" y1="54" x2="115" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="54" x2="170" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="54" x2="225" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="115" y1="286" x2="115" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="286" x2="170" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="286" x2="225" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="115" x2="26" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="170" x2="26" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="225" x2="26" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="115" x2="314" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="170" x2="314" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="225" x2="314" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="106" y1="106" x2="106" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="234" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="106" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/></svg>
		<?php esc_html_e( 'Naano AI Builder — Pages', 'naano-ai-website-builder' ); ?>
	</h1>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Page moved to trash.', 'naano-ai-website-builder' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="naano-admin-actions" style="margin-bottom:20px;">
		<a href="<?php echo esc_url( add_query_arg( [ 'naano_builder' => '1', 'naano_new' => '1' ], home_url( '/' ) ) ); ?>"
		   class="button button-primary" target="_blank">
			<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:-2px;"></span>
			<?php esc_html_e( 'Create New Page with AI', 'naano-ai-website-builder' ); ?>
		</a>
	</div>

	<?php if ( empty( $ai_pages ) ) : ?>
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
						<th scope="col"><?php esc_html_e( 'Language', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sections', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Modified', 'naano-ai-website-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'naano-ai-website-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ai_pages as $page ) :
					$sections      = get_post_meta( $page->ID, '_naano_sections', true );
					$section_count = is_array( $sections ) ? count( $sections ) : 0;
					$builder_url   = add_query_arg( 'naano_builder', '1', get_permalink( $page->ID ) );
					$page_lang     = get_post_meta( $page->ID, '_naano_lang', true );
					$is_translation = (bool) get_post_meta( $page->ID, '_naano_translation_of', true );
					$root_id        = $is_translation ? (int) get_post_meta( $page->ID, '_naano_translation_of', true ) : $page->ID;
					// Sibling translations (only needed on originals).
					$sibling_translations = $is_translation ? [] : get_posts( [
						'post_type'      => 'page',
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'meta_key'       => '_naano_translation_of',
						'meta_value'     => $page->ID,
					] );
					// Language codes already translated (to disable those options in the picker).
					$translated_langs = [];
					foreach ( $sibling_translations as $st ) {
						$tl = get_post_meta( $st->ID, '_naano_lang', true );
						if ( $tl ) { $translated_langs[] = $tl; }
					}
					?>
					<tr>
						<td class="column-title column-primary">
							<strong>
							<a href="<?php echo esc_url( $builder_url ); ?>" target="_blank">
									<?php echo esc_html( $page->post_title ?: __( '(no title)', 'naano-ai-website-builder' ) ); ?>
								</a>
							</strong>
						</td>
						<td>
							<span class="naano-status-badge naano-status-<?php echo esc_attr( $page->post_status ); ?>">
								<?php echo esc_html( get_post_status_object( $page->post_status )->label ?? $page->post_status ); ?>
							</span>
						</td>					<!-- Language column -->
					<td>
						<?php if ( $page_lang ) : ?>
							<span class="naano-status-badge naano-status-lang"><?php echo esc_html( strtoupper( $page_lang ) ); ?></span>
						<?php endif; ?>
						<?php if ( $is_translation ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'naano_builder', '1', get_permalink( $root_id ) ) ); ?>"
							   class="naano-lang-orig-link" title="<?php esc_attr_e( 'Go to original page', 'naano-ai-website-builder' ); ?>">
								&larr; <?php esc_html_e( 'Original', 'naano-ai-website-builder' ); ?>
							</a>
						<?php else : ?>
							<?php foreach ( $sibling_translations as $st ) :
								$st_lang = get_post_meta( $st->ID, '_naano_lang', true );
							?>
								<a href="<?php echo esc_url( add_query_arg( 'naano_builder', '1', get_permalink( $st->ID ) ) ); ?>"
								   class="naano-status-badge naano-status-lang naano-lang-badge--link"
								   title="<?php echo esc_attr( $naano_lang_map[ $st_lang ] ?? strtoupper( $st_lang ) ); ?>">
									<?php echo esc_html( strtoupper( $st_lang ) ); ?>
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
											<?php foreach ( $naano_languages as $lentry ) :
												$lcode  = $lentry['code']  ?? '';
												$llabel = $lentry['label'] ?? strtoupper( $lcode );
												$ldone  = in_array( $lcode, $translated_langs, true );
											?>
											<option value="<?php echo esc_attr( $lcode ); ?>" <?php disabled( $ldone ); ?>>
												<?php echo esc_html( $llabel . ' (' . strtoupper( $lcode ) . ')' . ( $ldone ? ' ✓' : '' ) ); ?>
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
					</td>						<td><?php echo (int) $section_count; ?></td>
						<td><?php echo esc_html( get_the_modified_date( 'Y-m-d H:i', $page ) ); ?></td>
						<td class="naano-row-actions">
							<a href="<?php echo esc_url( $builder_url ); ?>"
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
						$home_page_id = (int) get_option( 'page_on_front' );
						if ( get_option( 'show_on_front' ) === 'page' && $home_page_id === $page->ID ) :
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

<style>
.naano-status-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: .5px;
}
.naano-status-publish  { background: #d1fae5; color: #065f46; }
.naano-status-draft    { background: #fef3c7; color: #92400e; }
.naano-status-pending  { background: #dbeafe; color: #1e40af; }
.naano-status-private  { background: #ede9fe; color: #5b21b6; }
.naano-status-homepage { background: #fce7f3; color: #9d174d; }
.naano-status-lang     { background: #dbeafe; color: #1e3a8a; }
.naano-lang-badge--link{ text-decoration: none; }
.naano-lang-badge--link:hover { opacity: .85; }
.naano-lang-orig-link  { font-size: 11px; color: #2271b1; text-decoration: none; white-space: nowrap; }
.naano-lang-orig-link:hover { text-decoration: underline; }
.naano-translate-form select.naano-lang-select-admin { height: 28px; }
.naano-row-actions     { white-space: normal; }
.naano-row-actions .button,
.naano-row-actions form { display: inline-block; margin-bottom: 3px; }
</style>

<script>
jQuery(function($){
	// Show translate form on "Translate" button click.
	$(document).on('click', '.naano-translate-btn', function(){
		var id = $(this).data('page-id');
		$('#naano-translate-form-' + id).slideToggle(150);
	});
	// Hide translate form on "Cancel" click.
	$(document).on('click', '.naano-translate-cancel', function(){
		var id = $(this).data('page-id');
		$('#naano-translate-form-' + id).slideUp(150);
	});
});
</script>
