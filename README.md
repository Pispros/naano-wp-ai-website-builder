# Naano AI Website Builder

> An AI-powered, section-by-section WordPress website builder using Claude, Gemini, or Kimi — pure PHP, no external backend needed.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

---

## What's new (1.3.0)

- **WP-Cron job runner** — long generations now run as a chain of single-LLM-call cron ticks, so a 12-section website never trips a shared host's `LSAPI_MAX_PROCESS_TIME` ceiling.
- **Skip-on-fail policy** — if a single section's worker is killed, the runner persists whatever it had, advances the cursor, and continues. The whole job no longer dies on one bad section.
- **Failed sections drawer with Retry** — recovered sections clear automatically.
- **Click-to-edit by default** — the previous `Inspect Elements` toggle is gone; clicking any element in the live preview opens the floating Element Editor (top-right of the canvas, Elementor-style).
- **Inline text editing** via `contenteditable` — type directly in the preview to change copy.
- **Link tab** for `<a>` elements (href / target / rel + in-page anchor picker).
- **Classes tab** — add or replace user CSS classes; AI classes are preserved.
- **Save changes vs Publish** — manual edits are stored as a draft via the new orange `Save changes` button; `Publish` is its own action.
- **Global CSS textarea** in the drawer, persisted alongside section edits.
- **Body-margin reset** — `html, body { margin: 0; padding: 0 }` is injected into both the live preview and the published page so AI-generated sections sit flush with the page edges.
- **Recovery UI on errors** — when a job ends in error or polling times out, the builder still loads the sections that were already persisted in the DB.
- **OpenAI / GPT-5.x adapter** with `reasoning_effort: none` to keep tokens flowing within shared-host execution windows.

---

## Overview

**Naano AI Website Builder** lets you generate, edit, and visually inspect complete, production-ready websites directly inside your WordPress dashboard using the AI model of your choice. You bring your own API key (Claude, Gemini, or Kimi) — there is no external service, no subscription, and no data leaves your server except the prompts you send to the LLM provider.

### Core philosophy

| Principle | Detail |
|-----------|--------|
| **Section-by-section editing** | Only the section you're working on is sent in full to the LLM. All other sections are compressed to ~8-character hash placeholders — saving 70–90% of context tokens on each edit. |
| **Pure PHP** | Every LLM call is made with PHP's `cURL` extension. No Node.js, no webpack, no build step. |
| **Bring your own key** | Your API credentials are stored in `wp_options` and never leave your server other than the outbound HTTPS call to the LLM provider. |
| **Token-optimized payloads** | HTML is minified, CSS is compressed, conversation history is trimmed — every request is as lean as possible. |
| **Strictly HTML output** | Every LLM response is passed through a sanitiser that strips `<script>` tags, `on*` event attributes, and `javascript:` URIs before any HTML is stored or displayed. |

---

## Features

### AI Generation
- 🧩 **Section-based generation** — describe your site, pick sections, and generate a complete page in one shot
- ✏️ **Per-section refinement** — refine any section independently with natural-language instructions
- ➕ **Add new sections** — generate additional sections on an existing page at any time
- 🔄 **Drag-and-drop reordering** — reorder sections visually; order is persisted via AJAX
- 🗑️ **Delete sections** — remove any section from the page

