/**
 * Naano AI Builder — Settings page interactions.
 *
 * Previously inlined as a <script> block at the bottom of
 * templates/settings-page.php. Moved into a real JS file to comply with
 * the WordPress.org enqueue guideline.
 *
 * All ajax URLs, nonces, and translatable strings are passed in via
 * wp_localize_script() and read from window.naanoSettingsData.
 */
( function ( $ ) {
	$( function () {
		var data = window.naanoSettingsData || {};
		var ajaxUrl = data.ajaxUrl || '';
		var nonce = data.nonce || '';
		var i18n = data.i18n || {};

		// -----------------------------------------------------------------
		// Settings tab switching
		// -----------------------------------------------------------------
		var $tabs = $( '.naano-settings-tab-nav .nav-tab' );
		var $panels = $( '.naano-settings-panel' );

		function switchTab( tab ) {
			$tabs.removeClass( 'nav-tab-active' );
			$tabs.filter( '[data-naano-tab="' + tab + '"]' ).addClass( 'nav-tab-active' );
			$panels.hide();
			$( '#naano-panel-' + tab ).show();
			try {
				localStorage.setItem( 'naano_settings_tab', tab );
			} catch ( e ) {}
		}

		$tabs.on( 'click', function () {
			switchTab( $( this ).data( 'naano-tab' ) );
		} );

		try {
			var savedTab = localStorage.getItem( 'naano_settings_tab' );
			if ( savedTab && $( '#naano-panel-' + savedTab ).length ) {
				switchTab( savedTab );
			}
		} catch ( e ) {}

		// -----------------------------------------------------------------
		// Variable rows (key/value pairs for design variables)
		// -----------------------------------------------------------------
		$( '#naano-add-variable-btn' ).on( 'click', function () {
			var row =
				'<tr class="naano-variable-row">' +
					'<td><input type="text" name="naano_vars_keys[]" class="regular-text" placeholder="' +
					( i18n.varKeyPlaceholder || '' ) +
					'"></td>' +
					'<td><input type="text" name="naano_vars_values[]" class="regular-text" placeholder="' +
					( i18n.varValuePlaceholder || '' ) +
					'"></td>' +
					'<td><button type="button" class="button naano-remove-variable">' +
					( i18n.remove || 'Remove' ) +
					'</button></td>' +
				'</tr>';
			$( '#naano-variables-tbody' ).append( row );
		} );

		$( document ).on( 'click', '.naano-remove-variable', function () {
			$( this ).closest( 'tr' ).remove();
		} );

		// -----------------------------------------------------------------
		// Language rows (code/label pairs)
		// -----------------------------------------------------------------
		$( '#naano-add-language-btn' ).on( 'click', function () {
			var row =
				'<tr class="naano-language-row">' +
					'<td><input type="text" name="naano_lang_codes[]" class="regular-text" placeholder="' +
					( i18n.langCodePlaceholder || '' ) +
					'" style="max-width:100px;"></td>' +
					'<td><input type="text" name="naano_lang_labels[]" class="regular-text" placeholder="' +
					( i18n.langLabelPlaceholder || '' ) +
					'"></td>' +
					'<td><button type="button" class="button naano-remove-language">' +
					( i18n.remove || 'Remove' ) +
					'</button></td>' +
				'</tr>';
			$( '#naano-languages-tbody' ).append( row );
		} );

		$( document ).on( 'click', '.naano-remove-language', function () {
			$( this ).closest( 'tr' ).remove();
		} );

		// -----------------------------------------------------------------
		// Save global config
		// -----------------------------------------------------------------
		$( '#naano-save-global-config-btn' ).on( 'click', function () {
			var $btn = $( this );
			var $load = $( '#naano-save-global-loading' );
			var $result = $( '#naano-save-global-result' );

			$btn.prop( 'disabled', true );
			$load.show();
			$result.hide();

			$.post(
				ajaxUrl,
				{
					action: 'naano_save_global_config',
					nonce: nonce,
					initial_refinement_passes: $( '#naano_initial_refinement_passes' ).val(),
					update_refinement_passes: $( '#naano_update_refinement_passes' ).val()
				},
				function ( response ) {
					$load.hide();
					$btn.prop( 'disabled', false );
					$result.show();
					if ( response.success ) {
						$result.html( '<span class="naano-success">✅ ' + response.data.message + '</span>' );
					} else {
						$result.html( '<span class="naano-error">❌ ' + response.data.message + '</span>' );
					}
				}
			).fail( function () {
				$load.hide();
				$btn.prop( 'disabled', false );
				$result
					.show()
					.html(
						'<span class="naano-error">❌ ' + ( i18n.requestFailed || 'Request failed.' ) + '</span>'
					);
			} );
		} );

		// -----------------------------------------------------------------
		// Save API key (LLM provider)
		// -----------------------------------------------------------------
		$( '#naano-save-api-key-btn' ).on( 'click', function () {
			var $btn = $( this );
			var $load = $( '#naano-save-key-loading' );
			var $result = $( '#naano-save-key-result' );
			var apiKey = $( '#naano_api_key' ).val();

			if ( ! apiKey ) {
				$result
					.show()
					.html(
						'<span class="naano-error">⚠️ ' +
							( i18n.enterApiKey || 'Please enter an API key.' ) +
							'</span>'
					);
				$( '#naano_api_key' ).focus();
				return;
			}

			$btn.prop( 'disabled', true );
			$load.show();
			$result.hide();

			$.post(
				ajaxUrl,
				{
					action: 'naano_save_api_key',
					nonce: nonce,
					api_key: apiKey
				},
				function ( response ) {
					$load.hide();
					$btn.prop( 'disabled', false );
					$result.show();
					if ( response.success ) {
						$result.html( '<span class="naano-success">✅ ' + response.data.message + '</span>' );
					} else {
						$result.html( '<span class="naano-error">❌ ' + response.data.message + '</span>' );
					}
				}
			).fail( function () {
				$load.hide();
				$btn.prop( 'disabled', false );
				$result
					.show()
					.html(
						'<span class="naano-error">❌ ' + ( i18n.requestFailed || 'Request failed.' ) + '</span>'
					);
			} );
		} );

		// -----------------------------------------------------------------
		// Test LLM connection
		// -----------------------------------------------------------------
		$( '#naano-test-connection-btn' ).on( 'click', function () {
			var $btn = $( this );
			var $load = $( '#naano-test-loading' );
			var $result = $( '#naano-test-result' );
			var model = $( '#naano_model' ).val().trim();

			if ( ! model ) {
				$result
					.show()
					.html(
						'<span class="naano-error">⚠️ ' +
							( i18n.enterModel || 'Please enter a Model Override before testing.' ) +
							'</span>'
					);
				$( '#naano_model' ).focus();
				return;
			}

			$btn.prop( 'disabled', true );
			$load.show();
			$result.hide();

			$.post(
				ajaxUrl,
				{
					action: 'naano_test_connection',
					nonce: nonce,
					provider: $( '#naano_provider' ).val(),
					api_key: $( '#naano_api_key' ).val(),
					model: $( '#naano_model' ).val()
				},
				function ( response ) {
					$load.hide();
					$btn.prop( 'disabled', false );
					$result.show();
					if ( response.success ) {
						$result.html(
							'<span class="naano-success">✅ ' +
								( i18n.connected || 'Connected!' ) +
								' Model: ' +
								response.data.model +
								' — ' +
								response.data.latency_ms +
								'ms</span>'
						);
					} else {
						$result.html(
							'<span class="naano-error">❌ ' +
								( response.data.message || response.data.error ) +
								'</span>'
						);
					}
				}
			).fail( function () {
				$load.hide();
				$btn.prop( 'disabled', false );
				$result
					.show()
					.html(
						'<span class="naano-error">❌ ' + ( i18n.requestFailed || 'Request failed.' ) + '</span>'
					);
			} );
		} );

		// -----------------------------------------------------------------
		// Save Firecrawl API key
		// -----------------------------------------------------------------
		$( '#naano-save-firecrawl-key-btn' ).on( 'click', function () {
			var $btn = $( this );
			var $load = $( '#naano-save-firecrawl-loading' );
			var $result = $( '#naano-save-firecrawl-result' );
			var apiKey = $( '#naano_firecrawl_api_key' ).val();

			if ( ! apiKey ) {
				$result
					.show()
					.html(
						'<span class="naano-error">⚠️ ' +
							( i18n.enterApiKey || 'Please enter an API key.' ) +
							'</span>'
					);
				$( '#naano_firecrawl_api_key' ).focus();
				return;
			}

			$btn.prop( 'disabled', true );
			$load.show();
			$result.hide();

			$.post(
				ajaxUrl,
				{
					action: 'naano_save_firecrawl_key',
					nonce: nonce,
					api_key: apiKey
				},
				function ( response ) {
					$load.hide();
					$btn.prop( 'disabled', false );
					$result.show();
					if ( response.success ) {
						$result.html( '<span class="naano-success">✅ ' + response.data.message + '</span>' );
					} else {
						$result.html( '<span class="naano-error">❌ ' + response.data.message + '</span>' );
					}
				}
			).fail( function () {
				$load.hide();
				$btn.prop( 'disabled', false );
				$result
					.show()
					.html(
						'<span class="naano-error">❌ ' + ( i18n.requestFailed || 'Request failed.' ) + '</span>'
					);
			} );
		} );

		// -----------------------------------------------------------------
		// Test Firecrawl connection
		// -----------------------------------------------------------------
		$( '#naano-test-firecrawl-btn' ).on( 'click', function () {
			var $btn = $( this );
			var $load = $( '#naano-test-firecrawl-loading' );
			var $result = $( '#naano-test-firecrawl-result' );
			var apiKey = $( '#naano_firecrawl_api_key' ).val();

			if ( ! apiKey ) {
				$result
					.show()
					.html(
						'<span class="naano-error">⚠️ ' +
							( i18n.enterApiKeyFirst || 'Please enter an API key first.' ) +
							'</span>'
					);
				$( '#naano_firecrawl_api_key' ).focus();
				return;
			}

			$btn.prop( 'disabled', true );
			$load.show();
			$result.hide();

			$.post(
				ajaxUrl,
				{
					action: 'naano_test_firecrawl',
					nonce: nonce,
					api_key: apiKey
				},
				function ( response ) {
					$load.hide();
					$btn.prop( 'disabled', false );
					$result.show();
					if ( response.success ) {
						$result.html(
							'<span class="naano-success">✅ ' +
								response.data.message +
								' — ' +
								response.data.latency_ms +
								'</span>'
						);
					} else {
						$result.html(
							'<span class="naano-error">❌ ' +
								( response.data.message || response.data.error ) +
								'</span>'
						);
					}
				}
			).fail( function () {
				$load.hide();
				$btn.prop( 'disabled', false );
				$result
					.show()
					.html(
						'<span class="naano-error">❌ ' + ( i18n.requestFailed || 'Request failed.' ) + '</span>'
					);
			} );
		} );
	} );
} )( jQuery );
