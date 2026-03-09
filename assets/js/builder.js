/**
 * Naano AI Website Builder — builder.js
 *
 * Handles all builder UI interactions, AJAX calls, and section management.
 */
/* global naanoBuilderData, wp */
( function ( $, data ) {
	'use strict';

	var NaanoBuilder = {

		pageId: 0,
		editingSectionId: null,

		/**
		 * Initialise the builder.
		 */
		init: function () {
			NaanoBuilder.pageId = parseInt( data.pageId, 10 ) || 0;

			NaanoBuilder._bindGenerationForm();
			NaanoBuilder._bindActionBar();
			NaanoBuilder._bindEditPanel();
			NaanoBuilder._bindDragDrop();

			// If we already have sections (page reload), render them.
			if ( data.sections && data.sections.length > 0 ) {
				NaanoBuilder._showBuilder();
				NaanoBuilder._rebuildCardsFromData( data.sections );
			}
		},

		// =====================================================================
		// Site generation
		// =====================================================================

		/**
		 * Generate a full website from the form.
		 */
		generateSite: function () {
			var pageName    = $( '#naano-page-name' ).val().trim();
			var description = $( '#naano-description' ).val().trim();
			var sections    = [];

			$( '#naano-section-checkboxes input[type=checkbox]:checked' ).each( function () {
				sections.push( $( this ).val() );
			} );

			if ( ! description ) {
				NaanoBuilder._toast( 'Please enter a site description.', 'error' );
				return;
			}
			if ( sections.length === 0 ) {
				NaanoBuilder._toast( 'Please select at least one section.', 'error' );
				return;
			}

			NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', true );

			$.post( data.ajaxUrl, {
				action:      'naano_generate_site',
				nonce:       data.nonce,
				page_id:     NaanoBuilder.pageId,
				page_name:   pageName,
				description: description,
				sections:    sections
			} )
			.done( function ( response ) {
				NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', false );
				if ( response.success ) {
					NaanoBuilder.pageId = response.data.page_id || NaanoBuilder.pageId;
					NaanoBuilder._showBuilder();
					NaanoBuilder.renderSections( response.data.sections );
					NaanoBuilder._toast( 'Website generated successfully! 🎉', 'success' );
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', false );
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

		// =====================================================================
		// Section rendering
		// =====================================================================

		/**
		 * Render multiple sections from a {id: html} object.
		 *
		 * @param {Object} sections
		 */
		renderSections: function ( sections ) {
			$( '#naano-section-cards' ).empty();
			if ( Array.isArray( sections ) ) {
				sections.forEach( function ( sec ) {
					NaanoBuilder.renderSectionCard( sec.id, sec.html );
				} );
			} else {
				$.each( sections, function ( id, html ) {
					NaanoBuilder.renderSectionCard( id, html );
				} );
			}
		},

		/**
		 * Create and append a single section card.
		 *
		 * @param {string} id   Section identifier.
		 * @param {string} html Section HTML.
		 */
		renderSectionCard: function ( id, html ) {
			var displayName = id
				.replace( /[-_]/g, ' ' )
				.replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );

			var $card = $( '<div>', {
				'class':          'naano-section-card',
				'id':             'naano-card-' + id,
				'data-section-id': id,
				'draggable':       'true'
			} );

			var $header = $( '<div class="naano-section-card__header">' +
				'<span class="naano-drag-handle" title="Drag to reorder">⠿</span>' +
				'<span class="naano-section-card__name">' + $( '<span>' ).text( displayName ).html() + '</span>' +
				'<div class="naano-section-card__badges"></div>' +
				'<div class="naano-section-card__actions">' +
					'<button type="button" class="naano-btn-icon naano-btn-edit" data-section-id="' + id + '" title="Edit">✏️</button>' +
					'<button type="button" class="naano-btn-icon naano-btn-screenshot" data-section-id="' + id + '" title="Add screenshot">🖼️</button>' +
					'<button type="button" class="naano-btn-icon naano-btn-url-ref" data-section-id="' + id + '" title="Add URL">🔗</button>' +
					'<button type="button" class="naano-btn-icon naano-btn-delete" data-section-id="' + id + '" title="Delete">🗑️</button>' +
				'</div>' +
			'</div>' );

			var $preview = $( '<div class="naano-section-card__preview">' +
				'<iframe class="naano-section-iframe" sandbox="allow-same-origin" loading="lazy"></iframe>' +
			'</div>' );

			$card.append( $header ).append( $preview );
			$( '#naano-section-cards' ).append( $card );

			// Set iframe srcdoc after appending (avoids blank frame in some browsers).
			$card.find( 'iframe' ).get( 0 ).srcdoc = html;

			NaanoBuilder._bindCardButtons( $card );
		},

		// =====================================================================
		// Edit panel
		// =====================================================================

		/**
		 * Open the edit panel for a section.
		 *
		 * @param {string} sectionId
		 */
		openEditPanel: function ( sectionId ) {
			NaanoBuilder.editingSectionId = sectionId;

			var displayName = sectionId
				.replace( /[-_]/g, ' ' )
				.replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );

			$( '#naano-editing-section-name' ).text( displayName );
			$( '#naano-instruction' ).val( '' );

			// Load stored references.
			var refs = ( data.references && data.references[ sectionId ] ) ? data.references[ sectionId ] : [];
			NaanoBuilder._renderReferenceList( refs );

			$( '#naano-edit-panel' ).slideDown( 200 );
			$( 'html, body' ).animate( { scrollTop: $( '#naano-edit-panel' ).offset().top - 40 }, 300 );
		},

		/**
		 * Submit the update-section request.
		 */
		updateSection: function () {
			var sectionId   = NaanoBuilder.editingSectionId;
			var instruction = $( '#naano-instruction' ).val().trim();

			if ( ! instruction ) {
				NaanoBuilder._toast( 'Please enter an instruction.', 'error' );
				return;
			}

			NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', true );

			$.post( data.ajaxUrl, {
				action:     'naano_update_section',
				nonce:      data.nonce,
				page_id:    NaanoBuilder.pageId,
				section_id: sectionId,
				instruction: instruction
			} )
			.done( function ( response ) {
				NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
				if ( response.success ) {
					var id   = response.data.section_id;
					var html = response.data.section_html;

					// Update the iframe.
					var iframe = $( '#naano-card-' + id + ' iframe' ).get( 0 );
					if ( iframe ) {
						iframe.srcdoc = html;
					}

					$( '#naano-edit-panel' ).slideUp( 200 );
					NaanoBuilder.editingSectionId = null;
					NaanoBuilder._toast( 'Section updated! ✨', 'success' );
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

		// =====================================================================
		// References
		// =====================================================================

		/**
		 * Open the WP media library to select a screenshot.
		 *
		 * @param {string} sectionId
		 */
		addScreenshot: function ( sectionId ) {
			var frame = wp.media( {
				title:    'Select Screenshot Reference',
				button:   { text: 'Use this image' },
				multiple: false,
				library:  { type: 'image' }
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				$.post( data.ajaxUrl, {
					action:        'naano_add_reference',
					nonce:         data.nonce,
					page_id:       NaanoBuilder.pageId,
					section_id:    sectionId,
					type:          'screenshot',
					url:           attachment.url,
					attachment_id: attachment.id,
					notes:         ''
				} )
				.done( function ( response ) {
					if ( response.success ) {
						// Update local cache.
						if ( ! data.references ) { data.references = {}; }
						data.references[ sectionId ] = response.data.references;

						if ( NaanoBuilder.editingSectionId === sectionId ) {
							NaanoBuilder._renderReferenceList( response.data.references );
						}
						NaanoBuilder._updateBadges( sectionId, response.data.references );
						NaanoBuilder._toast( 'Screenshot added! 🖼️', 'success' );
					} else {
						NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
					}
				} );
			} );

			frame.open();
		},

		/**
		 * Show the URL reference form.
		 *
		 * @param {string} sectionId
		 */
		addUrlReference: function ( sectionId ) {
			NaanoBuilder.editingSectionId = NaanoBuilder.editingSectionId || sectionId;
			$( '#naano-add-url-form' ).slideDown( 150 );
			$( '#naano-ref-url' ).focus();
		},

		/**
		 * Save a URL reference.
		 *
		 * @param {string} sectionId
		 */
		saveUrlReference: function ( sectionId ) {
			var url   = $( '#naano-ref-url' ).val().trim();
			var notes = $( '#naano-ref-notes' ).val().trim();

			if ( ! url ) {
				NaanoBuilder._toast( 'Please enter a URL.', 'error' );
				return;
			}

			$.post( data.ajaxUrl, {
				action:     'naano_add_reference',
				nonce:      data.nonce,
				page_id:    NaanoBuilder.pageId,
				section_id: sectionId,
				type:       'url',
				url:        url,
				notes:      notes
			} )
			.done( function ( response ) {
				if ( response.success ) {
					if ( ! data.references ) { data.references = {}; }
					data.references[ sectionId ] = response.data.references;

					if ( NaanoBuilder.editingSectionId === sectionId ) {
						NaanoBuilder._renderReferenceList( response.data.references );
					}
					NaanoBuilder._updateBadges( sectionId, response.data.references );
					$( '#naano-add-url-form' ).hide();
					$( '#naano-ref-url' ).val( '' );
					$( '#naano-ref-notes' ).val( '' );
					NaanoBuilder._toast( 'URL reference added! 🔗', 'success' );
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} );
		},

		/**
		 * Remove a reference.
		 *
		 * @param {string} sectionId
		 * @param {number} index
		 */
		removeReference: function ( sectionId, index ) {
			$.post( data.ajaxUrl, {
				action:     'naano_remove_reference',
				nonce:      data.nonce,
				page_id:    NaanoBuilder.pageId,
				section_id: sectionId,
				index:      index
			} )
			.done( function ( response ) {
				if ( response.success ) {
					if ( ! data.references ) { data.references = {}; }
					data.references[ sectionId ] = response.data.references;

					if ( NaanoBuilder.editingSectionId === sectionId ) {
						NaanoBuilder._renderReferenceList( response.data.references );
					}
					NaanoBuilder._updateBadges( sectionId, response.data.references );
				}
			} );
		},

		// =====================================================================
		// Section operations
		// =====================================================================

		/**
		 * Delete a section after confirmation.
		 *
		 * @param {string} sectionId
		 */
		deleteSection: function ( sectionId ) {
			if ( ! window.confirm( data.strings.confirm_delete ) ) {
				return;
			}

			$.post( data.ajaxUrl, {
				action:     'naano_delete_section',
				nonce:      data.nonce,
				page_id:    NaanoBuilder.pageId,
				section_id: sectionId
			} )
			.done( function ( response ) {
				if ( response.success ) {
					$( '#naano-card-' + sectionId ).remove();
					NaanoBuilder._toast( 'Section deleted.', 'success' );
				}
			} );
		},

		/**
		 * Send the reorder AJAX call.
		 */
		reorderSections: function () {
			var order = [];
			$( '#naano-section-cards .naano-section-card' ).each( function () {
				order.push( $( this ).data( 'section-id' ) );
			} );

			$.post( data.ajaxUrl, {
				action:  'naano_reorder_sections',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId,
				order:   order
			} );
		},

		// =====================================================================
		// Action bar operations
		// =====================================================================

		/**
		 * Open the preview modal.
		 */
		previewSite: function () {
			$.post( data.ajaxUrl, {
				action:  'naano_export_html',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId
			} )
			.done( function ( response ) {
				if ( response.success ) {
					NaanoPreview.open( response.data.html );
				}
			} );
		},

		/**
		 * Export assembled HTML as a downloadable file.
		 */
		exportHtml: function () {
			$.post( data.ajaxUrl, {
				action:  'naano_export_html',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId
			} )
			.done( function ( response ) {
				if ( response.success ) {
					var blob = new Blob( [ response.data.html ], { type: 'text/html' } );
					var url  = URL.createObjectURL( blob );
					var a    = document.createElement( 'a' );
					a.href     = url;
					a.download = 'website.html';
					a.click();
					URL.revokeObjectURL( url );
				}
			} );
		},

		/**
		 * Copy assembled HTML to clipboard.
		 */
		copyToClipboard: function () {
			$.post( data.ajaxUrl, {
				action:  'naano_export_html',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId
			} )
			.done( function ( response ) {
				if ( response.success ) {
					navigator.clipboard.writeText( response.data.html ).then( function () {
						NaanoBuilder._toast( 'HTML copied to clipboard! 📋', 'success' );
					} );
				}
			} );
		},

		/**
		 * Save assembled HTML as a WordPress page.
		 */
		saveAsPage: function () {
			var title = window.prompt( 'Page title:', $( '#naano-current-page-name' ).text() );
			if ( title === null ) { return; }

			$.post( data.ajaxUrl, {
				action:  'naano_save_as_page',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId,
				title:   title
			} )
			.done( function ( response ) {
				if ( response.success ) {
					NaanoBuilder._toast(
						'Page saved as draft! <a href="' + response.data.edit_url + '" target="_blank">Edit it</a>',
						'success',
						5000
					);
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} );
		},

		// =====================================================================
		// Private helpers
		// =====================================================================

		_bindGenerationForm: function () {
			// Generate button.
			$( document ).on( 'click', '#naano-generate-btn', function () {
				NaanoBuilder.generateSite();
			} );

			// Add custom section checkbox.
			$( document ).on( 'click', '#naano-add-custom-section', function () {
				var name = $( '#naano-custom-section-input' ).val().trim();
				if ( ! name ) { return; }

				var slug = name.toLowerCase().replace( /\s+/g, '-' ).replace( /[^a-z0-9-]/g, '' );
				$( '#naano-section-checkboxes' ).append(
					'<label class="naano-checkbox-label">' +
					'<input type="checkbox" name="sections[]" value="' + slug + '" checked> ' + $( '<span>' ).text( name ).html() +
					'</label>'
				);
				$( '#naano-custom-section-input' ).val( '' );
			} );
		},

		_bindActionBar: function () {
			$( document ).on( 'click', '#naano-preview-btn',    function () { NaanoBuilder.previewSite(); } );
			$( document ).on( 'click', '#naano-export-btn',     function () { NaanoBuilder.exportHtml(); } );
			$( document ).on( 'click', '#naano-copy-btn',       function () { NaanoBuilder.copyToClipboard(); } );
			$( document ).on( 'click', '#naano-save-page-btn',  function () { NaanoBuilder.saveAsPage(); } );
		},

		_bindEditPanel: function () {
			// Update section.
			$( document ).on( 'click', '#naano-update-section-btn', function () {
				NaanoBuilder.updateSection();
			} );

			// Cancel edit.
			$( document ).on( 'click', '#naano-cancel-edit-btn', function () {
				$( '#naano-edit-panel' ).slideUp( 200 );
				NaanoBuilder.editingSectionId = null;
			} );

			// Add screenshot.
			$( document ).on( 'click', '#naano-add-screenshot-btn', function () {
				NaanoBuilder.addScreenshot( NaanoBuilder.editingSectionId );
			} );

			// Add URL.
			$( document ).on( 'click', '#naano-add-url-btn', function () {
				NaanoBuilder.addUrlReference( NaanoBuilder.editingSectionId );
			} );

			// Save URL.
			$( document ).on( 'click', '#naano-save-url-btn', function () {
				NaanoBuilder.saveUrlReference( NaanoBuilder.editingSectionId );
			} );

			// Cancel URL form.
			$( document ).on( 'click', '#naano-cancel-url-btn', function () {
				$( '#naano-add-url-form' ).hide();
			} );

			// Remove reference.
			$( document ).on( 'click', '.naano-remove-ref-btn', function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				NaanoBuilder.removeReference( NaanoBuilder.editingSectionId, idx );
			} );
		},

		_bindCardButtons: function ( $card ) {
			var sectionId = $card.data( 'section-id' );

			$card.find( '.naano-btn-edit' ).on( 'click', function () {
				NaanoBuilder.openEditPanel( sectionId );
			} );

			$card.find( '.naano-btn-screenshot' ).on( 'click', function () {
				NaanoBuilder.editingSectionId = sectionId;
				NaanoBuilder.addScreenshot( sectionId );
			} );

			$card.find( '.naano-btn-url-ref' ).on( 'click', function () {
				NaanoBuilder.openEditPanel( sectionId );
				setTimeout( function () {
					NaanoBuilder.addUrlReference( sectionId );
				}, 250 );
			} );

			$card.find( '.naano-btn-delete' ).on( 'click', function () {
				NaanoBuilder.deleteSection( sectionId );
			} );
		},

		_bindDragDrop: function () {
			var $container = $( '#naano-section-cards' );
			var dragged    = null;

			$container.on( 'dragstart', '.naano-section-card', function ( e ) {
				dragged = this;
				$( this ).addClass( 'naano-dragging' );
				e.originalEvent.dataTransfer.effectAllowed = 'move';
			} );

			$container.on( 'dragend', '.naano-section-card', function () {
				$( this ).removeClass( 'naano-dragging' );
				NaanoBuilder.reorderSections();
			} );

			$container.on( 'dragover', '.naano-section-card', function ( e ) {
				e.preventDefault();
				var $over = $( this );
				if ( dragged && dragged !== this ) {
					var midY = $over.offset().top + $over.outerHeight() / 2;
					if ( e.originalEvent.clientY < midY ) {
						$over.before( dragged );
					} else {
						$over.after( dragged );
					}
				}
			} );
		},

		_rebuildCardsFromData: function ( sections ) {
			$( '#naano-section-cards' ).empty();
			sections.forEach( function ( sec ) {
				NaanoBuilder.renderSectionCard( sec.id, sec.html );
			} );
		},

		_showBuilder: function () {
			$( '#naano-generation-form' ).hide();
			$( '#naano-builder-main' ).show();
		},

		_renderReferenceList: function ( refs ) {
			var $screenshots = $( '#naano-screenshot-list' ).empty();
			var $urls        = $( '#naano-url-list' ).empty();

			refs.forEach( function ( ref, index ) {
				var $li = $( '<li class="naano-reference-item">' );

				if ( ref.type === 'screenshot' ) {
					$li.append(
						'<img src="' + $( '<img>' ).attr( 'src', ref.url ).prop( 'outerHTML' ).match( /src="([^"]*)"/ )[1] +
						'" class="naano-ref-thumb"> ' +
						$( '<span>' ).text( ref.notes || ref.url ).html()
					);
					$li.append( ' <button type="button" class="naano-remove-ref-btn" data-index="' + index + '">✕</button>' );
					$screenshots.append( $li );
				} else {
					$li.append(
						'<a href="' + $( '<a>' ).attr( 'href', ref.url ).prop( 'outerHTML' ).match( /href="([^"]*)"/ )[1] +
						'" target="_blank">' + $( '<span>' ).text( ref.url ).html() + '</a>'
					);
					if ( ref.notes ) {
						$li.append( ' — ' + $( '<span>' ).text( ref.notes ).html() );
					}
					$li.append( ' <button type="button" class="naano-remove-ref-btn" data-index="' + index + '">✕</button>' );
					$urls.append( $li );
				}
			} );
		},

		_updateBadges: function ( sectionId, refs ) {
			var screenshots = refs.filter( function ( r ) { return r.type === 'screenshot'; } ).length;
			var urls        = refs.filter( function ( r ) { return r.type === 'url'; } ).length;
			var $badges     = $( '#naano-card-' + sectionId + ' .naano-section-card__badges' ).empty();

			if ( screenshots > 0 ) {
				$badges.append( '<span class="naano-badge naano-badge--screenshots">🖼️ ' + screenshots + '</span>' );
			}
			if ( urls > 0 ) {
				$badges.append( '<span class="naano-badge naano-badge--urls">🔗 ' + urls + '</span>' );
			}
		},

		_setLoading: function ( btnSelector, loadSelector, loading ) {
			$( btnSelector ).prop( 'disabled', loading );
			$( loadSelector ).toggle( loading );
		},

		_toast: function ( message, type, duration ) {
			duration = duration || 3500;
			var $toast = $( '<div class="naano-toast naano-toast--' + type + '">' + message + '</div>' );
			$( 'body' ).append( $toast );
			setTimeout( function () { $toast.addClass( 'naano-toast--visible' ); }, 10 );
			setTimeout( function () {
				$toast.removeClass( 'naano-toast--visible' );
				setTimeout( function () { $toast.remove(); }, 300 );
			}, duration );
		}
	};

	// Boot when DOM is ready.
	$( function () {
		NaanoBuilder.init();
	} );

	// Expose globally for inline event use.
	window.NaanoBuilder = NaanoBuilder;

}( jQuery, naanoBuilderData ) );