### Visual Builder
- 🖥️ **Live preview iframe** — see your changes instantly in a sandboxed preview panel
- 📱 **Responsive viewports** — toggle between Desktop (100%), Tablet (768 px) and Mobile (375 px) inside the builder
- 🖱️ **Click-to-edit by default** — no toggle needed. Click any element in the live preview to open the floating Element Editor (top-right of the canvas, Elementor-style)
- ✏️ **Inline text editing** — selected elements become `contenteditable`; type directly in the live preview to change copy
- 🎨 **Style tab** — Typography (color, size, weight, align), Background (color, image, size), Border, Border Radius
- 📐 **Spacing tab** — Width / Height / Max-width and individual Padding T/R/B/L + Margin T/R/B/L inputs
- 🏷️ **Classes tab** — add or override CSS class names on the selected element; existing AI-generated classes are preserved
- 🔗 **Link tab** (auto-shown on `<a>` elements) — edit `href`, `target`, `rel`, or pick an in-page anchor from a dropdown of detected sections (`#header`, `#hero`, …)
- 💅 **Custom CSS tab** — freeform CSS scoped to `[data-naano-el="…"]` for fine-tuning
- 🗑️ **Delete element** — remove any element directly from the editor; also exposed as a single click in the panel footer
- 🪄 **Edit-with-AI shortcut** — from the floating panel, jump straight to the AI editor for the section that contains the selected element
- 💾 **Save changes (manual)** — toolbar button persists all manual edits (text, styles, classes, deletions, global CSS) to a draft store. Always **separate from Publish** so you control when changes go live
- 📦 **Global CSS textarea** — page-level CSS injected into the assembled HTML; applies to the whole site, survives section regenerations
- 🩹 **Failed sections list with Retry** — sections that failed during the original generate (host kill, timeout) appear in a dedicated drawer with one-click Retry; entries clear automatically as sections recover
- 🪟 **Default browser margin reset** — the assembled page (and the live preview) ships with `html, body { margin: 0; padding: 0; box-sizing: border-box }` so sections sit flush against the page edges
- ⚡ **Live apply** — manual style changes are applied to the iframe in real time without regenerating the section

### Multi-LLM Support
- 🤖 **Claude** (Anthropic) — supports inline base64 image vision
- 🤖 **Gemini** (Google) — supports inline base64 image vision, generous free tier
- 🤖 **Kimi** (Moonshot) — text-based, good for copy-heavy pages

### References & Assets
- 🖼️ **Screenshot references** — attach images from the WordPress media library as visual inspiration; images are auto-resized to 1024 px JPEG/75% and base64-encoded
- 🔗 **URL references** — attach website URLs with notes; injected into the system prompt as a structured reference block
- 📎 **Page assets** — number-referenced assets (images, URLs) you can cite in instructions (e.g. "use asset #1 as hero image")
- 🔀 **URL redirections** — define named links (e.g. "Contact → /contact") so the AI uses your real site URLs

### Import & Reuse
- ♻️ **Import from existing pages** — on a new page, import the header or footer from any previously built Naano page instead of regenerating it; HTML is fetched server-side (never transported through the browser)

### Translations
- 🌐 **Configure languages** — add any number of languages (code + label) in Settings; e.g. `es → Spanish`, `fr → French`
- 🔀 **Duplicate for translation** — in the Pages List, click **Translate** on any original page, pick a language, and click **Duplicate & Translate**; a child page is created at `/<original-slug>/<lang-code>/` with all sections pre-copied
- 🔄 **Language switcher in builder** — when a page has translations, a `<select>` appears in the builder toolbar to jump directly between language variants
- 🏷️ **Translation badges** — the Pages List shows language badges (e.g. **EN**, **ES**) per row, with clickable links to each translation's builder and a back-link to the original

### Back-office Management
- 📋 **Pages list** — dedicated admin dashboard listing all Naano-built pages with status badges, section count, last-modified date, and quick actions
- 🗑️ **Delete page** — move any Naano page to WordPress trash directly from the pages list (with confirmation and a success notice on redirect)
- ⚙️ **Settings page** — configure LLM provider, API key, model override, and custom design variables from a single page
- 🔌 **Test connection** — validate your API key and model with a live ping before generating

### Design Variables
- 🎨 **Custom variables** — inject `primary_color`, `brand_name`, `font_family`, `tone`, `industry`, `target_audience`, and any custom key/value pairs into every prompt for consistent brand output

### Export & Publishing
- 📥 **Export HTML** — download the assembled full-page HTML document
- 📋 **Copy HTML** — copy the assembled HTML to the clipboard in one click
- 💾 **Save as WP Page** — publish the page as a standalone WordPress page served as raw HTML (no theme wrapping, no `wpautop`)
- 🏠 **Set as Homepage** — mark any Naano page as the WordPress static front page from within the builder

