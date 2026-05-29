/**
 * Naano AI Builder — admin "Pages" list interactions.
 *
 * Toggles the per-row translation form when the user clicks the
 * "Translate" or "Cancel" buttons. Previously this code was echoed in
 * the template as an inline <script> block; it now ships as a real JS
 * file enqueued via wp_enqueue_script() with the 'jquery' dependency
 * declared, per the WordPress.org enqueue guideline.
 *
 * Also handles the inline permalink editor: each row's slug input
 * starts disabled, lights up a "Save" button when the value changes,
 * and POSTs to naano_update_permalink on click.
 */
( function ( $ ) {
	$( function () {
		$( document ).on( 'click', '.naano-translate-btn', function () {
			var id = $( this ).data( 'page-id' );
			$( '#naano-translate-form-' + id ).slideToggle( 150 );
		} );

		$( document ).on( 'click', '.naano-translate-cancel', function () {
			var id = $( this ).data( 'page-id' );
			$( '#naano-translate-form-' + id ).slideUp( 150 );
		} );

		// ── Permalink inline editor ───────────────────────────────
		// Show the "Save" button as soon as the user types something
		// different from the original slug. Hitting Enter inside the
		// input is equivalent to clicking Save — matches the rest of
		// WP's inline editors.
		$( document ).on( 'input', '.naano-perma-input', function () {
			var $input = $( this );
			var $row   = $input.closest( '.naano-perma-wrap' );
			var dirty  = $input.val() !== $input.data( 'original' );
			$row.find( '.naano-perma-save' ).toggle( dirty );
			// Clear stale status messages whenever the user resumes typing.
			$row.find( '.naano-perma-msg' )
				.removeClass( 'is-success is-error is-saving' )
				.text( '' );
		} );

		$( document ).on( 'keydown', '.naano-perma-input', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				$( this ).closest( '.naano-perma-wrap' )
					.find( '.naano-perma-save' )
					.trigger( 'click' );
			}
		} );

		$( document ).on( 'click', '.naano-perma-save', function () {
			var $btn    = $( this );
			var $row    = $btn.closest( '.naano-perma-wrap' );
			var pageId  = $row.data( 'page-id' );
			var $input  = $row.find( '.naano-perma-input' );
			var slug    = ( $input.val() || '' ).trim();
			var data    = window.naanoPagesListData || {};
			var i18n    = data.i18n || {};
			var $msg    = $row.find( '.naano-perma-msg' );

			if ( !data.ajaxUrl || !data.nonce ) {
				$msg.removeClass( 'is-success is-saving' )
					.addClass( 'is-error' )
					.text( i18n.save_failed || 'Save failed.' );
				return;
			}

			$btn.prop( 'disabled', true );
			$msg.removeClass( 'is-success is-error' )
				.addClass( 'is-saving' )
				.text( i18n.saving || 'Saving…' );

			$.post( data.ajaxUrl, {
				action:  'naano_update_permalink',
				nonce:   data.nonce,
				page_id: pageId,
				slug:    slug,
			} )
				.done( function ( resp ) {
					if ( resp && resp.success ) {
						// Server may have suffixed the slug to avoid a
						// collision (e.g. "about" → "about-2"); reflect
						// whatever was actually stored so the user sees
						// the truth.
						var finalSlug = ( resp.data && resp.data.slug ) || slug;
						$input.val( finalSlug ).data( 'original', finalSlug );
						$btn.hide();
						$msg.removeClass( 'is-saving is-error' )
							.addClass( 'is-success' )
							.text( '✓ ' + ( i18n.saved || 'Permalink saved.' ) );
						setTimeout( function () {
							$msg.text( '' ).removeClass( 'is-success' );
						}, 2500 );
					} else {
						var em = ( resp && resp.data && resp.data.message ) ||
							( i18n.save_failed || 'Save failed.' );
						$msg.removeClass( 'is-saving is-success' )
							.addClass( 'is-error' )
							.text( '✗ ' + em );
					}
				} )
				.fail( function () {
					$msg.removeClass( 'is-saving is-success' )
						.addClass( 'is-error' )
						.text( '✗ ' + ( i18n.save_failed || 'Save failed.' ) );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	} );
} )( jQuery );
