<?php
/**
 * Settings Page Template
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider   = get_option( 'naano_provider', 'claude' );
$api_key    = get_option( 'naano_api_key', '' );
$model      = get_option( 'naano_model', '' );
$variables  = get_option( 'naano_variables', [] );
if ( ! is_array( $variables ) ) {
	$variables = [];
}
$languages            = get_option( 'naano_languages', [] );
if ( ! is_array( $languages ) ) {
	$languages = [];
}
$custom_prompt        = get_option( 'naano_custom_prompt', '' );
$default_lang_label   = get_option( 'naano_default_lang_label', '' );
$initial_refine       = get_option( 'naano_initial_refinement_passes', 1 );
$update_refine        = get_option( 'naano_update_refinement_passes', 1 );
?>
<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 340 340" aria-hidden="true" focusable="false"><rect x="54" y="54" width="232" height="232" rx="26" ry="26" fill="none" stroke="#2060F0" stroke-width="18"/><line x1="115" y1="54" x2="115" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="54" x2="170" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="54" x2="225" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="115" y1="286" x2="115" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="286" x2="170" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="286" x2="225" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="115" x2="26" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="170" x2="26" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="225" x2="26" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="115" x2="314" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="170" x2="314" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="225" x2="314" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="106" y1="106" x2="106" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="234" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="106" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/></svg>
		<?php esc_html_e( 'Naano AI Builder — Settings', 'naano-ai-website-builder' ); ?>
	</h1>

	<?php settings_errors(); ?>

	<nav class="nav-tab-wrapper naano-settings-tab-nav" style="margin-bottom:0;border-bottom:1px solid #c3c4c7;">
		<button type="button" class="nav-tab nav-tab-active" data-naano-tab="llm"><?php esc_html_e( 'LLM Provider', 'naano-ai-website-builder' ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="variables"><?php esc_html_e( 'Prompt Global Variables', 'naano-ai-website-builder' ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="global"><?php esc_html_e( 'Global Configurations', 'naano-ai-website-builder' ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="firecrawl"><?php esc_html_e( 'References / Website Scraping', 'naano-ai-website-builder' ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="translation"><?php esc_html_e( 'Translation', 'naano-ai-website-builder' ); ?></button>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'naano_settings_group' ); ?>

		<!-- ============================================================
		     LLM PROVIDER
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-llm">
		<div class="naano-card">
			<h2><?php esc_html_e( 'LLM Provider', 'naano-ai-website-builder' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano_provider"><?php esc_html_e( 'Provider', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<select name="naano_provider" id="naano_provider">
							<option value="claude"  <?php selected( $provider, 'claude' ); ?>>Claude (Anthropic)</option>
							<option value="gemini"  <?php selected( $provider, 'gemini' ); ?>>Gemini (Google)</option>
							<option value="kimi"    <?php selected( $provider, 'kimi' ); ?>>Kimi (Moonshot)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano_api_key"><?php esc_html_e( 'API Key', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<input type="password"
							   name="naano_api_key"
							   id="naano_api_key"
							   class="regular-text"
							   value="<?php echo esc_attr( $api_key ); ?>"
							   autocomplete="new-password">
						<button type="button" class="button" id="naano-save-api-key-btn" style="margin-left:8px;">
							<?php esc_html_e( 'Save Key', 'naano-ai-website-builder' ); ?>
						</button>
						<span class="naano-loading" id="naano-save-key-loading" style="display:none;">
							<span class="spinner is-active"></span>
						</span>
						<span id="naano-save-key-result" style="display:none;margin-left:8px;"></span>
						<p class="description">
							<?php esc_html_e( 'Your API key is stored in the WordPress options table. Use a read-only key when possible.', 'naano-ai-website-builder' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano_model"><?php esc_html_e( 'Model Override', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<input type="text"
							   name="naano_model"
							   id="naano_model"
							   class="regular-text"
							   value="<?php echo esc_attr( $model ); ?>"
							   placeholder="<?php esc_attr_e( 'Leave blank for default model', 'naano-ai-website-builder' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Defaults: Claude → claude-sonnet-4-20250514 | Gemini → gemini-2.5-flash | Kimi → kimi-k2-0711-preview', 'naano-ai-website-builder' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="naano-test-connection-row">
				<button type="button" class="button" id="naano-test-connection-btn">
					<?php esc_html_e( 'Test Connection', 'naano-ai-website-builder' ); ?>
				</button>
				<span class="naano-loading" id="naano-test-loading" style="display:none;">
					<span class="spinner is-active"></span>
				</span>
				<div id="naano-test-result" class="naano-test-result" style="display:none;"></div>
			</div>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#llm -->

		<!-- ============================================================
		     GLOBAL CONFIG
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-global" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e( 'Global Configuration', 'naano-ai-website-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Control how many self-review refinement passes the LLM runs after generation. More passes improve quality but take longer.', 'naano-ai-website-builder' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano_initial_refinement_passes"><?php esc_html_e( 'Initial Generation Refinements', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<input type="number"
							   name="naano_initial_refinement_passes"
							   id="naano_initial_refinement_passes"
							   class="small-text"
							   value="<?php echo esc_attr( $initial_refine ); ?>"
							   min="0"
							   max="10">
						<p class="description">
							<?php esc_html_e( 'Number of refinement passes when generating a new site. Each pass adds one extra LLM call per section. (Default: 1)', 'naano-ai-website-builder' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano_update_refinement_passes"><?php esc_html_e( 'Section Update Refinements', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<input type="number"
							   name="naano_update_refinement_passes"
							   id="naano_update_refinement_passes"
							   class="small-text"
							   value="<?php echo esc_attr( $update_refine ); ?>"
							   min="0"
							   max="10">
						<p class="description">
							<?php esc_html_e( 'Number of refinement passes when updating an existing section. Each pass adds one extra LLM call. (Default: 1)', 'naano-ai-website-builder' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div style="margin-top:10px;">
				<button type="button" class="button button-primary" id="naano-save-global-config-btn">
					<?php esc_html_e( 'Save Global Config', 'naano-ai-website-builder' ); ?>
				</button>
				<span class="naano-loading" id="naano-save-global-loading" style="display:none;">
					<span class="spinner is-active"></span>
				</span>
				<span id="naano-save-global-result" style="display:none;margin-left:8px;"></span>
			</div>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#global -->

		<!-- ============================================================
		     DESIGN VARIABLES
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-variables" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e( 'Custom Design Variables', 'naano-ai-website-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'These variables are injected into every AI prompt to ensure brand consistency.', 'naano-ai-website-builder' ); ?>
				<?php esc_html_e( 'Examples: primary_color, secondary_color, brand_name, font_family, tone, industry, target_audience.', 'naano-ai-website-builder' ); ?>
			</p>

			<table class="form-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variable Name', 'naano-ai-website-builder' ); ?></th>
						<th><?php esc_html_e( 'Value', 'naano-ai-website-builder' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="naano-variables-tbody">
					<?php foreach ( $variables as $key => $val ) : ?>
					<tr class="naano-variable-row">
						<td>
							<input type="text"
								   name="naano_vars_keys[]"
								   class="regular-text"
								   value="<?php echo esc_attr( $key ); ?>"
								   placeholder="<?php esc_attr_e( 'e.g. primary_color', 'naano-ai-website-builder' ); ?>">
						</td>
						<td>
							<input type="text"
								   name="naano_vars_values[]"
								   class="regular-text"
								   value="<?php echo esc_attr( $val ); ?>"
								   placeholder="<?php esc_attr_e( 'e.g. #3B82F6', 'naano-ai-website-builder' ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-variable">
								<?php esc_html_e( 'Remove', 'naano-ai-website-builder' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $variables ) ) : ?>
					<tr class="naano-variable-row">
						<td>
							<input type="text" name="naano_vars_keys[]" class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g. primary_color', 'naano-ai-website-builder' ); ?>">
						</td>
						<td>
							<input type="text" name="naano_vars_values[]" class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g. #3B82F6', 'naano-ai-website-builder' ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-variable">
								<?php esc_html_e( 'Remove', 'naano-ai-website-builder' ); ?>
							</button>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<button type="button" class="button" id="naano-add-variable-btn">
				+ <?php esc_html_e( 'Add Variable', 'naano-ai-website-builder' ); ?>
			</button>
		</div><!-- /.naano-card -->

	<div class="naano-card" style="margin-top:20px;">
		<h2><?php esc_html_e( 'Custom System Prompt', 'naano-ai-website-builder' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Additional instructions appended to every system prompt. Use this to enforce brand tone, content rules, or any site-specific constraints.', 'naano-ai-website-builder' ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="naano_custom_prompt"><?php esc_html_e( 'Additional Instructions', 'naano-ai-website-builder' ); ?></label>
				</th>
				<td>
					<textarea
						name="naano_custom_prompt"
						id="naano_custom_prompt"
						class="large-text"
						rows="6"
						placeholder="<?php esc_attr_e( 'e.g. Always write copy in a friendly, conversational tone. Avoid formal language. The brand voice is warm and approachable.', 'naano-ai-website-builder' ); ?>"><?php echo esc_textarea( $custom_prompt ); ?></textarea>
					<p class="description"><?php esc_html_e( 'These instructions are appended to the system prompt for every LLM call. You can also modify the full assembled prompt programmatically via the naano_system_prompt WordPress filter.', 'naano-ai-website-builder' ); ?></p>
				</td>
			</tr>
		</table>
	</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#variables -->

		<!-- ============================================================
		     FIRECRAWL
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-firecrawl" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e( 'Firecrawl — Web Scraping', 'naano-ai-website-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Firecrawl turns any website URL into clean, structured HTML that the AI can actually read.', 'naano-ai-website-builder' ); ?>
				<?php esc_html_e( 'When you add a URL reference to a section, the built-in scraper only grabs basic text. Firecrawl renders JavaScript, handles SPAs, and returns rich HTML — giving the AI much better context about layouts, components, and design patterns from the reference site.', 'naano-ai-website-builder' ); ?>
			</p>
			<p class="description" style="margin-top:8px;">
				<?php
				printf(
					/* translators: %s = link to firecrawl.dev */
					esc_html__( 'Get your free API key at %s (500 credits/month on the free plan).', 'naano-ai-website-builder' ),
					'<a href="https://www.firecrawl.dev" target="_blank" rel="noopener">firecrawl.dev</a>'
				);
				?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano_firecrawl_api_key"><?php esc_html_e( 'Firecrawl API Key', 'naano-ai-website-builder' ); ?></label>
					</th>
					<td>
						<input type="password"
							   name="naano_firecrawl_api_key"
							   id="naano_firecrawl_api_key"
							   class="regular-text"
							   value="<?php echo esc_attr( get_option( 'naano_firecrawl_api_key', '' ) ); ?>"
							   autocomplete="new-password">
						<button type="button" class="button" id="naano-save-firecrawl-key-btn" style="margin-left:8px;">
							<?php esc_html_e( 'Save Key', 'naano-ai-website-builder' ); ?>
						</button>
						<span class="naano-loading" id="naano-save-firecrawl-loading" style="display:none;">
							<span class="spinner is-active"></span>
						</span>
						<span id="naano-save-firecrawl-result" style="display:none;margin-left:8px;"></span>
						<p class="description">
							<?php esc_html_e( 'When set, URL references will be scraped with Firecrawl instead of the basic built-in fetcher. Results are cached so the same URL is never scraped twice.', 'naano-ai-website-builder' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="naano-test-connection-row">
				<button type="button" class="button" id="naano-test-firecrawl-btn">
					<?php esc_html_e( 'Test Connection', 'naano-ai-website-builder' ); ?>
				</button>
				<span class="naano-loading" id="naano-test-firecrawl-loading" style="display:none;">
					<span class="spinner is-active"></span>
				</span>
				<div id="naano-test-firecrawl-result" class="naano-test-result" style="display:none;"></div>
			</div>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#firecrawl -->

		<!-- ============================================================
		     TRANSLATION
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-translation" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e( 'Translation Languages', 'naano-ai-website-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Define the languages you want to translate your pages into. Each language gets its own URL variant (e.g. /my-page/es/).', 'naano-ai-website-builder' ); ?>
			</p>

			<table class="form-table" style="margin-bottom:16px;">
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Language Label', 'naano-ai-website-builder' ); ?></th>
					<td>
						<input type="text"
							   name="naano_default_lang_label"
							   id="naano_default_lang_label"
							   class="regular-text"
							   value="<?php echo esc_attr( $default_lang_label ); ?>"
							   placeholder="<?php esc_attr_e( 'e.g. English', 'naano-ai-website-builder' ); ?>">
						<p class="description"><?php esc_html_e( 'Label shown for the original (default) language in language switchers. Leave blank to show &ldquo;Default&rdquo;.', 'naano-ai-website-builder' ); ?></p>
					</td>
				</tr>
			</table>

			<table class="form-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Language Code', 'naano-ai-website-builder' ); ?></th>
						<th><?php esc_html_e( 'Language Label', 'naano-ai-website-builder' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="naano-languages-tbody">
					<?php foreach ( $languages as $lang ) : ?>
					<tr class="naano-language-row">
						<td>
							<input type="text"
								   name="naano_lang_codes[]"
								   class="regular-text"
								   value="<?php echo esc_attr( $lang['code'] ?? '' ); ?>"
								   placeholder="<?php esc_attr_e( 'e.g. es', 'naano-ai-website-builder' ); ?>"
								   style="max-width:100px;">
						</td>
						<td>
							<input type="text"
								   name="naano_lang_labels[]"
								   class="regular-text"
								   value="<?php echo esc_attr( $lang['label'] ?? '' ); ?>"
								   placeholder="<?php esc_attr_e( 'e.g. Spanish', 'naano-ai-website-builder' ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-language">
								<?php esc_html_e( 'Remove', 'naano-ai-website-builder' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $languages ) ) : ?>
					<tr class="naano-language-row">
						<td>
							<input type="text" name="naano_lang_codes[]" class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g. es', 'naano-ai-website-builder' ); ?>" style="max-width:100px;">
						</td>
						<td>
							<input type="text" name="naano_lang_labels[]" class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g. Spanish', 'naano-ai-website-builder' ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-language">
								<?php esc_html_e( 'Remove', 'naano-ai-website-builder' ); ?>
							</button>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<button type="button" class="button" id="naano-add-language-btn">
				+ <?php esc_html_e( 'Add Language', 'naano-ai-website-builder' ); ?>
			</button>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#translation -->

		<?php submit_button( __( 'Save Settings', 'naano-ai-website-builder' ) ); ?>
	</form>
</div>

<script>
jQuery(function($){	// Settings tab switching.
	var $tabs   = $('.naano-settings-tab-nav .nav-tab');
	var $panels = $('.naano-settings-panel');
	function switchTab(tab) {
		$tabs.removeClass('nav-tab-active');
		$tabs.filter('[data-naano-tab="' + tab + '"]').addClass('nav-tab-active');
		$panels.hide();
		$('#naano-panel-' + tab).show();
		try { localStorage.setItem('naano_settings_tab', tab); } catch(e) {}
	}
	$tabs.on('click', function(){ switchTab($(this).data('naano-tab')); });
	try {
		var savedTab = localStorage.getItem('naano_settings_tab');
		if (savedTab && $('#naano-panel-' + savedTab).length) { switchTab(savedTab); }
	} catch(e) {}
	// Add variable row.
	$('#naano-add-variable-btn').on('click', function(){
		var row = '<tr class="naano-variable-row">' +
			'<td><input type="text" name="naano_vars_keys[]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. primary_color', 'naano-ai-website-builder' ) ); ?>"></td>' +
			'<td><input type="text" name="naano_vars_values[]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. #3B82F6', 'naano-ai-website-builder' ) ); ?>"></td>' +
			'<td><button type="button" class="button naano-remove-variable"><?php echo esc_js( __( 'Remove', 'naano-ai-website-builder' ) ); ?></button></td>' +
			'</tr>';
		$('#naano-variables-tbody').append(row);
	});

	// Remove variable row.
	$(document).on('click', '.naano-remove-variable', function(){
		$(this).closest('tr').remove();
	});

	// Add language row.
	$('#naano-add-language-btn').on('click', function(){
		var row = '<tr class="naano-language-row">' +
			'<td><input type="text" name="naano_lang_codes[]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. es', 'naano-ai-website-builder' ) ); ?>" style="max-width:100px;"></td>' +
			'<td><input type="text" name="naano_lang_labels[]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. Spanish', 'naano-ai-website-builder' ) ); ?>"></td>' +
			'<td><button type="button" class="button naano-remove-language"><?php echo esc_js( __( 'Remove', 'naano-ai-website-builder' ) ); ?></button></td>' +
			'</tr>';
		$('#naano-languages-tbody').append(row);
	});

	// Remove language row.
	$(document).on('click', '.naano-remove-language', function(){
		$(this).closest('tr').remove();
	});

	// Save global config.
	$('#naano-save-global-config-btn').on('click', function(){
		var $btn    = $(this);
		var $load   = $('#naano-save-global-loading');
		var $result = $('#naano-save-global-result');

		$btn.prop('disabled', true);
		$load.show();
		$result.hide();

		$.post(
			<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			{
				action:                     'naano_save_global_config',
				nonce:                      <?php echo wp_json_encode( wp_create_nonce( 'naano_builder_nonce' ) ); ?>,
				initial_refinement_passes:  $('#naano_initial_refinement_passes').val(),
				update_refinement_passes:   $('#naano_update_refinement_passes').val()
			},
			function(response){
				$load.hide();
				$btn.prop('disabled', false);
				$result.show();
				if(response.success){
					$result.html('<span class="naano-success">✅ ' + response.data.message + '</span>');
				} else {
					$result.html('<span class="naano-error">❌ ' + response.data.message + '</span>');
				}
			}
		).fail(function(){
			$load.hide();
			$btn.prop('disabled', false);
			$result.show().html('<span class="naano-error">❌ <?php echo esc_js( __( 'Request failed.', 'naano-ai-website-builder' ) ); ?></span>');
		});
	});

	// Save API key.
	$('#naano-save-api-key-btn').on('click', function(){
		var $btn    = $(this);
		var $load   = $('#naano-save-key-loading');
		var $result = $('#naano-save-key-result');
		var apiKey  = $('#naano_api_key').val();

		if ( ! apiKey ) {
			$result.show().html('<span class="naano-error">⚠️ <?php echo esc_js( __( 'Please enter an API key.', 'naano-ai-website-builder' ) ); ?></span>');
			$('#naano_api_key').focus();
			return;
		}

		$btn.prop('disabled', true);
		$load.show();
		$result.hide();

		$.post(
			<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			{
				action:  'naano_save_api_key',
				nonce:   <?php echo wp_json_encode( wp_create_nonce( 'naano_builder_nonce' ) ); ?>,
				api_key: apiKey
			},
			function(response){
				$load.hide();
				$btn.prop('disabled', false);
				$result.show();
				if(response.success){
					$result.html('<span class="naano-success">✅ ' + response.data.message + '</span>');
				} else {
					$result.html('<span class="naano-error">❌ ' + response.data.message + '</span>');
				}
			}
		).fail(function(){
			$load.hide();
			$btn.prop('disabled', false);
			$result.show().html('<span class="naano-error">❌ <?php echo esc_js( __( 'Request failed.', 'naano-ai-website-builder' ) ); ?></span>');
		});
	});

	// Test connection.
	$('#naano-test-connection-btn').on('click', function(){
		var $btn    = $(this);
		var $load   = $('#naano-test-loading');
		var $result = $('#naano-test-result');
		var model   = $('#naano_model').val().trim();

		if ( ! model ) {
			$result.show().html('<span class="naano-error">⚠️ <?php echo esc_js( __( 'Please enter a Model Override before testing.', 'naano-ai-website-builder' ) ); ?></span>');
			$('#naano_model').focus();
			return;
		}

		$btn.prop('disabled', true);
		$load.show();
		$result.hide();

		$.post(
			<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			{
				action:   'naano_test_connection',
				nonce:    <?php echo wp_json_encode( wp_create_nonce( 'naano_builder_nonce' ) ); ?>,
				provider: $('#naano_provider').val(),
				api_key:  $('#naano_api_key').val(),
				model:    $('#naano_model').val()
			},
			function(response){
				$load.hide();
				$btn.prop('disabled', false);
				$result.show();
				if(response.success){
					$result.html(
						'<span class="naano-success">✅ <?php echo esc_js( __( 'Connected!', 'naano-ai-website-builder' ) ); ?> ' +
						'Model: ' + response.data.model + ' — ' + response.data.latency_ms + 'ms</span>'
					);
				} else {
					$result.html(
						'<span class="naano-error">❌ ' + (response.data.message || response.data.error) + '</span>'
					);
				}
			}
		).fail(function(){
			$load.hide();
			$btn.prop('disabled', false);
			$result.show().html('<span class="naano-error">❌ <?php echo esc_js( __( 'Request failed.', 'naano-ai-website-builder' ) ); ?></span>');
		});
	});

	// Save Firecrawl API key.
	$('#naano-save-firecrawl-key-btn').on('click', function(){
		var $btn    = $(this);
		var $load   = $('#naano-save-firecrawl-loading');
		var $result = $('#naano-save-firecrawl-result');
		var apiKey  = $('#naano_firecrawl_api_key').val();

		if ( ! apiKey ) {
			$result.show().html('<span class="naano-error">⚠️ <?php echo esc_js( __( 'Please enter an API key.', 'naano-ai-website-builder' ) ); ?></span>');
			$('#naano_firecrawl_api_key').focus();
			return;
		}

		$btn.prop('disabled', true);
		$load.show();
		$result.hide();

		$.post(
			<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			{
				action:  'naano_save_firecrawl_key',
				nonce:   <?php echo wp_json_encode( wp_create_nonce( 'naano_builder_nonce' ) ); ?>,
				api_key: apiKey
			},
			function(response){
				$load.hide();
				$btn.prop('disabled', false);
				$result.show();
				if(response.success){
					$result.html('<span class="naano-success">✅ ' + response.data.message + '</span>');
				} else {
					$result.html('<span class="naano-error">❌ ' + response.data.message + '</span>');
				}
			}
		).fail(function(){
			$load.hide();
			$btn.prop('disabled', false);
			$result.show().html('<span class="naano-error">❌ <?php echo esc_js( __( 'Request failed.', 'naano-ai-website-builder' ) ); ?></span>');
		});
	});

	// Test Firecrawl connection.
	$('#naano-test-firecrawl-btn').on('click', function(){
		var $btn    = $(this);
		var $load   = $('#naano-test-firecrawl-loading');
		var $result = $('#naano-test-firecrawl-result');
		var apiKey  = $('#naano_firecrawl_api_key').val();

		if ( ! apiKey ) {
			$result.show().html('<span class="naano-error">⚠️ <?php echo esc_js( __( 'Please enter an API key first.', 'naano-ai-website-builder' ) ); ?></span>');
			$('#naano_firecrawl_api_key').focus();
			return;
		}

		$btn.prop('disabled', true);
		$load.show();
		$result.hide();

		$.post(
			<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			{
				action:  'naano_test_firecrawl',
				nonce:   <?php echo wp_json_encode( wp_create_nonce( 'naano_builder_nonce' ) ); ?>,
				api_key: apiKey
			},
			function(response){
				$load.hide();
				$btn.prop('disabled', false);
				$result.show();
				if(response.success){
					$result.html(
						'<span class="naano-success">✅ ' + response.data.message + ' — ' + response.data.latency_ms + '</span>'
					);
				} else {
					$result.html(
						'<span class="naano-error">❌ ' + (response.data.message || response.data.error) + '</span>'
					);
				}
			}
		).fail(function(){
			$load.hide();
			$btn.prop('disabled', false);
			$result.show().html('<span class="naano-error">❌ <?php echo esc_js( __( 'Request failed.', 'naano-ai-website-builder' ) ); ?></span>');
		});
	});
});
</script>