### Security & Performance
- 🔒 **HTML sanitization** — every LLM response is cleaned of `<script>`, `on*` events, and `javascript:` URIs
- 🔑 **Nonce-protected AJAX** — all endpoints verify `naano_builder_nonce`
- 📦 **Token-optimized payloads** — section placeholder hashes, HTML/CSS minification, context trimming
- 💬 **Conversation history** — the last 3 exchanges are kept for context; older messages are automatically trimmed

---

## Architecture

```
User Action (browser)
    │
    ▼
jQuery AJAX (builder.js)
    │
    ▼
PHP AJAX Handler (class-ajax-handler.php)
    │
    ├─► Job Manager (class-job-manager.php)
    │       Creates a job (transient) and schedules the first WP-Cron tick
    │
    ▼
Job Runner (class-job-runner.php)
    Runs ONE LLM call per WP-Cron tick (init / refine / persist) so each
    worker stays well under any LSAPI / shared-host execution ceiling.
    On unexpected shutdown the runner's "skip-on-fail" policy advances
    past the broken section instead of failing the whole job.
    │
    ├─► Payload Compressor (class-payload-compressor.php)
    │       Minifies HTML & CSS, replaces unchanged sections with hash placeholders
    │
    ├─► Prompt Builder (class-prompt-builder.php)
    │       Injects design variables, URL references, assets and redirects into prompts
    │
    ▼
LLM Router (class-llm-router.php)
    │
    ├─► Claude Adapter  (class-llm-claude.php)   ─► cURL → api.anthropic.com
    ├─► Gemini Adapter  (class-llm-gemini.php)   ─► cURL → generativelanguage.googleapis.com
    ├─► Kimi Adapter    (class-llm-kimi.php)     ─► cURL → api.moonshot.cn
    └─► OpenAI Adapter  (class-llm-openai.php)   ─► cURL → api.openai.com
    │
    ▼
Raw LLM Response
    │
    ▼
HTML Sanitizer (class-html-sanitizer.php)
    Strips scripts, events, javascript: URIs; fixes DOM
    │
    ▼
Section Manager (class-section-manager.php)
    Stores / updates / reorders section HTML in wp_postmeta
    Also persists global CSS and the "failed sections" retry list
    │
    ▼
JSON Response → UI Update (builder.js)
    │
    ├─► Live-preview iframe refresh (srcdoc, with body-margin reset + global CSS)
    └─► Element Editor (postMessage bridge, floating top-right panel)
            ▲
            │  naano-element-selected · naano-apply-element-style ·
            │  naano-apply-element-classes · naano-apply-element-link ·
            │  naano-delete-element · naano-element-html-updated · naano-deselect-element
            ▼
        Iframe helper script (injected)
            Hover highlight · click selection · inline contenteditable text ·
            inline style apply · class merge · href/target editing · delete
```

### Persistent state (post meta)

| Meta key | Stored on | Value |
|----------|-----------|-------|
| `_naano_sections` | Each Naano page | Array of `[id, type, html, order]` per section |
| `_naano_global_css` | Each Naano page | Raw CSS injected into the assembled page after the platform reset |
| `_naano_failed_sections` | Each Naano page | List of `[section_id, section_type, reason]` for sections that failed during the last `generate_site` run |
| `_naano_lang` | Original + translations | Language code (`default`, `es`, `fr`, …) |
| `_naano_translation_of` | Translations only | Post ID of the original page |
| `_naano_page_html` | Each Naano page | Raw assembled HTML written by `Publish` (for non-WordPress rendering paths) |

---

## Requirements

| Requirement | Minimum Version |
|-------------|----------------|
| PHP | 8.1 |
| WordPress | 6.0 |
| PHP extension: curl | any |
| PHP extension: json | any |
| PHP extension: gd | any (for image resizing) |
| PHP extension: dom | any (for HTML fixing) |
| PHP extension: mbstring | any |
| Outbound HTTPS | port 443 to LLM provider domain |

---

## Installation

### Method 1 — Upload ZIP

1. Download or clone this repository.
2. Zip the `naano-ai-website-builder` folder.
3. In WordPress Admin, go to **Plugins → Add New Plugin → Upload Plugin**.
4. Choose the zip file and click **Install Now**.
5. Click **Activate Plugin**.

