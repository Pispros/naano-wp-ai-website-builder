/**
 * Naano AI Builder — admin "Pages" list interactions.
 *
 * Toggles the per-row translation form when the user clicks the
 * "Translate" or "Cancel" buttons. Previously this code was echoed in
 * the template as an inline <script> block; it now ships as a real JS
 * file enqueued via wp_enqueue_script() with the 'jquery' dependency
 * declared, per the WordPress.org enqueue guideline.
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
	} );
} )( jQuery );
