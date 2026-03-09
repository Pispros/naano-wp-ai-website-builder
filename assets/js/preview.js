/**
 * Naano AI Website Builder — preview.js
 *
 * Full-page site preview modal with responsive viewport toggles.
 */
/* global NaanoBuilder */
( function () {
	'use strict';

	var NaanoPreview = {

		_$modal: null,

		/**
		 * Open the preview modal with the given HTML.
		 *
		 * @param {string} html Full HTML document string.
		 */
		open: function ( html ) {
			NaanoPreview.close(); // Remove any existing modal.

			var $modal = jQuery( '<div>', { 'class': 'naano-preview-modal' } );

			var $toolbar = jQuery(
				'<div class="naano-preview-toolbar">' +
					'<div class="naano-preview-toolbar__viewports">' +
						'<button type="button" class="naano-viewport-btn naano-viewport-btn--active" data-width="100%" title="Desktop">🖥️ Desktop</button>' +
						'<button type="button" class="naano-viewport-btn" data-width="768px" title="Tablet">📱 Tablet</button>' +
						'<button type="button" class="naano-viewport-btn" data-width="375px" title="Mobile">📲 Mobile</button>' +
					'</div>' +
					'<button type="button" class="naano-preview-close" title="Close preview">✕ Close</button>' +
				'</div>'
			);

			var $frame_wrap = jQuery( '<div class="naano-preview-frame-wrap">' );
			var $iframe     = jQuery( '<iframe>', {
				'class':   'naano-preview-iframe',
				'sandbox': 'allow-same-origin allow-scripts'
			} );

			$frame_wrap.append( $iframe );
			$modal.append( $toolbar ).append( $frame_wrap );
			jQuery( 'body' ).append( $modal );

			NaanoPreview._$modal = $modal;

			// Write HTML into iframe.
			var iframeDoc = $iframe.get( 0 ).contentDocument || $iframe.get( 0 ).contentWindow.document;
			iframeDoc.open();
			iframeDoc.write( html );
			iframeDoc.close();

			// Toolbar events.
			$toolbar.find( '.naano-viewport-btn' ).on( 'click', function () {
				var width = jQuery( this ).data( 'width' );
				$toolbar.find( '.naano-viewport-btn' ).removeClass( 'naano-viewport-btn--active' );
				jQuery( this ).addClass( 'naano-viewport-btn--active' );
				$iframe.css( 'width', width );
			} );

			$toolbar.find( '.naano-preview-close' ).on( 'click', function () {
				NaanoPreview.close();
			} );

			// Close on Escape key.
			jQuery( document ).on( 'keydown.naano-preview', function ( e ) {
				if ( e.key === 'Escape' ) {
					NaanoPreview.close();
				}
			} );

			// Show with transition.
			setTimeout( function () { $modal.addClass( 'naano-preview-modal--open' ); }, 10 );
		},

		/**
		 * Close and remove the preview modal.
		 */
		close: function () {
			if ( NaanoPreview._$modal ) {
				NaanoPreview._$modal.removeClass( 'naano-preview-modal--open' );
				var $m = NaanoPreview._$modal;
				setTimeout( function () { $m.remove(); }, 300 );
				NaanoPreview._$modal = null;
			}
			jQuery( document ).off( 'keydown.naano-preview' );
		}
	};

	window.NaanoPreview = NaanoPreview;

}() );