### Method 2 — Manual FTP

1. Upload the `naano-ai-website-builder` folder to `/wp-content/plugins/`.
2. In WordPress Admin, go to **Plugins → Installed Plugins**.
3. Find **Naano AI Website Builder** and click **Activate**.

---

## Quick Start

1. **Activate** the plugin (see Installation above).
2. Go to **Naano AI Builder → Settings**.
3. Select your LLM **Provider** (Claude, Gemini, Kimi, or OpenAI).
4. Paste your **API Key** and click **Test Connection**.
5. Add **Custom Design Variables** (e.g. `primary_color → #3B82F6`, `brand_name → Acme Corp`).
6. Click **Save Settings**.
7. Go to **Naano AI Builder → AI Pages** and click **Create New Page with AI**.
8. Enter a page name and description, optionally import an existing header/footer, check the sections you want, and click **Generate Full Website**.
9. Once generation completes, **click any element directly in the live preview** to open the floating Element Editor — type to edit text, switch tabs to tweak Style / Spacing / Classes / Link / Custom CSS.
10. Use the drawer's **Global CSS** textarea to set page-wide CSS rules.
11. If sections failed during generation, find them in the **Failed sections** drawer and click **Retry** per entry.
12. Click **Save changes** in the toolbar to persist your manual edits and Global CSS as a draft (does **not** publish).
13. Click **Publish** to push the assembled HTML to the live WordPress page. Tick **Set as Homepage** if you want this page to replace the WordPress front page.

### Server prerequisite for AI generation

The AI generation pipeline runs as a chain of WP-Cron ticks. On shared hosts where `wp-cron.php` is unreliable (the default), add an OS-level cron that hits it every minute:

```
* * * * * wget -q -O - https://your-site.tld/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Without this, generation will still progress whenever a visitor lands on the site, but progress between ticks will be slow.

---

## Element Editor (Visual Editing)

The built-in element editor works like Elementor's style editor — without blocks, without a different page format. Inspect mode is the default, so any click in the live preview opens the editor.

### Selecting an element

1. Open the builder on any page that has sections.
2. Click any element in the live preview — a floating **Element Editor** panel slides in from the top-right corner of the canvas.
3. The clicked element is highlighted with a solid orange outline and becomes editable in place: type directly into headings, paragraphs, buttons, etc. to change their text. Press `Esc` or click the panel's `×` to deselect.

### Tabs in the floating panel

| Tab | What you can do |
|------|------------------|
| **Style** | Color, font-size, font-weight, text-align, background color/image/size, border, border-radius |
| **Spacing** | Width, height, max-width, padding T/R/B/L, margin T/R/B/L (each side independent) |
| **Classes** | Type space-separated CSS class names. Existing AI-generated classes are preserved (the iframe merges your input with the AI's classes on apply). |
| **Link** *(only on `<a>`)* | Edit `href`, `target` (same/new tab), `rel`. The Anchor dropdown lists every `data-section` ID on the page so you can wire up `#header`, `#contact`, etc. without typing. |
| **Custom CSS** | Freeform CSS scoped to that element via `[data-naano-el="…"]`. Useful for hover states, transitions, etc. |

### Footer actions

| Button | Effect |
|---------|---------|
| **Delete** | Removes the selected element from its section (cannot delete the section root) |
| **Edit section with AI** | Closes the panel and opens the AI edit drawer for the parent section |
| **Done** | Deselects without applying any pending changes from the inputs |
| **Apply** | Pushes the panel's styles + classes + link to the live preview and marks the section as "unsaved" |

### Save vs Publish

The toolbar separates the two operations clearly:

- **Save changes** (orange, only visible when there are unsaved manual edits) — persists all dirty section HTML and the **Global CSS** textarea to the draft store via `naano_save_section_html`. Does **not** modify the live published page.
- **Publish** (the existing primary button) — assembles the final HTML server-side (with the body-margin reset and your global CSS) and writes it to the published WordPress page.

A `beforeunload` warning prompts you if you try to close the tab with unsaved manual edits.

### Global CSS

