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
		<span class="dashicons dashicons-admin-settings"></span>
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
							<?php esc_html_e( 'Defaults: Claude → claude-sonnet-4-20250514 | Gemini → gemini-2.0-flash | Kimi → moonshot-v1-8k', 'naano-ai-website-builder' ); ?>
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

		$btn.prop('disabled', true);
		$load.show();
		$result.hide();

		$.post(
			naanoBuilderData.ajaxUrl,
			{
				action:   'naano_test_connection',
				nonce:    naanoBuilderData.nonce,
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
