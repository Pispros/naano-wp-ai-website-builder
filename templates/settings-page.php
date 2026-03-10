<?php
/**
 * Settings Page Template
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider  = get_option( 'naano_provider', 'claude' );
$api_key   = get_option( 'naano_api_key', '' );
$model     = get_option( 'naano_model', '' );
$variables = get_option( 'naano_variables', [] );
if ( ! is_array( $variables ) ) {
	$variables = [];
}
?>
<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 340 340" aria-hidden="true" focusable="false"><rect x="54" y="54" width="232" height="232" rx="26" ry="26" fill="none" stroke="#2060F0" stroke-width="18"/><line x1="115" y1="54" x2="115" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="54" x2="170" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="54" x2="225" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="115" y1="286" x2="115" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="286" x2="170" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="286" x2="225" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="115" x2="26" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="170" x2="26" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="225" x2="26" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="115" x2="314" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="170" x2="314" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="225" x2="314" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="106" y1="106" x2="106" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="234" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="106" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/></svg>
		<?php esc_html_e( 'Naano AI Builder — Settings', 'naano-ai-website-builder' ); ?>
	</h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'naano_settings_group' ); ?>

		<!-- ============================================================
		     LLM PROVIDER
		     ============================================================ -->
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
		</div>

		<!-- ============================================================
		     DESIGN VARIABLES
		     ============================================================ -->
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
		</div>

		<?php submit_button( __( 'Save Settings', 'naano-ai-website-builder' ) ); ?>
	</form>
</div>

<script>
jQuery(function($){
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
});
</script>
