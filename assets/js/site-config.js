/**
 * Naano AI Builder — Site Configuration page.
 *
 * Handles three concerns:
 *   1. Picking a favicon from the WP media library (wp.media()).
 *   2. Keeping the favicon URL field, the hidden attachment id, and
 *      the live preview <span> in sync.
 *   3. POSTing the whole form to naano_save_site_config via AJAX so the
 *      user doesn't get bounced through the standard options.php
 *      page-reload flow (which would lose the spot in the form on a
 *      long page).
 */
/* global wp, naanoSiteConfig */
( function ( $ ) {
	'use strict';

	$( function () {
		var data = window.naanoSiteConfig || {};
		var i18n = data.i18n || {};

		// ── Favicon picker (wp.media) ───────────────────────────
		$( '#naano-sc-favicon-pick' ).on( 'click', function () {
			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}
			var frame = wp.media( {
				title:    i18n.pick_favicon || 'Choose favicon',
				button:   { text: i18n.use_this || 'Use this image' },
				multiple: false,
				library:  { type: 'image' },
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				if ( ! att || ! att.url ) { return; }
				$( '#naano-sc-favicon-url' ).val( att.url );
				$( '#naano-sc-favicon-aid' ).val( att.id || 0 );
				$( '#naano-sc-favicon-preview' )
					.css( 'background-image', 'url("' + att.url + '")' );
				$( '#naano-sc-favicon-clear' ).show();
			} );
			frame.open();
		} );

		// Manual URL edits should also update the preview live, so the
		// user can paste a CDN-hosted favicon without going through the
		// media library at all.
		$( '#naano-sc-favicon-url' ).on( 'input', function () {
			var url = ( $( this ).val() || '' ).trim();
			$( '#naano-sc-favicon-preview' )
				.css( 'background-image', url ? 'url("' + url + '")' : 'none' );
			$( '#naano-sc-favicon-clear' ).toggle( !! url );
			// Picking a URL by hand decouples from any picked attachment.
			$( '#naano-sc-favicon-aid' ).val( 0 );
		} );

		$( '#naano-sc-favicon-clear' ).on( 'click', function () {
			$( '#naano-sc-favicon-url' ).val( '' );
			$( '#naano-sc-favicon-aid' ).val( 0 );
			$( '#naano-sc-favicon-preview' ).css( 'background-image', 'none' );
			$( this ).hide();
		} );

		// ── Maintenance toggle: update the status banner live ───
		$( '#naano-sc-maint-toggle' ).on( 'change', function () {
			var on   = $( this ).is( ':checked' );
			var $row = $( '#naano-sc-status-row' );
			var $txt = $( '#naano-sc-status-text' );
			var $icn = $row.find( '.dashicons' );
			if ( on ) {
				$row.removeClass( 'is-off' ).addClass( 'is-on' );
				$icn.removeClass( 'dashicons-yes-alt' ).addClass( 'dashicons-warning' );
				$txt.text( i18n.maint_on ||
					'Maintenance mode is ON — visitors see the maintenance page.' );
			} else {
				$row.removeClass( 'is-on' ).addClass( 'is-off' );
				$icn.removeClass( 'dashicons-warning' ).addClass( 'dashicons-yes-alt' );
				$txt.text( i18n.maint_off ||
					'Maintenance mode is OFF — your site is live.' );
			}
		} );

		// ── Save ────────────────────────────────────────────────
		$( '#naano-sc-save-btn' ).on( 'click', function () {
			var $btn = $( this );
			var $msg = $( '#naano-sc-save-msg' );
			$msg.removeClass( 'is-success is-error' ).text( '' );
			$btn.prop( 'disabled', true );

			$.post( data.ajaxUrl, {
				action:                'naano_save_site_config',
				nonce:                 data.nonce,
				slogan:                $( '#naano-sc-slogan' ).val() || '',
				favicon_url:           $( '#naano-sc-favicon-url' ).val() || '',
				favicon_attachment_id: $( '#naano-sc-favicon-aid' ).val() || 0,
				maintenance_enabled:   $( '#naano-sc-maint-toggle' ).is( ':checked' ) ? 1 : 0,
				maintenance_page_id:   $( '#naano-sc-maint-page' ).val() || 0,
			} )
				.done( function ( resp ) {
					if ( resp && resp.success ) {
						$msg.addClass( 'is-success' )
							.text( '✓ ' + ( ( resp.data && resp.data.message ) || i18n.saved || 'Configuration saved.' ) );
					} else {
						var m = ( resp && resp.data && resp.data.message ) || i18n.save_failed || 'Save failed.';
						$msg.addClass( 'is-error' ).text( '✗ ' + m );
					}
				} )
				.fail( function () {
					$msg.addClass( 'is-error' ).text( '✗ ' + ( i18n.save_failed || 'Save failed.' ) );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	} );
} )( jQuery );
