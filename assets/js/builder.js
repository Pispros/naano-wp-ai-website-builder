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

		/** @type {string[]} All currently selected section IDs (multi-select). */
		editingSectionIds: [],

		/** @type {Array<{id: string, html: string}>} In-memory sections store. */
		sectionsData: [],

		/** @type {Array<{url: string, desc: string}>} Page-level asset URLs. */
		pageAssets: [],

		/** @type {Array<{label: string, url: string}>} Page-level URL redirections. */
		pageRedirects: [],

		/** @type {Object|null} Active code-animation state (timers, counters). */
		_claState: null,

		/** @type {Array<{id: string, type: string, html: string}>} Sections imported from other pages (pre-seeded, no LLM generation). */
		importedSections: [],

		/** @type {Array<{url: string, notes: string}>} URL references for initial generation. */
		initialReferences: [],

		/** @type {boolean} Whether element-inspect mode is active. */
		inspectModeActive: false,

		/** @type {string|null} data-naano-el ID of the currently selected element. */
		selectedElId: null,

		/** @type {string|null} Section ID that contains the selected element. */
		selectedElSectionId: null,

		/**
		 * Initialise the builder.
		 */
		init: function () {
			NaanoBuilder.pageId = parseInt( data.pageId, 10 ) || 0;

			// Activate full-screen layout.
			$( 'body' ).addClass( 'naano-fullscreen' );

			// Inject code-generation loading overlay.
			( function () {
				if ( ! document.getElementById( 'naano-cla-fonts' ) ) {
					var link = document.createElement( 'link' );
					link.id   = 'naano-cla-fonts';
					link.rel  = 'stylesheet';
					link.href = 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap';
					document.head.appendChild( link );
				}
				$( '#naano-vb-canvas' ).append(
					'<div class="naano-canvas-loading-overlay" id="naano-canvas-loading-overlay">' +
					'<canvas class="naano-cla-rain" id="naano-cla-rain"></canvas>' +
					'<div class="naano-cla-scanline"></div>' +
					'<div class="naano-cla-wrapper">' +
					'<div class="naano-cla-card">' +
					'<div class="naano-cla-titlebar">' +
					'<div class="naano-cla-dots"><div class="naano-cla-dot"></div><div class="naano-cla-dot"></div><div class="naano-cla-dot"></div></div>' +
					'<span class="naano-cla-title-label" id="naano-cla-title-text">output.html — generating</span>' +
					'<span class="naano-cla-badge">LLM ✶</span>' +
					'</div>' +
					'<div class="naano-cla-status-row">' +
					'<div class="naano-cla-pulse"></div>' +
					'<span class="naano-cla-status-text">Generating<span class="naano-cla-ellipsis"><span>.</span><span>.</span><span>.</span></span></span>' +
					'<span class="naano-cla-token-count">tokens: <span id="naano-cla-token-count">0</span></span>' +
					'</div>' +
					'<div class="naano-cla-progress-wrap"><div class="naano-cla-progress-track"><div class="naano-cla-progress-fill" id="naano-cla-progress-fill"></div></div></div>' +
					'<div class="naano-cla-code-area">' +
					'<div class="naano-cla-code-header">' +
					'<span class="naano-cla-lang-tag">HTML/CSS/JS</span>' +
					'<span id="naano-cla-filename">output.html</span>' +
					'<span class="naano-cla-line-count">lines: <span id="naano-cla-line-count">0</span></span>' +
					'</div>' +
					'<div class="naano-cla-code-scroll"><div class="naano-cla-code-lines" id="naano-cla-code-lines"></div></div>' +
					'</div>' +
					'<div class="naano-cla-meta-row">' +
					'<div class="naano-cla-meta-item"><span class="naano-cla-meta-label">Speed</span>' +
					'<span class="naano-cla-meta-value naano-cla-meta-value--cyan" id="naano-cla-speed">0</span>' +
					'<span class="naano-cla-meta-label">tok/s</span></div>' +
					'<div class="naano-cla-meta-item"><span class="naano-cla-meta-label">Model</span>' +
					'<span class="naano-cla-meta-value naano-cla-meta-value--purple" id="naano-cla-model-main"></span>' +
					'<span class="naano-cla-meta-label" id="naano-cla-model-ver"></span></div>' +
					'<div class="naano-cla-meta-item"><span class="naano-cla-meta-label">Elapsed</span>' +
					'<span class="naano-cla-meta-value naano-cla-meta-value--green" id="naano-cla-elapsed">0.0</span>' +
					'<span class="naano-cla-meta-label">seconds</span></div>' +
					'</div>' +
					'</div></div></div>'
				);
				// Set model name safely via text() to prevent XSS.
				var m    = data.modelLabel || 'llm';
				var dash = m.lastIndexOf( '-' );
				$( '#naano-cla-model-main' ).text( dash > 0 ? m.slice( 0, dash ) : m );
				$( '#naano-cla-model-ver' ).text( dash > 0 ? m.slice( dash + 1 ) : '' );
			}() );

			NaanoBuilder._bindGenerationForm();
			NaanoBuilder._bindImportComponents();
			NaanoBuilder._bindActionBar();
			NaanoBuilder._bindDrawer();
			NaanoBuilder._bindViewportToggle();
			NaanoBuilder._bindEditPanel();
			NaanoBuilder._bindAssetsPanel();
			NaanoBuilder._bindRedirectsPanel();
			NaanoBuilder._bindElementInspector();
			NaanoBuilder._bindIframeMessages();
			NaanoBuilder._bindLangSwitcher();

			// If we already have sections (page reload), render them.
			if ( data.sections && data.sections.length > 0 ) {
				NaanoBuilder.sectionsData = data.sections.slice();
				NaanoBuilder._showBuilder();
				NaanoBuilder._refreshLivePreview();
				NaanoBuilder._renderSectionsList();
			}

			// Restore persisted assets & redirects.
			if ( data.assets && data.assets.length ) {
				NaanoBuilder.pageAssets = data.assets.slice();
				NaanoBuilder._renderAssetList();
			}
			if ( data.redirects && data.redirects.length ) {
				NaanoBuilder.pageRedirects = data.redirects.slice();
				NaanoBuilder._renderRedirectList();
			}
		},

		// =====================================================================
		// Site generation
		// =====================================================================

		/**
		 * Enhance a prompt (description or instruction) using the LLM.
		 *
		 * @param {string} context 'initial' or 'edit'
		 */
		enhancePrompt: function ( context ) {
			var isInitial = context === 'initial';
			var $textarea = isInitial ? $( '#naano-description' ) : $( '#naano-instruction' );
			var $btn      = isInitial ? $( '#naano-enhance-description-btn' ) : $( '#naano-enhance-instruction-btn' );
			var $loading  = isInitial ? $( '#naano-enhance-description-loading' ) : $( '#naano-enhance-instruction-loading' );
			var rawText   = $textarea.val().trim();

			if ( ! rawText ) {
				NaanoBuilder._toast( isInitial ? data.strings.enter_description : data.strings.enter_instruction, 'error' );
				$textarea.focus();
				return;
			}

			$btn.prop( 'disabled', true );
			$loading.show();

			$.post( data.ajaxUrl, {
				action:    'naano_enhance_prompt',
				nonce:     data.nonce,
				raw_text:  rawText,
				context:   context,
				page_name: $( '#naano-page-name' ).val() || ''
			} )
			.done( function ( response ) {
				$btn.prop( 'disabled', false );
				$loading.hide();

				if ( response && response.success ) {
					$textarea.val( response.data.enhanced_text );

					// For initial context, auto-check suggested sections.
					if ( isInitial && response.data.suggested_sections ) {
						var suggested = response.data.suggested_sections;

						// Uncheck all first.
						$( '#naano-section-checkboxes input[type=checkbox]' ).prop( 'checked', false );

						// Check suggested ones.
						suggested.forEach( function ( sectionName ) {
							var slug = sectionName.replace( /\s+/g, '-' ).replace( /[^a-z0-9-]/g, '' );
							var $cb  = $( '#naano-section-checkboxes input[value="' + slug + '"]' );
							if ( $cb.length ) {
								$cb.prop( 'checked', true );
							} else if ( slug ) {
								// Add as custom section if not in the default list.
								var label = sectionName.charAt( 0 ).toUpperCase() + sectionName.slice( 1 ).replace( /-/g, ' ' );
								var $label = $( '<label class="naano-checkbox-label naano-checkbox-label--custom">' );
								$label.append(
									$( '<input>', { type: 'checkbox', name: 'sections[]', value: slug, checked: true } ),
									document.createTextNode( ' ' + label + ' ' ),
									$( '<button>', {
										type: 'button',
										'class': 'naano-remove-custom-section',
										title: 'Remove'
									} ).text( '\u00d7' )
								);
								$( '#naano-section-checkboxes' ).append( $label );
							}
						} );
					}

					NaanoBuilder._toast( isInitial ? 'Prompt enhanced & sections suggested!' : 'Instruction enhanced!', 'success' );
				} else {
					NaanoBuilder._toast( ( response && response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				$btn.prop( 'disabled', false );
				$loading.hide();
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

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

			if ( sections.length === 0 && NaanoBuilder.importedSections.length === 0 ) {
				$( '#naano-sections-error' ).show();
				if ( valid ) { $( '#naano-section-checkboxes' ).find( 'input' ).first().focus(); }
				valid = false;
			} else {
				$( '#naano-sections-error' ).hide();
			}

			if ( ! valid ) { return; }

			NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', true );
			NaanoBuilder._showCanvasLoading( { filename: ( pageName || 'output' ) + '.html' } );

			$.post( data.ajaxUrl, {
				action:             'naano_generate_site',
				nonce:              data.nonce,
				page_id:            NaanoBuilder.pageId,
				page_name:          pageName,
				description:        description,
				sections:           sections,
				imported_sections:  JSON.stringify( NaanoBuilder.importedSections ),
				initial_references: JSON.stringify( NaanoBuilder.initialReferences )
			} )
			.done( function ( response ) {
				NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', false );
				NaanoBuilder._hideCanvasLoading();
				if ( response && response.success ) {
					NaanoBuilder.pageId = response.data.page_id || NaanoBuilder.pageId;

					if ( Array.isArray( response.data.sections ) ) {
						NaanoBuilder.sectionsData = response.data.sections.slice();
					} else {
						NaanoBuilder.sectionsData = [];
						$.each( response.data.sections, function ( id, html ) {
							NaanoBuilder.sectionsData.push( { id: id, html: html } );
						} );
					}

					if ( pageName ) {
						$( '#naano-current-page-name' ).text( pageName );
					}

					NaanoBuilder._showBuilder();
					NaanoBuilder._refreshLivePreview();
					NaanoBuilder._renderSectionsList();
					NaanoBuilder._toast( 'Website generated successfully! 🎉', 'success' );
					NaanoBuilder.importedSections = [];
					NaanoBuilder.initialReferences = [];
					$( '.naano-import-section-btn' )
						.removeClass( 'naano-import-section-btn--selected' )
						.find( '.naano-import-check' ).hide();
				} else {
					NaanoBuilder._toast( ( response && response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', false );
				NaanoBuilder._hideCanvasLoading();
				NaanoBuilder._toast( data.strings.error_generic, 'error' );
			} );
		},

		/**
		 * Recursively generate each section in sequence (initial site generation).
		 * Each call produces one LLM request so no individual request can time out.
		 *
		 * @param {string[]} sections      Ordered list of section type names.
		 * @param {string}   description   Site brief.
		 * @param {number}   index         Current position in the list.
		 * @param {number}   [successCount] Sections successfully generated so far.
		 * @param {string}   [lastError]    Last error message received, if any.
		 */
		_generateSectionsSequential: function ( sections, description, index, successCount, lastError ) {
			successCount = successCount || 0;
			lastError    = lastError    || '';

			if ( index >= sections.length ) {
				NaanoBuilder._setLoading( '#naano-generate-btn', '#naano-generate-loading', false );
				NaanoBuilder._hideCanvasLoading();
				NaanoBuilder.importedSections = [];
				$( '.naano-import-section-btn' )
					.removeClass( 'naano-import-section-btn--selected' )
					.find( '.naano-import-check' ).hide();

				if ( successCount > 0 ) {
					var msg = successCount === sections.length
						? 'Website generated successfully! 🎉'
						: successCount + ' of ' + sections.length + ' sections generated. ⚠️';
					NaanoBuilder._toast( msg, successCount === sections.length ? 'success' : 'warning' );
				} else {
					NaanoBuilder._toast(
						'Generation failed: ' + ( lastError || 'unknown error — check API settings.' ),
						'error'
					);
				}
				return;
			}

			var sectionType = sections[ index ];
			var label = sectionType.charAt( 0 ).toUpperCase() + sectionType.slice( 1 ).replace( /-/g, ' ' );

			$( '#naano-cla-title-text' ).text( label + ' (' + ( index + 1 ) + '/' + sections.length + ') — generating' );
			$( '#naano-cla-filename' ).text( sectionType + '.html' );

			$.post( data.ajaxUrl, {
				action:       'naano_generate_site_section',
				nonce:        data.nonce,
				page_id:      NaanoBuilder.pageId,
				description:  description,
				section_type: sectionType
			} )
			.done( function ( response ) {
				if ( response && response.success ) {
					var id   = response.data.section_id;
					var html = response.data.section_html;

					var existing = NaanoBuilder._findSectionIndex( id );
					if ( existing === -1 ) {
						NaanoBuilder.sectionsData.push( { id: id, html: html } );
					} else {
						NaanoBuilder.sectionsData[ existing ].html = html;
					}

					NaanoBuilder._refreshLivePreview();
					NaanoBuilder._renderSectionsList();
					NaanoBuilder._generateSectionsSequential( sections, description, index + 1, successCount + 1, lastError );
				} else {
					var errMsg = ( response && response.data && response.data.message )
						? response.data.message
						: data.strings.error_generic;
					NaanoBuilder._toast( label + ': ' + errMsg, 'error' );
					NaanoBuilder._generateSectionsSequential( sections, description, index + 1, successCount, errMsg );
				}
			} )
			.fail( function () {
				NaanoBuilder._toast( label + ': ' + data.strings.error_generic, 'error' );
				NaanoBuilder._generateSectionsSequential( sections, description, index + 1, successCount, data.strings.error_generic );
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
		 * Open / toggle-select the edit panel for a section.
		 *
		 * @param {string}  sectionId  Section to select/toggle.
		 * @param {boolean} [forceOnly] If true, replace entire selection with just this section (used from iframe clicks).
		 */
		openEditPanel: function ( sectionId, forceOnly ) {
			if ( forceOnly ) {
				NaanoBuilder.editingSectionIds = [ sectionId ];
				NaanoBuilder.editingSectionId  = sectionId;
			} else {
				var idx = NaanoBuilder.editingSectionIds.indexOf( sectionId );
				if ( idx === -1 ) {
					NaanoBuilder.editingSectionIds.push( sectionId );
					NaanoBuilder.editingSectionId = sectionId;
				} else {
					NaanoBuilder.editingSectionIds.splice( idx, 1 );
					NaanoBuilder.editingSectionId =
						NaanoBuilder.editingSectionIds[ NaanoBuilder.editingSectionIds.length - 1 ] || null;
				}
			}
			NaanoBuilder._updateEditPanelState();
		},

		/**
		 * Sync the edit-panel UI to the current editingSectionIds state.
		 */
		_updateEditPanelState: function () {
			var ids   = NaanoBuilder.editingSectionIds;
			var count = ids.length;

			// Badge.
			if ( count === 0 ) {
				$( '#naano-editing-section-name' ).text( data.strings.click_section );
			} else if ( count === 1 ) {
				$( '#naano-editing-section-name' ).text( NaanoBuilder._displayName( ids[ 0 ] ) );
			} else {
				$( '#naano-editing-section-name' ).text( count + ' sections selected' );
			}

			// Update button label & state.
			var btnLabel = count > 1 ? 'Update ' + count + ' Sections' : 'Update Section';
			$( '#naano-update-section-btn' ).find( '.naano-update-btn-label' ).text( btnLabel );
			$( '#naano-update-section-btn' ).prop( 'disabled', count === 0 );

			// Active highlights in sections list.
			$( '#naano-sections-list .naano-sections-list__item' )
				.removeClass( 'naano-sections-list__item--active' );
			ids.forEach( function ( id ) {
				$( '#naano-sl-item-' + id ).addClass( 'naano-sections-list__item--active' );
			} );

			// References — show for last-focused section.
			if ( NaanoBuilder.editingSectionId ) {
				var refs = ( data.references && data.references[ NaanoBuilder.editingSectionId ] )
					? data.references[ NaanoBuilder.editingSectionId ] : [];
				NaanoBuilder._renderReferenceList( refs );
			} else {
				NaanoBuilder._renderReferenceList( [] );
			}

			// Highlight all selected sections in the iframe.
			NaanoBuilder._iframePost( { type: 'naano-highlight-sections', sectionIds: ids } );
		},

		/**
		 * Submit the update-section request (loops through all selected sections sequentially).
		 */
		updateSection: function () {
			var ids         = NaanoBuilder.editingSectionIds.slice();
			var instruction = $( '#naano-instruction' ).val().trim();

			if ( ! ids.length ) {
				NaanoBuilder._toast( data.strings.select_section, 'error' );
				return;
			}
			if ( ! instruction ) {
				NaanoBuilder._toast( data.strings.enter_instruction, 'error' );
				return;
			}

			// Auto-save any URL reference that is typed in the form but not yet added.
			var $urlForm = $( '#naano-add-url-form' );
			var pendingUrl = $( '#naano-ref-url' ).val().trim();
			if ( $urlForm.is( ':visible' ) && pendingUrl && NaanoBuilder.editingSectionId ) {
				NaanoBuilder.saveUrlReference( NaanoBuilder.editingSectionId );
				// saveUrlReference is async; delay dispatch until it completes.
				var _ids = ids, _instruction = instruction;
				$( document ).one( 'naano:url-ref-saved naano:url-ref-failed', function () {
					NaanoBuilder._dispatchUpdate( _ids, _instruction );
				} );
				return;
			}

			NaanoBuilder._dispatchUpdate( ids, instruction );
		},

		_dispatchUpdate: function ( ids, instruction ) {
			var fileLabel = ids.length === 1
				? NaanoBuilder._displayName( ids[ 0 ] ).toLowerCase().replace( /\s+/g, '-' ) + '.html'
				: ids.length + '-sections.html';

			NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', true );
			NaanoBuilder._showCanvasLoading( { filename: fileLabel } );

			ids.forEach( function ( id ) {
				NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: id, loading: true } );
			} );
			$( '#naano-live-iframe-wrap' ).addClass( 'naano-live-iframe-wrap--loading' );

			NaanoBuilder._updateSectionsSequential( ids, instruction, 0 );
		},

		/**
		 * Recursively update each section ID in sequence.
		 *
		 * @param {string[]} ids         Full list of section IDs to update.
		 * @param {string}   instruction The instruction text.
		 * @param {number}   index       Current position in the list.
		 */
		_updateSectionsSequential: function ( ids, instruction, index ) {
			if ( index >= ids.length ) {
				// All done.
				NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
				NaanoBuilder._hideCanvasLoading();
				$( '#naano-live-iframe-wrap' ).removeClass( 'naano-live-iframe-wrap--loading' );
				$( '#naano-instruction' ).val( '' );
				$( '#naano-add-url-form' ).hide();
				NaanoBuilder._renderReferenceList( [] );
				var msg = ids.length === 1 ? 'Section updated! ✨' : ids.length + ' sections updated! ✨';
				NaanoBuilder._toast( msg, 'success' );
				return;
			}

			var sectionId = ids[ index ];

			$.post( data.ajaxUrl, {
				action:      'naano_update_section',
				nonce:       data.nonce,
				page_id:     NaanoBuilder.pageId,
				section_id:  sectionId,
				instruction: instruction,
				assets:      JSON.stringify( NaanoBuilder.pageAssets ),
				redirects:   JSON.stringify( NaanoBuilder.pageRedirects ),
				references:  JSON.stringify( ( data.references && data.references[ sectionId ] ) ? data.references[ sectionId ] : [] )
			} )
			.done( function ( response ) {
				if ( response.success ) {
					var id   = response.data.section_id;
					var html = response.data.section_html;

					var idx = NaanoBuilder._findSectionIndex( id );
					if ( idx !== -1 ) {
						NaanoBuilder.sectionsData[ idx ].html = html;
					}

					NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: id, loading: false } );
					NaanoBuilder._iframePost( { type: 'naano-update-section',  sectionId: id, html: html } );

					// Continue to next section.
					NaanoBuilder._updateSectionsSequential( ids, instruction, index + 1 );
				} else {
					// Abort on error.
					NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
					NaanoBuilder._hideCanvasLoading();
					$( '#naano-live-iframe-wrap' ).removeClass( 'naano-live-iframe-wrap--loading' );
					ids.slice( index ).forEach( function ( id ) {
						NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: id, loading: false } );
					} );
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
				}
			} )
			.fail( function () {
				NaanoBuilder._setLoading( '#naano-update-section-btn', '#naano-update-loading', false );
				NaanoBuilder._hideCanvasLoading();
				$( '#naano-live-iframe-wrap' ).removeClass( 'naano-live-iframe-wrap--loading' );
				ids.slice( index ).forEach( function ( id ) {
					NaanoBuilder._iframePost( { type: 'naano-loading-section', sectionId: id, loading: false } );
				} );
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
					$( document ).trigger( 'naano:url-ref-saved' );
				} else {
					NaanoBuilder._toast( ( response.data && response.data.message ) || data.strings.error_generic, 'error' );
					$( document ).trigger( 'naano:url-ref-failed' );
				}
			} )
			.fail( function () {
				$( document ).trigger( 'naano:url-ref-failed' );
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

		/**
		 * Language switcher — navigate to a translation variant's builder URL.
		 */
		_bindLangSwitcher: function () {
			$( document ).on( 'change', '#naano-lang-switcher', function () {
				var url = $( this ).val();
				if ( url ) {
					window.location.href = url;
				}
			} );
		},

		_bindImportComponents: function () {
			// Toggle visibility of the import list.
			$( document ).on( 'click', '#naano-import-toggle', function () {
				var $list = $( '#naano-import-list' );
				if ( $list.is( ':visible' ) ) {
					$list.slideUp( 150 );
					$( this ).text( 'Show' );
				} else {
					$list.slideDown( 150 );
					$( this ).text( 'Hide' );
				}
			} );

			// Select / deselect an imported section.
			$( document ).on( 'click', '.naano-import-section-btn', function () {
				var $btn    = $( this );
				var secId   = String( $btn.data( 'section-id' ) );
				var secType = String( $btn.data( 'section-type' ) );

				// Find sourcePageId from the localized component data.
				var sourcePageId = 0;
				( data.existingComponents || [] ).forEach( function ( page ) {
					( page.sections || [] ).forEach( function ( sec ) {
						if ( String( sec.id ) === secId ) { sourcePageId = sec.sourcePageId || 0; }
					} );
				} );

				// Is it already selected?
				var existingIdx = -1;
				NaanoBuilder.importedSections.forEach( function ( s, i ) {
					if ( s.id === secId ) { existingIdx = i; }
				} );

				if ( existingIdx > -1 ) {
					// Deselect.
					NaanoBuilder.importedSections.splice( existingIdx, 1 );
					$btn.removeClass( 'naano-import-section-btn--selected' );
					$btn.find( '.naano-import-check' ).hide();
					// Re-enable the matching generation checkbox.
					$( '#naano-section-checkboxes input[value="' + secType + '"]' ).prop( 'checked', true );
				} else {
					// Deselect any other btn of the same type (only one per type allowed).
					NaanoBuilder.importedSections = NaanoBuilder.importedSections.filter( function ( s ) {
						return s.type !== secType;
					} );
					$( '.naano-import-section-btn[data-section-type="' + secType + '"]' )
						.removeClass( 'naano-import-section-btn--selected' )
						.find( '.naano-import-check' ).hide();
					// Select — store only reference; server fetches HTML from DB.
					NaanoBuilder.importedSections.push( { id: secId, type: secType, sourcePageId: sourcePageId } );
					$btn.addClass( 'naano-import-section-btn--selected' );
					$btn.find( '.naano-import-check' ).show();
					// Uncheck the generation checkbox for this type.
					$( '#naano-section-checkboxes input[value="' + secType + '"]' ).prop( 'checked', false );
				}

				// Clear sections error if we now have something selected.
				if ( $( '#naano-section-checkboxes input:checked' ).length > 0 || NaanoBuilder.importedSections.length > 0 ) {
					$( '#naano-sections-error' ).hide();
				}
			} );
		},

		_bindGenerationForm: function () {
			$( document ).on( 'click', '#naano-generate-btn', function () {
				NaanoBuilder.generateSite();
			} );

			$( document ).on( 'click', '#naano-enhance-description-btn', function () {
				NaanoBuilder.enhancePrompt( 'initial' );
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

			// Initial URL references.
			$( document ).on( 'click', '#naano-initial-add-url-btn', function () {
				$( '#naano-initial-add-url-form' ).slideDown( 150 );
				$( '#naano-initial-ref-url' ).focus();
			} );

			$( document ).on( 'click', '#naano-initial-cancel-url-btn', function () {
				$( '#naano-initial-add-url-form' ).slideUp( 150 );
				$( '#naano-initial-ref-url' ).val( '' );
				$( '#naano-initial-ref-notes' ).val( '' );
			} );

			$( document ).on( 'click', '#naano-initial-save-url-btn', function () {
				var url   = $( '#naano-initial-ref-url' ).val().trim();
				var notes = $( '#naano-initial-ref-notes' ).val().trim();

				if ( ! url ) {
					NaanoBuilder._toast( 'Please enter a URL.', 'error' );
					return;
				}

				NaanoBuilder.initialReferences.push( { url: url, notes: notes } );
				NaanoBuilder._renderInitialUrlList();

				$( '#naano-initial-ref-url' ).val( '' );
				$( '#naano-initial-ref-notes' ).val( '' );
				$( '#naano-initial-add-url-form' ).slideUp( 150 );
			} );

			$( document ).on( 'click', '.naano-initial-remove-ref', function () {
				var idx = $( this ).data( 'index' );
				NaanoBuilder.initialReferences.splice( idx, 1 );
				NaanoBuilder._renderInitialUrlList();
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

			$( document ).on( 'click', '#naano-enhance-instruction-btn', function () {
				NaanoBuilder.enhancePrompt( 'edit' );
			} );

			// Select-all / deselect-all.
			$( document ).on( 'click', '#naano-sl-select-all', function () {
				NaanoBuilder.editingSectionIds = NaanoBuilder.sectionsData.map( function ( s ) { return s.id; } );
				NaanoBuilder.editingSectionId  = NaanoBuilder.editingSectionIds[ NaanoBuilder.editingSectionIds.length - 1 ] || null;
				NaanoBuilder._updateEditPanelState();
			} );
			$( document ).on( 'click', '#naano-sl-select-none', function () {
				NaanoBuilder.editingSectionIds = [];
				NaanoBuilder.editingSectionId  = null;
				NaanoBuilder._updateEditPanelState();
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

		// =====================================================================
		// Element Inspector
		// =====================================================================

		/**
		 * Bind all element-inspector UI events (inspect toggle, style apply, tabs).
		 */
		_bindElementInspector: function () {
			$( document ).on( 'click', '#naano-inspect-toggle-btn', function () {
				NaanoBuilder._toggleInspectMode( ! NaanoBuilder.inspectModeActive );
			} );

			$( document ).on( 'click', '#naano-esp-apply-btn', function () {
				NaanoBuilder._applyElementStyle();
			} );

			$( document ).on( 'click', '#naano-esp-deselect-btn', function () {
				NaanoBuilder._clearElementSelection();
			} );

			$( document ).on( 'click', '.naano-esp-tab', function () {
				var tab = $( this ).data( 'tab' );
				$( '.naano-esp-tab' ).removeClass( 'naano-esp-tab--active' );
				$( this ).addClass( 'naano-esp-tab--active' );
				$( '.naano-esp-tab-pane' ).hide();
				$( '#naano-esp-pane-' + tab ).show();
			} );
		},

		/**
		 * Enable or disable element-inspect mode in the iframe.
		 *
		 * @param {boolean} on
		 */
		_toggleInspectMode: function ( on ) {
			NaanoBuilder.inspectModeActive = !!on;
			$( '#naano-inspect-toggle-btn' ).toggleClass( 'naano-inspect-mode-active', !!on );
			NaanoBuilder._iframePost( { type: 'naano-inspect-mode', active: !!on } );
			if ( ! on ) {
				NaanoBuilder._clearElementSelection();
			}
		},

		/**
		 * Deselect the current element and hide the style panel.
		 */
		_clearElementSelection: function () {
			NaanoBuilder.selectedElId        = null;
			NaanoBuilder.selectedElSectionId = null;
			$( '#naano-element-style-panel' ).hide();
		},

		/**
		 * Populate and show the style panel from element data returned by the iframe.
		 *
		 * @param {Object} elData  { breadcrumb, tagName, computed: { propName: val, … } }
		 */
		_renderStylePanel: function ( elData ) {
			$( '#naano-esp-breadcrumb' ).text( elData.breadcrumb || elData.tagName || '' );
			// Reset tabs to Style pane.
			$( '.naano-esp-tab' ).removeClass( 'naano-esp-tab--active' );
			$( '.naano-esp-tab[data-tab="style"]' ).addClass( 'naano-esp-tab--active' );
			$( '.naano-esp-tab-pane' ).hide();
			$( '#naano-esp-pane-style' ).show();
			$( '#naano-esp-custom-css' ).val( '' );

			var computed = elData.computed || {};
			$( '#naano-element-style-panel [data-prop]' ).each( function () {
				var prop = $( this ).data( 'prop' );
				var val  = computed[ prop ] || '';
				if ( $( this ).is( 'input[type=color]' ) && val ) {
					val = NaanoBuilder._rgbToHex( val ) || val;
				}
				$( this ).val( val );
			} );

			$( '#naano-element-style-panel' ).show();
		},

		/**
		 * Read all style-panel inputs and post them to the iframe to be applied inline.
		 */
		_applyElementStyle: function () {
			if ( ! NaanoBuilder.selectedElId ) { return; }

			var styles = {};
			$( '#naano-esp-pane-style [data-prop]' ).each( function () {
				var prop = $( this ).data( 'prop' );
				var val  = $( this ).val().trim();
				if ( val ) { styles[ prop ] = val; }
			} );

			var customCss = $( '#naano-esp-custom-css' ).val().trim();

			NaanoBuilder._iframePost( {
				type:      'naano-apply-element-style',
				elId:      NaanoBuilder.selectedElId,
				styles:    styles,
				customCss: customCss
			} );
		},

		/**
		 * Convert an rgb(r,g,b) string to #rrggbb hex for use in <input type="color">.
		 *
		 * @param  {string} rgb
		 * @return {string|null}
		 */
		_rgbToHex: function ( rgb ) {
			var m = rgb.match( /^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/ );
			if ( ! m ) { return null; }
			return '#' + [ m[1], m[2], m[3] ].map( function ( x ) {
				return ( '0' + parseInt( x, 10 ).toString( 16 ) ).slice( -2 );
			} ).join( '' );
		},

		/**
		 * Listen for postMessage events from the live-preview iframe.
		 */
		_bindIframeMessages: function () {
			window.addEventListener( 'message', function ( e ) {
				var msg = e.data;
				if ( ! msg || ! msg.type ) { return; }

				if ( msg.type === 'naano-section-clicked' ) {
					var sectionId = msg.sectionId;
					if ( sectionId && NaanoBuilder.pageId ) {
						NaanoBuilder.openEditPanel( sectionId, true );
					}
				}

				if ( msg.type === 'naano-element-selected' ) {
					NaanoBuilder.selectedElId        = msg.elId;
					NaanoBuilder.selectedElSectionId = msg.sectionId;
					NaanoBuilder._renderStylePanel( msg );
				}

				if ( msg.type === 'naano-element-html-updated' ) {
					var idx = NaanoBuilder._findSectionIndex( msg.sectionId );
					if ( idx !== -1 ) {
						NaanoBuilder.sectionsData[ idx ].html = msg.html;
					}
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
			// - Listens for postMessage from parent (update section, highlight, loading, inspect).
			// - Reports section clicks + element selections back to parent via postMessage.
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
				'+"@keyframes naano-flash{0%{box-shadow:inset 0 0 0 3px rgba(34,113,177,0.7);}100%{box-shadow:none;}}"',
				'+"body.naano-inspect-active{cursor:crosshair!important;}"',
				'+"body.naano-inspect-active *{cursor:crosshair!important;}"',
				'+"body.naano-inspect-active [data-section]:hover{outline:none!important;}"',
				'+"body.naano-inspect-active .naano-el-hover{outline:2px dashed #f59e0b!important;outline-offset:2px;z-index:9998;}"',
				'+"body.naano-inspect-active .naano-el-selected{outline:2px solid #f59e0b!important;outline-offset:2px;z-index:9998;}";',
				'document.head.appendChild(s);',

				// Inspect-mode state.
				'var inspectActive=false;',
				'var elCounter=0;',

				'function getOrAssignId(el){',
				'  if(!el.dataset.naanoEl)el.dataset.naanoEl="nel-"+(++elCounter);',
				'  return el.dataset.naanoEl;',
				'}',

				'function getSectionId(el){',
				'  var p=el;',
				'  while(p&&p!==document.body){',
				'    if(p.hasAttribute("data-section"))return p.getAttribute("data-section");',
				'    p=p.parentElement;',
				'  }',
				'  return null;',
				'}',

				'function buildBreadcrumb(el){',
				'  var parts=[];var cur=el;',
				'  while(cur&&cur!==document.body){',
				'    var tag=cur.tagName.toLowerCase();',
				'    if(cur.id)tag+="#"+cur.id;',
				'    else if(cur.className&&typeof cur.className==="string"){',
				'      var cls=cur.className.trim().split(/\\s+/).slice(0,2).join(".");',
				'      if(cls)tag+="."+cls;',
				'    }',
				'    parts.unshift(tag);cur=cur.parentElement;',
				'    if(parts.length>4){parts.unshift("\\u2026");break;}',
				'  }',
				'  return parts.join(" \\u203a ");',
				'}',

				// Hover highlight in inspect mode.
				'document.addEventListener("mouseover",function(e){',
				'  if(!inspectActive)return;',
				'  e.stopPropagation();',
				'  document.querySelectorAll(".naano-el-hover").forEach(function(n){n.classList.remove("naano-el-hover");});',
				'  var el=e.target;',
				'  if(el&&el!==document.body&&el!==document.documentElement)el.classList.add("naano-el-hover");',
				'});',

				// Click in inspect mode — capture phase so it fires before section-click handler.
				'document.addEventListener("click",function(e){',
				'  if(!inspectActive)return;',
				'  e.stopImmediatePropagation();e.preventDefault();',
				'  var el=e.target;',
				'  if(!el||el===document.body||el===document.documentElement)return;',
				'  var elId=getOrAssignId(el);',
				'  var sectionId=getSectionId(el);',
				'  var cs=window.getComputedStyle(el);',
				'  var styleProps=["color","backgroundColor","fontSize","fontWeight","textAlign",',
				'    "width","height","maxWidth",',
				'    "paddingTop","paddingRight","paddingBottom","paddingLeft",',
				'    "marginTop","marginRight","marginBottom","marginLeft",',
				'    "border","borderRadius","backgroundImage","backgroundSize"];',
				'  var computed={};',
				'  styleProps.forEach(function(p){computed[p]=cs[p]||"";});',
				'  document.querySelectorAll(".naano-el-selected").forEach(function(n){n.classList.remove("naano-el-selected");});',
				'  el.classList.add("naano-el-selected");',
				'  window.parent.postMessage({type:"naano-element-selected",elId:elId,sectionId:sectionId,',
				'    tagName:el.tagName.toLowerCase(),breadcrumb:buildBreadcrumb(el),computed:computed},"*");',
				'},true);',

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

				// Highlight a section (keep for backward compat).
				'  if(m.type==="naano-highlight-section"){',
				'    document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
				'    var el=document.querySelector(\'[data-section="\'+m.sectionId+\'"]\');',
				'    if(el)el.classList.add("naano-section-selected");',
				'  }',

				// Highlight multiple sections at once.
				'  if(m.type==="naano-highlight-sections"){',
				'    document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
				'    (m.sectionIds||[]).forEach(function(id){',
				'      var el=document.querySelector(\'[data-section="\'+id+\'"]\');',
				'      if(el)el.classList.add("naano-section-selected");',
				'    });',
				'  }',

				// Show/hide loading overlay on a section.
				'  if(m.type==="naano-loading-section"){',
				'    var el=document.querySelector(\'[data-section="\'+m.sectionId+\'"]\');',
				'    if(el){',
				'      if(m.loading)el.classList.add("naano-section-loading");',
				'      else el.classList.remove("naano-section-loading");',
				'    }',
				'  }',

				// Toggle inspect mode.
				'  if(m.type==="naano-inspect-mode"){',
				'    inspectActive=!!m.active;',
				'    document.body.classList.toggle("naano-inspect-active",inspectActive);',
				'    if(!inspectActive){',
				'      document.querySelectorAll(".naano-el-hover,.naano-el-selected").forEach(function(n){',
				'        n.classList.remove("naano-el-hover","naano-el-selected");',
				'      });',
				'    }',
				'  }',

				// Apply inline styles to a [data-naano-el] element.
				'  if(m.type==="naano-apply-element-style"){',
				'    var el=document.querySelector(\'[data-naano-el="\'+m.elId+\'"]\');',
				'    if(!el)return;',
				'    var props=m.styles||{};',
				'    Object.keys(props).forEach(function(p){if(props[p]!=="")el.style[p]=props[p];});',
				'    if(m.customCss&&m.customCss.trim()){',
				'      var styleId="naano-custom-"+m.elId;',
				'      var existing=document.getElementById(styleId);',
				'      if(existing)existing.remove();',
				'      var tag=document.createElement("style");',
				'      tag.id=styleId;',
				'      tag.textContent=\'[data-naano-el="\'+m.elId+\'"]{\'+ m.customCss +\'}\';',
				'      document.head.appendChild(tag);',
				'    }',
				'    var sectionEl=el.closest("[data-section]");',
				'    if(sectionEl){',
				'      window.parent.postMessage({type:"naano-element-html-updated",',
				'        sectionId:sectionEl.getAttribute("data-section"),html:sectionEl.innerHTML},"*");',
				'    }',
				'  }',
				'});',

				// Report section clicks to parent — only when NOT in inspect mode.
				'document.addEventListener("click",function(e){',
				'  if(inspectActive)return;',
				'  var el=e.target;',
				'  while(el&&el!==document.body){',
				'    if(el.hasAttribute("data-section")){',
				'      e.preventDefault();',
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

				// Click on the item row toggles section selection.
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
			NaanoBuilder._showCanvasLoading( { filename: slug + '.html' } );

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

		_showCanvasLoading: function ( opts ) {
			opts = opts || {};
			var filename = opts.filename || 'output.html';
			$( '#naano-cla-title-text' ).text( filename + ' — generating' );
			$( '#naano-cla-filename' ).text( filename );
			$( '#naano-canvas-loading-overlay' ).addClass( 'naano-canvas-loading-overlay--visible' );
			NaanoBuilder._claStart();
		},

		_hideCanvasLoading: function () {
			NaanoBuilder._claStop();
			$( '#naano-canvas-loading-overlay' ).removeClass( 'naano-canvas-loading-overlay--visible' );
		},

		_claStart: function () {
			NaanoBuilder._claStop();

			// Restart progress-bar CSS animation via clone trick.
			var pfill = document.getElementById( 'naano-cla-progress-fill' );
			if ( pfill ) {
				var clone = pfill.cloneNode( false );
				pfill.parentNode.replaceChild( clone, pfill );
			}

			// Clear code lines and counters.
			var linesEl = document.getElementById( 'naano-cla-code-lines' );
			if ( linesEl ) { linesEl.innerHTML = ''; }
			$( '#naano-cla-token-count' ).text( '0' );
			$( '#naano-cla-line-count' ).text( '0' );
			$( '#naano-cla-speed' ).text( '0' );
			$( '#naano-cla-elapsed' ).text( '0.0' );

			var state = {
				lineIndex:    0,
				tokenCount:   0,
				elapsed:      0,
				lastTime:     performance.now(),
				lineTimer:    null,
				statsTimer:   null,
				rainInterval: null
			};
			NaanoBuilder._claState = state;

			// ── Matrix rain ──────────────────────────────────────────────
			var canvas  = document.getElementById( 'naano-cla-rain' );
			var overlay = document.getElementById( 'naano-canvas-loading-overlay' );
			if ( canvas && overlay ) {
				canvas.width  = overlay.offsetWidth;
				canvas.height = overlay.offsetHeight;
				var ctx   = canvas.getContext( '2d' );
				var chars = '01\u30A2\u30A4\u30A6\u30A8\u30AA{}[]<>/\\;:=()!?#';
				var cols  = Math.floor( canvas.width / 18 );
				var drops = new Array( cols ).fill( 1 );
				state.rainInterval = setInterval( function () {
					ctx.fillStyle = 'rgba(8,12,16,0.1)';
					ctx.fillRect( 0, 0, canvas.width, canvas.height );
					ctx.fillStyle = '#00e5ff';
					ctx.font = '13px Courier New, monospace';
					for ( var i = 0; i < drops.length; i++ ) {
						var c = chars[ Math.floor( Math.random() * chars.length ) ];
						ctx.fillText( c, i * 18, drops[ i ] * 18 );
						if ( drops[ i ] * 18 > canvas.height && Math.random() > 0.97 ) { drops[ i ] = 0; }
						drops[ i ]++;
					}
				}, 55 );
			}

			// ── Fake code lines ──────────────────────────────────────────
			var codeData = [
				[ [ 'k', '<!DOCTYPE ' ], [ 'p', 'html' ], [ 'k', '>' ] ],
				[ [ 't', '<html ' ], [ 'a', 'lang' ], [ 'p', '=' ], [ 's', '"en"' ], [ 't', '>' ] ],
				[ [ 't', '<head>' ] ],
				[ [ 'c', '  <!-- meta & viewport -->' ] ],
				[ [ 't', '  <meta ' ], [ 'a', 'charset' ], [ 'p', '=' ], [ 's', '"UTF-8"' ], [ 'p', '/>' ] ],
				[ [ 't', '  <meta ' ], [ 'a', 'name' ], [ 'p', '="viewport" ' ], [ 'a', 'content' ], [ 'p', '=' ], [ 's', '"width=device-width"' ], [ 'p', '/>' ] ],
				[ [ 't', '  <title>' ], [ 'p', 'Page' ], [ 't', '</title>' ] ],
				[ [ 't', '</head>' ] ],
				[ [ 't', '<body>' ] ],
				[ [ 't', '  <div ' ], [ 'a', 'class' ], [ 'p', '=' ], [ 's', '"app"' ], [ 't', '>' ] ],
				[ [ 't', '    <nav ' ], [ 'a', 'class' ], [ 'p', '=' ], [ 's', '"navbar"' ], [ 't', '>' ] ],
				[ [ 't', '      <a ' ], [ 'a', 'href' ], [ 'p', '=' ], [ 's', '"/"' ], [ 't', '>' ], [ 'p', 'Home' ], [ 't', '</a>' ] ],
				[ [ 't', '    </nav>' ] ],
				[ [ 't', '    <main>' ] ],
				[ [ 't', '      <section ' ], [ 'a', 'id' ], [ 'p', '=' ], [ 's', '"hero"' ], [ 't', '>' ] ],
				[ [ 'k', '      <style>' ] ],
				[ [ 'p', '        .hero { display: ' ], [ 'n', 'grid' ], [ 'p', '; }' ] ],
				[ [ 'p', '          gap: ' ], [ 'n', '2rem' ], [ 'p', '; padding: ' ], [ 'n', '4rem 2rem' ], [ 'p', ';' ] ],
				[ [ 'p', '          background: linear-gradient(' ] ],
				[ [ 'n', '            135deg' ], [ 'p', ',' ] ],
				[ [ 's', '            #0d1117' ], [ 'p', ', ' ], [ 's', '#1a1f2e' ], [ 'p', ' );' ] ],
				[ [ 'k', '      </style>' ] ],
				[ [ 't', '      </section>' ] ],
				[ [ 't', '    </main>' ] ],
				[ [ 't', '  </div>' ] ],
				[ [ 'k', '<script>' ] ],
				[ [ 'k', '  const ' ], [ 'f', 'init' ], [ 'p', ' = () => {' ] ],
				[ [ 'k', '    const ' ], [ 'p', 'el = document.querySelector( ' ], [ 's', '"#app"' ], [ 'p', ' );' ] ],
				[ [ 'p', '    el.classList.add( ' ], [ 's', '"ready"' ], [ 'p', ' );' ] ],
				[ [ 'k', '    fetch' ], [ 'p', '( ' ], [ 's', '"/api/content"' ], [ 'p', ' )' ] ],
				[ [ 'p', '      .' ], [ 'f', 'then' ], [ 'p', '( r => r.' ], [ 'f', 'json' ], [ 'p', '() )' ] ],
				[ [ 'p', '      .' ], [ 'f', 'then' ], [ 'p', '( data => ' ], [ 'f', 'render' ], [ 'p', '( data ) );' ] ],
				[ [ 'p', '  };' ] ],
				[ [ 'f', '  document' ], [ 'p', '.addEventListener( ' ], [ 's', '"DOMContentLoaded"' ], [ 'p', ', init );' ] ],
				[ [ 'k', '</script>' ] ],
				[ [ 't', '</body>' ] ],
				[ [ 't', '</html>' ] ]
			];

			function addCodeLine() {
				var el = document.getElementById( 'naano-cla-code-lines' );
				if ( ! el ) { return; }
				var fragment = codeData[ state.lineIndex % codeData.length ];
				var ln       = String( state.lineIndex + 1 ).padStart( 3, ' ' );
				var inner    = fragment.map( function ( tok ) {
					return '<span class="naano-cla-' + tok[ 0 ] + '">' +
						tok[ 1 ].replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ) + '</span>';
				} ).join( '' );
				var row = document.createElement( 'div' );
				row.className = 'naano-cla-code-line';
				row.innerHTML = '<span class="naano-cla-ln">' + ln + '</span>' + inner;
				el.appendChild( row );
				// Move cursor to last line.
				var old = el.querySelector( '.naano-cla-cursor' );
				if ( old ) { old.remove(); }
				var cur = document.createElement( 'span' );
				cur.className = 'naano-cla-cursor';
				row.appendChild( cur );
				// Keep last 22 lines visible.
				while ( el.children.length > 22 ) { el.removeChild( el.firstChild ); }
				state.lineIndex++;
				var charCount = fragment.reduce( function ( acc, t ) { return acc + t[ 1 ].length; }, 0 );
				state.tokenCount += charCount;
				var lc = document.getElementById( 'naano-cla-line-count' );
				var tc = document.getElementById( 'naano-cla-token-count' );
				if ( lc ) { lc.textContent = state.lineIndex; }
				if ( tc ) { tc.textContent = state.tokenCount.toLocaleString(); }
			}

			function nextLine() {
				state.lineTimer = setTimeout( function () {
					if ( ! NaanoBuilder._claState ) { return; }
					addCodeLine();
					nextLine();
				}, 120 + Math.random() * 160 );
			}
			nextLine();

			// Stats interval.
			state.statsTimer = setInterval( function () {
				if ( ! NaanoBuilder._claState ) { return; }
				var now = performance.now();
				state.elapsed += ( now - state.lastTime ) / 1000;
				state.lastTime  = now;
				var speed     = state.elapsed > 0 ? Math.round( state.tokenCount / state.elapsed ) : 0;
				var elapsedEl = document.getElementById( 'naano-cla-elapsed' );
				var speedEl   = document.getElementById( 'naano-cla-speed' );
				if ( elapsedEl ) { elapsedEl.textContent = state.elapsed.toFixed( 1 ); }
				if ( speedEl )   { speedEl.textContent   = speed; }
			}, 250 );
		},

		_claStop: function () {
			var s = NaanoBuilder._claState;
			if ( ! s ) { return; }
			clearTimeout( s.lineTimer );
			clearInterval( s.statsTimer );
			clearInterval( s.rainInterval );
			NaanoBuilder._claState = null;
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
				NaanoBuilder._persistAssets();
				$( '#naano-add-asset-form' ).hide().find( 'input' ).val( '' );
				$( '#naano-asset-picker' ).show();
			} );

			$( document ).on( 'click', '.naano-remove-asset-btn', function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				NaanoBuilder.pageAssets.splice( idx, 1 );
				NaanoBuilder._renderAssetList();
				NaanoBuilder._persistAssets();
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
				NaanoBuilder._persistAssets();
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
				NaanoBuilder._persistRedirects();
				$( '#naano-add-redirect-form' ).hide().find( 'input' ).val( '' );
				$( '#naano-add-redirect-btn' ).show();
			} );

			$( document ).on( 'click', '.naano-remove-redirect-btn', function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				NaanoBuilder.pageRedirects.splice( idx, 1 );
				NaanoBuilder._renderRedirectList();
				NaanoBuilder._persistRedirects();
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

		_persistAssets: function () {
			if ( ! NaanoBuilder.pageId ) { return; }
			$.post( data.ajaxUrl, {
				action:  'naano_save_assets',
				nonce:   data.nonce,
				page_id: NaanoBuilder.pageId,
				assets:  JSON.stringify( NaanoBuilder.pageAssets )
			} );
		},

		_persistRedirects: function () {
			if ( ! NaanoBuilder.pageId ) { return; }
			$.post( data.ajaxUrl, {
				action:    'naano_save_redirects',
				nonce:     data.nonce,
				page_id:   NaanoBuilder.pageId,
				redirects: JSON.stringify( NaanoBuilder.pageRedirects )
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

		_renderInitialUrlList: function () {
			var $list = $( '#naano-initial-url-list' ).empty();
			NaanoBuilder.initialReferences.forEach( function ( ref, index ) {
				var $li   = $( '<li class="naano-reference-item">' );
				var $link = $( '<a>' ).attr( { href: ref.url, target: '_blank' } ).text( ref.url );
				$li.append( $link );
				if ( ref.notes ) {
					$li.append( ' — ' + $( '<span>' ).text( ref.notes ).html() );
				}
				$li.append( ' <button type="button" class="naano-initial-remove-ref" data-index="' + index + '">✕</button>' );
				$list.append( $li );
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
