/**
 * Naano AI Website Builder — builder.js
 *
 * Handles all builder UI interactions, AJAX calls, and section management.
 * Visual builder (Elementor-style) with real-time live preview.
 */
/* global naanoBuilderData, wp */
(function ($, data) {
  "use strict";

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

    /** @type {string|null} Tag name of the currently selected element (lowercased). Used to show/hide the Link tab. */
    selectedElTag: null,

    /**
     * Classes that were on the selected element BEFORE the user touched it.
     * The iframe merges these "AI classes" with the user-edited classes
     * field on apply, so AI-generated styling hooks survive class edits.
     * @type {string}
     */
    selectedElAiClasses: "",

    /**
     * Set of section IDs that have unsaved manual edits (text, style,
     * deletion, classes). Cleared after a successful "Save changes".
     * Implemented as a plain object to avoid Set polyfill concerns.
     * @type {Object<string,boolean>}
     */
    _dirtySections: {},

    /** @type {boolean} True if the global-css textarea has been modified since last save. */
    _dirtyGlobalCss: false,

    /** @type {string} Last-saved value of the global CSS so we can detect dirty state. */
    _lastSavedGlobalCss: "",

    /** @type {Array} Currently-known list of failed section descriptors (from server). */
    _failedSections: [],

    /**
     * Initialise the builder.
     */
    init: function () {
      NaanoBuilder.pageId = parseInt(data.pageId, 10) || 0;

      // Activate full-screen layout.
      $("body").addClass("naano-fullscreen");

      // Inject code-generation loading overlay.
      (function () {
        if (!document.getElementById("naano-cla-fonts")) {
          var link = document.createElement("link");
          link.id = "naano-cla-fonts";
          link.rel = "stylesheet";
          link.href =
            "https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap";
          document.head.appendChild(link);
        }
        $("#naano-vb-canvas").append(
          '<div class="naano-canvas-loading-overlay" id="naano-canvas-loading-overlay">' +
            '<canvas class="naano-cla-rain" id="naano-cla-rain"></canvas>' +
            '<div class="naano-cla-scanline"></div>' +
            '<div class="naano-cla-wrapper">' +
            '<div class="naano-cla-card">' +
            '<div class="naano-cla-titlebar">' +
            '<div class="naano-cla-dots"><div class="naano-cla-dot"></div><div class="naano-cla-dot"></div><div class="naano-cla-dot"></div></div>' +
            '<span class="naano-cla-title-label" id="naano-cla-title-text">output.html — generating</span>' +
            '<span class="naano-cla-badge">LLM ✶</span>' +
            "</div>" +
            '<div class="naano-cla-status-row">' +
            '<div class="naano-cla-pulse"></div>' +
            '<span class="naano-cla-status-text">Generating<span class="naano-cla-ellipsis"><span>.</span><span>.</span><span>.</span></span></span>' +
            '<span class="naano-cla-token-count">tokens: <span id="naano-cla-token-count">0</span></span>' +
            "</div>" +
            '<div class="naano-cla-progress-wrap"><div class="naano-cla-progress-track"><div class="naano-cla-progress-fill" id="naano-cla-progress-fill"></div></div></div>' +
            '<div class="naano-cla-code-area">' +
            '<div class="naano-cla-code-header">' +
            '<span class="naano-cla-lang-tag">HTML/CSS/JS</span>' +
            '<span id="naano-cla-filename">output.html</span>' +
            '<span class="naano-cla-line-count">lines: <span id="naano-cla-line-count">0</span></span>' +
            "</div>" +
            '<div class="naano-cla-code-scroll"><div class="naano-cla-code-lines" id="naano-cla-code-lines"></div></div>' +
            "</div>" +
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
            "</div>" +
            "</div></div></div>",
        );
        // Set model name safely via text() to prevent XSS.
        var m = data.modelLabel || "llm";
        var dash = m.lastIndexOf("-");
        $("#naano-cla-model-main").text(dash > 0 ? m.slice(0, dash) : m);
        $("#naano-cla-model-ver").text(dash > 0 ? m.slice(dash + 1) : "");
      })();

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

      // Populate WP menu dropdowns.
      if (data.wpMenus && data.wpMenus.length) {
        var menuOpts = "";
        $.each(data.wpMenus, function (_, m) {
          menuOpts +=
            '<option value="' +
            m.id +
            '">' +
            $("<span>").text(m.name).html() +
            "</option>";
        });
        $("#naano-initial-wp-menu, #naano-edit-wp-menu").append(menuOpts);
      }

      // If we already have sections (page reload), render them.
      if (data.sections && data.sections.length > 0) {
        NaanoBuilder.sectionsData = data.sections.slice();
        NaanoBuilder._showBuilder();
        NaanoBuilder._refreshLivePreview();
        NaanoBuilder._renderSectionsList();
      }

      // Restore persisted assets & redirects.
      if (data.assets && data.assets.length) {
        NaanoBuilder.pageAssets = data.assets.slice();
        NaanoBuilder._renderAssetList();
      }
      if (data.redirects && data.redirects.length) {
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
    enhancePrompt: function (context) {
      var isInitial = context === "initial";
      var $textarea = isInitial
        ? $("#naano-description")
        : $("#naano-instruction");
      var $btn = isInitial
        ? $("#naano-enhance-description-btn")
        : $("#naano-enhance-instruction-btn");
      var $loading = isInitial
        ? $("#naano-enhance-description-loading")
        : $("#naano-enhance-instruction-loading");
      var rawText = $textarea.val().trim();

      if (!rawText) {
        NaanoBuilder._toast(
          isInitial
            ? data.strings.enter_description
            : data.strings.enter_instruction,
          "error",
        );
        $textarea.focus();
        return;
      }

      $btn.prop("disabled", true);
      $loading.show();

      NaanoBuilder._jobAjax({
        action: "naano_enhance_prompt",
        nonce: data.nonce,
        raw_text: rawText,
        context: context,
        page_name: $("#naano-page-name").val() || "",
      })
        .done(function (response) {
          $btn.prop("disabled", false);
          $loading.hide();

          if (response && response.success) {
            $textarea.val(response.data.enhanced_text);

            // For initial context, auto-check suggested sections.
            if (isInitial && response.data.suggested_sections) {
              var suggested = response.data.suggested_sections;

              // Uncheck all first.
              $("#naano-section-checkboxes input[type=checkbox]").prop(
                "checked",
                false,
              );

              // Check suggested ones.
              suggested.forEach(function (sectionName) {
                var slug = sectionName
                  .replace(/\s+/g, "-")
                  .replace(/[^a-z0-9-]/g, "");
                var $cb = $(
                  '#naano-section-checkboxes input[value="' + slug + '"]',
                );
                if ($cb.length) {
                  $cb.prop("checked", true);
                } else if (slug) {
                  // Add as custom section if not in the default list.
                  var label =
                    sectionName.charAt(0).toUpperCase() +
                    sectionName.slice(1).replace(/-/g, " ");
                  var $label = $(
                    '<label class="naano-checkbox-label naano-checkbox-label--custom">',
                  );
                  $label.append(
                    $("<input>", {
                      type: "checkbox",
                      name: "sections[]",
                      value: slug,
                      checked: true,
                    }),
                    document.createTextNode(" " + label + " "),
                    $("<button>", {
                      type: "button",
                      class: "naano-remove-custom-section",
                      title: "Remove",
                    }).text("\u00d7"),
                  );
                  $("#naano-section-checkboxes").append($label);
                }
              });
            }

            NaanoBuilder._toast(
              isInitial
                ? "Prompt enhanced & sections suggested!"
                : "Instruction enhanced!",
              "success",
            );
          } else {
            NaanoBuilder._toast(
              (response && response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
          }
        })
        .fail(function () {
          $btn.prop("disabled", false);
          $loading.hide();
          NaanoBuilder._toast(data.strings.error_generic, "error");
        });
    },

    /**
     * Generate a full website from the form.
     */
    generateSite: function () {
      var pageName = $("#naano-page-name").val().trim();
      var description = $("#naano-description").val().trim();
      var sections = [];

      $("#naano-section-checkboxes input[type=checkbox]:checked").each(
        function () {
          sections.push($(this).val());
        },
      );

      var valid = true;

      if (!description) {
        $("#naano-description-error").show();
        $("#naano-description").focus();
        valid = false;
      } else {
        $("#naano-description-error").hide();
      }

      if (sections.length === 0 && NaanoBuilder.importedSections.length === 0) {
        $("#naano-sections-error").show();
        if (valid) {
          $("#naano-section-checkboxes").find("input").first().focus();
        }
        valid = false;
      } else {
        $("#naano-sections-error").hide();
      }

      if (!valid) {
        return;
      }

      NaanoBuilder._setLoading(
        "#naano-generate-btn",
        "#naano-generate-loading",
        true,
      );
      NaanoBuilder._showCanvasLoading({
        filename: (pageName || "output") + ".html",
      });

      NaanoBuilder._jobAjax({
        action: "naano_generate_site",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        page_name: pageName,
        description: description,
        sections: sections,
        imported_sections: JSON.stringify(NaanoBuilder.importedSections),
        initial_references: JSON.stringify(NaanoBuilder.initialReferences),
        wp_menu: $("#naano-initial-wp-menu").val() || "",
      })
        .done(function (response) {
          NaanoBuilder._setLoading(
            "#naano-generate-btn",
            "#naano-generate-loading",
            false,
          );
          NaanoBuilder._hideCanvasLoading();
          if (response && response.success) {
            NaanoBuilder.pageId = response.data.page_id || NaanoBuilder.pageId;

            if (Array.isArray(response.data.sections)) {
              NaanoBuilder.sectionsData = response.data.sections.slice();
            } else {
              NaanoBuilder.sectionsData = [];
              $.each(response.data.sections, function (id, html) {
                NaanoBuilder.sectionsData.push({ id: id, html: html });
              });
            }

            if (pageName) {
              $("#naano-current-page-name").text(pageName);
            }

            NaanoBuilder._showBuilder();
            NaanoBuilder._refreshLivePreview();
            NaanoBuilder._renderSectionsList();

            // The server's skip-on-LSAPI policy may have completed the job
            // while skipping one or more sections that hit LSAPI kills /
            // exceptions during generation. Surface that to the user so
            // they know to regenerate the missing parts manually.
            var failed =
              response.data && Array.isArray(response.data.failed_sections)
                ? response.data.failed_sections
                : [];
            if (failed.length > 0) {
              var names = failed
                .map(function (f) {
                  return f.section_type || f.section_id || "?";
                })
                .join(", ");
              NaanoBuilder._toast(
                "Website generated, but " +
                  failed.length +
                  " section(s) were skipped due to server timeouts: " +
                  names +
                  ". You can regenerate them individually from the builder.",
                "success",
                10000,
              );
            } else {
              NaanoBuilder._toast(
                "Website generated successfully! 🎉",
                "success",
              );
            }
            NaanoBuilder.importedSections = [];
            NaanoBuilder.initialReferences = [];
            $(".naano-import-section-btn")
              .removeClass("naano-import-section-btn--selected")
              .find(".naano-import-check")
              .hide();
          } else {
            // Job ended in error OR client polling deadline was hit while
            // the server was still working. Either way, the runner
            // persists each section to the DB as soon as it's generated,
            // so there may already be useful sections to show. The poll
            // handler attaches them under `data.partial.sections`.
            //
            // If we have anything, show it with a warning toast rather
            // than throwing all that work away with a hard error toast.
            var errMsg =
              (response && response.data && response.data.message) ||
              data.strings.error_generic;
            var partial =
              (response && response.data && response.data.partial) || null;
            var partialSections =
              partial &&
              Array.isArray(partial.sections) &&
              partial.sections.length
                ? partial.sections
                : null;
            var partialPageId = partial && partial.page_id;

            if (partialSections) {
              NaanoBuilder.pageId = partialPageId || NaanoBuilder.pageId;
              NaanoBuilder.sectionsData = partialSections.slice();

              if (pageName) {
                $("#naano-current-page-name").text(pageName);
              }

              NaanoBuilder._showBuilder();
              NaanoBuilder._refreshLivePreview();
              NaanoBuilder._renderSectionsList();
              NaanoBuilder.importedSections = [];
              NaanoBuilder.initialReferences = [];
              $(".naano-import-section-btn")
                .removeClass("naano-import-section-btn--selected")
                .find(".naano-import-check")
                .hide();

              var status =
                (response && response.data && response.data.status) || "error";
              var label =
                status === "running"
                  ? "Generation is still in progress on the server. Showing " +
                    partialSections.length +
                    " section(s) generated so far — refresh in a moment to see more."
                  : "Generation interrupted: " +
                    errMsg +
                    " Showing " +
                    partialSections.length +
                    " section(s) that were saved before the error.";
              // Use "success" toast variant when we recovered work — the
              // sections ARE there and visible to the user. The detailed
              // message conveys the nuance that the run was incomplete.
              // (There's no `warning` variant in builder.css so picking
              // "error" would visually contradict the fact that the user
              // is now looking at a populated builder.)
              NaanoBuilder._toast(
                label,
                status === "running" ? "success" : "success",
                8000,
              );
            } else {
              NaanoBuilder._toast(errMsg, "error");
            }
          }
        })
        .fail(function () {
          NaanoBuilder._setLoading(
            "#naano-generate-btn",
            "#naano-generate-loading",
            false,
          );
          NaanoBuilder._hideCanvasLoading();
          NaanoBuilder._toast(data.strings.error_generic, "error");
        });
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
    _generateSectionsSequential: function (
      sections,
      description,
      index,
      successCount,
      lastError,
    ) {
      successCount = successCount || 0;
      lastError = lastError || "";

      if (index >= sections.length) {
        NaanoBuilder._setLoading(
          "#naano-generate-btn",
          "#naano-generate-loading",
          false,
        );
        NaanoBuilder._hideCanvasLoading();
        NaanoBuilder.importedSections = [];
        $(".naano-import-section-btn")
          .removeClass("naano-import-section-btn--selected")
          .find(".naano-import-check")
          .hide();

        if (successCount > 0) {
          var msg =
            successCount === sections.length
              ? "Website generated successfully! 🎉"
              : successCount +
                " of " +
                sections.length +
                " sections generated. ⚠️";
          NaanoBuilder._toast(
            msg,
            successCount === sections.length ? "success" : "warning",
          );
        } else {
          NaanoBuilder._toast(
            "Generation failed: " +
              (lastError || "unknown error — check API settings."),
            "error",
          );
        }
        return;
      }

      var sectionType = sections[index];
      var label =
        sectionType.charAt(0).toUpperCase() +
        sectionType.slice(1).replace(/-/g, " ");

      $("#naano-cla-title-text").text(
        label + " (" + (index + 1) + "/" + sections.length + ") — generating",
      );
      $("#naano-cla-filename").text(sectionType + ".html");

      $.post(data.ajaxUrl, {
        action: "naano_generate_site_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        description: description,
        section_type: sectionType,
      })
        .done(function (response) {
          if (response && response.success) {
            var id = response.data.section_id;
            var html = response.data.section_html;

            var existing = NaanoBuilder._findSectionIndex(id);
            if (existing === -1) {
              NaanoBuilder.sectionsData.push({ id: id, html: html });
            } else {
              NaanoBuilder.sectionsData[existing].html = html;
            }

            NaanoBuilder._refreshLivePreview();
            NaanoBuilder._renderSectionsList();
            NaanoBuilder._generateSectionsSequential(
              sections,
              description,
              index + 1,
              successCount + 1,
              lastError,
            );
          } else {
            var errMsg =
              response && response.data && response.data.message
                ? response.data.message
                : data.strings.error_generic;
            NaanoBuilder._toast(label + ": " + errMsg, "error");
            NaanoBuilder._generateSectionsSequential(
              sections,
              description,
              index + 1,
              successCount,
              errMsg,
            );
          }
        })
        .fail(function () {
          NaanoBuilder._toast(
            label + ": " + data.strings.error_generic,
            "error",
          );
          NaanoBuilder._generateSectionsSequential(
            sections,
            description,
            index + 1,
            successCount,
            data.strings.error_generic,
          );
        });
    },

    // =====================================================================
    // Section rendering
    // =====================================================================

    /**
     * (Legacy) Render multiple sections — kept for backward compat.
     *
     * @param {Object|Array} sections
     */
    renderSections: function (sections) {
      if (Array.isArray(sections)) {
        NaanoBuilder.sectionsData = sections.slice();
      } else {
        NaanoBuilder.sectionsData = [];
        $.each(sections, function (id, html) {
          NaanoBuilder.sectionsData.push({ id: id, html: html });
        });
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
    renderSectionCard: function (id, html) {
      var existing = NaanoBuilder._findSectionIndex(id);
      if (existing === -1) {
        NaanoBuilder.sectionsData.push({ id: id, html: html });
      } else {
        NaanoBuilder.sectionsData[existing].html = html;
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
    openEditPanel: function (sectionId, forceOnly) {
      if (forceOnly) {
        NaanoBuilder.editingSectionIds = [sectionId];
        NaanoBuilder.editingSectionId = sectionId;
      } else {
        var idx = NaanoBuilder.editingSectionIds.indexOf(sectionId);
        if (idx === -1) {
          NaanoBuilder.editingSectionIds.push(sectionId);
          NaanoBuilder.editingSectionId = sectionId;
        } else {
          NaanoBuilder.editingSectionIds.splice(idx, 1);
          NaanoBuilder.editingSectionId =
            NaanoBuilder.editingSectionIds[
              NaanoBuilder.editingSectionIds.length - 1
            ] || null;
        }
      }
      NaanoBuilder._updateEditPanelState();
    },

    /**
     * Sync the edit-panel UI to the current editingSectionIds state.
     */
    _updateEditPanelState: function () {
      var ids = NaanoBuilder.editingSectionIds;
      var count = ids.length;

      // Badge.
      if (count === 0) {
        $("#naano-editing-section-name").text(data.strings.click_section);
      } else if (count === 1) {
        $("#naano-editing-section-name").text(
          NaanoBuilder._displayName(ids[0]),
        );
      } else {
        $("#naano-editing-section-name").text(count + " sections selected");
      }

      // Update button label & state.
      var btnLabel =
        count > 1 ? "Update " + count + " Sections" : "Update Section";
      $("#naano-update-section-btn")
        .find(".naano-update-btn-label")
        .text(btnLabel);
      $("#naano-update-section-btn").prop("disabled", count === 0);

      // Active highlights in sections list.
      $("#naano-sections-list .naano-sections-list__item").removeClass(
        "naano-sections-list__item--active",
      );
      ids.forEach(function (id) {
        $("#naano-sl-item-" + id).addClass("naano-sections-list__item--active");
      });

      // References — show for last-focused section.
      if (NaanoBuilder.editingSectionId) {
        var refs =
          data.references && data.references[NaanoBuilder.editingSectionId]
            ? data.references[NaanoBuilder.editingSectionId]
            : [];
        NaanoBuilder._renderReferenceList(refs);
      } else {
        NaanoBuilder._renderReferenceList([]);
      }

      // Highlight all selected sections in the iframe.
      NaanoBuilder._iframePost({
        type: "naano-highlight-sections",
        sectionIds: ids,
      });
    },

    /**
     * Submit the update-section request (loops through all selected sections sequentially).
     */
    updateSection: function () {
      var ids = NaanoBuilder.editingSectionIds.slice();
      var instruction = $("#naano-instruction").val().trim();

      if (!ids.length) {
        NaanoBuilder._toast(data.strings.select_section, "error");
        return;
      }
      if (!instruction) {
        NaanoBuilder._toast(data.strings.enter_instruction, "error");
        return;
      }

      // Auto-save any URL reference that is typed in the form but not yet added.
      var $urlForm = $("#naano-add-url-form");
      var pendingUrl = $("#naano-ref-url").val().trim();
      if (
        $urlForm.is(":visible") &&
        pendingUrl &&
        NaanoBuilder.editingSectionId
      ) {
        NaanoBuilder.saveUrlReference(NaanoBuilder.editingSectionId);
        // saveUrlReference is async; delay dispatch until it completes.
        var _ids = ids,
          _instruction = instruction;
        $(document).one(
          "naano:url-ref-saved naano:url-ref-failed",
          function () {
            NaanoBuilder._dispatchUpdate(_ids, _instruction);
          },
        );
        return;
      }

      NaanoBuilder._dispatchUpdate(ids, instruction);
    },

    _dispatchUpdate: function (ids, instruction) {
      var fileLabel =
        ids.length === 1
          ? NaanoBuilder._displayName(ids[0])
              .toLowerCase()
              .replace(/\s+/g, "-") + ".html"
          : ids.length + "-sections.html";

      NaanoBuilder._setLoading(
        "#naano-update-section-btn",
        "#naano-update-loading",
        true,
      );
      NaanoBuilder._showCanvasLoading({ filename: fileLabel });

      ids.forEach(function (id) {
        NaanoBuilder._iframePost({
          type: "naano-loading-section",
          sectionId: id,
          loading: true,
        });
      });
      $("#naano-live-iframe-wrap").addClass("naano-live-iframe-wrap--loading");

      NaanoBuilder._updateSectionsSequential(ids, instruction, 0);
    },

    /**
     * Recursively update each section ID in sequence.
     *
     * @param {string[]} ids         Full list of section IDs to update.
     * @param {string}   instruction The instruction text.
     * @param {number}   index       Current position in the list.
     */
    _updateSectionsSequential: function (ids, instruction, index) {
      if (index >= ids.length) {
        // All done.
        NaanoBuilder._setLoading(
          "#naano-update-section-btn",
          "#naano-update-loading",
          false,
        );
        NaanoBuilder._hideCanvasLoading();
        $("#naano-live-iframe-wrap").removeClass(
          "naano-live-iframe-wrap--loading",
        );
        $("#naano-instruction").val("");
        $("#naano-add-url-form").hide();
        NaanoBuilder._renderReferenceList([]);
        var msg =
          ids.length === 1
            ? "Section updated! ✨"
            : ids.length + " sections updated! ✨";
        NaanoBuilder._toast(msg, "success");
        return;
      }

      var sectionId = ids[index];

      NaanoBuilder._jobAjax({
        action: "naano_update_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: sectionId,
        instruction: instruction,
        assets: JSON.stringify(NaanoBuilder.pageAssets),
        redirects: JSON.stringify(NaanoBuilder.pageRedirects),
        references: JSON.stringify(
          data.references && data.references[sectionId]
            ? data.references[sectionId]
            : [],
        ),
        wp_menu: $("#naano-edit-wp-menu").val() || "",
      })
        .done(function (response) {
          if (response.success) {
            var id = response.data.section_id;
            var html = response.data.section_html;

            var idx = NaanoBuilder._findSectionIndex(id);
            if (idx !== -1) {
              NaanoBuilder.sectionsData[idx].html = html;
            }

            NaanoBuilder._iframePost({
              type: "naano-loading-section",
              sectionId: id,
              loading: false,
            });
            NaanoBuilder._iframePost({
              type: "naano-update-section",
              sectionId: id,
              html: html,
            });

            // Continue to next section.
            NaanoBuilder._updateSectionsSequential(ids, instruction, index + 1);
          } else {
            // Abort on error.
            NaanoBuilder._setLoading(
              "#naano-update-section-btn",
              "#naano-update-loading",
              false,
            );
            NaanoBuilder._hideCanvasLoading();
            $("#naano-live-iframe-wrap").removeClass(
              "naano-live-iframe-wrap--loading",
            );
            ids.slice(index).forEach(function (id) {
              NaanoBuilder._iframePost({
                type: "naano-loading-section",
                sectionId: id,
                loading: false,
              });
            });
            NaanoBuilder._toast(
              (response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
          }
        })
        .fail(function () {
          NaanoBuilder._setLoading(
            "#naano-update-section-btn",
            "#naano-update-loading",
            false,
          );
          NaanoBuilder._hideCanvasLoading();
          $("#naano-live-iframe-wrap").removeClass(
            "naano-live-iframe-wrap--loading",
          );
          ids.slice(index).forEach(function (id) {
            NaanoBuilder._iframePost({
              type: "naano-loading-section",
              sectionId: id,
              loading: false,
            });
          });
          NaanoBuilder._toast(data.strings.error_generic, "error");
        });
    },

    // =====================================================================
    // References
    // =====================================================================

    /**
     * Open the WP media library to select a screenshot.
     *
     * @param {string} sectionId
     */
    addScreenshot: function (sectionId) {
      var frame = wp.media({
        title: "Select Screenshot Reference",
        button: { text: "Use this image" },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();

        $.post(data.ajaxUrl, {
          action: "naano_add_reference",
          nonce: data.nonce,
          page_id: NaanoBuilder.pageId,
          section_id: sectionId,
          type: "screenshot",
          url: attachment.url,
          attachment_id: attachment.id,
          notes: "",
        }).done(function (response) {
          if (response.success) {
            if (!data.references) {
              data.references = {};
            }
            data.references[sectionId] = response.data.references;

            if (NaanoBuilder.editingSectionId === sectionId) {
              NaanoBuilder._renderReferenceList(response.data.references);
            }
            NaanoBuilder._toast("Screenshot added! 🖼️", "success");
          } else {
            NaanoBuilder._toast(
              (response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
          }
        });
      });

      frame.open();
    },

    /**
     * Show the URL reference form.
     *
     * @param {string} sectionId
     */
    addUrlReference: function (sectionId) {
      NaanoBuilder.editingSectionId =
        NaanoBuilder.editingSectionId || sectionId;
      $("#naano-add-url-form").slideDown(150);
      $("#naano-ref-url").focus();
    },

    /**
     * Save a URL reference.
     *
     * @param {string} sectionId
     */
    saveUrlReference: function (sectionId) {
      var url = $("#naano-ref-url").val().trim();
      var notes = $("#naano-ref-notes").val().trim();

      if (!url) {
        NaanoBuilder._toast("Please enter a URL.", "error");
        return;
      }

      $.post(data.ajaxUrl, {
        action: "naano_add_reference",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: sectionId,
        type: "url",
        url: url,
        notes: notes,
      })
        .done(function (response) {
          if (response.success) {
            if (!data.references) {
              data.references = {};
            }
            data.references[sectionId] = response.data.references;

            if (NaanoBuilder.editingSectionId === sectionId) {
              NaanoBuilder._renderReferenceList(response.data.references);
            }
            $("#naano-add-url-form").hide();
            $("#naano-ref-url").val("");
            $("#naano-ref-notes").val("");
            NaanoBuilder._toast("URL reference added! 🔗", "success");
            $(document).trigger("naano:url-ref-saved");
          } else {
            NaanoBuilder._toast(
              (response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
            $(document).trigger("naano:url-ref-failed");
          }
        })
        .fail(function () {
          $(document).trigger("naano:url-ref-failed");
        });
    },

    /**
     * Remove a reference.
     *
     * @param {string} sectionId
     * @param {number} index
     */
    removeReference: function (sectionId, index) {
      $.post(data.ajaxUrl, {
        action: "naano_remove_reference",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: sectionId,
        index: index,
      }).done(function (response) {
        if (response.success) {
          if (!data.references) {
            data.references = {};
          }
          data.references[sectionId] = response.data.references;

          if (NaanoBuilder.editingSectionId === sectionId) {
            NaanoBuilder._renderReferenceList(response.data.references);
          }
        }
      });
    },

    // =====================================================================
    // Section operations
    // =====================================================================

    /**
     * Delete a section after confirmation.
     *
     * @param {string} sectionId
     */
    deleteSection: function (sectionId) {
      if (!window.confirm(data.strings.confirm_delete)) {
        return;
      }

      $.post(data.ajaxUrl, {
        action: "naano_delete_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: sectionId,
      }).done(function (response) {
        if (response.success) {
          // Remove from in-memory store and rebuild preview.
          var idx = NaanoBuilder._findSectionIndex(sectionId);
          if (idx !== -1) {
            NaanoBuilder.sectionsData.splice(idx, 1);
          }

          if (NaanoBuilder.editingSectionId === sectionId) {
            NaanoBuilder.editingSectionId = null;
            $("#naano-editing-section-name").text(data.strings.click_section);
            $("#naano-update-section-btn").prop("disabled", true);
          }

          NaanoBuilder._refreshLivePreview();
          NaanoBuilder._renderSectionsList();
          NaanoBuilder._toast("Section deleted.", "success");
        }
      });
    },

    /**
     * Send the reorder AJAX call (called after reordering sectionsData).
     */
    reorderSections: function () {
      var order = NaanoBuilder.sectionsData.map(function (sec) {
        return sec.id;
      });

      $.post(data.ajaxUrl, {
        action: "naano_reorder_sections",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        order: order,
      });
    },

    // =====================================================================
    // Action bar operations
    // =====================================================================

    /**
     * Open the preview modal.
     */
    previewSite: function () {
      $.post(data.ajaxUrl, {
        action: "naano_export_html",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
      }).done(function (response) {
        if (response.success) {
          NaanoPreview.open(response.data.html);
        }
      });
    },

    /**
     * Export assembled HTML as a downloadable file.
     */
    exportHtml: function () {
      $.post(data.ajaxUrl, {
        action: "naano_export_html",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
      }).done(function (response) {
        if (response.success) {
          var blob = new Blob([response.data.html], { type: "text/html" });
          var url = URL.createObjectURL(blob);
          var a = document.createElement("a");
          a.href = url;
          a.download = "website.html";
          a.click();
          URL.revokeObjectURL(url);
        }
      });
    },

    /**
     * Copy assembled HTML to clipboard.
     */
    copyToClipboard: function () {
      $.post(data.ajaxUrl, {
        action: "naano_export_html",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
      }).done(function (response) {
        if (response.success) {
          navigator.clipboard.writeText(response.data.html).then(function () {
            NaanoBuilder._toast("HTML copied to clipboard! 📋", "success");
          });
        }
      });
    },

    /**
     * Save assembled HTML as a WordPress page.
     */
    saveAsPage: function () {
      $("#naano-save-page-title").val($("#naano-current-page-name").text());
      $("#naano-save-page-error").hide();
      $("#naano-save-page-modal").show();
      setTimeout(function () {
        $("#naano-save-page-title").select();
      }, 60);
    },

    _doPublishPage: function () {
      var title = $("#naano-save-page-title").val().trim();
      if (!title) {
        $("#naano-save-page-error").text("Please enter a page title.").show();
        $("#naano-save-page-title").focus();
        return;
      }

      // Build clean HTML from client-side sectionsData — this is reliable
      // regardless of what may or may not be stored in the DB on the server.
      var sectionsHtml = "";
      NaanoBuilder.sectionsData.forEach(function (sec) {
        sectionsHtml += sec.html;
      });
      var escapedTitle = $("<div>").text(title).html();
      var fullHtml =
        '<!DOCTYPE html><html lang="en"><head>' +
        '<meta charset="UTF-8">' +
        '<meta name="viewport" content="width=device-width,initial-scale=1">' +
        "<title>" +
        escapedTitle +
        "</title>" +
        "</head><body>" +
        sectionsHtml +
        "</body></html>";

      $("#naano-save-page-modal").hide();
      $("#naano-save-page-btn").prop("disabled", true);

      $.post(data.ajaxUrl, {
        action: "naano_save_as_page",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        title: title,
        html: fullHtml,
      })
        .done(function (response) {
          $("#naano-save-page-btn").prop("disabled", false);
          if (response.success) {
            // Update displayed page name in builder header.
            $("#naano-current-page-name").text(response.data.title || title);

            NaanoBuilder._toast(
              'Page published! <a href="' +
                response.data.view_url +
                '" target="_blank">View it</a> · <a href="' +
                response.data.edit_url +
                '" target="_blank">Edit in WP</a>',
              "success",
              6000,
            );

            // Set as homepage if checkbox was checked.
            if ($("#naano-set-homepage-chk").is(":checked")) {
              $.post(data.ajaxUrl, {
                action: "naano_set_homepage",
                nonce: data.nonce,
                page_id: NaanoBuilder.pageId,
              }).done(function (res) {
                if (res.success) {
                  NaanoBuilder._toast("Set as homepage!", "success", 3500);
                }
              });
            }
          } else {
            NaanoBuilder._toast(
              (response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
          }
        })
        .fail(function () {
          $("#naano-save-page-btn").prop("disabled", false);
          NaanoBuilder._toast(data.strings.error_generic, "error");
        });
    },

    // =====================================================================
    // Private helpers
    // =====================================================================

    /**
     * Language switcher — navigate to a translation variant's builder URL.
     */
    _bindLangSwitcher: function () {
      $(document).on("change", "#naano-lang-switcher", function () {
        var url = $(this).val();
        if (url) {
          window.location.href = url;
        }
      });
    },

    _bindImportComponents: function () {
      // Toggle visibility of the import list.
      $(document).on("click", "#naano-import-toggle", function () {
        var $list = $("#naano-import-list");
        if ($list.is(":visible")) {
          $list.slideUp(150);
          $(this).text("Show");
        } else {
          $list.slideDown(150);
          $(this).text("Hide");
        }
      });

      // Select / deselect an imported section.
      $(document).on("click", ".naano-import-section-btn", function () {
        var $btn = $(this);
        var secId = String($btn.data("section-id"));
        var secType = String($btn.data("section-type"));

        // Find sourcePageId from the localized component data.
        var sourcePageId = 0;
        (data.existingComponents || []).forEach(function (page) {
          (page.sections || []).forEach(function (sec) {
            if (String(sec.id) === secId) {
              sourcePageId = sec.sourcePageId || 0;
            }
          });
        });

        // Is it already selected?
        var existingIdx = -1;
        NaanoBuilder.importedSections.forEach(function (s, i) {
          if (s.id === secId) {
            existingIdx = i;
          }
        });

        if (existingIdx > -1) {
          // Deselect.
          NaanoBuilder.importedSections.splice(existingIdx, 1);
          $btn.removeClass("naano-import-section-btn--selected");
          $btn.find(".naano-import-check").hide();
          // Re-enable the matching generation checkbox.
          $('#naano-section-checkboxes input[value="' + secType + '"]').prop(
            "checked",
            true,
          );
        } else {
          // Deselect any other btn of the same type (only one per type allowed).
          NaanoBuilder.importedSections = NaanoBuilder.importedSections.filter(
            function (s) {
              return s.type !== secType;
            },
          );
          $('.naano-import-section-btn[data-section-type="' + secType + '"]')
            .removeClass("naano-import-section-btn--selected")
            .find(".naano-import-check")
            .hide();
          // Select — store only reference; server fetches HTML from DB.
          NaanoBuilder.importedSections.push({
            id: secId,
            type: secType,
            sourcePageId: sourcePageId,
          });
          $btn.addClass("naano-import-section-btn--selected");
          $btn.find(".naano-import-check").show();
          // Uncheck the generation checkbox for this type.
          $('#naano-section-checkboxes input[value="' + secType + '"]').prop(
            "checked",
            false,
          );
        }

        // Clear sections error if we now have something selected.
        if (
          $("#naano-section-checkboxes input:checked").length > 0 ||
          NaanoBuilder.importedSections.length > 0
        ) {
          $("#naano-sections-error").hide();
        }
      });
    },

    _bindGenerationForm: function () {
      $(document).on("click", "#naano-generate-btn", function () {
        NaanoBuilder.generateSite();
      });

      $(document).on("click", "#naano-enhance-description-btn", function () {
        NaanoBuilder.enhancePrompt("initial");
      });

      $(document).on("click", "#naano-add-custom-section", function () {
        var $input = $("#naano-custom-section-input");
        var $error = $("#naano-custom-section-error");
        var name = $input.val().trim();

        if (!name) {
          $error.show();
          $input.focus();
          return;
        }
        $error.hide();

        var slug = name
          .toLowerCase()
          .replace(/\s+/g, "-")
          .replace(/[^a-z0-9-]/g, "");
        if (!slug) {
          return;
        }

        var $label = $(
          '<label class="naano-checkbox-label naano-checkbox-label--custom">',
        );
        $label.append(
          $("<input>", {
            type: "checkbox",
            name: "sections[]",
            value: slug,
            checked: true,
          }),
          document.createTextNode(" " + name + " "),
          $("<button>", {
            type: "button",
            class: "naano-remove-custom-section",
            title: "Remove",
          }).text("×"),
        );
        $("#naano-section-checkboxes").append($label);
        $input.val("");
      });

      $(document).on("click", ".naano-remove-custom-section", function (e) {
        e.preventDefault();
        $(this).closest("label").remove();
      });

      $(document).on("keydown", "#naano-custom-section-input", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          $("#naano-add-custom-section").trigger("click");
        }
      });

      // Initial URL references.
      $(document).on("click", "#naano-initial-add-url-btn", function () {
        $("#naano-initial-add-url-form").slideDown(150);
        $("#naano-initial-ref-url").focus();
      });

      $(document).on("click", "#naano-initial-cancel-url-btn", function () {
        $("#naano-initial-add-url-form").slideUp(150);
        $("#naano-initial-ref-url").val("");
        $("#naano-initial-ref-notes").val("");
      });

      $(document).on("click", "#naano-initial-save-url-btn", function () {
        var url = $("#naano-initial-ref-url").val().trim();
        var notes = $("#naano-initial-ref-notes").val().trim();

        if (!url) {
          NaanoBuilder._toast("Please enter a URL.", "error");
          return;
        }

        NaanoBuilder.initialReferences.push({ url: url, notes: notes });
        NaanoBuilder._renderInitialUrlList();

        $("#naano-initial-ref-url").val("");
        $("#naano-initial-ref-notes").val("");
        $("#naano-initial-add-url-form").slideUp(150);
      });

      $(document).on("click", ".naano-initial-remove-ref", function () {
        var idx = $(this).data("index");
        NaanoBuilder.initialReferences.splice(idx, 1);
        NaanoBuilder._renderInitialUrlList();
      });
    },

    _bindActionBar: function () {
      $(document).on("click", "#naano-preview-btn", function () {
        NaanoBuilder.previewSite();
      });
      $(document).on("click", "#naano-export-btn", function () {
        NaanoBuilder.exportHtml();
      });
      $(document).on("click", "#naano-copy-btn", function () {
        NaanoBuilder.copyToClipboard();
      });
      $(document).on("click", "#naano-save-page-btn", function () {
        NaanoBuilder.saveAsPage();
      });

      // Publish page modal handlers.
      $(document).on("click", "#naano-save-page-confirm-btn", function () {
        NaanoBuilder._doPublishPage();
      });
      $(document).on("click", "#naano-save-page-cancel-btn", function () {
        $("#naano-save-page-modal").hide();
      });
      // Close on backdrop click.
      $(document).on("click", "#naano-save-page-modal", function (e) {
        if ($(e.target).is("#naano-save-page-modal")) {
          $("#naano-save-page-modal").hide();
        }
      });
      $(document).on("keydown", "#naano-save-page-title", function (e) {
        if (e.key === "Enter") {
          NaanoBuilder._doPublishPage();
        }
        if (e.key === "Escape") {
          $("#naano-save-page-modal").hide();
        }
      });
    },

    _bindDrawer: function () {
      $(document).on("click", "#naano-drawer-toggle", function () {
        $("#naano-drawer").toggleClass("naano-drawer--collapsed");
      });
    },

    _bindViewportToggle: function () {
      $(document).on(
        "click",
        "#naano-viewport-group .naano-viewport-btn",
        function () {
          var width = $(this).data("width");
          $("#naano-viewport-group .naano-viewport-btn").removeClass(
            "naano-viewport-btn--active",
          );
          $(this).addClass("naano-viewport-btn--active");

          var $iframe = $("#naano-live-preview");
          if (width === "100%") {
            $iframe.css({ "max-width": "100%", width: "100%" });
          } else {
            $iframe.css({ "max-width": width, width: width });
          }
        },
      );
    },

    _bindEditPanel: function () {
      $(document).on("click", "#naano-update-section-btn", function () {
        NaanoBuilder.updateSection();
      });

      $(document).on("click", "#naano-enhance-instruction-btn", function () {
        NaanoBuilder.enhancePrompt("edit");
      });

      // Select-all / deselect-all.
      $(document).on("click", "#naano-sl-select-all", function () {
        NaanoBuilder.editingSectionIds = NaanoBuilder.sectionsData.map(
          function (s) {
            return s.id;
          },
        );
        NaanoBuilder.editingSectionId =
          NaanoBuilder.editingSectionIds[
            NaanoBuilder.editingSectionIds.length - 1
          ] || null;
        NaanoBuilder._updateEditPanelState();
      });
      $(document).on("click", "#naano-sl-select-none", function () {
        NaanoBuilder.editingSectionIds = [];
        NaanoBuilder.editingSectionId = null;
        NaanoBuilder._updateEditPanelState();
      });

      $(document).on("click", "#naano-add-screenshot-btn", function () {
        if (!NaanoBuilder.editingSectionId) {
          NaanoBuilder._toast(data.strings.select_section, "error");
          return;
        }
        NaanoBuilder.addScreenshot(NaanoBuilder.editingSectionId);
      });

      $(document).on("click", "#naano-add-url-btn", function () {
        if (!NaanoBuilder.editingSectionId) {
          NaanoBuilder._toast(data.strings.select_section, "error");
          return;
        }
        NaanoBuilder.addUrlReference(NaanoBuilder.editingSectionId);
      });

      $(document).on("click", "#naano-save-url-btn", function () {
        NaanoBuilder.saveUrlReference(NaanoBuilder.editingSectionId);
      });

      $(document).on("click", "#naano-cancel-url-btn", function () {
        $("#naano-add-url-form").hide();
      });

      $(document).on("click", ".naano-remove-ref-btn", function () {
        var idx = parseInt($(this).data("index"), 10);
        NaanoBuilder.removeReference(NaanoBuilder.editingSectionId, idx);
      });

      $(document).on("click", "#naano-add-new-section-btn", function () {
        $("#naano-add-new-section-btn").hide();
        $("#naano-new-section-form").show();
        $("#naano-new-section-name").val("").focus();
        $("#naano-new-section-error").hide();
      });

      $(document).on("click", "#naano-cancel-new-section-btn", function () {
        $("#naano-new-section-form").hide();
        $("#naano-new-section-error").hide();
        $("#naano-add-new-section-btn").show();
      });

      $(document).on("click", "#naano-confirm-new-section-btn", function () {
        var name = $("#naano-new-section-name").val().trim();
        if (!name) {
          $("#naano-new-section-error")
            .text("Please enter a section name.")
            .show();
          $("#naano-new-section-name").focus();
          return;
        }
        $("#naano-new-section-form").hide();
        $("#naano-new-section-error").hide();
        $("#naano-add-new-section-btn").show();
        NaanoBuilder._addNewSection(name);
      });

      $(document).on("keydown", "#naano-new-section-name", function (e) {
        if (e.key === "Enter") {
          $("#naano-confirm-new-section-btn").trigger("click");
        }
        if (e.key === "Escape") {
          $("#naano-cancel-new-section-btn").trigger("click");
        }
      });
    },

    // =====================================================================
    // Element Inspector
    // =====================================================================

    /**
     * Bind all element-inspector UI events (inspect toggle, style apply, tabs).
     */
    /**
     * Bind all element-inspector UI events (inspect toggle, style apply, tabs,
     * delete, classes, link, save changes, retry failed sections).
     */
    _bindElementInspector: function () {
      // Legacy toggle button (kept for backward compat — inspect is now on
      // by default but the button still works as an explicit on/off).
      $(document).on("click", "#naano-inspect-toggle-btn", function () {
        NaanoBuilder._toggleInspectMode(!NaanoBuilder.inspectModeActive);
      });

      $(document).on("click", "#naano-esp-apply-btn", function () {
        NaanoBuilder._applyElementStyle();
      });

      $(document).on(
        "click",
        "#naano-esp-deselect-btn, #naano-esp-close-btn",
        function () {
          NaanoBuilder._clearElementSelection();
        },
      );

      $(document).on("click", "#naano-esp-delete-btn", function () {
        NaanoBuilder._deleteSelectedElement();
      });

      $(document).on("click", "#naano-esp-edit-section-btn", function () {
        // Shortcut: jump to AI editor for the section that contains the
        // currently-selected element. Closes the panel first so the drawer
        // takes focus.
        var sid = NaanoBuilder.selectedElSectionId;
        if (!sid) return;
        NaanoBuilder._clearElementSelection();
        if (typeof NaanoBuilder.openEditPanel === "function") {
          NaanoBuilder.openEditPanel(sid);
        } else {
          // Fall back: ask iframe to highlight, then nudge sidebar list.
          NaanoBuilder._iframePost({
            type: "naano-highlight-section",
            sectionId: sid,
          });
          $('.naano-sections-list [data-section-id="' + sid + '"]').click();
        }
      });

      // Anchor picker auto-fills the URL field when a section is chosen.
      $(document).on("change", "#naano-esp-anchor-select", function () {
        var v = $(this).val();
        if (v) {
          $("#naano-esp-href-input").val(v);
        }
      });

      // Tab switching.
      $(document).on("click", ".naano-esp-tab", function () {
        var tab = $(this).data("tab");
        $(".naano-esp-tab").removeClass("naano-esp-tab--active");
        $(this).addClass("naano-esp-tab--active");
        $(".naano-esp-tab-pane").hide();
        $("#naano-esp-pane-" + tab).show();
      });

      // Save changes: persists section HTML edits + global CSS in one POST.
      $(document).on("click", "#naano-save-changes-btn", function () {
        NaanoBuilder._saveChanges();
      });

      // Mark global CSS as dirty when user types into the textarea.
      $(document).on("input", "#naano-global-css", function () {
        NaanoBuilder._dirtyGlobalCss =
          ($(this).val() || "") !== NaanoBuilder._lastSavedGlobalCss;
        NaanoBuilder._refreshSaveChangesBtn();
        // Live-preview: re-render the iframe with the new global CSS so
        // the user sees their CSS immediately. Throttled to avoid
        // re-rendering on every keystroke for very long stylesheets.
        clearTimeout(NaanoBuilder._globalCssLivePreviewT);
        NaanoBuilder._globalCssLivePreviewT = setTimeout(function () {
          NaanoBuilder._refreshLivePreview();
        }, 400);
      });

      // Failed sections — retry button delegated.
      $(document).on(
        "click",
        ".naano-failed-list .naano-failed-retry",
        function () {
          var sid = $(this).data("section-id");
          NaanoBuilder._retrySection(sid);
        },
      );

      // Beforeunload guard: warn if there are unsaved manual edits.
      $(window).on("beforeunload", function () {
        if (NaanoBuilder._hasUnsavedChanges()) {
          return "You have unsaved manual edits. Click \u201cSave changes\u201d before leaving.";
        }
      });
    },

    /**
     * Enable or disable element-inspect mode in the iframe. Inspect is on
     * by default in the new helper script; this is here for the legacy
     * toggle button only.
     *
     * @param {boolean} on
     */
    _toggleInspectMode: function (on) {
      NaanoBuilder.inspectModeActive = !!on;
      $("#naano-inspect-toggle-btn").toggleClass(
        "naano-inspect-mode-active",
        !!on,
      );
      NaanoBuilder._iframePost({ type: "naano-inspect-mode", active: !!on });
      if (!on) {
        NaanoBuilder._clearElementSelection();
      }
    },

    /**
     * Deselect the current element, hide the floating panel, and tell the
     * iframe to drop its contenteditable + outline.
     */
    _clearElementSelection: function () {
      NaanoBuilder.selectedElId = null;
      NaanoBuilder.selectedElSectionId = null;
      NaanoBuilder.selectedElTag = null;
      NaanoBuilder.selectedElAiClasses = "";
      $("#naano-element-style-panel").hide();
      NaanoBuilder._iframePost({ type: "naano-deselect-element" });
    },

    /**
     * Populate and show the floating panel from element data returned by
     * the iframe.
     *
     * @param {Object} elData  { breadcrumb, tagName, computed, classes, linkInfo }
     */
    _renderStylePanel: function (elData) {
      $("#naano-esp-tag").text(elData.tagName || "div");
      $("#naano-esp-breadcrumb").text(elData.breadcrumb || "");

      // Reset to Style tab on each new selection.
      $(".naano-esp-tab").removeClass("naano-esp-tab--active");
      $('.naano-esp-tab[data-tab="style"]').addClass("naano-esp-tab--active");
      $(".naano-esp-tab-pane").hide();
      $("#naano-esp-pane-style").show();
      $("#naano-esp-custom-css").val("");

      // Style + spacing inputs.
      var computed = elData.computed || {};
      $("#naano-element-style-panel [data-prop]").each(function () {
        var prop = $(this).data("prop");
        var val = computed[prop] || "";
        if ($(this).is("input[type=color]") && val) {
          val = NaanoBuilder._rgbToHex(val) || val;
        }
        $(this).val(val);
      });

      // Classes input — pre-fill with the user-extra classes detected in
      // the iframe (which excluded internal helper hooks like
      // .naano-el-selected, but kept everything the AI generated). Keep a
      // ref so apply can preserve them.
      var classesStr = elData.classes || "";
      NaanoBuilder.selectedElAiClasses = classesStr;
      $("#naano-esp-classes-input").val(classesStr);

      // Link tab: only show + populate when an <a> is selected.
      var isAnchor = (elData.tagName || "").toLowerCase() === "a";
      $('.naano-esp-tab[data-tab="link"]').toggle(isAnchor);
      if (isAnchor) {
        var li = elData.linkInfo || {};
        $("#naano-esp-href-input").val(li.href || "");
        $("#naano-esp-rel-input").val(li.rel || "");
        $("#naano-esp-target-select").val(li.target || "_self");
        // Build anchor list from current sections so the user can pick.
        NaanoBuilder._buildAnchorOptions();
        // Pre-select the option if href matches a known section anchor.
        if (li.href && li.href.charAt(0) === "#") {
          $("#naano-esp-anchor-select").val(li.href);
        } else {
          $("#naano-esp-anchor-select").val("");
        }
      }

      $("#naano-element-style-panel").show();
    },

    /**
     * Populate the in-page anchor <select> with the current page's section IDs.
     */
    _buildAnchorOptions: function () {
      var $sel = $("#naano-esp-anchor-select");
      $sel.find("option").not(":first").remove();
      var seen = {};
      (NaanoBuilder.sectionsData || []).forEach(function (s) {
        var id = s && s.id;
        if (!id || seen[id]) return;
        seen[id] = true;
        $sel.append(
          $("<option>")
            .val("#" + id)
            .text("#" + id),
        );
      });
    },

    /**
     * Read the floating panel and push everything to the iframe:
     *   1. inline styles + custom CSS (.naano-apply-element-style)
     *   2. classes              (.naano-apply-element-classes)
     *   3. link attributes      (.naano-apply-element-link)
     */
    _applyElementStyle: function () {
      if (!NaanoBuilder.selectedElId) {
        return;
      }

      // 1) Inline styles + custom CSS.
      var styles = {};
      $("#naano-element-style-panel [data-prop]").each(function () {
        var prop = $(this).data("prop");
        var raw = $(this).val();
        var val = raw == null ? "" : String(raw).trim();
        if (val) {
          styles[prop] = val;
        }
      });
      var customCss = ($("#naano-esp-custom-css").val() || "").trim();
      NaanoBuilder._iframePost({
        type: "naano-apply-element-style",
        elId: NaanoBuilder.selectedElId,
        styles: styles,
        customCss: customCss,
      });

      // 2) Classes — merged with AI classes by the iframe.
      var userClasses = ($("#naano-esp-classes-input").val() || "").trim();
      // The iframe merges aiClasses + userClasses; we send aiClasses as
      // the snapshot taken at selection time, and userClasses as the
      // current textbox content.
      NaanoBuilder._iframePost({
        type: "naano-apply-element-classes",
        elId: NaanoBuilder.selectedElId,
        aiClasses: NaanoBuilder.selectedElAiClasses,
        userClasses: userClasses,
      });

      // 3) Link attributes — only sent when the Link tab is visible.
      if (
        (NaanoBuilder.selectedElTag || "").toLowerCase() === "a" ||
        $('.naano-esp-tab[data-tab="link"]').is(":visible")
      ) {
        var href = ($("#naano-esp-href-input").val() || "").trim();
        var target = $("#naano-esp-target-select").val() || "_self";
        var rel = ($("#naano-esp-rel-input").val() || "").trim();
        // Auto-add rel=noopener for new-tab links if user left rel empty.
        if (target === "_blank" && !rel) {
          rel = "noopener noreferrer";
        }
        NaanoBuilder._iframePost({
          type: "naano-apply-element-link",
          elId: NaanoBuilder.selectedElId,
          href: href,
          target: target,
          rel: rel,
        });
      }

      NaanoBuilder._toast("Applied", "success", 1500);
    },

    /**
     * Tell the iframe to delete the currently-selected element and
     * deselect locally.
     */
    _deleteSelectedElement: function () {
      if (!NaanoBuilder.selectedElId) return;
      if (
        !window.confirm(
          "Delete this element? You can undo by hitting Ctrl+Z in the iframe (or by regenerating the section).",
        )
      ) {
        return;
      }
      NaanoBuilder._iframePost({
        type: "naano-delete-element",
        elId: NaanoBuilder.selectedElId,
      });
      NaanoBuilder._clearElementSelection();
    },

    /**
     * Mark a section as having unsaved manual edits and refresh the
     * "Save changes" button affordance.
     */
    _markUnsaved: function (sectionId) {
      if (sectionId) {
        NaanoBuilder._dirtySections[sectionId] = true;
      }
      NaanoBuilder._refreshSaveChangesBtn();
    },

    /**
     * @return {boolean} True if there are unsaved sections OR unsaved global CSS.
     */
    _hasUnsavedChanges: function () {
      return (
        NaanoBuilder._dirtyGlobalCss ||
        Object.keys(NaanoBuilder._dirtySections).length > 0
      );
    },

    /**
     * Show/hide and update the counter on the toolbar's Save changes button.
     */
    _refreshSaveChangesBtn: function () {
      var n = Object.keys(NaanoBuilder._dirtySections).length;
      var hasGlobal = NaanoBuilder._dirtyGlobalCss;
      var $btn = $("#naano-save-changes-btn");
      var $counter = $("#naano-save-changes-counter");
      if (!n && !hasGlobal) {
        $btn.hide();
        return;
      }
      $btn.show();
      var label = n + (hasGlobal ? " + CSS" : "");
      $counter.text(label).show();
    },

    /**
     * POST all dirty section HTML + the global CSS textarea to the server.
     */
    _saveChanges: function () {
      if (!NaanoBuilder._hasUnsavedChanges()) return;
      var $btn = $("#naano-save-changes-btn").prop("disabled", true);

      // Build the sections payload from sectionsData (which reflects the
      // current iframe state thanks to naano-element-html-updated events).
      var dirtyIds = Object.keys(NaanoBuilder._dirtySections);
      var sectionsPayload = (NaanoBuilder.sectionsData || [])
        .filter(function (s) {
          return s && s.id && dirtyIds.indexOf(s.id) !== -1;
        })
        .map(function (s) {
          return { id: s.id, html: s.html || "" };
        });

      var globalCss = $("#naano-global-css").val() || "";

      $.post(data.ajaxUrl, {
        action: "naano_save_section_html",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        sections: JSON.stringify(sectionsPayload),
        global_css: globalCss,
      })
        .done(function (resp) {
          if (resp && resp.success) {
            NaanoBuilder._dirtySections = {};
            NaanoBuilder._dirtyGlobalCss = false;
            NaanoBuilder._lastSavedGlobalCss = globalCss;
            NaanoBuilder._refreshSaveChangesBtn();
            var saved = (resp.data && resp.data.saved_count) || 0;
            NaanoBuilder._toast(
              "Saved " +
                saved +
                " section" +
                (saved === 1 ? "" : "s") +
                (resp.data && resp.data.global_css_saved
                  ? " + global CSS"
                  : ""),
              "success",
            );
          } else {
            NaanoBuilder._toast(
              (resp && resp.data && resp.data.message) || "Save failed.",
              "error",
            );
          }
        })
        .fail(function () {
          NaanoBuilder._toast("Save failed (network).", "error");
        })
        .always(function () {
          $btn.prop("disabled", false);
        });
    },

    /**
     * Retry one previously-failed section by launching a fresh
     * update_section job with a default "regenerate this section now"
     * instruction.
     */
    _retrySection: function (sectionId) {
      if (!sectionId) return;
      var entry = (NaanoBuilder._failedSections || []).filter(function (e) {
        return e && e.section_id === sectionId;
      })[0];
      var sectionType = (entry && entry.section_type) || sectionId;

      NaanoBuilder._toast("Retrying section: " + sectionType + "…", "success");

      NaanoBuilder._jobAjax({
        action: "naano_update_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: sectionId,
        instruction:
          "Generate this " +
          sectionType +
          " section from scratch using the page description and references already on file.",
        wp_menu_id: $("#naano-wp-menu-select").val() || 0,
      }).done(function (response) {
        if (response && response.success) {
          // Refresh sectionsData with the new HTML.
          var newHtml = response.data && response.data.section_html;
          if (newHtml) {
            var found = false;
            (NaanoBuilder.sectionsData || []).forEach(function (s) {
              if (s.id === sectionId) {
                s.html = newHtml;
                found = true;
              }
            });
            if (!found) {
              NaanoBuilder.sectionsData.push({
                id: sectionId,
                html: newHtml,
              });
            }
            NaanoBuilder._refreshLivePreview();
            NaanoBuilder._renderSectionsList();
          }
          NaanoBuilder._toast(
            "Section recovered: " + sectionType + " ✓",
            "success",
          );
          // Reload the failed list (server-side mark_section_recovered
          // already stripped this section).
          NaanoBuilder._loadFailedSections();
        } else {
          NaanoBuilder._toast(
            "Retry failed: " +
              ((response && response.data && response.data.message) ||
                "unknown error"),
            "error",
          );
        }
      });
    },

    /**
     * Fetch the current failed_sections list from the server and render it
     * in the sidebar drawer. Called after generation finishes, after retry,
     * and at builder boot if a pageId is already set.
     */
    _loadFailedSections: function () {
      if (!NaanoBuilder.pageId) return;
      $.post(data.ajaxUrl, {
        action: "naano_get_failed_sections",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
      }).done(function (resp) {
        if (!resp || !resp.success) return;
        var failed = (resp.data && resp.data.failed_sections) || [];
        var globalCss = (resp.data && resp.data.global_css) || "";
        NaanoBuilder._failedSections = failed;
        NaanoBuilder._renderFailedList();
        // Sync global CSS textarea to server state on load (only if user
        // hasn't already started editing it).
        if (!NaanoBuilder._dirtyGlobalCss) {
          $("#naano-global-css").val(globalCss);
          NaanoBuilder._lastSavedGlobalCss = globalCss;
        }
      });
    },

    /**
     * Render the failed sections list with retry buttons in the drawer.
     */
    _renderFailedList: function () {
      var failed = NaanoBuilder._failedSections || [];
      var $wrap = $("#naano-failed-sections-wrap");
      var $list = $("#naano-failed-list").empty();
      if (!failed.length) {
        $wrap.hide();
        $("#naano-failed-count").text("0");
        return;
      }
      $wrap.show();
      $("#naano-failed-count").text(String(failed.length));
      failed.forEach(function (f) {
        if (!f || !f.section_id) return;
        var $li = $('<li class="naano-failed-item"></li>');
        var $name = $('<span class="naano-failed-name"></span>').text(
          f.section_type || f.section_id,
        );
        var $reason = $('<span class="naano-failed-reason"></span>').text(
          f.reason || "(failed)",
        );
        var $btn = $(
          '<button type="button" class="naano-btn-secondary naano-failed-retry"></button>',
        )
          .attr("data-section-id", f.section_id)
          .html('<span class="dashicons dashicons-update"></span> Retry');
        $li.append($name).append($reason).append($btn);
        $list.append($li);
      });
    },

    /**
     * Convert an rgb(r,g,b) string to #rrggbb hex for use in <input type="color">.
     *
     * @param  {string} rgb
     * @return {string|null}
     */
    _rgbToHex: function (rgb) {
      var m = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
      if (!m) {
        return null;
      }
      return (
        "#" +
        [m[1], m[2], m[3]]
          .map(function (x) {
            return ("0" + parseInt(x, 10).toString(16)).slice(-2);
          })
          .join("")
      );
    },

    /**
     * Listen for postMessage events from the live-preview iframe.
     */
    _bindIframeMessages: function () {
      window.addEventListener("message", function (e) {
        var msg = e.data;
        if (!msg || !msg.type) {
          return;
        }

        // Legacy section-click — kept as a no-op since inspect is now the
        // default mode (the iframe never emits this message anymore unless
        // someone runs an older cached helper).
        if (msg.type === "naano-section-clicked") {
          // Intentionally ignored.
        }

        if (msg.type === "naano-element-selected") {
          NaanoBuilder.selectedElId = msg.elId;
          NaanoBuilder.selectedElSectionId = msg.sectionId;
          NaanoBuilder.selectedElTag = (msg.tagName || "").toLowerCase();
          NaanoBuilder._renderStylePanel(msg);
        }

        if (msg.type === "naano-element-deselected") {
          NaanoBuilder._clearElementSelection();
        }

        if (msg.type === "naano-element-html-updated") {
          // Mirror the new HTML into sectionsData and mark the section as
          // dirty so the toolbar's "Save changes" button appears.
          var idx = NaanoBuilder._findSectionIndex(msg.sectionId);
          if (idx !== -1) {
            NaanoBuilder.sectionsData[idx].html = msg.html;
          }
          NaanoBuilder._markUnsaved(msg.sectionId);
        }
      });
    },

    /**
     * Build the full-page srcdoc HTML from sectionsData and set on the iframe.
     */
    _refreshLivePreview: function () {
      var iframe = document.getElementById("naano-live-preview");
      if (!iframe) {
        return;
      }

      iframe.srcdoc = NaanoBuilder._buildIframeSrcdoc();
    },

    /**
     * Assemble all sections into a full HTML document with the interaction helper script injected.
     *
     * @return {string}
     */
    _buildIframeSrcdoc: function () {
      var sectionsHtml = "";
      NaanoBuilder.sectionsData.forEach(function (sec) {
        // Wrap each section so [data-section] is always present in the iframe
        // for click detection, highlight, loading overlay and live HTML updates.
        sectionsHtml +=
          '<div data-section="' + sec.id + '">' + sec.html + "</div>";
      });

      // Interaction helper script injected into the iframe.
      // Inspect mode is now the DEFAULT and only mode — clicking any element
      // selects it for editing. The previous "naano-section-clicked" path
      // (whole-section AI editing) is gone from the iframe; the user reaches
      // the AI editor for a section through the sections list in the parent
      // sidebar instead.
      //
      // Selected element behaviour:
      //   - outline highlight in orange
      //   - made contenteditable so the user can type new text in place
      //   - relevant computed styles + class list reported to parent for
      //     the floating editor panel
      var helperScript = [
        "(function(){",
        'var s=document.createElement("style");',
        "s.textContent=",
        // Hide the section-level hover/selection that no longer applies.
        '"[data-section]{cursor:default;}"',
        '+"[data-section].naano-section-loading{position:relative;pointer-events:none;}"',
        "+\"[data-section].naano-section-loading::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,0.65);z-index:9999;animation:naano-pulse 1s infinite;}\"",
        '+"@keyframes naano-pulse{0%,100%{opacity:0.5;}50%{opacity:1;}}"',
        '+"@keyframes naano-flash{0%{box-shadow:inset 0 0 0 3px rgba(34,113,177,0.7);}100%{box-shadow:none;}}"',
        // Element-level inspect outlines (now permanent).
        '+".naano-el-hover{outline:2px dashed #f59e0b!important;outline-offset:2px;}"',
        '+".naano-el-selected{outline:2px solid #f59e0b!important;outline-offset:2px;box-shadow:0 0 0 1px rgba(255,255,255,0.6)!important;}"',
        // Contenteditable affordance: subtle inset highlight + caret cursor.
        '+"[contenteditable=\\"true\\"]{cursor:text!important;outline:2px solid #f59e0b!important;outline-offset:2px;background:rgba(255,251,235,0.5);}"',
        // Inspect mode kept for legacy class hooks.
        '+"body.naano-inspect-active{cursor:default;}";',
        "document.head.appendChild(s);",

        // Inspect-mode is now ALWAYS active.
        "var inspectActive=true;",
        "document.body.classList.add('naano-inspect-active');",
        "var elCounter=0;",
        "var currentSelected=null;",

        "function getOrAssignId(el){",
        '  if(!el.dataset.naanoEl)el.dataset.naanoEl="nel-"+(++elCounter);',
        "  return el.dataset.naanoEl;",
        "}",

        "function getSectionId(el){",
        "  var p=el;",
        "  while(p&&p!==document.body){",
        '    if(p.hasAttribute("data-section"))return p.getAttribute("data-section");',
        "    p=p.parentElement;",
        "  }",
        "  return null;",
        "}",

        "function buildBreadcrumb(el){",
        "  var parts=[];var cur=el;",
        "  while(cur&&cur!==document.body){",
        "    var tag=cur.tagName.toLowerCase();",
        '    if(cur.id)tag+="#"+cur.id;',
        '    else if(cur.className&&typeof cur.className==="string"){',
        '      var cls=cur.className.trim().split(/\\s+/).slice(0,2).join(".");',
        '      if(cls)tag+="."+cls;',
        "    }",
        "    parts.unshift(tag);cur=cur.parentElement;",
        '    if(parts.length>4){parts.unshift("\\u2026");break;}',
        "  }",
        '  return parts.join(" \\u203a ");',
        "}",

        // Notify parent that the section's HTML changed (called whenever
        // we mutate the DOM: style, class, delete, text edit).
        "function notifySectionChanged(el){",
        '  var sectionEl=el&&el.closest&&el.closest("[data-section]");',
        "  if(!sectionEl)return;",
        '  window.parent.postMessage({type:"naano-element-html-updated",',
        '    sectionId:sectionEl.getAttribute("data-section"),',
        '    html:sectionEl.innerHTML},"*");',
        "}",

        // Disable all link navigation inside the iframe so clicks select
        // instead of opening pages. Capture phase, prevents any lingering
        // anchor behaviour even if the user clicks an <a> child.
        'document.addEventListener("click",function(e){',
        "  var anchor=e.target&&e.target.closest&&e.target.closest('a');",
        "  if(anchor)e.preventDefault();",
        "},true);",

        // Hover highlight.
        'document.addEventListener("mouseover",function(e){',
        '  document.querySelectorAll(".naano-el-hover").forEach(function(n){n.classList.remove("naano-el-hover");});',
        "  var el=e.target;",
        "  if(!el||el===document.body||el===document.documentElement)return;",
        // Don't show hover outline on the currently-selected element
        // (would make the dashed outline fight with the solid one).
        "  if(el===currentSelected)return;",
        '  el.classList.add("naano-el-hover");',
        "});",

        // Click to select an element.
        'document.addEventListener("click",function(e){',
        // If the user clicked inside the currently-selected (and now
        // contenteditable) element, let them place the caret freely
        // without re-selecting and resetting state.
        "  if(currentSelected&&currentSelected.contains(e.target)&&",
        "     currentSelected.getAttribute('contenteditable')==='true'){",
        "    return;",
        "  }",
        "  e.stopImmediatePropagation();e.preventDefault();",
        "  var el=e.target;",
        "  if(!el||el===document.body||el===document.documentElement)return;",

        // Clear previous selection's contenteditable and outline.
        "  if(currentSelected){",
        "    currentSelected.removeAttribute('contenteditable');",
        '    currentSelected.classList.remove("naano-el-selected");',
        "  }",

        "  var elId=getOrAssignId(el);",
        "  var sectionId=getSectionId(el);",
        "  var cs=window.getComputedStyle(el);",
        '  var styleProps=[\"color\",\"backgroundColor\",\"fontSize\",\"fontWeight\",\"textAlign\",',
        '    \"width\",\"height\",\"maxWidth\",',
        '    \"paddingTop\",\"paddingRight\",\"paddingBottom\",\"paddingLeft\",',
        '    \"marginTop\",\"marginRight\",\"marginBottom\",\"marginLeft\",',
        '    \"border\",\"borderRadius\",\"backgroundImage\",\"backgroundSize\"];',
        "  var computed={};",
        '  styleProps.forEach(function(p){computed[p]=cs[p]||"";});',

        // Compute the user's "extra" classes — i.e. anything currently on
        // the element that is NOT one of our internal helper hooks. The
        // editor will populate the Classes field with this list and any
        // edit replaces the user's classes (helper hooks are preserved).
        "  var INTERNAL_CLS=['naano-el-hover','naano-el-selected'];",
        "  var existingCls=(el.className&&typeof el.className==='string')",
        "    ?el.className.trim().split(/\\s+/).filter(function(c){",
        "      return c&&INTERNAL_CLS.indexOf(c)===-1;",
        "    }):[];",

        '  el.classList.add("naano-el-selected");',
        // Make the element editable in place so the user can type.
        "  el.setAttribute('contenteditable','true');",
        "  currentSelected=el;",

        // Anchor-specific info: when the selected element is an <a>, ship
        // its current href / target / rel so the floating panel's Link tab
        // can pre-populate.
        "  var linkInfo=null;",
        "  if(el.tagName==='A'){",
        "    linkInfo={",
        "      href:el.getAttribute('href')||'',",
        "      target:el.getAttribute('target')||'_self',",
        "      rel:el.getAttribute('rel')||''",
        "    };",
        "  }",

        '  window.parent.postMessage({type:"naano-element-selected",',
        "    elId:elId,",
        "    sectionId:sectionId,",
        "    tagName:el.tagName.toLowerCase(),",
        "    breadcrumb:buildBreadcrumb(el),",
        "    computed:computed,",
        "    classes:existingCls.join(' '),",
        '    linkInfo:linkInfo},"*");',
        "},true);",

        // Capture text edits inline. Debounced via input event — fires on
        // every keystroke but the parent only persists on Save.
        'document.addEventListener("input",function(e){',
        "  if(!currentSelected)return;",
        "  if(!currentSelected.contains(e.target)&&e.target!==currentSelected)return;",
        "  notifySectionChanged(currentSelected);",
        "});",

        // Listen for messages from parent.
        'window.addEventListener("message",function(e){',
        "  var m=e.data;if(!m||!m.type)return;",

        // Update a specific section's HTML (used after AI regeneration).
        '  if(m.type==="naano-update-section"){',
        "    var el=document.querySelector('[data-section=\"'+m.sectionId+'\"]');",
        "    if(el){",
        '      el.classList.remove("naano-section-loading");',
        "      el.innerHTML=m.html;",
        // The element we had selected just got replaced — clear our ref.
        "      if(currentSelected&&!document.body.contains(currentSelected))currentSelected=null;",
        '      el.style.animation="naano-flash 1.5s ease forwards";',
        '      setTimeout(function(){el.style.animation="";},1600);',
        "    }",
        "  }",

        // Highlight a section (e.g. when user picks one in sidebar list).
        '  if(m.type==="naano-highlight-section"){',
        '    document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
        "    var el=document.querySelector('[data-section=\"'+m.sectionId+'\"]');",
        "    if(el){",
        '      el.classList.add("naano-section-selected");',
        "      el.scrollIntoView({behavior:'smooth',block:'start'});",
        "    }",
        "  }",

        // Highlight multiple sections at once (used for multi-select).
        '  if(m.type==="naano-highlight-sections"){',
        '    document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
        "    (m.sectionIds||[]).forEach(function(id){",
        "      var el=document.querySelector('[data-section=\"'+id+'\"]');",
        '      if(el)el.classList.add("naano-section-selected");',
        "    });",
        "  }",

        // Show/hide loading overlay on a section.
        '  if(m.type==="naano-loading-section"){',
        "    var el=document.querySelector('[data-section=\"'+m.sectionId+'\"]');",
        "    if(el){",
        '      if(m.loading)el.classList.add("naano-section-loading");',
        '      else el.classList.remove("naano-section-loading");',
        "    }",
        "  }",

        // Inspect mode is permanent now; this message is kept for backward
        // compatibility but only toggles the visual class hook.
        '  if(m.type==="naano-inspect-mode"){',
        '    document.body.classList.toggle("naano-inspect-active",!!m.active);',
        "  }",

        // Apply inline styles + custom CSS to selected element.
        '  if(m.type==="naano-apply-element-style"){',
        "    var el=document.querySelector('[data-naano-el=\"'+m.elId+'\"]');",
        "    if(!el)return;",
        "    var props=m.styles||{};",
        '    Object.keys(props).forEach(function(p){if(props[p]!=="")el.style[p]=props[p];});',
        "    if(m.customCss&&m.customCss.trim()){",
        '      var styleId="naano-custom-"+m.elId;',
        "      var existing=document.getElementById(styleId);",
        "      if(existing)existing.remove();",
        '      var tag=document.createElement("style");',
        "      tag.id=styleId;",
        "      tag.textContent='[data-naano-el=\"'+m.elId+'\"]{'+ m.customCss +'}';",
        "      document.head.appendChild(tag);",
        "    }",
        "    notifySectionChanged(el);",
        "  }",

        // Apply user-provided class names (replaces previous user classes,
        // preserves any existing AI-generated classes that were already
        // there before the user's edit since we tracked them as 'extra').
        '  if(m.type==="naano-apply-element-classes"){',
        "    var el=document.querySelector('[data-naano-el=\"'+m.elId+'\"]');",
        "    if(!el)return;",
        "    var INTERNAL_CLS=['naano-el-hover','naano-el-selected'];",
        // Strip everything except internal hooks + AI-original classes.
        // m.aiClasses contains the classes that were on the element when
        // first selected; m.userClasses is the new user-typed set.
        "    var aiCls=(m.aiClasses||'').split(/\\s+/).filter(Boolean);",
        "    var userCls=(m.userClasses||'').split(/\\s+/).filter(Boolean);",
        "    var keepInternal=(el.className||'').split(/\\s+/).filter(function(c){",
        "      return INTERNAL_CLS.indexOf(c)!==-1;",
        "    });",
        "    var merged=keepInternal.concat(aiCls).concat(userCls);",
        // Dedupe.
        "    var seen={};var dedup=[];merged.forEach(function(c){if(c&&!seen[c]){seen[c]=1;dedup.push(c);}});",
        "    el.className=dedup.join(' ');",
        "    notifySectionChanged(el);",
        "  }",

        // Apply link attributes (href, target, rel) to an <a> element.
        // Empty href removes the attribute outright. Same for rel/target.
        '  if(m.type==="naano-apply-element-link"){',
        "    var el=document.querySelector('[data-naano-el=\"'+m.elId+'\"]');",
        "    if(!el)return;",
        "    if(el.tagName!=='A')return;",
        "    if(typeof m.href==='string'){",
        "      if(m.href)el.setAttribute('href',m.href);",
        "      else el.removeAttribute('href');",
        "    }",
        "    if(typeof m.target==='string'){",
        "      if(m.target&&m.target!=='_self')el.setAttribute('target',m.target);",
        "      else el.removeAttribute('target');",
        "    }",
        "    if(typeof m.rel==='string'){",
        "      if(m.rel)el.setAttribute('rel',m.rel);",
        "      else el.removeAttribute('rel');",
        "    }",
        "    notifySectionChanged(el);",
        "  }",

        // Delete the selected element from its parent.
        '  if(m.type==="naano-delete-element"){',
        "    var el=document.querySelector('[data-naano-el=\"'+m.elId+'\"]');",
        "    if(!el)return;",
        '    var sectionEl=el.closest("[data-section]");',
        "    if(el===sectionEl)return;", // Don't allow deleting the section wrapper itself.
        "    el.remove();",
        "    if(currentSelected===el)currentSelected=null;",
        "    if(sectionEl){",
        '      window.parent.postMessage({type:"naano-element-html-updated",',
        '        sectionId:sectionEl.getAttribute("data-section"),html:sectionEl.innerHTML},"*");',
        "    }",
        "  }",

        // Parent asks us to deselect (panel closed by user).
        '  if(m.type==="naano-deselect-element"){',
        "    if(currentSelected){",
        "      currentSelected.removeAttribute('contenteditable');",
        '      currentSelected.classList.remove("naano-el-selected");',
        "    }",
        '    document.querySelectorAll(".naano-el-hover").forEach(function(n){n.classList.remove("naano-el-hover");});',
        "    currentSelected=null;",
        "  }",
        "});",

        // Escape key inside iframe deselects.
        'document.addEventListener("keydown",function(e){',
        '  if(e.key==="Escape"&&currentSelected){',
        "    currentSelected.removeAttribute('contenteditable');",
        '    currentSelected.classList.remove("naano-el-selected");',
        "    currentSelected=null;",
        '    window.parent.postMessage({type:"naano-element-deselected"},"*");',
        "  }",
        "});",
        "}());",
      ].join("");

      // Read the user's "Global CSS" textarea so the live preview matches
      // the published page (same CSS will be injected server-side at
      // assembly time). Also reset the default 8px body margin every
      // browser ships with so sections sit flush against the page edges.
      var userGlobalCss = "";
      var $gcss = $("#naano-global-css");
      if ($gcss.length) userGlobalCss = $gcss.val() || "";
      var baseReset =
        "html,body{margin:0;padding:0;}" +
        "body{box-sizing:border-box;}" +
        "*,*::before,*::after{box-sizing:inherit;}";

      return (
        "<!DOCTYPE html><html><head>" +
        '<meta charset="utf-8">' +
        '<meta name="viewport" content="width=device-width,initial-scale=1">' +
        "<style>" +
        baseReset +
        "\n" +
        userGlobalCss +
        "</style>" +
        "</head><body>" +
        sectionsHtml +
        "<script>" +
        helperScript +
        "<\/script>" +
        "</body></html>"
      );
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
    _iframePost: function (msg) {
      var iframe = document.getElementById("naano-live-preview");
      if (iframe && iframe.contentWindow) {
        iframe.contentWindow.postMessage(msg, "*");
      }
    },

    /**
     * Render the section quick-select list in the drawer.
     */
    _renderSectionsList: function () {
      var $list = $("#naano-sections-list").empty();

      NaanoBuilder.sectionsData.forEach(function (sec) {
        var displayName = NaanoBuilder._displayName(sec.id);

        var $item = $("<li>", {
          class: "naano-sections-list__item",
          id: "naano-sl-item-" + sec.id,
          draggable: "true",
          "data-id": sec.id,
        });

        $item.html(
          '<span class="naano-sections-list__handle" title="Drag to reorder">⠿</span>' +
            '<span class="naano-sections-list__name">' +
            $("<span>").text(displayName).html() +
            "</span>" +
            '<span class="naano-sections-list__actions">' +
            '<button type="button" class="naano-sections-list__btn" data-action="edit" data-id="' +
            sec.id +
            '" title="Edit">✏️</button>' +
            '<button type="button" class="naano-sections-list__btn naano-sections-list__btn--delete" data-action="delete" data-id="' +
            sec.id +
            '" title="Delete">🗑️</button>' +
            "</span>",
        );

        // Click on the item row toggles section selection.
        $item.on("click", function (e) {
          if (
            $(e.target).closest("[data-action], .naano-sections-list__handle")
              .length
          ) {
            return;
          }
          NaanoBuilder.openEditPanel(sec.id);
        });

        // Action buttons.
        $item.find("[data-action=edit]").on("click", function (e) {
          e.stopPropagation();
          NaanoBuilder.openEditPanel(sec.id);
        });

        $item.find("[data-action=delete]").on("click", function (e) {
          e.stopPropagation();
          NaanoBuilder.deleteSection(sec.id);
        });

        // ── Drag-and-drop reordering ──────────────────────────────────
        var el = $item.get(0);

        el.addEventListener("dragstart", function (e) {
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData("text/plain", sec.id);
          setTimeout(function () {
            $item.addClass("naano-sections-list__item--dragging");
          }, 0);
        });

        el.addEventListener("dragend", function () {
          $item.removeClass("naano-sections-list__item--dragging");
          $("#naano-sections-list .naano-sections-list__item").removeClass(
            "naano-sections-list__item--dragover",
          );
        });

        el.addEventListener("dragover", function (e) {
          e.preventDefault();
          e.dataTransfer.dropEffect = "move";
          $("#naano-sections-list .naano-sections-list__item").removeClass(
            "naano-sections-list__item--dragover",
          );
          $item.addClass("naano-sections-list__item--dragover");
        });

        el.addEventListener("dragleave", function (e) {
          if (!el.contains(e.relatedTarget)) {
            $item.removeClass("naano-sections-list__item--dragover");
          }
        });

        el.addEventListener("drop", function (e) {
          e.preventDefault();
          $item.removeClass("naano-sections-list__item--dragover");

          var fromId = e.dataTransfer.getData("text/plain");
          var toId = sec.id;
          if (fromId === toId) {
            return;
          }

          var fromIdx = NaanoBuilder._findSectionIndex(fromId);
          var toIdx = NaanoBuilder._findSectionIndex(toId);
          if (fromIdx === -1 || toIdx === -1) {
            return;
          }

          // Move dragged item to drop target position.
          var moved = NaanoBuilder.sectionsData.splice(fromIdx, 1)[0];
          NaanoBuilder.sectionsData.splice(toIdx, 0, moved);

          NaanoBuilder._refreshLivePreview();
          NaanoBuilder._renderSectionsList();
          NaanoBuilder.reorderSections();
        });

        $list.append($item);
      });
    },

    /**
     * Add a new section by prompting the AI.
     *
     * @param {string} sectionName
     */
    _addNewSection: function (sectionName) {
      // Use updateSection mechanism: a new section_id and instruction.
      var instruction =
        'Create a new "' + sectionName + '" section for this website.';
      var slug = sectionName
        .toLowerCase()
        .replace(/\s+/g, "-")
        .replace(/[^a-z0-9-]/g, "");

      NaanoBuilder._setLoading(
        "#naano-add-new-section-btn",
        "#naano-update-loading",
        true,
      );
      NaanoBuilder._showCanvasLoading({ filename: slug + ".html" });

      NaanoBuilder._jobAjax({
        action: "naano_update_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: slug,
        instruction: instruction,
      })
        .done(function (response) {
          NaanoBuilder._setLoading(
            "#naano-add-new-section-btn",
            "#naano-update-loading",
            false,
          );
          NaanoBuilder._hideCanvasLoading();
          if (response.success) {
            var id = response.data.section_id;
            var html = response.data.section_html;

            var idx = NaanoBuilder._findSectionIndex(id);
            if (idx !== -1) {
              NaanoBuilder.sectionsData[idx].html = html;
            } else {
              NaanoBuilder.sectionsData.push({ id: id, html: html });
            }

            NaanoBuilder._refreshLivePreview();
            NaanoBuilder._renderSectionsList();
            NaanoBuilder._toast(
              'Section "' + sectionName + '" added! ✨',
              "success",
            );
          } else {
            NaanoBuilder._toast(
              (response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
          }
        })
        .fail(function () {
          NaanoBuilder._setLoading(
            "#naano-add-new-section-btn",
            "#naano-update-loading",
            false,
          );
          NaanoBuilder._hideCanvasLoading();
          NaanoBuilder._toast(data.strings.error_generic, "error");
        });
    },

    /**
     * Run a long AJAX action through the async-job pattern: POST to the
     * start endpoint, get back a job_id, then poll naano_poll_job until
     * the job finishes. Returns a jQuery Deferred promise that mimics the
     * shape of $.post(data.ajaxUrl, ...) so call sites only need to swap
     * the function name — .done(response) and .fail() callbacks behave
     * exactly as before.
     *
     * If the start endpoint returns the final result synchronously
     * (e.g. validation error, or imported-only generation), the response
     * is forwarded as-is to .done().
     *
     * @param {Object} startParams Same shape as the original $.post body
     *                             (must include `action` and `nonce`).
     * @return {Promise}
     */
    _jobAjax: function (startParams) {
      var deferred = $.Deferred();
      var pollInterval = 1500;
      var pollMaxBackoff = 5000;
      // Deadline must comfortably cover the worst case: 38+ cron ticks for a
      // 12-section site, with the cPanel cron OS as the slowest backstop
      // (~1 min between ticks if spawn_cron is throttled). 60 min gives a
      // healthy margin without the UI giving up on a healthy slow job.
      var pollDeadline = Date.now() + 60 * 60 * 1000;
      var debugTag = "[Naano " + (startParams.action || "?") + "]";
      var pollCount = 0;
      // Most recent successful poll payload — kept so we can forward any
      // `partial` data even when the final outcome is an error or timeout.
      var lastPollData = null;

      $.post(data.ajaxUrl, startParams)
        .done(function (startResp) {
          if (
            !startResp ||
            !startResp.success ||
            !startResp.data ||
            !startResp.data.job_id
          ) {
            console.warn(
              debugTag,
              "no job_id in start response — forwarding as-is",
            );
            deferred.resolve(startResp);
            return;
          }

          var jobId = startResp.data.job_id;

          var poll = function () {
            if (Date.now() > pollDeadline) {
              deferred.resolve({
                success: false,
                data: {
                  message: "Generation timed out waiting for the server.",
                  // Forward the most recent partial state we know about so
                  // the caller can still show whatever sections persisted
                  // before the deadline.
                  partial: (lastPollData && lastPollData.partial) || null,
                  status: (lastPollData && lastPollData.status) || "running",
                  log: (lastPollData && lastPollData.log) || null,
                },
              });
              return;
            }

            pollCount++;

            $.post(data.ajaxUrl, {
              action: "naano_poll_job",
              nonce: data.nonce,
              job_id: jobId,
            })
              .done(function (pollResp) {
                if (!pollResp || !pollResp.success || !pollResp.data) {
                  console.error(debugTag, "poll lookup failed", pollResp);
                  deferred.resolve({
                    success: false,
                    data: (pollResp && pollResp.data) || {
                      message: "Job lookup failed.",
                    },
                  });
                  return;
                }

                lastPollData = pollResp.data;
                var status = pollResp.data.status;

                if (status === "done") {
                  if (pollResp.data.log) {
                    console.log(debugTag, "job log:", pollResp.data.log);
                  }
                  deferred.resolve({
                    success: true,
                    data: pollResp.data.data || {},
                    // Surface partial alongside the success path too, so
                    // call sites have a consistent shape to read from.
                    partial: pollResp.data.partial || null,
                  });
                  return;
                }
                if (status === "error") {
                  console.error(debugTag, "✗ error:", pollResp.data.error);
                  if (pollResp.data.log) {
                    console.log(debugTag, "job log:", pollResp.data.log);
                  }
                  deferred.resolve({
                    success: false,
                    data: {
                      message: pollResp.data.error || "Generation failed.",
                      // Sections that successfully persisted before the
                      // failing tick. The caller decides whether to show
                      // them — see naano_generate_site .done() below.
                      partial: pollResp.data.partial || null,
                      status: "error",
                      log: pollResp.data.log || null,
                    },
                  });
                  return;
                }

                setTimeout(poll, pollInterval);
                pollInterval = Math.min(pollInterval + 500, pollMaxBackoff);
              })
              .fail(function (jq, st, er) {
                console.warn(
                  debugTag,
                  "poll #" + pollCount,
                  "network fail, retrying",
                  st,
                  er,
                );
                setTimeout(poll, pollInterval);
                pollInterval = Math.min(pollInterval + 500, pollMaxBackoff);
              });
          };

          setTimeout(poll, pollInterval);
        })
        .fail(function (jqxhr, status, err) {
          console.error(
            debugTag,
            "start request FAILED",
            status,
            err,
            jqxhr && jqxhr.responseText,
          );
          deferred.reject(jqxhr, status, err);
        });

      return deferred.promise();
    },

    _showBuilder: function () {
      $("#naano-drawer-generate").hide();
      $("#naano-drawer-edit").show();
      $("#naano-canvas-placeholder").hide();
      $("#naano-live-iframe-wrap").show();
      // Pull the per-page state that lives in post meta (failed sections
      // list + saved global CSS) so the drawer reflects reality even after
      // a refresh. Safe to call without a pageId — the helper no-ops.
      NaanoBuilder._loadFailedSections();
    },

    _showCanvasLoading: function (opts) {
      opts = opts || {};
      var filename = opts.filename || "output.html";
      $("#naano-cla-title-text").text(filename + " — generating");
      $("#naano-cla-filename").text(filename);
      $("#naano-canvas-loading-overlay").addClass(
        "naano-canvas-loading-overlay--visible",
      );
      NaanoBuilder._claStart();
    },

    _hideCanvasLoading: function () {
      NaanoBuilder._claStop();
      $("#naano-canvas-loading-overlay").removeClass(
        "naano-canvas-loading-overlay--visible",
      );
    },

    _claStart: function () {
      NaanoBuilder._claStop();

      // Restart progress-bar CSS animation via clone trick.
      var pfill = document.getElementById("naano-cla-progress-fill");
      if (pfill) {
        var clone = pfill.cloneNode(false);
        pfill.parentNode.replaceChild(clone, pfill);
      }

      // Clear code lines and counters.
      var linesEl = document.getElementById("naano-cla-code-lines");
      if (linesEl) {
        linesEl.innerHTML = "";
      }
      $("#naano-cla-token-count").text("0");
      $("#naano-cla-line-count").text("0");
      $("#naano-cla-speed").text("0");
      $("#naano-cla-elapsed").text("0.0");

      var state = {
        lineIndex: 0,
        tokenCount: 0,
        elapsed: 0,
        lastTime: performance.now(),
        lineTimer: null,
        statsTimer: null,
        rainInterval: null,
      };
      NaanoBuilder._claState = state;

      // ── Matrix rain ──────────────────────────────────────────────
      var canvas = document.getElementById("naano-cla-rain");
      var overlay = document.getElementById("naano-canvas-loading-overlay");
      if (canvas && overlay) {
        canvas.width = overlay.offsetWidth;
        canvas.height = overlay.offsetHeight;
        var ctx = canvas.getContext("2d");
        var chars = "01\u30A2\u30A4\u30A6\u30A8\u30AA{}[]<>/\\;:=()!?#";
        var cols = Math.floor(canvas.width / 18);
        var drops = new Array(cols).fill(1);
        state.rainInterval = setInterval(function () {
          ctx.fillStyle = "rgba(8,12,16,0.1)";
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.fillStyle = "#00e5ff";
          ctx.font = "13px Courier New, monospace";
          for (var i = 0; i < drops.length; i++) {
            var c = chars[Math.floor(Math.random() * chars.length)];
            ctx.fillText(c, i * 18, drops[i] * 18);
            if (drops[i] * 18 > canvas.height && Math.random() > 0.97) {
              drops[i] = 0;
            }
            drops[i]++;
          }
        }, 55);
      }

      // ── Fake code lines ──────────────────────────────────────────
      var codeData = [
        [
          ["k", "<!DOCTYPE "],
          ["p", "html"],
          ["k", ">"],
        ],
        [
          ["t", "<html "],
          ["a", "lang"],
          ["p", "="],
          ["s", '"en"'],
          ["t", ">"],
        ],
        [["t", "<head>"]],
        [["c", "  <!-- meta & viewport -->"]],
        [
          ["t", "  <meta "],
          ["a", "charset"],
          ["p", "="],
          ["s", '"UTF-8"'],
          ["p", "/>"],
        ],
        [
          ["t", "  <meta "],
          ["a", "name"],
          ["p", '="viewport" '],
          ["a", "content"],
          ["p", "="],
          ["s", '"width=device-width"'],
          ["p", "/>"],
        ],
        [
          ["t", "  <title>"],
          ["p", "Page"],
          ["t", "</title>"],
        ],
        [["t", "</head>"]],
        [["t", "<body>"]],
        [
          ["t", "  <div "],
          ["a", "class"],
          ["p", "="],
          ["s", '"app"'],
          ["t", ">"],
        ],
        [
          ["t", "    <nav "],
          ["a", "class"],
          ["p", "="],
          ["s", '"navbar"'],
          ["t", ">"],
        ],
        [
          ["t", "      <a "],
          ["a", "href"],
          ["p", "="],
          ["s", '"/"'],
          ["t", ">"],
          ["p", "Home"],
          ["t", "</a>"],
        ],
        [["t", "    </nav>"]],
        [["t", "    <main>"]],
        [
          ["t", "      <section "],
          ["a", "id"],
          ["p", "="],
          ["s", '"hero"'],
          ["t", ">"],
        ],
        [["k", "      <style>"]],
        [
          ["p", "        .hero { display: "],
          ["n", "grid"],
          ["p", "; }"],
        ],
        [
          ["p", "          gap: "],
          ["n", "2rem"],
          ["p", "; padding: "],
          ["n", "4rem 2rem"],
          ["p", ";"],
        ],
        [["p", "          background: linear-gradient("]],
        [
          ["n", "            135deg"],
          ["p", ","],
        ],
        [
          ["s", "            #0d1117"],
          ["p", ", "],
          ["s", "#1a1f2e"],
          ["p", " );"],
        ],
        [["k", "      </style>"]],
        [["t", "      </section>"]],
        [["t", "    </main>"]],
        [["t", "  </div>"]],
        [["k", "<script>"]],
        [
          ["k", "  const "],
          ["f", "init"],
          ["p", " = () => {"],
        ],
        [
          ["k", "    const "],
          ["p", "el = document.querySelector( "],
          ["s", '"#app"'],
          ["p", " );"],
        ],
        [
          ["p", "    el.classList.add( "],
          ["s", '"ready"'],
          ["p", " );"],
        ],
        [
          ["k", "    fetch"],
          ["p", "( "],
          ["s", '"/api/content"'],
          ["p", " )"],
        ],
        [
          ["p", "      ."],
          ["f", "then"],
          ["p", "( r => r."],
          ["f", "json"],
          ["p", "() )"],
        ],
        [
          ["p", "      ."],
          ["f", "then"],
          ["p", "( data => "],
          ["f", "render"],
          ["p", "( data ) );"],
        ],
        [["p", "  };"]],
        [
          ["f", "  document"],
          ["p", ".addEventListener( "],
          ["s", '"DOMContentLoaded"'],
          ["p", ", init );"],
        ],
        [["k", "</script>"]],
        [["t", "</body>"]],
        [["t", "</html>"]],
      ];

      function addCodeLine() {
        var el = document.getElementById("naano-cla-code-lines");
        if (!el) {
          return;
        }
        var fragment = codeData[state.lineIndex % codeData.length];
        var ln = String(state.lineIndex + 1).padStart(3, " ");
        var inner = fragment
          .map(function (tok) {
            return (
              '<span class="naano-cla-' +
              tok[0] +
              '">' +
              tok[1]
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;") +
              "</span>"
            );
          })
          .join("");
        var row = document.createElement("div");
        row.className = "naano-cla-code-line";
        row.innerHTML = '<span class="naano-cla-ln">' + ln + "</span>" + inner;
        el.appendChild(row);
        // Move cursor to last line.
        var old = el.querySelector(".naano-cla-cursor");
        if (old) {
          old.remove();
        }
        var cur = document.createElement("span");
        cur.className = "naano-cla-cursor";
        row.appendChild(cur);
        // Keep last 22 lines visible.
        while (el.children.length > 22) {
          el.removeChild(el.firstChild);
        }
        state.lineIndex++;
        var charCount = fragment.reduce(function (acc, t) {
          return acc + t[1].length;
        }, 0);
        state.tokenCount += charCount;
        var lc = document.getElementById("naano-cla-line-count");
        var tc = document.getElementById("naano-cla-token-count");
        if (lc) {
          lc.textContent = state.lineIndex;
        }
        if (tc) {
          tc.textContent = state.tokenCount.toLocaleString();
        }
      }

      function nextLine() {
        state.lineTimer = setTimeout(
          function () {
            if (!NaanoBuilder._claState) {
              return;
            }
            addCodeLine();
            nextLine();
          },
          120 + Math.random() * 160,
        );
      }
      nextLine();

      // Stats interval.
      state.statsTimer = setInterval(function () {
        if (!NaanoBuilder._claState) {
          return;
        }
        var now = performance.now();
        state.elapsed += (now - state.lastTime) / 1000;
        state.lastTime = now;
        var speed =
          state.elapsed > 0 ? Math.round(state.tokenCount / state.elapsed) : 0;
        var elapsedEl = document.getElementById("naano-cla-elapsed");
        var speedEl = document.getElementById("naano-cla-speed");
        if (elapsedEl) {
          elapsedEl.textContent = state.elapsed.toFixed(1);
        }
        if (speedEl) {
          speedEl.textContent = speed;
        }
      }, 250);
    },

    _claStop: function () {
      var s = NaanoBuilder._claState;
      if (!s) {
        return;
      }
      clearTimeout(s.lineTimer);
      clearInterval(s.statsTimer);
      clearInterval(s.rainInterval);
      NaanoBuilder._claState = null;
    },

    _bindAssetsPanel: function () {
      // "From URL" button — show the URL form.
      $(document).on("click", "#naano-add-asset-btn", function () {
        if (!NaanoBuilder.editingSectionId) {
          NaanoBuilder._toast(data.strings.select_section, "error");
          return;
        }
        $("#naano-add-asset-form").show();
        $("#naano-asset-picker").hide();
        $("#naano-asset-url").focus();
      });

      // "From Media Library" button.
      $(document).on("click", "#naano-add-asset-media-btn", function () {
        if (!NaanoBuilder.editingSectionId) {
          NaanoBuilder._toast(data.strings.select_section, "error");
          return;
        }
        NaanoBuilder.addAssetFromMedia();
      });

      $(document).on("click", "#naano-cancel-asset-btn", function () {
        $("#naano-add-asset-form").hide().find("input").val("");
        $("#naano-asset-picker").show();
      });

      $(document).on("click", "#naano-save-asset-btn", function () {
        var url = $("#naano-asset-url").val().trim();
        var desc = $("#naano-asset-desc").val().trim();
        if (!url) {
          $("#naano-asset-url").focus();
          return;
        }
        NaanoBuilder.pageAssets.push({ url: url, desc: desc });
        NaanoBuilder._renderAssetList();
        NaanoBuilder._persistAssets();
        $("#naano-add-asset-form").hide().find("input").val("");
        $("#naano-asset-picker").show();
      });

      $(document).on("click", ".naano-remove-asset-btn", function () {
        var idx = parseInt($(this).data("index"), 10);
        NaanoBuilder.pageAssets.splice(idx, 1);
        NaanoBuilder._renderAssetList();
        NaanoBuilder._persistAssets();
      });

      $(document).on(
        "keydown",
        "#naano-asset-url, #naano-asset-desc",
        function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            $("#naano-save-asset-btn").trigger("click");
          }
        },
      );
    },

    _renderAssetList: function () {
      var $list = $("#naano-asset-list").empty();
      NaanoBuilder.pageAssets.forEach(function (asset, i) {
        var label =
          "#" +
          (i + 1) +
          ": " +
          asset.url +
          (asset.desc ? " — " + asset.desc : "");
        $list.append(
          $('<li class="naano-reference-item">')
            .append($("<span>").text(label))
            .append(
              ' <button type="button" class="naano-remove-ref-btn naano-remove-asset-btn" data-index="' +
                i +
                '">✕</button>',
            ),
        );
      });
    },

    /**
     * Open the WP media library to pick an asset URL.
     */
    addAssetFromMedia: function () {
      var frame = wp.media({
        title: "Select Asset",
        button: { text: "Use this file" },
        multiple: false,
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        NaanoBuilder.pageAssets.push({
          url: attachment.url,
          desc: attachment.title || "",
        });
        NaanoBuilder._renderAssetList();
        NaanoBuilder._persistAssets();
        NaanoBuilder._toast("Asset added!", "success");
      });

      frame.open();
    },

    _bindRedirectsPanel: function () {
      $(document).on("click", "#naano-add-redirect-btn", function () {
        if (!NaanoBuilder.editingSectionId) {
          NaanoBuilder._toast(data.strings.select_section, "error");
          return;
        }
        $("#naano-add-redirect-form").show();
        $("#naano-add-redirect-btn").hide();
        $("#naano-redirect-label").focus();
      });

      $(document).on("click", "#naano-cancel-redirect-btn", function () {
        $("#naano-add-redirect-form").hide().find("input").val("");
        $("#naano-add-redirect-btn").show();
      });

      $(document).on("click", "#naano-save-redirect-btn", function () {
        var label = $("#naano-redirect-label").val().trim();
        var url = $("#naano-redirect-url").val().trim();
        if (!label || !url) {
          $(!label ? "#naano-redirect-label" : "#naano-redirect-url").focus();
          return;
        }
        NaanoBuilder.pageRedirects.push({ label: label, url: url });
        NaanoBuilder._renderRedirectList();
        NaanoBuilder._persistRedirects();
        $("#naano-add-redirect-form").hide().find("input").val("");
        $("#naano-add-redirect-btn").show();
      });

      $(document).on("click", ".naano-remove-redirect-btn", function () {
        var idx = parseInt($(this).data("index"), 10);
        NaanoBuilder.pageRedirects.splice(idx, 1);
        NaanoBuilder._renderRedirectList();
        NaanoBuilder._persistRedirects();
      });

      $(document).on(
        "keydown",
        "#naano-redirect-label, #naano-redirect-url",
        function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            $("#naano-save-redirect-btn").trigger("click");
          }
        },
      );
    },

    _renderRedirectList: function () {
      var $list = $("#naano-redirect-list").empty();
      NaanoBuilder.pageRedirects.forEach(function (redirect, i) {
        var label = redirect.label + " → " + redirect.url;
        $list.append(
          $('<li class="naano-reference-item">')
            .append($("<span>").text(label))
            .append(
              ' <button type="button" class="naano-remove-ref-btn naano-remove-redirect-btn" data-index="' +
                i +
                '">✕</button>',
            ),
        );
      });
    },

    _persistAssets: function () {
      if (!NaanoBuilder.pageId) {
        return;
      }
      $.post(data.ajaxUrl, {
        action: "naano_save_assets",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        assets: JSON.stringify(NaanoBuilder.pageAssets),
      });
    },

    _persistRedirects: function () {
      if (!NaanoBuilder.pageId) {
        return;
      }
      $.post(data.ajaxUrl, {
        action: "naano_save_redirects",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        redirects: JSON.stringify(NaanoBuilder.pageRedirects),
      });
    },

    _renderReferenceList: function (refs) {
      var $screenshots = $("#naano-screenshot-list").empty();
      var $urls = $("#naano-url-list").empty();

      refs.forEach(function (ref, index) {
        var $li = $('<li class="naano-reference-item">');

        if (ref.type === "screenshot") {
          var thumb = $("<img>")
            .attr("src", ref.url)
            .addClass("naano-ref-thumb");
          $li.append(thumb);
          $li.append($("<span>").text(ref.notes || ref.url));
          $li.append(
            ' <button type="button" class="naano-remove-ref-btn" data-index="' +
              index +
              '">✕</button>',
          );
          $screenshots.append($li);
        } else {
          var $link = $("<a>")
            .attr({ href: ref.url, target: "_blank" })
            .text(ref.url);
          $li.append($link);
          if (ref.notes) {
            $li.append(" — " + $("<span>").text(ref.notes).html());
          }
          $li.append(
            ' <button type="button" class="naano-remove-ref-btn" data-index="' +
              index +
              '">✕</button>',
          );
          $urls.append($li);
        }
      });
    },

    _renderInitialUrlList: function () {
      var $list = $("#naano-initial-url-list").empty();
      NaanoBuilder.initialReferences.forEach(function (ref, index) {
        var $li = $('<li class="naano-reference-item">');
        var $link = $("<a>")
          .attr({ href: ref.url, target: "_blank" })
          .text(ref.url);
        $li.append($link);
        if (ref.notes) {
          $li.append(" — " + $("<span>").text(ref.notes).html());
        }
        $li.append(
          ' <button type="button" class="naano-initial-remove-ref" data-index="' +
            index +
            '">✕</button>',
        );
        $list.append($li);
      });
    },

    _setLoading: function (btnSelector, loadSelector, loading) {
      $(btnSelector).prop("disabled", loading);
      $(loadSelector).toggle(loading);
    },

    _findSectionIndex: function (id) {
      for (var i = 0; i < NaanoBuilder.sectionsData.length; i++) {
        if (NaanoBuilder.sectionsData[i].id === id) {
          return i;
        }
      }
      return -1;
    },

    _displayName: function (id) {
      return id.replace(/[-_]/g, " ").replace(/\b\w/g, function (c) {
        return c.toUpperCase();
      });
    },

    _toast: function (message, type, duration) {
      duration = duration || 3500;
      var $toast = $(
        '<div class="naano-toast naano-toast--' +
          type +
          '">' +
          message +
          "</div>",
      );
      $("body").append($toast);
      setTimeout(function () {
        $toast.addClass("naano-toast--visible");
      }, 10);
      setTimeout(function () {
        $toast.removeClass("naano-toast--visible");
        setTimeout(function () {
          $toast.remove();
        }, 300);
      }, duration);
    },
  };

  // Boot when DOM is ready.
  $(function () {
    NaanoBuilder.init();
  });

  // Expose globally.
  window.NaanoBuilder = NaanoBuilder;
})(jQuery, naanoBuilderData);
