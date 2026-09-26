# Changelog

All notable changes to Naano AI Website Builder are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.3.9] - 2026-09-25

### Fixed

- **Removed icons from buttons** — Removed dashicons from "Create New Page with AI", "Site Configuration", and "Build Your First Page" buttons to fix alignment issues with text.

### Added

- **LSAPI-compliant job scheduling system** — The entire generation system now uses a tick-based approach where each WP-Cron tick executes exactly one LLM call (or setup/persist operations). This ensures compatibility with shared hosting environments that enforce `LSAPI_MAX_PROCESS_TIME` limits (typically 60-300s).
  - `generate_site`: Each section (initial generation + refinement passes) runs in its own tick. Failed sections are recorded and can be retried individually via the UI.
  - `update_section`: Implemented retry policy (max 2 attempts) with persistence of intermediate state between retries.
  - `enhance_prompt`: Implemented retry policy (max 2 attempts) to tolerate transient LSAPI kills during the LLM call.
- **State persistence between ticks** — Multi-step jobs (generate_site, update_section) store their cursor and intermediate data in post meta, surviving host kills and resuming cleanly on the next cron tick.
- **Enhanced error handling** — Added tiered recovery policy:
  - `generate_site`: Skip failing sections and continue with remaining ones.
  - `update_section` / `enhance_prompt`: Retry up to 2 times before marking as error with a clear message.

---

## [2.3.5] - 2026-XX-XX

### Fixed

- **Kimi-K3 temperature fix** — Kimi-K3 now correctly uses `temperature=1` (fixed HTTP 400 error: "invalid temperature: only 1 is allowed for this model"). Kimi-K2.5 still uses the default 0.6 and cannot be changed.

---

## [2.3.4] - 2026-XX-XX

### Added

- **Global CSS applied on editor open** — Your saved Global CSS is now injected into the very first live-preview render (passed in the localized page data and pre-filled before the first paint). Previously it was fetched asynchronously after the first render, leaving the editor briefly un-styled until you touched the field.
- **French translations** — The new JS tab label, its help text, and the syntax-error toast ship translated (`.po`/`.mo`/`.pot` updated).

### Changed

- Cache-bump: `NAANO_VERSION` is now `2.3.4` so WordPress regenerates the asset URL and browsers fetch the new `builder.js`.

---

## [2.3.2] - 2026-XX-XX

### Added

- **JS tab on buttons** — The floating Element Editor gains a contextual **JS** tab, shown only for button-like elements (`<button>`, `<input type="button\|submit\|reset">`, `role="button"`, or an `<a>` whose class contains `btn` / `button` / `cta`). Type raw JavaScript and it becomes the button's click handler **on the published page** — no theme files, no enqueue, no build step. Inside the code, `this` is the button and `event` is the click event.
- **Same persistence model as Custom CSS** — The code is stored verbatim on the element as a `data-naano-cust-js` attribute (single source of truth, auto HTML-escaped), and a generic `<script data-naano-cust-scripts>` block is regenerated at the end of the section. It reads each button's code at click time via `new Function`, so nothing is inlined into the script body (a stray `</script>` can never break out). Both survive the server-side sanitizer, which keeps inline scripts and `data-*` attributes.
- **Never runs in the editor** — A builder flag short-circuits the handler inside the preview iframe, so clicks there keep selecting elements instead of firing your code.
- **Broken code no longer hijacks the page** — If your handler throws (syntax or runtime error), the binder calls `event.preventDefault()` and logs to the console, so a faulty script on a `type="submit"` button can't submit the form and land the visitor on a blank/default template.
- **Syntax checked on Apply** — The editor compiles your code with the same `new Function` the live page uses; on error it shows the exact message in a toast and still saves so you don't lose your work.

---

## [2.3.1] - 2026-XX-XX

### Added

- **♻ Recycle button on every hovered element** — In the live preview iframe (where inspect mode is the default), every element you hover now gets a small amber ♻ button at its top-right corner. Click it to open a widget picker modal and swap the element for a fresh Text or Image widget.
- **Text widget** — Replaces the target element with an editable `<p>` containing `"Texte à éditer…"`. AI-generated class names are preserved so the new paragraph keeps the section's layout context.
- **Image widget** — Opens the WordPress media library immediately. On select, the element is replaced with an `<img>` whose `src` and `alt` come from the attachment. `max-width: 100%` and `height: auto` are applied inline so the image scales correctly.
- **Section is marked dirty automatically** — The replacement triggers the same `naano-element-html-updated` flow as every other DOM mutation, so the toolbar's _Save changes_ button surfaces immediately.
- **No existing behaviour changed** — The section "+" button, click-to-edit inspect mode, contenteditable inline typing, and the floating Element Editor panel all continue to work exactly as before. The recycle button has its own hover scope and z-index.

---

## [1.3.0] - 2026-XX-XX

### Added

- **WP-Cron job runner** — Long generations now run as a chain of single-LLM-call cron ticks, so a 12-section website never trips a shared host's `LSAPI_MAX_PROCESS_TIME` ceiling.
- **Skip-on-fail policy** — If a single section's worker is killed, the runner persists whatever it had, advances the cursor, and continues. The whole job no longer dies on one bad section.
- **Failed sections drawer with Retry** — Recovered sections clear automatically.
- **Click-to-edit by default** — The previous `Inspect Elements` toggle is gone; clicking any element in the live preview opens the floating Element Editor (top-right of the canvas, Elementor-style).
- **Inline text editing** via `contenteditable` — Type directly in the preview to change copy.
- **Link tab** for `<a>` elements (href / target / rel + in-page anchor picker).
- **Classes tab** — Add or replace user CSS classes; AI classes are preserved.
- **Save changes vs Publish** — Manual edits are stored as a draft via the new orange `Save changes` button; `Publish` is its own action.
- **Global CSS textarea** in the drawer, persisted alongside section edits.
- **Body-margin reset** — `html, body { margin: 0; padding: 0 }` is injected into both the live preview and the published page so AI-generated sections sit flush with the page edges.
- **Recovery UI on errors** — When a job ends in error or polling times out, the builder still loads the sections that were already persisted in the DB.
- **OpenAI / GPT-5.x adapter** with `reasoning_effort: none` to keep tokens flowing within shared-host execution windows.

---

## Format note

Each entry includes:

- **Added** — New features
- **Changed** — Behavior or UX changes
- **Fixed** — Bug fixes
- **Removed** — Deprecated features removed
