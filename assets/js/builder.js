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
     * Tiny sprintf-style helper for translated strings that contain
     * positional placeholders like %s, %d, %1$s, %2$d. Lets us keep all
     * user-visible text in PHP-translated `data.strings` while still
     * substituting runtime values (counts, names) on the JS side.
     *
     * Supported tokens: %s, %d (sequential), %1$s, %2$d, ... %9$s (positional).
     * Anything else is left untouched.
     *
     * @param {string} key    Lookup key inside data.strings. If missing the
     *                        key itself is used as the format string so the
     *                        caller can pass a literal English fallback.
     * @param {...*}   args   Values to substitute for the placeholders.
     * @return {string}
     */
    _i18n: function (key) {
      var fmt =
        data.strings && data.strings[key] != null && data.strings[key] !== ""
          ? data.strings[key]
          : key;
      var args = Array.prototype.slice.call(arguments, 1);
      var seq = 0;
      return String(fmt).replace(
        /%(?:(\d+)\$)?([sd])/g,
        function (_match, posStr, _type) {
          var idx = posStr ? parseInt(posStr, 10) - 1 : seq++;
          var v = args[idx];
          return v == null ? "" : String(v);
        },
      );
    },

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
      NaanoBuilder._bindFloatingPanelDrag();

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

        // Single-section pages (most notably the maintenance page) are
        // implicitly "the section you're editing" — requiring the user
        // to click into the iframe before the Update button enables is
        // pure friction with nothing to disambiguate. Pre-select it so
        // the user can type a prompt and click Update straight away.
        // For multi-section pages we leave selection empty so the user
        // explicitly picks which one they want to update.
        if (NaanoBuilder.sectionsData.length === 1) {
          NaanoBuilder.openEditPanel(NaanoBuilder.sectionsData[0].id, true);
        }
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
                      title: NaanoBuilder._i18n("remove"),
                    }).text("\u00d7"),
                  );
                  $("#naano-section-checkboxes").append($label);
                }
              });
            }

            NaanoBuilder._toast(
              isInitial
                ? NaanoBuilder._i18n("prompt_enhanced_sections")
                : NaanoBuilder._i18n("instruction_enhanced"),
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

            // Same single-section auto-select as on cold boot — see init()
            // above for the rationale (maintenance pages, single-section
            // landings: pre-select so the Update button enables without
            // requiring an extra click).
            if (NaanoBuilder.sectionsData.length === 1) {
              NaanoBuilder.openEditPanel(
                NaanoBuilder.sectionsData[0].id,
                true,
              );
            }

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
                NaanoBuilder._i18n(
                  "site_generated_with_skips",
                  failed.length,
                  names,
                ),
                "success",
                10000,
              );
            } else {
              NaanoBuilder._toast(
                NaanoBuilder._i18n("site_generated"),
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
                  ? NaanoBuilder._i18n(
                      "generation_in_progress",
                      partialSections.length,
                    )
                  : NaanoBuilder._i18n(
                      "generation_interrupted",
                      errMsg,
                      partialSections.length,
                    );
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
              ? NaanoBuilder._i18n("site_generated")
              : NaanoBuilder._i18n(
                  "site_generated_partial",
                  successCount,
                  sections.length,
                );
          NaanoBuilder._toast(
            msg,
            successCount === sections.length ? "success" : "warning",
          );
        } else {
          NaanoBuilder._toast(
            NaanoBuilder._i18n(
              "generation_failed",
              lastError || NaanoBuilder._i18n("unknown_error_check_settings"),
            ),
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
      // User just picked a section in the sidebar (or via iframe click) —
      // ask the iframe to scroll to it as well as highlight it.
      NaanoBuilder._updateEditPanelState({ scroll: true });
    },

    /**
     * Sync the edit-panel UI to the current editingSectionIds state.
     *
     * @param {Object} [opts]
     * @param {boolean} [opts.scroll] If true, the iframe will also smooth-scroll
     *   to the first highlighted section. Default false so passive UI refreshes
     *   (e.g. after re-renders) don't yank the user's viewport around.
     */
    _updateEditPanelState: function (opts) {
      var ids = NaanoBuilder.editingSectionIds;
      var count = ids.length;
      var scroll = !!(opts && opts.scroll);

      // Badge.
      if (count === 0) {
        $("#naano-editing-section-name").text(data.strings.click_section);
      } else if (count === 1) {
        $("#naano-editing-section-name").text(
          NaanoBuilder._displayName(ids[0]),
        );
      } else {
        $("#naano-editing-section-name").text(
          NaanoBuilder._i18n("sections_selected", count),
        );
      }

      // Update button label & state.
      var btnLabel =
        count > 1
          ? NaanoBuilder._i18n("update_n_sections", count)
          : NaanoBuilder._i18n("update_section");
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
        scroll: scroll,
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
            ? NaanoBuilder._i18n("section_updated")
            : NaanoBuilder._i18n("n_sections_updated", ids.length);
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
            NaanoBuilder._toast(
              NaanoBuilder._i18n("screenshot_added"),
              "success",
            );
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
        NaanoBuilder._toast(NaanoBuilder._i18n("enter_url"), "error");
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
            NaanoBuilder._toast(NaanoBuilder._i18n("url_added"), "success");
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
          NaanoBuilder._toast(NaanoBuilder._i18n("section_deleted"), "success");
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
            NaanoBuilder._toast(NaanoBuilder._i18n("html_copied"), "success");
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
        $("#naano-save-page-error")
          .text(NaanoBuilder._i18n("enter_page_title"))
          .show();
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
              NaanoBuilder._i18n(
                "page_published_html",
                response.data.view_url,
                response.data.edit_url,
              ),
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
                  NaanoBuilder._toast(
                    NaanoBuilder._i18n("set_homepage"),
                    "success",
                    3500,
                  );
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
          $(this).text(NaanoBuilder._i18n("show"));
        } else {
          $list.slideDown(150);
          $(this).text(NaanoBuilder._i18n("hide"));
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
            title: NaanoBuilder._i18n("remove"),
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
          NaanoBuilder._toast(NaanoBuilder._i18n("enter_url"), "error");
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
            .text(NaanoBuilder._i18n("enter_section_name"))
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

      // ── Custom-HTML widget editor (floating panel) ────────────────────
      // The widget is INSERTED via the "+" hover button injected into the
      // iframe (see helperScript below) — that path immediately calls
      // _addCustomHtmlSection with empty HTML, then auto-opens this
      // editor so the user can paste their content. The editor is also
      // re-opened any time the user clicks an existing custom-html
      // widget in inspect mode.
      $(document).on(
        "click",
        "#naano-custom-html-editor-cancel-btn, #naano-custom-html-editor-close-btn",
        function () {
          NaanoBuilder._closeCustomHtmlEditor();
        },
      );

      $(document).on(
        "click",
        "#naano-custom-html-editor-save-btn",
        function () {
          var html = $("#naano-custom-html-editor-input").val() || "";
          var sid = NaanoBuilder._customHtmlEditingId;
          if (!sid) return;
          // Empty save is allowed — the iframe will fall back to the
          // placeholder and the user can come back later.
          NaanoBuilder._updateCustomHtmlSection(sid, html);
          NaanoBuilder._closeCustomHtmlEditor();
        },
      );

      // Esc closes the editor; Ctrl/Cmd+Enter triggers Save so power
      // users can stay on the keyboard.
      $(document).on(
        "keydown",
        "#naano-custom-html-editor-input",
        function (e) {
          if (e.key === "Escape") {
            e.preventDefault();
            NaanoBuilder._closeCustomHtmlEditor();
          }
          if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            $("#naano-custom-html-editor-save-btn").trigger("click");
          }
        },
      );
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

      // ── Clear background color ──────────────────────────────────────
      // Color <input>s always have a value — they don't have a "blank"
      // state — so the only way for the user to UNSET a bg-color is via
      // a dedicated button. We:
      //   1. Reset the visible color input so the next Apply doesn't
      //      re-add the previous color.
      //   2. Send a one-off naano-apply-element-style with the special
      //      "__remove__" sentinel for backgroundColor, which the iframe
      //      handler turns into removeProperty('background-color').
      //   3. Re-flow the section's persistence pipeline so the cleared
      //      style sticks across saves and refreshes.
      $(document).on("click", "#naano-esp-clear-bg-btn", function () {
        if (!NaanoBuilder.selectedElId) return;
        $('#naano-element-style-panel [data-prop="backgroundColor"]').val(
          "#ffffff",
        );
        // No customCss field on this payload — the iframe handler only
        // touches the scoped <style> tag when m.customCss is a string,
        // so the user's existing custom CSS survives a bg-color clear.
        NaanoBuilder._iframePost({
          type: "naano-apply-element-style",
          elId: NaanoBuilder.selectedElId,
          styles: { backgroundColor: "__remove__" },
        });
        NaanoBuilder._toast(NaanoBuilder._i18n("applied"), "success", 1200);
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

      // ── Replace image button (Image tab) ────────────────────────────
      // Opens the WP media library. On select, we push the chosen
      // attachment's URL into the iframe so the live preview updates
      // immediately — the user doesn't have to click Apply for image
      // swaps to feel responsive. Alt text is also pulled from the
      // attachment's WP-saved alt field so accessibility metadata
      // travels with the image by default.
      $(document).on("click", "#naano-esp-img-pick-btn", function () {
        if (!NaanoBuilder.selectedElId) return;
        if (typeof wp === "undefined" || !wp.media) {
          NaanoBuilder._toast(
            "Media library unavailable — refresh the page.",
            "error",
          );
          return;
        }
        var frame = wp.media({
          title: "Choose image",
          button: { text: "Use this image" },
          multiple: false,
          library: { type: "image" },
        });
        frame.on("select", function () {
          var att = frame.state().get("selection").first().toJSON();
          if (!att || !att.url) return;
          // Sync the panel inputs so they reflect the picked image.
          $("#naano-esp-img-src-input").val(att.url);
          // Prefer the attachment's own alt; fall back to title if not set.
          var altGuess = att.alt || att.title || "";
          $("#naano-esp-img-alt-input").val(altGuess);
          $("#naano-esp-img-preview").attr("src", att.url).attr("alt", altGuess);
          // Push to the iframe so the user sees the swap immediately.
          NaanoBuilder._applyElementImage();
        });
        frame.open();
      });

      // Live-sync the URL field: every keystroke updates the thumbnail
      // and pushes the new src into the iframe. `change` (not `input`)
      // would only fire on blur — too sluggish for a builder.
      $(document).on("input", "#naano-esp-img-src-input", function () {
        var url = $(this).val();
        $("#naano-esp-img-preview").attr("src", url);
        if (NaanoBuilder.selectedElId && NaanoBuilder.selectedElTag === "img") {
          NaanoBuilder._applyElementImage();
        }
      });

      // Live-sync the alt field — same reasoning as the URL field.
      $(document).on("input", "#naano-esp-img-alt-input", function () {
        var alt = $(this).val();
        $("#naano-esp-img-preview").attr("alt", alt);
        if (NaanoBuilder.selectedElId && NaanoBuilder.selectedElTag === "img") {
          NaanoBuilder._applyElementImage();
        }
      });

      $(document).on("click", "#naano-esp-edit-section-btn", function () {
        // Shortcut: jump to the editor for the section that contains
        // the currently-selected element. Two paths:
        //   - Custom-HTML widget → open the raw-HTML editor pre-filled
        //     with the section's current HTML, so the user can paste a
        //     new version. There is no AI prompt for these.
        //   - Anything else (AI section) → open the AI editor panel.
        var sid = NaanoBuilder.selectedElSectionId;
        if (!sid) return;
        var isCustom = NaanoBuilder.selectedIsCustomHtml;
        NaanoBuilder._clearElementSelection();
        if (isCustom) {
          NaanoBuilder._openCustomHtmlEditor(sid);
          return;
        }
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

      // Mark global CSS as dirty when user types into either textarea.
      // Both #naano-global-css (create panel) and #naano-global-css-edit
      // (edit panel) share the .naano-global-css-textarea class. Typing
      // into one mirrors the value into the other so the user sees the
      // same content regardless of which panel is currently active.
      $(document).on("input", ".naano-global-css-textarea", function () {
        var val = $(this).val() || "";
        // Sync sibling textareas without re-triggering the input event.
        $(".naano-global-css-textarea")
          .not(this)
          .each(function () {
            if (this.value !== val) this.value = val;
          });
        NaanoBuilder._dirtyGlobalCss = val !== NaanoBuilder._lastSavedGlobalCss;
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
          return NaanoBuilder._i18n("unsaved_warning");
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
      // Pre-populate custom CSS with whatever the iframe extracted from
      // the element's stable <style data-naano-cust=...> tag, so the
      // user can edit existing rules instead of rewriting from scratch.
      $("#naano-esp-custom-css").val(elData.customCss || "");

      // Style + spacing inputs.
      // CRITICAL: we ALSO snapshot each input's NORMALISED value AFTER
      // setting it, into data('naano-initial-val'). _applyElementStyle
      // uses this snapshot to detect which fields the user actually
      // edited and only forwards THOSE to the iframe. Without this,
      // every Apply re-pushes every computed value as a forced inline
      // style — most notoriously, an <input type=\"color\"> for an
      // element with a transparent background gets _rgbToHex(\"rgba(0,
      // 0, 0, 0)\") = null, the browser silently falls back to its
      // default #000000, and the Apply path then writes
      // style=\"background-color:#000000\" onto the element, painting
      // it BLACK out of nowhere. Snapshotting + diffing makes Apply
      // a no-op for untouched fields, so custom CSS rules win and
      // nothing gets a black background by accident.
      var computed = elData.computed || {};
      $("#naano-element-style-panel [data-prop]").each(function () {
        var prop = $(this).data("prop");
        var val = computed[prop] || "";
        if ($(this).is("input[type=color]") && val) {
          // _rgbToHex returns null for rgba()/transparent/named-color
          // strings. In that case the color picker will display its
          // browser-default (#000000 in every browser we tested) — we
          // capture that exact default below as the initial value so
          // we can detect "user didn't touch this field" on Apply.
          val = NaanoBuilder._rgbToHex(val) || "";
        }
        $(this).val(val);
        // Snapshot the post-set value (which may have been normalised
        // or clamped by the browser, especially for color inputs).
        $(this).data("naano-initial-val", String($(this).val() || ""));
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

      // Image tab: only show + populate when an <img> is selected.
      // The tab button stays hidden for every other element type so the
      // panel doesn't sprout an irrelevant tab on text/buttons/divs.
      var isImage = (elData.tagName || "").toLowerCase() === "img";
      $('.naano-esp-tab[data-tab="image"]').toggle(isImage);
      if (isImage) {
        var ii = elData.imageInfo || {};
        var imgSrc = ii.src || "";
        var imgAlt = ii.alt || "";
        $("#naano-esp-img-src-input").val(imgSrc);
        $("#naano-esp-img-alt-input").val(imgAlt);
        // Pre-populate the thumbnail. If src is empty (newly-inserted
        // <img> with no source yet) we hide the preview via the CSS
        // `[src=""]` selector so we don't show a broken icon.
        $("#naano-esp-img-preview")
          .attr("src", imgSrc)
          .attr("alt", imgAlt);
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
      // We diff each input against its snapshot from _renderStylePanel
      // (data('naano-initial-val')). Only fields the user ACTUALLY
      // edited are forwarded as inline-style overrides. Untouched
      // fields are skipped entirely — preserving custom CSS rules
      // that would otherwise lose specificity to a redundant inline
      // style, and (critically) preventing the bg-color color-picker
      // default #000000 from ever leaking onto elements whose
      // computed background was transparent. See the matching
      // comment block in _renderStylePanel for the full rationale.
      var styles = {};
      $("#naano-element-style-panel [data-prop]").each(function () {
        var prop = $(this).data("prop");
        var raw = $(this).val();
        var val = raw == null ? "" : String(raw).trim();
        var initial = $(this).data("naano-initial-val");
        if (typeof initial !== "string") initial = "";
        // Only push the prop if the user changed the field. Empty
        // strings are also skipped — an empty input means "leave
        // alone", not "force empty style".
        if (val && val !== initial) {
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

      // 4) Image attributes — only when an <img> is currently selected.
      // The src + alt fields already live-sync via the input handlers in
      // _bindElementInspector, but a user clicking Apply expects ALL
      // fields to be pushed in one go, so we resend here too. This is a
      // no-op when the src/alt didn't actually change.
      if ((NaanoBuilder.selectedElTag || "").toLowerCase() === "img") {
        NaanoBuilder._applyElementImage();
      }

      NaanoBuilder._toast(NaanoBuilder._i18n("applied"), "success", 1500);
    },

    /**
     * Tell the iframe to delete the currently-selected element and
     * deselect locally.
     */
    _deleteSelectedElement: function () {
      if (!NaanoBuilder.selectedElId) return;
      if (!window.confirm(NaanoBuilder._i18n("delete_element_confirm"))) {
        return;
      }
      NaanoBuilder._iframePost({
        type: "naano-delete-element",
        elId: NaanoBuilder.selectedElId,
      });
      NaanoBuilder._clearElementSelection();
    },

    /**
     * Push the current Image tab fields (src + alt) to the iframe so the
     * selected <img> updates live. Called on every keystroke in the URL
     * and Alt inputs and right after the WP media library picker
     * resolves a new attachment.
     *
     * The iframe handles the actual DOM mutation + notifies the parent
     * via naano-element-html-updated, which marks the section dirty so
     * the toolbar's "Save changes" button surfaces the change.
     */
    _applyElementImage: function () {
      if (!NaanoBuilder.selectedElId) return;
      var src = $("#naano-esp-img-src-input").val() || "";
      var alt = $("#naano-esp-img-alt-input").val() || "";
      NaanoBuilder._iframePost({
        type: "naano-apply-element-image",
        elId: NaanoBuilder.selectedElId,
        src: src,
        alt: alt,
      });
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

      // Read global CSS from the first textarea matching the shared class
      // (both create-panel and edit-panel share the same value via input
      // sync, so either is fine).
      var globalCss = $(".naano-global-css-textarea").first().val() || "";

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
            var savedKey =
              saved === 1 ? "saved_n_sections" : "saved_n_sections_plural";
            var savedMsg = NaanoBuilder._i18n(savedKey, saved);
            if (resp.data && resp.data.global_css_saved) {
              savedMsg += NaanoBuilder._i18n("and_global_css");
            }
            NaanoBuilder._toast(savedMsg, "success");
          } else {
            NaanoBuilder._toast(
              (resp && resp.data && resp.data.message) ||
                NaanoBuilder._i18n("save_failed"),
              "error",
            );
          }
        })
        .fail(function () {
          NaanoBuilder._toast(
            NaanoBuilder._i18n("save_failed_network"),
            "error",
          );
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

      NaanoBuilder._toast(
        NaanoBuilder._i18n("retrying_section", sectionType),
        "success",
      );

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
            NaanoBuilder._i18n("section_recovered", sectionType),
            "success",
          );
          // Reload the failed list (server-side mark_section_recovered
          // already stripped this section).
          NaanoBuilder._loadFailedSections();
        } else {
          NaanoBuilder._toast(
            NaanoBuilder._i18n(
              "retry_failed",
              (response && response.data && response.data.message) ||
                NaanoBuilder._i18n("unknown_error"),
            ),
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
        // hasn't already started editing it). Apply to both create-panel
        // and edit-panel textareas via the shared class.
        if (!NaanoBuilder._dirtyGlobalCss) {
          $(".naano-global-css-textarea").val(globalCss);
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
          f.reason || NaanoBuilder._i18n("failed_label"),
        );
        var $btn = $(
          '<button type="button" class="naano-btn-secondary naano-failed-retry"></button>',
        )
          .attr("data-section-id", f.section_id)
          .html(
            '<span class="dashicons dashicons-update"></span> ' +
              NaanoBuilder._i18n("retry"),
          );
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
          NaanoBuilder.selectedIsCustomHtml = !!msg.isCustomHtml;
          // For custom-html widgets we ALWAYS want the editor to feel
          // like Elementor's: clicking the widget pops up the HTML
          // textarea right next to it. Skip the regular style panel
          // entirely — its tabs (typography, spacing, etc.) don't make
          // sense for a raw HTML block.
          if (msg.isCustomHtml && msg.sectionId) {
            NaanoBuilder._openCustomHtmlEditor(msg.sectionId);
          } else {
            NaanoBuilder._renderStylePanel(msg);
          }
        }

        if (msg.type === "naano-element-deselected") {
          NaanoBuilder._clearElementSelection();
        }

        // The "+" button injected into the iframe was clicked on a
        // hovered section. Insert a fresh custom-html widget directly
        // above it (server side handles ordering + sanitizing).
        if (msg.type === "naano-insert-custom-html-above") {
          if (msg.sectionId) {
            NaanoBuilder._addCustomHtmlSection("", msg.sectionId);
          }
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
      // Empty placeholder shown for fresh custom-html widgets so the
      // user actually SEES something in the preview the moment they
      // click "+". Plain text inside a div with the same class our
      // helperScript styles below — visible blue dashed border, big
      // hint text, fully click-targetable.
      var emptyCustomHtmlPlaceholder =
        '<div class="naano-custom-html-empty">📝&nbsp;' +
        NaanoBuilder._i18n("custom_html_empty_hint") +
        "</div>";
      NaanoBuilder.sectionsData.forEach(function (sec) {
        // Wrap each section so [data-section] is always present in the iframe
        // for click detection, highlight, loading overlay and live HTML updates.
        // For custom-html sections (the Elementor-style raw-HTML widget),
        // an extra data-naano-custom-html flag tells the inspect helper
        // script to treat the wrapper as a single selectable block — clicks
        // inside don't drill into children, contenteditable is disabled, so
        // the user-pasted markup stays exactly as they wrote it.
        var isCustom = sec.type === "custom-html";
        var customAttr = isCustom ? ' data-naano-custom-html="1"' : "";
        // Pick the body. Three states count as "empty" and trigger the
        // visible placeholder so the user can SEE and CLICK the widget:
        //   1. Stored html is literally empty / whitespace only
        //   2. Stored html is just our server-side marker comment
        //   3. Stored html has tags but no visible content (e.g. <p></p>)
        // We deliberately don't strip media tags from the visibility
        // check — an <img> alone IS visible content.
        var body = sec.html || "";
        var bodyTrimmed = body.trim();
        var isEmpty =
          isCustom &&
          (!bodyTrimmed ||
            bodyTrimmed === "<!-- naano:custom-html:empty -->" ||
            (!body.replace(/<[^>]*>/g, "").trim() &&
              !/<img|<iframe|<svg|<video|<canvas|<picture|<embed/i.test(body)));
        if (isEmpty) {
          body = emptyCustomHtmlPlaceholder;
        }
        sectionsHtml +=
          '<div data-section="' +
          sec.id +
          '"' +
          customAttr +
          ">" +
          body +
          "</div>";
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
        // Section-level selection (when user picks a section in the
        // sidebar list to edit it with AI). Same shape as the inspect
        // mode element selection but uses the WordPress admin blue so
        // it's visually distinct from the orange element-inspect.
        // NOTE: outline-offset is NEGATIVE here on purpose. AI-generated
        // sections typically fill 100% of the iframe width with no body
        // margin, so a positive offset would push the side outlines
        // outside the visible viewport (clipped) and the top/bottom
        // outlines would be hidden under adjacent siblings. An inset
        // outline (offset:-3px) is always visible, and the inner
        // box-shadow gives the same "rim of light" feel inspect mode
        // has on individual elements.
        '+"[data-section].naano-section-selected{outline:3px solid #2271b1!important;outline-offset:-3px!important;box-shadow:inset 0 0 0 4px rgba(34,113,177,0.25)!important;}"',
        // Contenteditable affordance: subtle inset highlight + caret cursor.
        '+"[contenteditable=\\"true\\"]{cursor:text!important;outline:2px solid #f59e0b!important;outline-offset:2px;background:rgba(255,251,235,0.5);}"',
        // Inspect mode kept for legacy class hooks.
        '+"body.naano-inspect-active{cursor:default;}"',
        // Empty-state placeholder for fresh custom-html widgets. We
        // render this client-side when the section's stored HTML is
        // blank so the widget actually OCCUPIES SPACE in the preview
        // — otherwise a 0px tall section is impossible to click.
        '+".naano-custom-html-empty{display:flex;align-items:center;justify-content:center;min-height:90px;padding:32px 20px;margin:0;border:2px dashed #2271b1;background:linear-gradient(135deg,rgba(34,113,177,0.05),rgba(34,113,177,0.12));color:#2271b1;font:600 15px/1.4 -apple-system,BlinkMacSystemFont,\\"Segoe UI\\",Roboto,sans-serif;text-align:center;cursor:pointer;}"',
        // Floating "+" button that follows the hovered section. Big
        // enough to click comfortably, half-overlapping the top edge
        // of its target so it visually reads as "insert above this".
        '+".naano-section-add-btn{position:absolute;width:34px;height:34px;border-radius:50%;background:#2271b1;color:#fff;border:2px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,0.35);cursor:pointer;font:700 22px/1 system-ui,-apple-system,sans-serif;display:none;align-items:center;justify-content:center;z-index:2147483646;transform:translate(-50%,-50%);padding:0;user-select:none;}"',
        '+".naano-section-add-btn:hover{background:#135e96;transform:translate(-50%,-50%) scale(1.1);}"',
        '+".naano-section-add-btn span{display:block;line-height:1;margin-top:-2px;};";',
        "document.head.appendChild(s);",

        // ── Floating "+" hover button (Elementor-style insert) ─────────
        // A single absolutely-positioned button that the mouseover
        // handler repositions as the user moves between sections. On
        // click it postMessages the current hover-section id up to the
        // parent, which inserts a new custom-html widget directly
        // above it. We keep ONE button in the DOM (instead of one per
        // section) so we don't pollute the iframe's DOM and so empty
        // pages can still get a "+" once they have their first section.
        'var addBtn=document.createElement("button");',
        'addBtn.type="button";',
        'addBtn.className="naano-section-add-btn";',
        'addBtn.setAttribute("aria-label","Insert custom HTML above");',
        'addBtn.innerHTML="<span>+</span>";',
        "var hoverSectionEl=null;",
        // Update position. Called from mouseover on sections + on a
        // scroll/resize observer so the button stays glued to its
        // target if the user scrolls the iframe.
        "function positionAddBtn(){",
        "  if(!hoverSectionEl||!hoverSectionEl.isConnected){",
        '    addBtn.style.display="none";return;',
        "  }",
        "  var r=hoverSectionEl.getBoundingClientRect();",
        "  var x=r.left+r.width/2+(window.scrollX||0);",
        "  var y=r.top+(window.scrollY||0);",
        '  addBtn.style.left=x+"px";',
        '  addBtn.style.top=y+"px";',
        '  addBtn.style.display="flex";',
        "}",
        "window.addEventListener('scroll',positionAddBtn,{passive:true});",
        "window.addEventListener('resize',positionAddBtn,{passive:true});",
        // Click handler: bubble id up to parent, do NOT let the click
        // also reach the body's click handler (which would try to
        // select the button itself).
        "addBtn.addEventListener('click',function(e){",
        "  e.stopPropagation();e.preventDefault();",
        "  if(!hoverSectionEl)return;",
        '  var sid=hoverSectionEl.getAttribute("data-section");',
        "  if(!sid)return;",
        '  window.parent.postMessage({type:"naano-insert-custom-html-above",sectionId:sid},"*");',
        "},true);",
        // Don't let hover events on the button trigger the section's
        // hover state — the button isn't part of the section visually.
        "addBtn.addEventListener('mouseenter',function(e){e.stopPropagation();});",
        "document.body.appendChild(addBtn);",

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

        // Convert camelCase JS style property names ("backgroundColor")
        // back to kebab-case CSS names ("background-color") for the
        // CSSStyleDeclaration.removeProperty() API, which only knows
        // the kebab form. Used by the "remove style" path triggered by
        // the bg-color clear button (and any future similar buttons).
        "function _dashCase(s){return String(s).replace(/[A-Z]/g,function(m){return '-'+m.toLowerCase();});}",

        // Rebuild a section's generated <style data-naano-cust-styles>
        // block from every [data-naano-cust-css] attribute it contains.
        // The element's data-naano-cust-css attribute is the SINGLE
        // SOURCE OF TRUTH for user-applied custom CSS — this function
        // is the only place that emits the actual <style> rules. It
        // always wipes the previous block first, so callers can simply
        // mutate attributes and trigger a regenerate without worrying
        // about stale rules accumulating.
        //
        // Called from:
        //   1. naano-apply-element-style after an attribute changes
        //   2. The IIFE bottom (on every iframe srcdoc load), so a
        //      fresh document with persisted attributes immediately
        //      gets its visual rules back even before the user
        //      interacts.
        "function regenerateSectionCustomCss(sec){",
        "  if(!sec)return;",
        // Migration: older builds stored the source CSS inside
        // <style data-naano-cust=\"cXXX\"> tags rather than on the
        // element's data-naano-cust-css attribute. Walk those tags
        // FIRST, find their target element by id, and copy the
        // extracted CSS body onto the new attribute. After this loop
        // the data-naano-cust-css attribute is always the source of
        // truth, even for pages saved with the old code.
        "  var legacy=sec.querySelectorAll('style[data-naano-cust]');",
        "  for(var lg=0;lg<legacy.length;lg++){",
        "    var lgTag=legacy[lg];",
        "    var lgId=lgTag.getAttribute('data-naano-cust');",
        "    if(!lgId)continue;",
        "    var lgTarget=sec.querySelector('[data-naano-cust-id=\"'+lgId+'\"]');",
        "    if(lgTarget&&!lgTarget.hasAttribute('data-naano-cust-css')){",
        "      var lgRaw=lgTag.textContent||'';",
        "      var lgMatch=lgRaw.match(/\\{([\\s\\S]*)\\}/);",
        "      if(lgMatch)lgTarget.setAttribute('data-naano-cust-css',lgMatch[1].trim());",
        "    }",
        "  }",
        // Drop any previous generated block(s). We tolerate multiple
        // since older saved markup might have leftover legacy
        // <style data-naano-cust=\"…\"> tags.
        "  var olds=sec.querySelectorAll('style[data-naano-cust-styles],style[data-naano-cust]');",
        "  for(var i=0;i<olds.length;i++)olds[i].parentNode.removeChild(olds[i]);",
        // Walk the section for every element with a CSS attribute.
        // Build one rule per element scoped through its stable id.
        // Elements without an id get one assigned now (cheap, idempotent).
        "  var nodes=sec.querySelectorAll('[data-naano-cust-css]');",
        "  if(!nodes.length)return;",
        "  var rules=[];",
        "  for(var j=0;j<nodes.length;j++){",
        "    var n=nodes[j];",
        "    var css=n.getAttribute('data-naano-cust-css')||'';",
        "    if(!css.trim())continue;",
        "    var id=n.getAttribute('data-naano-cust-id');",
        "    if(!id){id='c'+Math.random().toString(36).slice(2,9);n.setAttribute('data-naano-cust-id',id);}",
        "    rules.push('[data-naano-cust-id=\"'+id+'\"]{'+css+'}');",
        "  }",
        "  if(!rules.length)return;",
        "  var tag=document.createElement('style');",
        "  tag.setAttribute('data-naano-cust-styles','1');",
        "  tag.textContent=rules.join('\\n');",
        "  sec.insertBefore(tag,sec.firstChild);",
        "}",

        // Notify parent that the section's HTML changed (called whenever
        // we mutate the DOM: style, class, delete, text edit). Critical:
        // we MUST strip transient inspect-mode artifacts (naano-el-hover,
        // naano-el-selected, contenteditable="true", data-naano-el="...")
        // before reporting the HTML upstream — otherwise those attributes
        // get baked into sectionsData, persisted to the DB, and reappear
        // on every subsequent render. That's what made sections suddenly
        // sprout orange outlines and yellow backgrounds with no user
        // input: the previous selection state was being saved as content.
        // Persistent attributes like data-naano-cust-id are KEPT so that
        // user-applied custom CSS keeps targeting the right element.
        "function notifySectionChanged(el){",
        '  var sectionEl=el&&el.closest&&el.closest("[data-section]");',
        "  if(!sectionEl)return;",
        "  var clone=sectionEl.cloneNode(true);",
        "  function stripInternal(node){",
        "    if(node.nodeType!==1)return;",
        "    if(node.hasAttribute){",
        "      if(node.hasAttribute('contenteditable'))node.removeAttribute('contenteditable');",
        "      if(node.hasAttribute('data-naano-el'))node.removeAttribute('data-naano-el');",
        "    }",
        "    var cls=node.className;",
        "    if(typeof cls==='string'&&cls){",
        "      var keep=cls.split(/\\s+/).filter(function(c){",
        "        return c&&c!=='naano-el-hover'&&c!=='naano-el-selected'&&c!=='naano-section-selected'&&c!=='naano-section-loading';",
        "      });",
        "      var newCls=keep.join(' ');",
        "      if(newCls!==cls){",
        "        if(newCls)node.className=newCls;",
        "        else if(node.removeAttribute)node.removeAttribute('class');",
        "      }",
        "    }",
        "    var children=node.children;",
        "    for(var i=0;i<children.length;i++)stripInternal(children[i]);",
        "  }",
        "  stripInternal(clone);",
        '  window.parent.postMessage({type:"naano-element-html-updated",',
        '    sectionId:sectionEl.getAttribute("data-section"),',
        '    html:clone.innerHTML},"*");',
        "}",

        // For raw-HTML "custom" sections we want the WHOLE block to
        // behave like one Elementor-style widget: hovers, clicks, and
        // contenteditable should all target the wrapper, never its
        // children. This helper walks up from any node and, if it lives
        // inside a [data-naano-custom-html], returns that ancestor; else
        // returns the original node so AI sections work as before.
        "function resolveCustomTarget(el){",
        "  if(!el||el.nodeType!==1)return el;",
        "  var cur=el;",
        "  while(cur&&cur!==document.body){",
        "    if(cur.hasAttribute&&cur.hasAttribute('data-naano-custom-html'))return cur;",
        "    cur=cur.parentElement;",
        "  }",
        "  return el;",
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
        // CRITICAL: ignore mouseover events whose target is the floating
        // "+" button itself (or its inner <span>). The button is
        // appended to <body> and lives OUTSIDE any [data-section], so
        // without this guard the next two lines would set
        // hoverSectionEl=null and immediately hide the button as soon
        // as the user moves their mouse onto it — making it physically
        // impossible to click. The user's click would then fall
        // through to the section underneath and open the floating
        // editor panel instead of inserting a new custom-HTML widget,
        // which is exactly the bug the user reported. We freeze the
        // current hover state while the cursor is over the button.
        "  if(e.target&&(e.target===addBtn||addBtn.contains(e.target)))return;",
        '  document.querySelectorAll(".naano-el-hover").forEach(function(n){n.classList.remove("naano-el-hover");});',
        // Update the floating "+" button position to follow whichever
        // section the user is hovering. We walk up to the nearest
        // [data-section] (which is always the outer wrapper this
        // builder injected) so the button targets sections, not the
        // children. If we're not over any section, hide the button.
        "  var hoverSec=e.target&&e.target.closest&&e.target.closest('[data-section]');",
        "  if(hoverSec){hoverSectionEl=hoverSec;positionAddBtn();}",
        "  else{hoverSectionEl=null;addBtn.style.display='none';}",
        "  var el=resolveCustomTarget(e.target);",
        "  if(!el||el===document.body||el===document.documentElement)return;",
        // Don't show hover outline on the currently-selected element
        // (would make the dashed outline fight with the solid one).
        "  if(el===currentSelected)return;",
        '  el.classList.add("naano-el-hover");',
        "});",

        // Click to select an element.
        'document.addEventListener("click",function(e){',
        // Defensive guard: never treat a click on the floating "+"
        // button as an element-selection click. The addBtn's own click
        // handler already calls stopPropagation in capture phase, but
        // if anything ever gets that listener detached or out of order
        // we still don't want clicks on the button to fall through
        // here and pop the wrong panel.
        "  if(e.target&&(e.target===addBtn||addBtn.contains(e.target)))return;",
        // If the user clicked inside the currently-selected (and now
        // contenteditable) element, let them place the caret freely
        // without re-selecting and resetting state.
        "  if(currentSelected&&currentSelected.contains(e.target)&&",
        "     currentSelected.getAttribute('contenteditable')==='true'){",
        "    return;",
        "  }",
        "  e.stopImmediatePropagation();e.preventDefault();",
        // Walk up to the custom-html wrapper if we're inside one. AI
        // sections fall through to the original click target.
        "  var el=resolveCustomTarget(e.target);",
        "  if(!el||el===document.body||el===document.documentElement)return;",
        "  var isCustomHtml=el.hasAttribute&&el.hasAttribute('data-naano-custom-html');",

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
        // For custom-HTML widgets we explicitly DO NOT enable
        // contenteditable: the user pasted HTML they want preserved
        // verbatim, so editing should go through the dedicated "Edit
        // custom HTML" textarea in the sidebar (triggered via the
        // "Edit section" button on the floating panel) instead of
        // letting them type directly into rendered output.
        "  if(!isCustomHtml){",
        "    el.setAttribute('contenteditable','true');",
        "  }",
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

        // Image-specific info: when the selected element is an <img>,
        // ship its current src + alt so the floating panel's Image tab
        // can pre-populate and show a thumbnail of the current source.
        "  var imageInfo=null;",
        "  if(el.tagName==='IMG'){",
        "    imageInfo={",
        // Prefer the resolved absolute URL the browser computed so the
        // preview thumbnail in the panel always loads, even when the
        // raw attribute uses a relative path.
        "      src:el.currentSrc||el.src||el.getAttribute('src')||'',",
        "      alt:el.getAttribute('alt')||''",
        "    };",
        "  }",

        // Pre-populate the Custom CSS textarea with whatever the user
        // previously applied to this element. The single source of
        // truth is the data-naano-cust-css attribute on the element
        // itself — atomic, escape-safe, and always present in
        // serialized HTML. Older builds stored the source in a sibling
        // <style data-naano-cust=\"…\"> tag; the second branch below
        // handles that case for one-time backwards compatibility so
        // pages saved with the previous code still load their CSS.
        "  var savedCustomCss='';",
        "  var savedCssAttr=el.getAttribute('data-naano-cust-css');",
        "  if(savedCssAttr!==null){",
        "    savedCustomCss=savedCssAttr;",
        "  }else{",
        "    var custIdRead=el.getAttribute('data-naano-cust-id');",
        "    if(custIdRead){",
        "      var sectionRead=el.closest('[data-section]');",
        "      var styleRead=sectionRead&&sectionRead.querySelector('style[data-naano-cust=\"'+custIdRead+'\"]');",
        "      if(styleRead){",
        "        var raw=styleRead.textContent||'';",
        "        var braceMatch=raw.match(/\\{([\\s\\S]*)\\}/);",
        "        if(braceMatch){",
        "          savedCustomCss=braceMatch[1].trim();",
        // Migrate legacy storage to the attribute model so the rest
        // of the system has a single source from now on. Next save
        // will persist the attribute, future loads skip this branch.
        "          el.setAttribute('data-naano-cust-css',savedCustomCss);",
        "        }",
        "      }",
        "    }",
        "  }",

        '  window.parent.postMessage({type:"naano-element-selected",',
        "    elId:elId,",
        "    sectionId:sectionId,",
        "    tagName:el.tagName.toLowerCase(),",
        "    breadcrumb:buildBreadcrumb(el),",
        "    computed:computed,",
        "    classes:existingCls.join(' '),",
        "    isCustomHtml:isCustomHtml,",
        "    customCss:savedCustomCss,",
        '    linkInfo:linkInfo,',
        '    imageInfo:imageInfo},"*");',
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
        //
        // IMPORTANT: innerHTML inserts <script> tags as inert nodes — the
        // browser will NOT execute them. That silently hides bugs in the
        // AI-generated JS (e.g. a mobile nav that opens itself on load):
        // the section LOOKS correct after the update, but the moment the
        // iframe srcdoc is rebuilt (a fresh load), scripts run for real
        // and the bug "reappears". To make the live preview faithful to
        // what a real page load would do, we re-create every <script> in
        // the freshly injected HTML so it actually executes here too.
        '  if(m.type==="naano-update-section"){',
        "    var el=document.querySelector('[data-section=\"'+m.sectionId+'\"]');",
        "    if(el){",
        '      el.classList.remove("naano-section-loading");',
        "      el.innerHTML=m.html;",
        "      el.querySelectorAll('script').forEach(function(oldS){",
        "        var s=document.createElement('script');",
        "        for(var i=0;i<oldS.attributes.length;i++){",
        "          var a=oldS.attributes[i];",
        "          s.setAttribute(a.name,a.value);",
        "        }",
        "        s.text=oldS.textContent;",
        "        oldS.parentNode.replaceChild(s,oldS);",
        "      });",
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
        // Also smooth-scrolls the iframe to the first newly-selected
        // section so the user immediately sees what they just picked.
        '  if(m.type==="naano-highlight-sections"){',
        '    document.querySelectorAll(".naano-section-selected").forEach(function(n){n.classList.remove("naano-section-selected");});',
        "    var firstEl=null;",
        "    (m.sectionIds||[]).forEach(function(id){",
        "      var el=document.querySelector('[data-section=\"'+id+'\"]');",
        '      if(el){el.classList.add("naano-section-selected");if(!firstEl)firstEl=el;}',
        "    });",
        "    if(firstEl&&m.scroll!==false){",
        "      firstEl.scrollIntoView({behavior:'smooth',block:'start'});",
        "    }",
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
        // Custom CSS is injected via a <style> tag INSIDE the section
        // (not in document.head) and scoped through a stable
        // data-naano-cust-id attribute on the element. Both the style
        // tag and the attribute are part of section.innerHTML, so when
        // notifySectionChanged captures the section they get persisted
        // to sectionsData → DB → next render. Earlier code put the
        // style tag in document.head with a [data-naano-el="nel-N"]
        // selector that the next-render counter never matched, which
        // is why custom CSS didn't survive a refresh.
        // Apply inline styles + custom CSS to selected element.
        // Custom CSS persistence model (Bulletproof v2):
        //   - Source of truth = data-naano-cust-css ATTRIBUTE on the
        //     element itself. HTML attributes are atomic with the
        //     element, naturally HTML-escape on serialization, and are
        //     ALWAYS captured by innerHTML. Storing the rule on the
        //     element instead of in a sibling <style> tag means the
        //     two can never go out of sync.
        //   - Each element with custom CSS also gets a stable
        //     data-naano-cust-id so the generated rule has a unique,
        //     non-counter-based selector.
        //   - The actual <style> block is REGENERATED from those
        //     attributes whenever (a) the user applies CSS and (b) a
        //     new iframe srcdoc loads (see regenerateSectionCustomCss
        //     calls below). The block is tagged data-naano-cust-styles
        //     and lives at the very top of each section. Any prior
        //     instance is removed before the new one is inserted, so
        //     repeated applies never accumulate stale rules.
        //   - Server-side rendering of the published page emits an
        //     equivalent <style> block by walking the same attributes
        //     (see Naano_Section_Manager::regenerate_custom_css),
        //     guaranteeing identical visual results in the iframe and
        //     on the live page.
        '  if(m.type==="naano-apply-element-style"){',
        "    var el=document.querySelector('[data-naano-el=\"'+m.elId+'\"]');",
        "    if(!el)return;",
        "    var props=m.styles||{};",
        "    Object.keys(props).forEach(function(p){",
        '      if(props[p]==="__remove__"){el.style.removeProperty(_dashCase(p));el.style[p]="";}',
        '      else if(props[p]!=="")el.style[p]=props[p];',
        "    });",
        // Custom CSS is only touched when explicitly provided as a
        // string. Callers that just want to update inline styles (like
        // the bg-color clear button) omit the field and the existing
        // custom-CSS scoped <style> tag stays intact.
        "    if(typeof m.customCss==='string'){",
        "      var custCss=m.customCss;",
        "      var custTrim=custCss.trim();",
        "      if(custTrim){",
        // Ensure a stable id so the generated selector is unique.
        "        var custId=el.getAttribute('data-naano-cust-id');",
        "        if(!custId){",
        "          custId='c'+Math.random().toString(36).slice(2,9);",
        "          el.setAttribute('data-naano-cust-id',custId);",
        "        }",
        // Store the raw CSS source as an attribute. Browsers
        // automatically HTML-escape special chars when serialising
        // attributes via innerHTML/outerHTML, and unescape when
        // parsing back, so no manual encoding is needed.
        "        el.setAttribute('data-naano-cust-css',custCss);",
        "      }else{",
        // Empty CSS string = user cleared the rule. Drop both the
        // source-of-truth attribute and the now-orphaned id so we
        // don't leave dangling metadata on the element.
        "        el.removeAttribute('data-naano-cust-css');",
        "        el.removeAttribute('data-naano-cust-id');",
        "      }",
        // Rebuild the section's <style data-naano-cust-styles> block
        // from scratch using all elements that currently have a CSS
        // attribute. This is the ONE function that owns generated
        // <style> tags — the apply handler never writes them directly.
        "      var sec=el.closest('[data-section]');",
        "      if(sec)regenerateSectionCustomCss(sec);",
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

        // Apply image attributes (src, alt) to an <img> element.
        // Triggered when the user picks a new image from the WP media
        // library or edits the URL / alt fields in the Image tab of the
        // floating panel. Empty src is ignored (would render a broken
        // image); empty alt clears the attribute outright since alt=""
        // is meaningful (declares the image purely decorative).
        '  if(m.type==="naano-apply-element-image"){',
        "    var el=document.querySelector('[data-naano-el=\"'+m.elId+'\"]');",
        "    if(!el)return;",
        "    if(el.tagName!=='IMG')return;",
        "    if(typeof m.src==='string'&&m.src){",
        "      el.setAttribute('src',m.src);",
        // Clear srcset if present — it would otherwise win over our new
        // src on responsive layouts and the user's swap would look like
        // it did nothing on certain viewport widths.
        "      if(el.hasAttribute('srcset'))el.removeAttribute('srcset');",
        // Same reasoning for <picture> parents: if this <img> sits inside
        // a <picture>, drop the sibling <source> tags so the browser
        // doesn't pick one of them instead of our new src. The user just
        // told us explicitly which file they want — honour that.
        "      var pic=el.parentElement;",
        "      if(pic&&pic.tagName==='PICTURE'){",
        "        var sources=pic.querySelectorAll('source');",
        "        for(var si=0;si<sources.length;si++){sources[si].parentNode.removeChild(sources[si]);}",
        "      }",
        "    }",
        "    if(typeof m.alt==='string'){",
        "      el.setAttribute('alt',m.alt);",
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

        // Bulletproof boot step: as soon as the helper script runs in
        // a freshly-loaded iframe, walk every section and regenerate
        // its <style data-naano-cust-styles> block from the persisted
        // data-naano-cust-css attributes. This is what makes user
        // custom CSS survive a srcdoc rebuild — the attributes are
        // still on the elements (innerHTML round-trip preserves
        // them), and this call materialises them back into actual
        // CSS rules. Without this, a refresh would leave the
        // attributes intact but no <style> emitting their effect.
        "document.querySelectorAll('[data-section]').forEach(function(s){regenerateSectionCustomCss(s);});",

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
     * Make every .naano-esp--floating panel draggable by its header.
     *
     * Behaviour:
     *   - Drag handle is the `.naano-esp__header` of each panel. We grab
     *     `mousedown` there, switch the panel to absolute top/left coords
     *     (clearing the CSS `right` anchor), and follow the mouse until
     *     mouseup.
     *   - Position is clamped to the viewport so the panel can never end
     *     up off-screen where the user couldn't reach it again.
     *   - Position is persisted in localStorage per panel id, so the
     *     panel stays where the user last dropped it across reloads. We
     *     re-clamp on read in case the window was resized smaller since.
     *   - We never start a drag from a button, input, select or textarea
     *     inside the header — those need to keep their own click/focus
     *     behaviour (the close "X", for example).
     *   - On panel show (MutationObserver on the `style` attribute) we
     *     restore the saved position; on hide we leave it alone so the
     *     next open feels continuous.
     */
    _bindFloatingPanelDrag: function () {
      var STORAGE_KEY = "naanoEspPanelPositions";

      function readSaved() {
        try {
          var raw = window.localStorage.getItem(STORAGE_KEY);
          return raw ? JSON.parse(raw) : {};
        } catch (e) {
          return {};
        }
      }
      function writeSaved(map) {
        try {
          window.localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
        } catch (e) {
          /* quota / disabled — silently ignore */
        }
      }

      // Clamp (x, y) so the panel stays at least 40px inside the
      // viewport on every side. 40px is enough that the header is
      // always grabbable, no matter what the panel's current size is.
      function clamp(x, y, panel) {
        var rect = panel.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var minX = 8;
        var minY = 8;
        var maxX = Math.max(minX, vw - 40);
        var maxY = Math.max(minY, vh - 40);
        // Don't let the entire panel slide past the right/bottom edges
        // either — keep at least its left/top within the safe area.
        if (x + rect.width < 40) x = 40 - rect.width;
        if (y + rect.height < 40) y = 40 - rect.height;
        if (x > maxX) x = maxX;
        if (y > maxY) y = maxY;
        if (x < minX) x = minX;
        if (y < minY) y = minY;
        return { x: x, y: y };
      }

      function applyPos(panel, pos) {
        var c = clamp(pos.x, pos.y, panel);
        // Switch from the CSS-default top/right anchor to top/left
        // so dragging math works in a single coordinate space.
        panel.style.left = c.x + "px";
        panel.style.top = c.y + "px";
        panel.style.right = "auto";
        panel.style.bottom = "auto";
      }

      function restoreFor(panel) {
        var saved = readSaved();
        var id = panel.id;
        if (id && saved[id]) {
          applyPos(panel, saved[id]);
        }
      }

      function startDrag(panel, header, ev) {
        // Ignore drags that begin on interactive children of the header.
        var t = ev.target;
        if (
          t &&
          (t.closest("button") ||
            t.closest("input") ||
            t.closest("select") ||
            t.closest("textarea") ||
            t.closest("a"))
        ) {
          return;
        }

        ev.preventDefault();

        // Whatever the panel's current rendered position is, lock it in
        // as top/left BEFORE we start moving so the first frame doesn't
        // jump from the CSS `right` anchor to the cursor offset.
        var rect = panel.getBoundingClientRect();
        panel.style.left = rect.left + "px";
        panel.style.top = rect.top + "px";
        panel.style.right = "auto";
        panel.style.bottom = "auto";
        // Suppress the slide-in animation while dragging so the panel
        // doesn't fight the user's cursor.
        panel.style.animation = "none";

        var startX = ev.clientX;
        var startY = ev.clientY;
        var origX = rect.left;
        var origY = rect.top;

        // While dragging, kill text selection on the whole page and
        // pause iframe pointer events so a fast drag over the live
        // preview doesn't get eaten by the iframe.
        var prevUserSelect = document.body.style.userSelect;
        document.body.style.userSelect = "none";
        var iframe = document.getElementById("naano-live-preview");
        var prevIframePointer = iframe ? iframe.style.pointerEvents : "";
        if (iframe) iframe.style.pointerEvents = "none";

        function onMove(e) {
          var nx = origX + (e.clientX - startX);
          var ny = origY + (e.clientY - startY);
          var c = clamp(nx, ny, panel);
          panel.style.left = c.x + "px";
          panel.style.top = c.y + "px";
        }
        function onUp() {
          document.removeEventListener("mousemove", onMove);
          document.removeEventListener("mouseup", onUp);
          document.body.style.userSelect = prevUserSelect;
          if (iframe) iframe.style.pointerEvents = prevIframePointer;

          // Persist final position.
          if (panel.id) {
            var saved = readSaved();
            saved[panel.id] = {
              x: parseInt(panel.style.left, 10) || 0,
              y: parseInt(panel.style.top, 10) || 0,
            };
            writeSaved(saved);
          }
        }
        document.addEventListener("mousemove", onMove);
        document.addEventListener("mouseup", onUp);
      }

      // Wire up every floating panel that already exists in the DOM.
      // Both the element-style panel and the custom-html editor panel
      // use the `.naano-esp--floating` class.
      document
        .querySelectorAll(".naano-esp--floating")
        .forEach(function (panel) {
          var header = panel.querySelector(".naano-esp__header");
          if (!header) return;
          header.style.cursor = "move";
          header.style.userSelect = "none";
          header.addEventListener("mousedown", function (e) {
            startDrag(panel, header, e);
          });

          // Restore saved position whenever the panel becomes visible
          // again. We watch the inline `style` attribute because that's
          // how the rest of the codebase shows/hides the panel
          // ($.show() / $.hide() toggle display in the inline style).
          // The restore is deferred one frame so layout has run and the
          // panel has real dimensions to clamp against.
          var obs = new MutationObserver(function () {
            if (panel.style.display !== "none" && panel.offsetParent !== null) {
              window.requestAnimationFrame(function () {
                restoreFor(panel);
              });
            }
          });
          obs.observe(panel, {
            attributes: true,
            attributeFilter: ["style"],
          });

          // If the panel happens to be visible at boot, restore now.
          if (panel.style.display !== "none") {
            restoreFor(panel);
          }
        });

      // If the window is resized smaller, re-clamp every visible panel
      // so it doesn't end up partially off-screen.
      window.addEventListener("resize", function () {
        document
          .querySelectorAll(".naano-esp--floating")
          .forEach(function (panel) {
            if (panel.style.display === "none") return;
            var rect = panel.getBoundingClientRect();
            var c = clamp(rect.left, rect.top, panel);
            panel.style.left = c.x + "px";
            panel.style.top = c.y + "px";
          });
      });
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
          '<span class="naano-sections-list__handle" title="' +
            NaanoBuilder._i18n("drag_to_reorder") +
            '">⠿</span>' +
            '<span class="naano-sections-list__name">' +
            $("<span>").text(displayName).html() +
            "</span>" +
            '<span class="naano-sections-list__actions">' +
            '<button type="button" class="naano-sections-list__btn" data-action="edit" data-id="' +
            sec.id +
            '" title="' +
            NaanoBuilder._i18n("edit") +
            '">✏️</button>' +
            '<button type="button" class="naano-sections-list__btn naano-sections-list__btn--delete" data-action="delete" data-id="' +
            sec.id +
            '" title="' +
            NaanoBuilder._i18n("delete") +
            '">🗑️</button>' +
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
              NaanoBuilder._i18n("section_added", sectionName),
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
     * Insert a raw-HTML widget (Elementor-style HTML block) above an
     * existing section. Triggered by the floating "+" button injected
     * into the iframe — the user clicks it on a hovered section, and
     * we POST that section's id as `before_section_id` so the new
     * widget lands directly above it.
     *
     * The default HTML payload is empty so the textbox is blank when
     * the editor opens; the iframe falls back to a visible placeholder
     * div for empty custom-html sections (see _buildIframeSrcdoc).
     *
     * On success the new section is auto-selected (highlighted +
     * scrolled into view) and the floating HTML editor is opened
     * straight away — same UX as Elementor: drop a widget, edit it.
     *
     * @param {string} html      Raw HTML to embed (may be empty).
     * @param {string} beforeId  Section id this widget should precede
     *                           (empty = append at end).
     */
    _addCustomHtmlSection: function (html, beforeId) {
      $.post(data.ajaxUrl, {
        action: "naano_add_custom_html_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        before_section_id: beforeId || "",
        // The server requires non-empty HTML (so it can't accidentally
        // create empty rows). For an empty insert from the "+" button
        // we ship a single newline as a minimal payload — the iframe
        // renderer detects this as "effectively empty" and shows the
        // placeholder instead.
        html: html && html.trim() ? html : "\n",
      })
        .done(function (response) {
          if (response && response.success) {
            // Server returns the full reordered list — replace our
            // in-memory copy wholesale rather than splicing, so the
            // type field on every section is up to date.
            if (response.data && Array.isArray(response.data.sections)) {
              NaanoBuilder.sectionsData = response.data.sections.slice();
            }
            NaanoBuilder._refreshLivePreview();
            NaanoBuilder._renderSectionsList();
            NaanoBuilder._toast(
              NaanoBuilder._i18n("custom_html_inserted"),
              "success",
            );
            // Auto-select + auto-edit. The iframe needs a moment to
            // re-render before its scrollIntoView call has anything
            // to target, so highlight + open editor on a tiny delay.
            if (response.data && response.data.section_id) {
              var newId = response.data.section_id;
              setTimeout(function () {
                NaanoBuilder.openEditPanel(newId, true);
                NaanoBuilder._openCustomHtmlEditor(newId);
              }, 120);
            }
          } else {
            NaanoBuilder._toast(
              (response && response.data && response.data.message) ||
                data.strings.error_generic,
              "error",
            );
          }
        })
        .fail(function () {
          NaanoBuilder._toast(data.strings.error_generic, "error");
        });
    },

    /**
     * Open the floating Custom-HTML editor for a section. Pre-fills
     * the textarea with the section's current HTML (empty for fresh
     * inserts) and remembers the section id in _customHtmlEditingId
     * so the Save button knows which section to update.
     *
     * @param {string} sectionId Must be a custom-html section.
     */
    _openCustomHtmlEditor: function (sectionId) {
      var idx = NaanoBuilder._findSectionIndex(sectionId);
      if (idx === -1) return;
      var current = NaanoBuilder.sectionsData[idx].html || "";
      // Treat anything that's whitespace-only OR our server-side empty
      // marker comment as a truly blank textarea — the user shouldn't
      // see "<!-- naano:custom-html:empty -->" in the box.
      if (
        !current.trim() ||
        current.trim() === "<!-- naano:custom-html:empty -->"
      ) {
        current = "";
      }
      NaanoBuilder._customHtmlEditingId = sectionId;
      $("#naano-custom-html-editor-input").val(current);
      $("#naano-custom-html-editor-panel").show();
      // Defer focus so the show() animation doesn't fight with it.
      setTimeout(function () {
        $("#naano-custom-html-editor-input").focus();
      }, 50);
    },

    /**
     * Hide the floating editor and clear its editing-id state.
     */
    _closeCustomHtmlEditor: function () {
      NaanoBuilder._customHtmlEditingId = null;
      $("#naano-custom-html-editor-panel").hide();
    },

    /**
     * Push updated raw HTML for a custom-html section to the server,
     * then re-render the iframe with the sanitized result.
     *
     * @param {string} sectionId
     * @param {string} html
     */
    _updateCustomHtmlSection: function (sectionId, html) {
      $.post(data.ajaxUrl, {
        action: "naano_update_custom_html_section",
        nonce: data.nonce,
        page_id: NaanoBuilder.pageId,
        section_id: sectionId,
        html: html,
      })
        .done(function (response) {
          if (response && response.success) {
            var idx = NaanoBuilder._findSectionIndex(sectionId);
            if (idx !== -1) {
              NaanoBuilder.sectionsData[idx].html =
                response.data.section_html || html;
            }
            NaanoBuilder._refreshLivePreview();
            NaanoBuilder._renderSectionsList();
            NaanoBuilder._toast(
              NaanoBuilder._i18n("custom_html_updated"),
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
        title: NaanoBuilder._i18n("select_asset"),
        button: { text: NaanoBuilder._i18n("use_this_file") },
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
        NaanoBuilder._toast(NaanoBuilder._i18n("asset_added"), "success");
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
