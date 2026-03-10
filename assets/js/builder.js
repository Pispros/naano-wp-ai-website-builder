/**
 * Naano AI Website Builder — builder.js
 *
 * Handles all builder UI interactions, AJAX calls, and section management.
 * Visual builder (Elementor-style) with real-time live preview.
 */
/* global naanoBuilderData, wp */
( function ( $, data ) {
	'use strict';

	var NaanoBuilder = {

		pageId: 0,
		editingSectionId: null,

		/** @type {Array<{id: string, html: string}>} In-memory sections store. */
		sectionsData: [],

		/** @type {Array<{url: string, desc: string}>} Page-level asset URLs. */
		pageAssets: [],

		/** @type {Array<{label: string, url: string}>} Page-level URL redirections. */
		pageRedirects: [],

		/**
		 * Initialise the builder.
		 */
		init: function () {
			NaanoBuilder.pageId = parseInt( data.pageId, 10 ) || 0;

			// Activate full-screen layout.
			$( 'body' ).addClass( 'naano-fullscreen' );

			// Inject the shared canvas loading video overlay (hidden by default).
			if ( data.videoUrl ) {
				$( '#naano-vb-canvas' ).append(
					'<div class="naano-canvas-loading-overlay" id="naano-canvas-loading-overlay">' +
					'<video class="naano-canvas-loading-overlay__video" src="' + $( '<div>' ).text( data.videoUrl ).html() + '" autoplay loop muted playsinline></video>' +
					'</div>'
				);
			}

			NaanoBuilder._bindGenerationForm();
			NaanoBuilder._bindActionBar();
			NaanoBuilder._bindDrawer();
			NaanoBuilder._bindViewportToggle();
			NaanoBuilder._bindEditPanel();
			NaanoBuilder._bindAssetsPanel();
			NaanoBuilder._bindRedirectsPanel();
			NaanoBuilder._bindIframeMessages();

			// If we already have sections (page reload), render them.
			if ( data.sections && data.sections.length > 0 ) {
				NaanoBuilder.sectionsData = data.sections.slice();
				NaanoBuilder._showBuilder();
				NaanoBuilder._refreshLivePreview();
				NaanoBuilder._renderSectionsList();
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

			var valid = true;

			if ( ! description ) {
				$( '#naano-description-error' ).show();
				$( '#naano-description' ).focus();
				valid = false;
			} else {
				$( '#naano-description-error' ).hide();
			}

			if ( sections.length === 0 ) {
				$( '#naano-sections-error' ).show();
				if ( valid ) { $( '#naano-section-checkboxes' ).find( 'input' ).first().focus(); }
				valid = false;
			} else {
				$( '#naano-sections-error' ).hide();
			}

			if ( ! valid ) { return; }

			NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', true );

			// Show the video loading overlay on the canvas.
			NaanoBuilder._showCanvasLoading();

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
				NaanoBuilder._hideCanvasLoading();
				if ( response.success ) {
					NaanoBuilder.pageId = response.data.page_id || NaanoBuilder.pageId;

					// Store sections in memory.
					if ( Array.isArray( response.data.sections ) ) {
						NaanoBuilder.sectionsData = response.data.sections.slice();
					} else {
						NaanoBuilder.sectionsData = [];
						$.each( response.data.sections, function ( id, html ) {
							NaanoBuilder.sectionsData.push( { id: id, html: html } );
						} );
					}

					// Update page name display.
					if ( pageName ) {
						$( '#naano-current-page-name' ).text( pageName );
					}

					NaanoBuilder._showBuilder();
					NaanoBuilder._refreshLivePreview();
					NaanoBuilder._renderSectionsList();
					NaanoBuilder._toast( 'Website generated successfully! 🎉', 'success' );
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', false );
				NaanoBuilder._hideCanvasLoading();
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

		// =====================================================================
		// Section rendering
		// =====================================================================

		/**
		 * (Legacy) Render multiple sections — kept for backward compat.
		 *
		 * @param {Object|Array} sections
		 */
		renderSections: function ( sections ) {
			if ( Array.isArray( sections ) ) {
				NaanoBuilder.sectionsData = sections.slice();
			} else {
				NaanoBuilder.sectionsData = [];
				$.each( sections, function ( id, html ) {
					NaanoBuilder.sectionsData.push( { id: id, html: html } );
				} );
			}
			NaanoBuilder._refreshLivePreview();
			NaanoBuilder._renderSectionsList();
		},

		/**
		 * (Legacy) Render a single section card — now adds/updates in sectionsData.
		 *
		 * @param {string} id
		 * @param {string} html
		 */
		renderSectionCard: function ( id, html ) {
			var existing = NaanoBuilder._findSectionIndex( id );
			if ( existing === -1 ) {
				NaanoBuilder.sectionsData.push( { id: id, html: html } );
			} else {
				NaanoBuilder.sectionsData[ existing ].html = html;
			}
			NaanoBuilder._refreshLivePreview();
			NaanoBuilder._renderSectionsList();
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

			var displayName = NaanoBuilder._displayName( sectionId );
			$( '#naano-editing-section-name' ).text( displayName );
			$( '#naano-instruction' ).val( '' );

			// Enable the update button now that a section is selected.
			$( '#naano-update-section-btn' ).prop( 'disabled', false );

			// Highlight in sections list.
			$( '#naano-sections-list .naano-sections-list__item' ).removeClass( 'naano-sections-list__item--active' );
			$( '#naano-sl-item-' + sectionId ).addClass( 'naano-sections-list__item--active' );

			// Load stored references.
			var refs = ( data.references && data.references[ sectionId ] ) ? data.references[ sectionId ] : [];
			NaanoBuilder._renderReferenceList( refs );

			// Tell the iframe to highlight the section.
			// NOTE: postMessage target '*' is intentional — srcdoc iframes have a null origin,
			// so a specific origin cannot be used as targetOrigin here.
			NaanoBuilder._iframePost( { type: 'naano-highlight-section', sectionId: sectionId } );
		},

		/**
		 * Submit the update-section request.
		 */
		updateSection: function () {
			var sectionId   = NaanoBuilder.editingSectionId;
			var instruction = $( '#naano-instruction' ).val().trim();

			if ( ! sectionId ) {
				NaanoBuilder._toast( data.strings.select_section, 'error' );
				return;
			}
			if ( ! instruction ) {
				NaanoBuilder._toast( data.strings.enter_instruction, 'error' );
				return;
			}

			NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', true );
			NaanoBuilder._showCanvasLoading();

			// Show loading overlay on the section and dim the canvas.
			NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: sectionId, loading: true } );
			$( '#naano-live-iframe-wrap' ).addClass( 'naano-live-iframe-wrap--loading' );

			$.post( data.ajaxUrl, {
				action:      'naano_update_section',
				nonce:       data.nonce,
				page_id:     NaanoBuilder.pageId,
				section_id:  sectionId,
				instruction: instruction,
				assets:      JSON.stringify( NaanoBuilder.pageAssets ),
				redirects:   JSON.stringify( NaanoBuilder.pageRedirects )
			} )
			.done( function ( response ) {
				NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
				NaanoBuilder._hideCanvasLoading();
				$( '#naano-live-iframe-wrap' ).removeClass( 'naano-live-iframe-wrap--loading' );

				if ( response.success ) {
					var id   = response.data.section_id;
					var html = response.data.section_html;

					// Update in-memory store.
					var idx = NaanoBuilder._findSectionIndex( id );
					if ( idx !== -1 ) {
						NaanoBuilder.sectionsData[ idx ].html = html;
					}

					// Live-update the section inside the iframe via postMessage.
					// The [data-section] wrapper is always present (see _buildIframeSrcdoc),
					// so the innerHTML swap is reliable without a full reload.
					NaanoBuilder._iframePost( {
						type:      'naano-update-section',
						sectionId: id,
						html:      html
					} );

					// Reset the edit panel so the user can start a fresh instruction.
					$( '#naano-instruction' ).val( '' );
					$( '#naano-add-url-form' ).hide();
					NaanoBuilder._renderReferenceList( [] );

					NaanoBuilder._toast( 'Section updated! ✨', 'success' );
				} else {
					// Remove loading overlay on error.
					NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: sectionId, loading: false } );
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
				NaanoBuilder._hideCanvasLoading();
				$( '#naano-live-iframe-wrap' ).removeClass( 'naano-live-iframe-wrap--loading' );
				NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: sectionId, loading: false } );
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
						if ( ! data.references ) { data.references = {}; }
						data.references[ sectionId ] = response.data.references;

						if ( NaanoBuilder.editingSectionId === sectionId ) {
							NaanoBuilder._renderReferenceList( response.data.references );
						}
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
					// Remove from in-memory store and rebuild preview.
					var idx = NaanoBuilder._findSectionIndex( sectionId );
					if ( idx !== -1 ) {
						NaanoBuilder.sectionsData.splice( idx, 1 );
					}

					if ( NaanoBuilder.editingSectionId === sectionId ) {
						NaanoBuilder.editingSectionId = null;
						$( '#naano-editing-section-name' ).text( data.strings.click_section );
						$( '#naano-update-section-btn' ).prop( 'disabled', true );
					}

					NaanoBuilder._refreshLivePreview();
					NaanoBuilder._renderSectionsList();
					NaanoBuilder._toast( 'Section deleted.', 'success' );
				}
			} );
		},

		/**
		 * Send the reorder AJAX call (called after reordering sectionsData).
		 */
		reorderSections: function () {
			var order = NaanoBuilder.sectionsData.map( function ( sec ) { return sec.id; } );

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
			$( '#naano-save-page-title' ).val( $( '#naano-current-page-name' ).text() );
			$( '#naano-save-page-error' ).hide();
			$( '#naano-save-page-modal' ).show();
			setTimeout( function () { $( '#naano-save-page-title' ).select(); }, 60 );
		},

		_doPublishPage: function () {
			var title = $( '#naano-save-page-title' ).val().trim();
			if ( ! title ) {
				$( '#naano-save-page-error' ).text( 'Please enter a page title.' ).show();
				$( '#naano-save-page-title' ).focus();
				return;
			}

			// Build clean HTML from client-side sectionsData — this is reliable
			// regardless of what may or may not be stored in the DB on the server.
			var sectionsHtml = '';
			NaanoBuilder.sectionsData.forEach( function ( sec ) {
				sectionsHtml += sec.html;
			} );
			var escapedTitle = $( '<div>' ).text( title ).html();
			var fullHtml =
				'<!DOCTYPE html><html lang="en"><head>' +
				'<meta charset="UTF-8">' +
				'<meta name="viewport" content="width=device-width,initial-scale=1">' +
				'<title>' + escapedTitle + '</title>' +
				'</head><body>' + sectionsHtml + '</body></html>';

			$( '#naano-save-page-modal' ).hide();
			$( '#naano-save-page-btn' ).prop( 'disabled', true );

			$.post( data.ajaxUrl, {
				action:  'naano_save_as_page',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId,
				title:   title,
				html:    fullHtml
			} )
			.done( function ( response ) {
				$( '#naano-save-page-btn' ).prop( 'disabled', false );
				if ( response.success ) {
					// Update displayed page name in builder header.
					$( '#naano-current-page-name' ).text( response.data.title || title );

					NaanoBuilder._toast(
						'Page published! <a href="' + response.data.view_url + '" target="_blank">View it</a> · <a href="' + response.data.edit_url + '" target="_blank">Edit in WP</a>',
						'success',
						6000
					);

					// Set as homepage if checkbox was checked.
					if ( $( '#naano-set-homepage-chk' ).is( ':checked' ) ) {
						$.post( data.ajaxUrl, {
							action:  'naano_set_homepage',
							nonce:   data.nonce,
							page_id: NaanoBuilder.pageId
						} ).done( function ( res ) {
							if ( res.success ) {
								NaanoBuilder._toast( 'Set as homepage!', 'success', 3500 );
							}
						} );
					}
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				$( '#naano-save-page-btn' ).prop( 'disabled', false );
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

		// =====================================================================
		// Private helpers
		// =====================================================================

		_bindGenerationForm: function () {
			$( document ).on( 'click', '#naano-generate-btn', function () {
				NaanoBuilder.generateSite();
			} );

			$( document ).on( 'click', '#naano-add-custom-section', function () {
				var $input = $( '#naano-custom-section-input' );
				var $error = $( '#naano-custom-section-error' );
				var name   = $input.val().trim();

				if ( ! name ) {
					$error.show();
					$input.focus();
					return;
				}
				$error.hide();

				var slug = name.toLowerCase().replace( /\s+/g, '-' ).replace( /[^a-z0-9-]/g, '' );
				if ( ! slug ) { return; }

				var $label = $( '<label class="naano-checkbox-label naano-checkbox-label--custom">' );
				$label.append(
					$( '<input>', { type: 'checkbox', name: 'sections[]', value: slug, checked: true } ),
					document.createTextNode( ' ' + name + ' ' ),
					$( '<button>', {
						type: 'button',
						'class': 'naano-remove-custom-section',
						title: 'Remove'
					} ).text( '×' )
				);
				$( '#naano-section-checkboxes' ).append( $label );
				$input.val( '' );
			} );

			$( document ).on( 'click', '.naano-remove-custom-section', function ( e ) {
				e.preventDefault();
				$( this ).closest( 'label' ).remove();
			} );

			$( document ).on( 'keydown', '#naano-custom-section-input', function ( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					$( '#naano-add-custom-section' ).trigger( 'click' );
				}
			} );
		},

		_bindActionBar: function () {
			$( document ).on( 'click', '#naano-preview-btn',   function () { NaanoBuilder.previewSite(); } );
			$( document ).on( 'click', '#naano-export-btn',    function () { NaanoBuilder.exportHtml(); } );
			$( document ).on( 'click', '#naano-copy-btn',      function () { NaanoBuilder.copyToClipboard(); } );
			$( document ).on( 'click', '#naano-save-page-btn', function () { NaanoBuilder.saveAsPage(); } );

			// Publish page modal handlers.
			$( document ).on( 'click', '#naano-save-page-confirm-btn', function () {
				NaanoBuilder._doPublishPage();
			} );
			$( document ).on( 'click', '#naano-save-page-cancel-btn', function () {
				$( '#naano-save-page-modal' ).hide();
			} );
			// Close on backdrop click.
			$( document ).on( 'click', '#naano-save-page-modal', function ( e ) {
				if ( $( e.target ).is( '#naano-save-page-modal' ) ) {
					$( '#naano-save-page-modal' ).hide();
				}
			} );
			$( document ).on( 'keydown', '#naano-save-page-title', function ( e ) {
				if ( e.key === 'Enter' )  { NaanoBuilder._doPublishPage(); }
				if ( e.key === 'Escape' ) { $( '#naano-save-page-modal' ).hide(); }
			} );
		},

		_bindDrawer: function () {
			$( document ).on( 'click', '#naano-drawer-toggle', function () {
				$( '#naano-drawer' ).toggleClass( 'naano-drawer--collapsed' );
			} );
		},

		_bindViewportToggle: function () {
			$( document ).on( 'click', '#naano-viewport-group .naano-viewport-btn', function () {
				var width = $( this ).data( 'width' );
				$( '#naano-viewport-group .naano-viewport-btn' ).removeClass( 'naano-viewport-btn--active' );
				$( this ).addClass( 'naano-viewport-btn--active' );

				var $iframe = $( '#naano-live-preview' );
				if ( width === '100%' ) {
					$iframe.css( { 'max-width': '100%', width: '100%' } );
				} else {
					$iframe.css( { 'max-width': width, width: width } );
				}
			} );
		},

		_bindEditPanel: function () {
			$( document ).on( 'click', '#naano-update-section-btn', function () {
				NaanoBuilder.updateSection();
			} );

			$( document ).on( 'click', '#naano-add-screenshot-btn', function () {
				if ( ! NaanoBuilder.editingSectionId ) {
					NaanoBuilder._toast( data.strings.select_section, 'error' );
					return;
				}
				NaanoBuilder.addScreenshot( NaanoBuilder.editingSectionId );
			} );

			$( document ).on( 'click', '#naano-add-url-btn', function () {
				if ( ! NaanoBuilder.editingSectionId ) {
					NaanoBuilder._toast( data.strings.select_section, 'error' );
					return;
				}
				NaanoBuilder.addUrlReference( NaanoBuilder.editingSectionId );
			} );

			$( document ).on( 'click', '#naano-save-url-btn', function () {
				NaanoBuilder.saveUrlReference( NaanoBuilder.editingSectionId );
			} );

			$( document ).on( 'click', '#naano-cancel-url-btn', function () {
				$( '#naano-add-url-form' ).hide();
			} );

			$( document ).on( 'click', '.naano-remove-ref-btn', function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				NaanoBuilder.removeReference( NaanoBuilder.editingSectionId, idx );
			} );

			$( document ).on( 'click', '#naano-add-new-section-btn', function () {
				$( '#naano-add-new-section-btn' ).hide();
				$( '#naano-new-section-form' ).show();
				$( '#naano-new-section-name' ).val( '' ).focus();
				$( '#naano-new-section-error' ).hide();
			} );

			$( document ).on( 'click', '#naano-cancel-new-section-btn', function () {
				$( '#naano-new-section-form' ).hide();
				$( '#naano-new-section-error' ).hide();
				$( '#naano-add-new-section-btn' ).show();
			} );

			$( document ).on( 'click', '#naano-confirm-new-section-btn', function () {
				var name = $( '#naano-new-section-name' ).val().trim();
				if ( ! name ) {
					$( '#naano-new-section-error' )
						.text( 'Please enter a section name.' )
						.show();
					$( '#naano-new-section-name' ).focus();
					return;
				}
				$( '#naano-new-section-form' ).hide();
				$( '#naano-new-section-error' ).hide();
				$( '#naano-add-new-section-btn' ).show();
				NaanoBuilder._addNewSection( name );
			} );

			$( document ).on( 'keydown', '#naano-new-section-name', function ( e ) {
				if ( e.key === 'Enter' ) { $( '#naano-confirm-new-section-btn' ).trigger( 'click' ); }
				if ( e.key === 'Escape' ) { $( '#naano-cancel-new-section-btn' ).trigger( 'click' ); }
			} );
		},

		/**
		 * Listen for postMessage events from the live-preview iframe.
		 */
		_bindIframeMessages: function () {
			window.addEventListener( 'message', function ( e ) {
				var msg = e.data;
				if ( ! msg || msg.type !== 'naano-section-clicked' ) { return; }

				var sectionId = msg.sectionId;
				if ( sectionId && NaanoBuilder.pageId ) {
					NaanoBuilder.openEditPanel( sectionId );
				}
			} );
		},

		/**
		 * Build the full-page srcdoc HTML from sectionsData and set on the iframe.
		 */
		_refreshLivePreview: function () {
			var iframe = document.getElementById( 'naano-live-preview' );
			if ( ! iframe ) { return; }

			iframe.srcdoc = NaanoBuilder._buildIframeSrcdoc();
		},

		/**
		 * Assemble all sections into a full HTML document with the interaction helper script injected.
		 *
		 * @return {string}
		 */
		_buildIframeSrcdoc: function () {
			var sectionsHtml = '';
			NaanoBuilder.sectionsData.forEach( function ( sec ) {
				// Wrap each section so [data-section] is always present in the iframe
				// for click detection, highlight, loading overlay and live HTML updates.
				sectionsHtml += '<div data-section="' + sec.id + '">' + sec.html + '</div>';
			} );

			// Interaction helper script injected into the iframe.
			// - Listens for postMessage from parent (update section, highlight, loading).
			// - Reports section clicks back to parent via postMessage.
			var helperScript = [
				'(function(){',
				'var s=document.createElement("style");',
				's.textContent=',
				'"[data-section]{cursor:pointer;transition:outline 0.15s;}"',
				'+"[data-section]:hover{outline:2px dashed rgba(34,113,177,0.5);outline-offset:2px;}"',
				'+"[data-section].naano-section-selected{outline:2px solid #2271b1;outline-offset:2px;}"',
				'+"[data-section].naano-section-loading{position:relative;pointer-events:none;}"',
				'+"[data-section].naano-section-loading::after{content:\'\';position:absolute;inset:0;background:rgba(255,255,255,0.65);z-index:9999;animation:naano-pulse 1s infinite;}"',
				'+"@keyframes naano-pulse{0%,100%{opacity:0.5;}50%{opacity:1;}}"',
				'+"@keyframes naano-flash{0%{box-shadow:inset 0 0 0 3px rgba(34,113,177,0.7);}100%{box-shadow:none;}}";',
				'document.head.appendChild(s);',

				// Listen for messages from parent.
				'window.addEventListener("message",function(e){',
				'  var m=e.data;if(!m||!m.type)return;',

				// Update a specific section's HTML (el is always the [data-section] wrapper).
				'  if(m.type==="naano-update-section"){',
				'    var el=document.querySelector(\'[data-section="\'+m.sectionId+\'"]\');',
				'    if(el){',
				'      el.classList.remove("naano-section-loading");',
				'      el.innerHTML=m.html;',
				'      el.style.animation="naano-flash 1.5s ease forwards";',
				'      setTimeout(function(){el.style.animation="";},1600);',
				'    }',
				'  }',

				// Highlight a section.
				'  if(m.type==="naano-highlight-section"){',
				'    document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
				'    var el=document.querySelector(\'[data-section="\'+m.sectionId+\'"]\');',
				'    if(el)el.classList.add("naano-section-selected");',
				'  }',

				// Show/hide loading overlay on a section.
				'  if(m.type==="naano-loading-section"){',
				'    var el=document.querySelector(\'[data-section="\'+m.sectionId+\'"]\');',
				'    if(el){',
				'      if(m.loading)el.classList.add("naano-section-loading");',
				'      else el.classList.remove("naano-section-loading");',
				'    }',
				'  }',
				'});',

				// Report section clicks to parent.
				'document.addEventListener("click",function(e){',
				'  var el=e.target;',
				'  while(el&&el!==document.body){',
				'    if(el.hasAttribute("data-section")){',
				'      e.preventDefault();',
				'      document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
				'      el.classList.add("naano-section-selected");',
				'      window.parent.postMessage({type:"naano-section-clicked",sectionId:el.getAttribute("data-section")},"*");',
				'      break;',
				'    }',
				'    el=el.parentElement;',
				'  }',
				'});',
				'}());'
			].join( '' );

			return '<!DOCTYPE html><html><head>' +
				'<meta charset="utf-8">' +
				'<meta name="viewport" content="width=device-width,initial-scale=1">' +
				'</head><body>' +
				sectionsHtml +
				'<script>' + helperScript + '<\/script>' +
				'</body></html>';
		},

		/**
		 * Post a message to the live-preview iframe.
		 *
		 * NOTE: We intentionally use '*' as targetOrigin because the iframe is loaded
		 * via srcdoc, which gives it a null (opaque) origin. A specific origin cannot
		 * be used as targetOrigin for null-origin iframes, so '*' is required here.
		 *
		 * @param {Object} msg
		 */
		_iframePost: function ( msg ) {
			var iframe = document.getElementById( 'naano-live-preview' );
			if ( iframe && iframe.contentWindow ) {
				iframe.contentWindow.postMessage( msg, '*' );
			}
		},

		/**
		 * Render the section quick-select list in the drawer.
		 */
		_renderSectionsList: function () {
			var $list = $( '#naano-sections-list' ).empty();

			NaanoBuilder.sectionsData.forEach( function ( sec ) {
				var displayName = NaanoBuilder._displayName( sec.id );

				var $item = $( '<li>', {
					'class':     'naano-sections-list__item',
					'id':        'naano-sl-item-' + sec.id,
					'draggable': 'true',
					'data-id':   sec.id
				} );

				$item.html(
					'<span class="naano-sections-list__handle" title="Drag to reorder">⠿</span>' +
					'<span class="naano-sections-list__name">' + $( '<span>' ).text( displayName ).html() + '</span>' +
					'<span class="naano-sections-list__actions">' +
					'<button type="button" class="naano-sections-list__btn" data-action="edit" data-id="' + sec.id + '" title="Edit">✏️</button>' +
					'<button type="button" class="naano-sections-list__btn naano-sections-list__btn--delete" data-action="delete" data-id="' + sec.id + '" title="Delete">🗑️</button>' +
					'</span>'
				);

				// Click on the item row selects the section.
				$item.on( 'click', function ( e ) {
					if ( $( e.target ).closest( '[data-action], .naano-sections-list__handle' ).length ) { return; }
					NaanoBuilder.openEditPanel( sec.id );
				} );

				// Action buttons.
				$item.find( '[data-action=edit]' ).on( 'click', function ( e ) {
					e.stopPropagation();
					NaanoBuilder.openEditPanel( sec.id );
				} );

				$item.find( '[data-action=delete]' ).on( 'click', function ( e ) {
					e.stopPropagation();
					NaanoBuilder.deleteSection( sec.id );
				} );

				// ── Drag-and-drop reordering ──────────────────────────────────
				var el = $item.get( 0 );

				el.addEventListener( 'dragstart', function ( e ) {
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData( 'text/plain', sec.id );
					setTimeout( function () { $item.addClass( 'naano-sections-list__item--dragging' ); }, 0 );
				} );

				el.addEventListener( 'dragend', function () {
					$item.removeClass( 'naano-sections-list__item--dragging' );
					$( '#naano-sections-list .naano-sections-list__item' )
						.removeClass( 'naano-sections-list__item--dragover' );
				} );

				el.addEventListener( 'dragover', function ( e ) {
					e.preventDefault();
					e.dataTransfer.dropEffect = 'move';
					$( '#naano-sections-list .naano-sections-list__item' )
						.removeClass( 'naano-sections-list__item--dragover' );
					$item.addClass( 'naano-sections-list__item--dragover' );
				} );

				el.addEventListener( 'dragleave', function ( e ) {
					if ( ! el.contains( e.relatedTarget ) ) {
						$item.removeClass( 'naano-sections-list__item--dragover' );
					}
				} );

				el.addEventListener( 'drop', function ( e ) {
					e.preventDefault();
					$item.removeClass( 'naano-sections-list__item--dragover' );

					var fromId = e.dataTransfer.getData( 'text/plain' );
					var toId   = sec.id;
					if ( fromId === toId ) { return; }

					var fromIdx = NaanoBuilder._findSectionIndex( fromId );
					var toIdx   = NaanoBuilder._findSectionIndex( toId );
					if ( fromIdx === -1 || toIdx === -1 ) { return; }

					// Move dragged item to drop target position.
					var moved = NaanoBuilder.sectionsData.splice( fromIdx, 1 )[ 0 ];
					NaanoBuilder.sectionsData.splice( toIdx, 0, moved );

					NaanoBuilder._refreshLivePreview();
					NaanoBuilder._renderSectionsList();
					NaanoBuilder.reorderSections();
				} );

				$list.append( $item );
			} );
		},

		/**
		 * Add a new section by prompting the AI.
		 *
		 * @param {string} sectionName
		 */
		_addNewSection: function ( sectionName ) {
			// Use updateSection mechanism: a new section_id and instruction.
			var instruction = 'Create a new "' + sectionName + '" section for this website.';
			var slug        = sectionName.toLowerCase().replace( /\s+/g, '-' ).replace( /[^a-z0-9-]/g, '' );

			NaanoBuilder._setLoading( '#naano-add-new-section-btn', '#naano-update-loading', true );
			NaanoBuilder._showCanvasLoading();

			$.post( data.ajaxUrl, {
				action:      'naano_update_section',
				nonce:       data.nonce,
				page_id:     NaanoBuilder.pageId,
				section_id:  slug,
				instruction: instruction
			} )
			.done( function ( response ) {
				NaanoBuilder._setLoading( '#naano-add-new-section-btn', '#naano-update-loading', false );
				NaanoBuilder._hideCanvasLoading();
				if ( response.success ) {
					var id   = response.data.section_id;
					var html = response.data.section_html;

					var idx = NaanoBuilder._findSectionIndex( id );
					if ( idx !== -1 ) {
						NaanoBuilder.sectionsData[ idx ].html = html;
					} else {
						NaanoBuilder.sectionsData.push( { id: id, html: html } );
					}

					NaanoBuilder._refreshLivePreview();
					NaanoBuilder._renderSectionsList();
					NaanoBuilder._toast( 'Section "' + sectionName + '" added! ✨', 'success' );
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-add-new-section-btn', '#naano-update-loading', false );
				NaanoBuilder._hideCanvasLoading();
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

		_showBuilder: function () {
			$( '#naano-drawer-generate' ).hide();
			$( '#naano-drawer-edit' ).show();
			$( '#naano-canvas-placeholder' ).hide();
			$( '#naano-live-iframe-wrap' ).show();
		},

		_showCanvasLoading: function () {
			var $overlay = $( '#naano-canvas-loading-overlay' );
			$overlay.addClass( 'naano-canvas-loading-overlay--visible' );
			var vid = $overlay.find( 'video' ).get( 0 );
			if ( vid ) { vid.currentTime = 0; vid.play(); }
		},

		_hideCanvasLoading: function () {
			var $overlay = $( '#naano-canvas-loading-overlay' );
			$overlay.removeClass( 'naano-canvas-loading-overlay--visible' );
			var vid = $overlay.find( 'video' ).get( 0 );
			if ( vid ) { vid.pause(); }
		},

		_bindAssetsPanel: function () {
			// "From URL" button — show the URL form.
			$( document ).on( 'click', '#naano-add-asset-btn', function () {
				if ( ! NaanoBuilder.editingSectionId ) {
					NaanoBuilder._toast( data.strings.select_section, 'error' );
					return;
				}
				$( '#naano-add-asset-form' ).show();
				$( '#naano-asset-picker' ).hide();
				$( '#naano-asset-url' ).focus();
			} );

			// "From Media Library" button.
			$( document ).on( 'click', '#naano-add-asset-media-btn', function () {
				if ( ! NaanoBuilder.editingSectionId ) {
					NaanoBuilder._toast( data.strings.select_section, 'error' );
					return;
				}
				NaanoBuilder.addAssetFromMedia();
			} );

			$( document ).on( 'click', '#naano-cancel-asset-btn', function () {
				$( '#naano-add-asset-form' ).hide().find( 'input' ).val( '' );
				$( '#naano-asset-picker' ).show();
			} );

			$( document ).on( 'click', '#naano-save-asset-btn', function () {
				var url  = $( '#naano-asset-url' ).val().trim();
				var desc = $( '#naano-asset-desc' ).val().trim();
				if ( ! url ) { $( '#naano-asset-url' ).focus(); return; }
				NaanoBuilder.pageAssets.push( { url: url, desc: desc } );
				NaanoBuilder._renderAssetList();
				$( '#naano-add-asset-form' ).hide().find( 'input' ).val( '' );
				$( '#naano-asset-picker' ).show();
			} );

			$( document ).on( 'click', '.naano-remove-asset-btn', function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				NaanoBuilder.pageAssets.splice( idx, 1 );
				NaanoBuilder._renderAssetList();
			} );

			$( document ).on( 'keydown', '#naano-asset-url, #naano-asset-desc', function ( e ) {
				if ( e.key === 'Enter' ) { e.preventDefault(); $( '#naano-save-asset-btn' ).trigger( 'click' ); }
			} );
		},

		_renderAssetList: function () {
			var $list = $( '#naano-asset-list' ).empty();
			NaanoBuilder.pageAssets.forEach( function ( asset, i ) {
				var label = '#' + ( i + 1 ) + ': ' + asset.url + ( asset.desc ? ' — ' + asset.desc : '' );
				$list.append(
					$( '<li class="naano-reference-item">' )
						.append( $( '<span>' ).text( label ) )
						.append( ' <button type="button" class="naano-remove-ref-btn naano-remove-asset-btn" data-index="' + i + '">✕</button>' )
				);
			} );
		},

		/**
		 * Open the WP media library to pick an asset URL.
		 */
		addAssetFromMedia: function () {
			var frame = wp.media( {
				title:    'Select Asset',
				button:   { text: 'Use this file' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				NaanoBuilder.pageAssets.push( { url: attachment.url, desc: attachment.title || '' } );
				NaanoBuilder._renderAssetList();
				NaanoBuilder._toast( 'Asset added!', 'success' );
			} );

			frame.open();
		},

		_bindRedirectsPanel: function () {
			$( document ).on( 'click', '#naano-add-redirect-btn', function () {
				if ( ! NaanoBuilder.editingSectionId ) {
					NaanoBuilder._toast( data.strings.select_section, 'error' );
					return;
				}
				$( '#naano-add-redirect-form' ).show();
				$( '#naano-add-redirect-btn' ).hide();
				$( '#naano-redirect-label' ).focus();
			} );

			$( document ).on( 'click', '#naano-cancel-redirect-btn', function () {
				$( '#naano-add-redirect-form' ).hide().find( 'input' ).val( '' );
				$( '#naano-add-redirect-btn' ).show();
			} );

			$( document ).on( 'click', '#naano-save-redirect-btn', function () {
				var label = $( '#naano-redirect-label' ).val().trim();
				var url   = $( '#naano-redirect-url' ).val().trim();
				if ( ! label || ! url ) {
					$( ! label ? '#naano-redirect-label' : '#naano-redirect-url' ).focus();
					return;
				}
				NaanoBuilder.pageRedirects.push( { label: label, url: url } );
				NaanoBuilder._renderRedirectList();
				$( '#naano-add-redirect-form' ).hide().find( 'input' ).val( '' );
				$( '#naano-add-redirect-btn' ).show();
			} );

			$( document ).on( 'click', '.naano-remove-redirect-btn', function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				NaanoBuilder.pageRedirects.splice( idx, 1 );
				NaanoBuilder._renderRedirectList();
			} );

			$( document ).on( 'keydown', '#naano-redirect-label, #naano-redirect-url', function ( e ) {
				if ( e.key === 'Enter' ) { e.preventDefault(); $( '#naano-save-redirect-btn' ).trigger( 'click' ); }
			} );
		},

		_renderRedirectList: function () {
			var $list = $( '#naano-redirect-list' ).empty();
			NaanoBuilder.pageRedirects.forEach( function ( redirect, i ) {
				var label = redirect.label + ' → ' + redirect.url;
				$list.append(
					$( '<li class="naano-reference-item">' )
						.append( $( '<span>' ).text( label ) )
						.append( ' <button type="button" class="naano-remove-ref-btn naano-remove-redirect-btn" data-index="' + i + '">✕</button>' )
				);
			} );
		},

		_renderReferenceList: function ( refs ) {
			var $screenshots = $( '#naano-screenshot-list' ).empty();
			var $urls        = $( '#naano-url-list' ).empty();

			refs.forEach( function ( ref, index ) {
				var $li = $( '<li class="naano-reference-item">' );

				if ( ref.type === 'screenshot' ) {
					var thumb = $( '<img>' ).attr( 'src', ref.url ).addClass( 'naano-ref-thumb' );
					$li.append( thumb );
					$li.append( $( '<span>' ).text( ref.notes || ref.url ) );
					$li.append( ' <button type="button" class="naano-remove-ref-btn" data-index="' + index + '">✕</button>' );
					$screenshots.append( $li );
				} else {
					var $link = $( '<a>' ).attr( { href: ref.url, target: '_blank' } ).text( ref.url );
					$li.append( $link );
					if ( ref.notes ) {
						$li.append( ' — ' + $( '<span>' ).text( ref.notes ).html() );
					}
					$li.append( ' <button type="button" class="naano-remove-ref-btn" data-index="' + index + '">✕</button>' );
					$urls.append( $li );
				}
			} );
		},

		_setLoading: function ( btnSelector, loadSelector, loading ) {
			$( btnSelector ).prop( 'disabled', loading );
			$( loadSelector ).toggle( loading );
		},

		_findSectionIndex: function ( id ) {
			for ( var i = 0; i < NaanoBuilder.sectionsData.length; i++ ) {
				if ( NaanoBuilder.sectionsData[ i ].id === id ) { return i; }
			}
			return -1;
		},

		_displayName: function ( id ) {
			return id
				.replace( /[-_]/g, ' ' )
				.replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );
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

	// Expose globally.
	window.NaanoBuilder = NaanoBuilder;

}( jQuery, naanoBuilderData ) );
