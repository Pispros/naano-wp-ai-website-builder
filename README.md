# Naano AI Website Builder

> An AI-powered, section-by-section WordPress website builder using Claude, Gemini, or Kimi — pure PHP, no external backend needed.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

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
- 🔍 **Element inspector** — Elementor-style inspect mode: click any element in the live preview to select it
- 🎨 **Visual style panel** — edit Typography (color, size, weight, align), Background (color, image, size), Size (width, height, max-width), Padding, Margin, Border, and Border Radius through a dedicated panel without writing a line of code
- 💅 **Custom CSS tab** — inject freeform CSS scoped to the selected element directly from the style panel
- ⚡ **Live apply** — style changes are applied to the iframe in real time without regenerating the section

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
    └─► Kimi Adapter    (class-llm-kimi.php)     ─► cURL → api.moonshot.cn
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
    │
    ▼
JSON Response → UI Update (builder.js)
    │
    ├─► Live-preview iframe refresh (srcdoc)
    └─► Element inspector (postMessage bridge)
            ▲
            │  naano-element-selected / naano-apply-element-style
            ▼
        Iframe helper script (injected)
            Hover highlight · click selection · inline style apply
```

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
3. Select your LLM **Provider** (Claude, Gemini, or Kimi).
4. Paste your **API Key** and click **Test Connection**.
5. Add **Custom Design Variables** (e.g. `primary_color → #3B82F6`, `brand_name → Acme Corp`).
6. Click **Save Settings**.
7. Go to **Naano AI Builder → AI Pages** and click **Create New Page with AI**.
8. Enter a page name and description, optionally import an existing header/footer, check the sections you want, and click **Generate Full Website**.
9. Click any section in the live preview to open its edit panel.
10. Attach **screenshot references** or **URL references** to guide the AI on the next update.
11. Click **Inspect Elements** to enter visual edit mode — click any element to open the style panel and tweak typography, spacing, colors, or inject custom CSS without re-running the AI.
12. Click **Preview** to see the full site at different breakpoints, **Export HTML** to download, or **Save** to publish as a standalone WordPress page.

---

## Element Inspector (Visual Editing)

The built-in element inspector works like Elementor's style editor — without blocks or a different page format.

| Step | Action |
|------|--------|
| 1 | Open the builder on any page that has sections |
| 2 | Click **Inspect Elements** in the left panel (cursor turns to crosshair) |
| 3 | Hover over any element in the live preview — it is highlighted with an orange dashed outline |
| 4 | Click the element — the **Element Style Panel** opens in the sidebar |
| 5 | Edit Typography, Background, Size, Padding, Margin or Border controls |
| 6 | Switch to the **Custom CSS** tab for freeform CSS scoped to that element |
| 7 | Click **Apply** — the change is applied live in the iframe |
| 8 | The updated section HTML is saved in memory; **Save** will persist it to the database |

> Styles applied through the inspector are stored as inline `style` attributes or scoped `<style>` blocks on `[data-naano-el]` elements — fully compatible with any subsequent AI regeneration of that section.

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
| **Export HTML** | Click "Export" in the toolbar | Downloads `website.html` (complete HTML5 document) |
| **Copy HTML** | Click "Copy" in the toolbar | Copies the full HTML to your clipboard |
| **Save** | Click "Save" in the toolbar | Publishes the page as a standalone WordPress page (raw HTML, no theme wrapping) |
| **Set as Homepage** | Tick the checkbox in the Save modal | Sets `show_on_front=page` and `page_on_front` in WordPress options |

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
| `naano_generate_site` | POST | `page_id`, `page_name`, `description`, `sections[]`, `imported_sections` (JSON) | `{page_id, sections, html}` |
| `naano_update_section` | POST | `page_id`, `section_id`, `instruction`, `assets` (JSON), `redirects` (JSON) | `{section_id, section_html}` |
| `naano_test_connection` | POST | `provider`, `api_key`, `model` | `{success, model, latency_ms}` |
| `naano_add_reference` | POST | `page_id`, `section_id`, `type`, `url`, `attachment_id`, `notes` | `{references}` |
| `naano_remove_reference` | POST | `page_id`, `section_id`, `index` | `{references}` |
| `naano_delete_section` | POST | `page_id`, `section_id` | `{}` |
| `naano_reorder_sections` | POST | `page_id`, `order[]` | `{}` |
| `naano_export_html` | POST | `page_id` | `{html}` |
| `naano_save_as_page` | POST | `page_id`, `title`, `html` | `{page_id, edit_url, view_url, title}` |
| `naano_set_homepage` | POST | `page_id` | `{}` |

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
│   ├── class-llm-router.php              # Provider router + sanitize pipeline
│   ├── class-payload-compressor.php      # HTML/CSS minification + section placeholders
│   ├── class-html-sanitizer.php          # Extract, clean, validate LLM HTML output
│   ├── class-prompt-builder.php          # System/user prompt assembly
│   ├── class-reference-manager.php       # Screenshot uploads + URL references
│   ├── class-section-manager.php         # Section CRUD + HTML assembly
│   ├── class-conversation.php            # Conversation history per page
│   ├── class-ajax-handler.php            # All wp_ajax_* endpoints
│   └── class-admin-page.php              # Admin menus, settings, asset enqueue, inspector styles
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