A textarea in the left drawer (right under URL References) accepts page-level CSS. It's injected into the assembled HTML **after** the platform's reset (`html, body { margin: 0; padding: 0 }` and `box-sizing: border-box`), so you can override anything you want. Persisted alongside section edits via the same **Save changes** button.

### Failed sections & Retry

When `generate_site` is interrupted (host kills the worker on a slow LLM call, exception during a section's render, etc.), failed sections are recorded per page in `_naano_failed_sections` post meta. They surface as a dedicated **Failed sections** drawer field with a one-click **Retry** button per entry. Recovered sections automatically disappear from the list.

> Styles applied through the editor are stored as inline `style` attributes, scoped `<style>` blocks on `[data-naano-el]` elements, or class additions — fully compatible with any subsequent AI regeneration of that section.

---

## Translations

Translations follow WordPress page hierarchy: each translated page is a **child** of the original with its language code as the page slug, giving automatic URLs:

| Original | Spanish translation | French translation |
|----------|--------------------|--------------------|  
| `/about/` | `/about/es/` | `/about/fr/` |
| `/` (homepage) | `/es/` | `/fr/` |

### Setup
1. Go to **Naano AI Builder → Settings → Translation Languages**.
2. Click **+ Add Language** and enter a language code (e.g. `es`) and label (e.g. `Spanish`).
3. Click **Save Settings**.

### Creating a translation
1. Go to **Naano AI Builder → AI Pages**.
2. Find your original page and click **Translate** in the Language column.
3. Select the target language from the dropdown and click **Duplicate & Translate**.
4. The plugin creates a child page with all sections copied, then redirects you to its builder.
5. Translate the content section-by-section using the AI — give the AI an instruction like _"Translate all text to Spanish, keeping the same HTML structure"_.
6. Click **Save** to publish the translated page.

### Language switcher in the builder
Once a page has at least one translation, a language `<select>` appears in the builder toolbar. Switching languages navigates immediately to that variant's builder.

### Meta keys

| Meta key | Set on | Value |
|----------|--------|-------|
| `_naano_lang` | Original + translations | Language code (e.g. `es`); `default` for root pages tagged at first translation |
| `_naano_translation_of` | Translations only | Post ID of the root (original) page |

---

## Import from Existing Pages

When creating a new page, you can skip AI generation for the header and/or footer and reuse them from an existing Naano page instead:

1. On the **Create New Page** panel, expand **Import from Existing Pages**.
2. Click **Header** or **Footer** next to any listed page to toggle it on (a checkmark appears).
3. Click **Generate Full Website** — imported sections are copied server-side from the source page's stored HTML (the HTML is never sent through the browser), then AI-generated sections are added alongside them.

---

## Configuration

### API Keys

| Provider | Where to get your key | Free tier |
|----------|----------------------|-----------|
| Claude (Anthropic) | [console.anthropic.com](https://console.anthropic.com/) | No (pay-as-you-go) |
| Gemini (Google) | [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | Yes (generous free tier) |
| Kimi (Moonshot) | [platform.moonshot.cn](https://platform.moonshot.cn/) | Yes (limited) |

### Custom Variables

Custom variables are injected into every system prompt as a structured list. The AI will use them to maintain brand consistency across all sections.

| Variable | Example Value | What the AI Uses It For |
|----------|--------------|------------------------|
| `primary_color` | `#3B82F6` | Main buttons, headings, accents |
| `secondary_color` | `#10B981` | Secondary elements, highlights |
| `brand_name` | `Acme Corp` | Company name throughout the site |
| `font_family` | `Inter, sans-serif` | All typography |
| `tone` | `modern and minimal` | Overall design aesthetic |
| `industry` | `SaaS / Technology` | Industry-appropriate imagery & copy |
| `target_audience` | `small teams, startups` | Tailored messaging |

---

## Token Optimization

| Strategy | Typical Savings |
|----------|----------------|
| Section placeholders (hash compression) | ~70–90% on section updates |
| HTML minification | ~15–25% |
| Inline CSS minification | ~10–20% |
| Conversation trimming (last 3 exchanges) | Prevents runaway costs |
| Image resizing to 1024 px JPEG 75% | ~50–80% on vision tokens |
| Targeted single-section responses | ~60–80% on response size |

---

## LLM Providers

| Provider | Endpoint | Auth | Image Support | Default Model |
|----------|----------|------|---------------|---------------|
| Claude | `api.anthropic.com/v1/messages` | `x-api-key` header | Base64 inline | `claude-sonnet-4-20250514` |
| Gemini | `generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | URL query param `?key=` | `inlineData` base64 | `gemini-2.5-flash` |
| Kimi | `api.moonshot.cn/v1/chat/completions` | `Authorization: Bearer` | Via text note | `kimi-k2-0711-preview` |
| OpenAI | `api.openai.com/v1/chat/completions` | `Authorization: Bearer` | `image_url` base64 | `gpt-5.5` |

All adapters implement `Naano_LLM_Provider_Interface` — adding a new provider is straightforward.

---

## Screenshot & URL References

### Screenshots
- Click the **🖼️ button** on a section card (or inside the edit panel).
- The WordPress media library opens; select or upload any image.
- The image is stored as a media attachment, then when a section update is triggered, the plugin:
  1. Fetches the file from disk.
  2. Resizes it to a maximum of 1024 × 1024 pixels using PHP GD (`imagecopyresampled`).
  3. Re-encodes it as JPEG at 75% quality.
  4. Base64-encodes the result.
  5. Injects it into the LLM message as an image content block (Claude / Gemini) or a text note (Kimi).

### URL References
- Click the **🔗 button** on a section card (or inside the edit panel).
- Enter the URL and optional notes.
- URL references are injected into the system prompt as a "REFERENCE WEBSITES" block.

---

## Export Options

| Option | How | Result |
|--------|-----|--------|
| **Preview** | Click "Preview" in the toolbar | Full-screen overlay with desktop/tablet/mobile viewport buttons |
| **Export HTML** | Click "Export" in the toolbar | Downloads `website.html` (complete HTML5 document with body-margin reset and your global CSS) |
| **Copy HTML** | Click "Copy" in the toolbar | Copies the full HTML to your clipboard |
| **Save changes** | Click the orange "Save changes" button (only visible when you have unsaved manual edits) | Persists section HTML edits + Global CSS to the draft store. Does **not** publish. |
| **Publish** | Click "Publish" in the toolbar | Publishes the page as a standalone WordPress page (raw HTML, no theme wrapping) |
| **Set as Homepage** | Tick the checkbox in the Publish modal | Sets `show_on_front=page` and `page_on_front` in WordPress options |

---

## Back-office Pages List

Go to **Naano AI Builder → AI Pages** to see a table of all pages built with Naano AI. Each row shows:

| Column | Description |
|--------|-------------|
| Page | Page title, linked to the builder |
| Status | Publish / Draft / Pending / Private badge |
| Sections | Number of stored sections |
| Last Modified | Date and time of the last edit |
| Actions | Open Builder · View (published only) · Homepage badge · **Delete** (moves to trash) |

---

## AJAX API Reference

All endpoints require a valid `naano_builder_nonce` nonce in the `nonce` POST field.

| Action | Method | Key Parameters | Success Response |
|--------|--------|---------------|-----------------|
| `naano_generate_site` | POST | `page_id`, `page_name`, `description`, `sections[]`, `imported_sections` (JSON) | `{job_id}` (poll via `naano_poll_job`) |
| `naano_update_section` | POST | `page_id`, `section_id`, `instruction`, `assets` (JSON), `redirects` (JSON) | `{job_id}` (poll via `naano_poll_job`) |
| `naano_poll_job` | POST | `job_id` | `{status, data?, partial?, log?}` |
| `naano_save_section_html` | POST | `page_id`, `sections` (JSON `[{id, html}]`), `global_css` (optional) | `{saved_count, page_id, global_css_saved}` |
| `naano_get_failed_sections` | POST | `page_id` | `{page_id, failed_sections, global_css}` |
| `naano_test_connection` | POST | `provider`, `api_key`, `model` | `{success, model, latency_ms}` |
| `naano_add_reference` | POST | `page_id`, `section_id`, `type`, `url`, `attachment_id`, `notes` | `{references}` |
| `naano_remove_reference` | POST | `page_id`, `section_id`, `index` | `{references}` |
| `naano_delete_section` | POST | `page_id`, `section_id` | `{}` |
| `naano_reorder_sections` | POST | `page_id`, `order[]` | `{}` |
| `naano_export_html` | POST | `page_id` | `{html}` |
| `naano_save_as_page` | POST | `page_id`, `title`, `html` *(html now optional — server prefers DB-assembled HTML)* | `{page_id, edit_url, view_url, title}` |
| `naano_set_homepage` | POST | `page_id` | `{}` |
| `naano_duplicate_for_translation` | POST (admin-post.php) | `page_id`, `lang` | Redirect to new page's builder |

---

## File Structure

```
naano-ai-website-builder/
├── naano-ai-website-builder.php          # Main plugin entry point, constants, hooks
├── assets/
│   ├── css/
│   │   └── builder.css                   # Full builder + admin styles
│   ├── images/
│   │   └── naano-icon.svg                # Custom white SVG sidebar icon
│   └── js/
│       ├── builder.js                    # Builder UI, AJAX, inspector, drag-drop
│       └── preview.js                    # Preview modal with responsive toggles
├── includes/
│   ├── interface-llm-provider.php        # LLM provider interface
│   ├── class-llm-claude.php              # Claude (Anthropic) adapter
│   ├── class-llm-gemini.php              # Gemini (Google) adapter
│   ├── class-llm-kimi.php                # Kimi (Moonshot) adapter
│   ├── class-llm-openai.php              # OpenAI (GPT-5.x with reasoning_effort) adapter
│   ├── class-llm-router.php              # Provider router + sanitize pipeline
│   ├── class-llm-utils.php               # Shared cURL defaults
│   ├── class-payload-compressor.php      # HTML/CSS minification + section placeholders
│   ├── class-html-sanitizer.php          # Extract, clean, validate LLM HTML output
│   ├── class-prompt-builder.php          # System/user prompt assembly
│   ├── class-reference-manager.php       # Screenshot uploads + URL references
│   ├── class-section-manager.php         # Section CRUD + assembled HTML + global CSS + failed sections meta
│   ├── class-conversation.php            # Conversation history per page
│   ├── class-ajax-handler.php            # All wp_ajax_* endpoints
│   ├── class-admin-page.php              # Admin menus, settings, asset enqueue, inspector styles
│   └── jobs/
│       ├── class-job-manager.php         # Transient-backed job state + log
│       └── class-job-runner.php          # WP-Cron-driven runner: 1 LLM call per tick + skip-on-fail policy
├── templates/
│   ├── admin-pages-list.php              # Back-office pages list (table + delete button)
│   ├── frontend-builder.php              # Full visual builder UI (toolbar, drawer, iframe, inspector)
│   └── settings-page.php                # Settings form template
├── README.md                             # This file
├── RENAMING.md                           # How to rename / rebrand the plugin
└── readme.txt                            # WordPress.org readme
```

---

## Hooks & Filters

| Hook | Type | Description |
|------|------|-------------|
| `naano_before_generate` | action | Fires before the LLM is called for full-site generation. Receives `$page_id` and `$sections[]`. |
| `naano_after_generate` | action | Fires after sections are stored. Receives `$page_id` and `$parsed_sections[]`. |
| `naano_before_update_section` | action | Fires before a section update LLM call. Receives `$page_id` and `$section_id`. |
| `naano_after_update_section` | action | Fires after a section is updated. Receives `$page_id`, `$section_id`, `$html`. |
| `naano_system_prompt` | filter | Modify the assembled system prompt before it is sent. |
| `naano_user_message` | filter | Modify the user message before it is sent. |
| `naano_sanitized_html` | filter | Modify cleaned HTML after sanitization. |

---

## How to Rename the Plugin

See [RENAMING.md](RENAMING.md) for a full step-by-step guide including automated `sed` / PowerShell commands and a database migration snippet.

---

## Contributing

Pull requests are welcome! Please open an issue first for significant changes.

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Commit your changes.
4. Push and open a pull request.

---

## License

GPL-2.0-or-later © Naano
