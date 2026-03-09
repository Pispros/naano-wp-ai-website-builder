# 🤖 Naano AI Website Builder

> An AI-powered, section-by-section WordPress website builder using Claude, Gemini, or Kimi — pure PHP, no external backend needed.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

---

## Overview

**Naano AI Website Builder** lets you generate complete, production-ready websites directly inside your WordPress dashboard using the AI model of your choice. You bring your own API key (Claude, Gemini, or Kimi) — there is no external service, no subscription, and no data leaves your server except the prompts you send to the LLM provider.

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

- 🧩 **Section-based generation and editing** — generate a full site in one shot, then refine each section independently
- 🤖 **Multi-LLM support** — Claude (Anthropic), Gemini (Google), Kimi (Moonshot)
- �� **Bring your own API key** — no middleman, no subscription
- 📦 **Token-optimized compressed payloads** — placeholder hashes for unchanged sections
- 🖼️ **Screenshot reference support** — upload images as visual inspiration; they are resized and base64-encoded automatically
- 🔗 **URL reference support** — attach website URLs with notes to any section
- 🎨 **Custom design variables** — inject brand colors, fonts, tone, industry etc. into every prompt
- 🧹 **Automatic HTML sanitization** — every LLM response is cleaned before storage
- 📱 **Responsive preview** — desktop (1200 px), tablet (768 px), mobile (375 px) in a full-screen overlay
- 📥 **Export options** — download as HTML file, copy to clipboard, or save as a WordPress draft page
- 🔒 **Pure PHP** — no external backend, no Node.js required
- 💬 **Conversation history** — the last 3 exchanges are included for context; older messages are automatically trimmed

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
    │       Injects design variables and URL references into system/user prompts
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
    Stores / updates section HTML in wp_postmeta
    │
    ▼
JSON Response → UI Update (builder.js)
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
4. Paste your **API Key**.
5. Optionally set a **Model Override** (leave blank for the default model).
6. Click **Test Connection** to verify the key works.
7. Add **Custom Design Variables** (e.g. `primary_color → #3B82F6`, `brand_name → Acme Corp`).
8. Click **Save Settings**.
9. Go to **Naano AI Builder → New Page**.
10. Enter a page name and description, check the sections you want, and click **Generate Full Website**.
11. Once generated, click the **✏️ edit icon** on any section card to refine it.
12. Optionally attach **screenshot references** (🖼️) or **URL references** (🔗) to guide the AI.
13. Click **Preview** to see the full site, **Export HTML** to download, or **Save as WP Page** to create a draft.

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
| Gemini | `generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | URL query param `?key=` | `inlineData` base64 | `gemini-2.0-flash` |
| Kimi | `api.moonshot.cn/v1/chat/completions` | `Authorization: Bearer` | Via text note | `moonshot-v1-8k` |

All adapters implement `Naano_LLM_Provider_Interface` — adding a new provider is straightforward.

---

## Screenshot & URL References

### Screenshots
- Click the **🖼️ button** on a section card (or inside the edit panel).
- The WordPress media library opens; select or upload any image.
- The image is stored as a media attachment, then when a section update is triggered, the plugin:
  1. Fetches the file from disk.
  2. Resizes it to a maximum of 1024 x 1024 pixels using PHP GD (`imagecopyresampled`).
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
| **Preview** | Click "Preview" in the action bar | Full-screen overlay with desktop/tablet/mobile viewport buttons |
| **Export HTML** | Click "Export HTML" | Downloads `website.html` (complete HTML5 document) |
| **Copy HTML** | Click "Copy HTML" | Copies the full HTML to your clipboard |
| **Save as WP Page** | Click "Save as WP Page" | Creates a WordPress page as a **draft** with the assembled HTML as content |

---

## How to Rename the Plugin

See [RENAMING.md](RENAMING.md) for a full step-by-step guide including automated `sed` / PowerShell commands and a database migration snippet.

---

## AJAX API Reference

All endpoints require a valid `naano_builder_nonce` nonce in the `nonce` POST field.

| Action | Method | Key Parameters | Success Response |
|--------|--------|---------------|-----------------|
| `naano_generate_site` | POST | `page_id`, `description`, `sections[]` | `{sections, html}` |
| `naano_update_section` | POST | `page_id`, `section_id`, `instruction` | `{section_id, section_html}` |
| `naano_test_connection` | POST | `provider`, `api_key`, `model` | `{success, model, latency_ms}` |
| `naano_add_reference` | POST | `page_id`, `section_id`, `type`, `url`, `attachment_id`, `notes` | `{references}` |
| `naano_remove_reference` | POST | `page_id`, `section_id`, `index` | `{references}` |
| `naano_delete_section` | POST | `page_id`, `section_id` | `{}` |
| `naano_reorder_sections` | POST | `page_id`, `order[]` | `{}` |
| `naano_export_html` | POST | `page_id` | `{html}` |
| `naano_save_as_page` | POST | `page_id`, `title` | `{page_id, edit_url, view_url}` |

---

## File Structure

```
naano-ai-website-builder/
├── naano-ai-website-builder.php          # Main plugin entry point, constants, hooks
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
│   └── class-admin-page.php              # Admin menus, settings, asset enqueue
├── assets/
│   ├── js/
│   │   ├── builder.js                    # Builder UI, AJAX, drag-drop
│   │   └── preview.js                    # Preview modal with responsive toggles
│   └── css/
│       └── builder.css                   # Admin builder styles
├── templates/
│   ├── builder-page.php                  # Main builder admin page template
│   ├── section-card.php                  # Section card partial
│   └── settings-page.php                 # Settings form template
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

## Contributing

Pull requests are welcome! Please open an issue first for significant changes.

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Commit your changes.
4. Push and open a pull request.

---

## License

GPL-2.0-or-later © Naano
