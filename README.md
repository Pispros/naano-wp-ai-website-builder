# Naano AI Website Builder

> An AI-powered, section-by-section WordPress website builder using Claude, Gemini, OpenAI, Kimi, or DeepSeek — pure PHP, no external backend needed.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![Version](https://img.shields.io/badge/Version-2.3.6-9A3412)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

[Documentation](https://github.com/Pispros/naano-wp-ai-website-builder) · [Download latest release](https://github.com/Pispros/naano-wp-ai-website-builder/releases) · [Changelog](CHANGELOG.md)

---

## Overview

**Naano AI Website Builder** lets you generate, edit, and visually inspect complete, production-ready websites directly inside your WordPress dashboard using the AI model of your choice. You bring your own API key — there is no external service, no subscription, and no data leaves your server except the prompts you send to the LLM provider.

### Core philosophy

| Principle                      | Detail                                                                                                                                                                |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Section-by-section editing** | Only the section you're working on is sent in full to the LLM. All other sections are compressed to ~8-character hash placeholders — saving 70–90% of context tokens. |
| **Pure PHP**                   | Every LLM call is made with PHP's `cURL` extension. No Node.js, no webpack, no build step.                                                                            |
| **Bring your own key**         | Your API credentials are stored in `wp_options` and never leave your server other than the outbound HTTPS call to the LLM provider.                                   |
| **Token-optimized payloads**   | HTML is minified, CSS is compressed, conversation history is trimmed — every request is as lean as possible.                                                          |
| **Strictly HTML output**       | Every LLM response is passed through a sanitiser that strips `<script>` tags, `on*` event attributes, and `javascript:` URIs before any HTML is stored or displayed.  |

---

## Features

### AI Generation

- 🧩 **Section-based generation** — describe your site, pick sections, and generate a complete page in one shot
- ✏️ **Per-section refinement** — refine any section independently with natural-language instructions
- ➕ **Add new sections** — generate additional sections on an existing page at any time
- 🔄 **Drag-and-drop reordering** — reorder sections visually
- 🗑️ **Delete sections** — remove any section from the page

### Visual Builder

- 🖥️ **Live preview iframe** — see your changes instantly in a sandboxed preview panel
- 📱 **Responsive viewports** — toggle between Desktop (100%), Tablet (768 px) and Mobile (375 px)
- 🖱️ **Click-to-edit** — click any element to open the floating Element Editor
- ♻ **Recycle button** (2.3.1+) — swap any element for a Text or Image widget
- 🟢 **JS tab on buttons** (2.3.2+) — attach click handlers directly from the panel
- 🎨 **Style/Spacing/Classes/Link tabs** — full editing controls
- 💅 **Global CSS** — page-level CSS that survives regenerations
- 🩹 **Failed sections with Retry** — retry individual sections that failed

### Multi-LLM Support

- 🤖 **Claude**, **Gemini**, **OpenAI**, **Kimi**, **DeepSeek**

### Security & Performance

- 🔒 HTML sanitization (scripts, events, URIs stripped)
- 🔑 Nonce-protected AJAX
- 📦 Token-optimized payloads (hash placeholders, minification, history trimming)

---

## Architecture

```
User Action (browser)
    │
    ▼
jQuery AJAX (builder.js)
    │
    ▼
PHP AJAX Handler
    │
    ├─► Job Manager → WP-Cron tick (1 LLM call per tick)
    │
    ▼
LLM Router (Claude / Gemini / OpenAI / Kimi / DeepSeek)
    │
    ▼
HTML Sanitizer
    │
    ▼
Section Manager (wp_postmeta)
    │
    ▼
JSON Response → UI Update
```

### Persistent state (post meta)

- `_naano_sections` — section HTML array
- `_naano_global_css` — page-level CSS
- `_naano_failed_sections` — failed section list
- `_naano_lang`, `_naano_translation_of` — translation metadata

---

## Requirements

| Requirement         | Minimum Version                 |
| ------------------- | ------------------------------- |
| PHP                 | 8.1                             |
| WordPress           | 6.0                             |
| PHP extension: curl | any                             |
| PHP extension: json | any                             |
| PHP extension: gd   | any (for image resizing)        |
| PHP extension: dom  | any (for HTML fixing)           |
| Outbound HTTPS      | port 443 to LLM provider domain |

---

## Installation

### Method 1 — Upload ZIP

1. Download the latest release from [GitHub](https://github.com/Pispros/naano-wp-ai-website-builder/releases)
2. In WordPress Admin → **Plugins → Add New Plugin → Upload Plugin**
3. Choose the zip file and click **Install Now**
4. Click **Activate Plugin**

### Method 2 — Manual FTP

1. Upload the `naano-ai-website-builder` folder to `/wp-content/plugins/`
2. In WordPress Admin → **Plugins → Installed Plugins**
3. Find **Naano AI Website Builder** and click **Activate**

### Updating

After updating, **hard-reload** the builder page (`Cmd/Ctrl + Shift + R`) to fetch the new `builder.js`.

---

## Quick Start

1. **Activate** the plugin
2. Go to **Naano AI Builder → Settings**
3. Select your LLM **Provider**, paste your **API Key**, click **Test Connection**
4. Add **Custom Design Variables** (optional)
5. Click **Save Settings**
6. Go to **Naano AI Builder → AI Pages** → **Create New Page with AI**
7. Enter page name/description, select sections, click **Generate Full Website**
8. Click any element in the preview to open the **Element Editor**
9. Hover elements to see the **♻ recycle button** (2.3.1+)
10. Use **Global CSS** textarea for page-wide styles
11. Click **Save changes** to persist edits (draft)
12. Click **Publish** to deploy

### Server prerequisite for AI generation

On shared hosts where `wp-cron.php` is unreliable, add an OS-level cron that hits it every minute:

```bash
* * * * * wget -q -O - https://your-site.tld/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

---

## Configuration

### API Keys

| Provider           | Where to get your key                                            | Free tier                |
| ------------------ | ---------------------------------------------------------------- | ------------------------ |
| Claude (Anthropic) | [console.anthropic.com](https://console.anthropic.com/)          | No (pay-as-you-go)       |
| Gemini (Google)    | [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | Yes (generous free tier) |
| OpenAI             | [platform.openai.com](https://platform.openai.com/)              | Free credits on signup   |
| Kimi (Moonshot)    | [platform.moonshot.cn](https://platform.moonshot.cn/)            | Yes (limited)            |
| DeepSeek           | [platform.deepseek.com](https://platform.deepseek.com/)          | Yes (limited)            |

### Custom Variables

Inject keys like `primary_color`, `brand_name`, `font_family`, `tone`, `industry`, `target_audience` into every prompt for brand consistency.

---

## Token Optimization

| Strategy                                 | Typical Savings            |
| ---------------------------------------- | -------------------------- |
| Section placeholders (hash compression)  | ~70–90% on section updates |
| HTML minification                        | ~15–25%                    |
| Conversation trimming (last 3 exchanges) | Prevents runaway costs     |
| Image resizing to 1024 px JPEG 75%       | ~50–80% on vision tokens   |

---

## LLM Providers

| Provider | Endpoint                                                                  | Auth                    | Image Support       | Default Model              |
| -------- | ------------------------------------------------------------------------- | ----------------------- | ------------------- | -------------------------- |
| Claude   | `api.anthropic.com/v1/messages`                                           | `x-api-key` header      | Base64 inline       | `claude-sonnet-4-20250514` |
| Gemini   | `generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | URL query param `?key=` | `inlineData` base64 | `gemini-2.5-flash`         |
| OpenAI   | `api.openai.com/v1/chat/completions`                                      | `Authorization: Bearer` | `image_url` base64  | `gpt-5.5`                  |
| Kimi     | `api.moonshot.cn/v1/chat/completions`                                     | `Authorization: Bearer` | Via text note       | `kimi-k2-0711-preview`     |
| DeepSeek | `api.deepseek.com/v1/chat/completions`                                    | `Authorization: Bearer` | Via text note       | Latest available           |

---

## Export Options

| Option              | How                                                         |
| ------------------- | ----------------------------------------------------------- |
| **Preview**         | Click "Preview" in the toolbar                              |
| **Export HTML**     | Click "Export" in the toolbar (downloads `website.html`)    |
| **Copy HTML**       | Click "Copy" in the toolbar (copies full HTML to clipboard) |
| **Save changes**    | Orange button (persists draft only)                         |
| **Publish**         | Primary button (publishes to WordPress page)                |
| **Set as Homepage** | Tick checkbox in Publish modal                              |

---

## AJAX API Reference

All endpoints require a valid `naano_builder_nonce` in the `nonce` POST field.

| Action                                     | Method                | Key Parameters                                                                  |
| ------------------------------------------ | --------------------- | ------------------------------------------------------------------------------- |
| `naano_generate_site`                      | POST                  | `page_id`, `page_name`, `description`, `sections[]`, `imported_sections` (JSON) |
| `naano_update_section`                     | POST                  | `page_id`, `section_id`, `instruction`, `assets`, `redirects`                   |
| `naano_poll_job`                           | POST                  | `job_id`                                                                        |
| `naano_save_section_html`                  | POST                  | `page_id`, `sections`, `global_css`                                             |
| `naano_test_connection`                    | POST                  | `provider`, `api_key`, `model`                                                  |
| `naano_add_reference` / `remove_reference` | POST                  | `page_id`, `section_id`, `type`, `url`, `attachment_id`, `notes`                |
| `naano_delete_section`                     | POST                  | `page_id`, `section_id`                                                         |
| `naano_reorder_sections`                   | POST                  | `page_id`, `order[]`                                                            |
| `naano_export_html`                        | POST                  | `page_id`                                                                       |
| `naano_save_as_page`                       | POST                  | `page_id`, `title`, `html`                                                      |
| `naano_set_homepage`                       | POST                  | `page_id`                                                                       |
| `naano_duplicate_for_translation`          | POST (admin-post.php) | `page_id`, `lang`                                                               |

---

## File Structure

```
naano-ai-website-builder/
├── naano-ai-website-builder.php          # Main plugin entry point
├── assets/
│   ├── css/builder.css                   # Builder styles
│   └── js/
│       ├── builder.js                    # Builder UI, AJAX, inspector
│       └── preview.js                    # Preview modal
├── includes/
│   ├── interface-llm-provider.php
│   ├── class-llm-*.php                   # Claude, Gemini, OpenAI, Kimi, DeepSeek
│   ├── class-llm-router.php
│   ├── class-payload-compressor.php
│   ├── class-html-sanitizer.php
│   ├── class-prompt-builder.php
│   ├── class-section-manager.php
│   ├── class-ajax-handler.php
│   └── jobs/
│       ├── class-job-manager.php
│       └── class-job-runner.php
├── templates/
│   ├── admin-pages-list.php
│   ├── frontend-builder.php
│   └── settings-page.php
└── README.md
```

---

## Troubleshooting

| Issue                                       | Solution                                                                  |
| ------------------------------------------- | ------------------------------------------------------------------------- |
| The recycle button doesn't appear           | Hard-reload builder page (`Cmd/Ctrl + Shift + R`) or bump `NAANO_VERSION` |
| Generation is stuck on "queued"             | WP-Cron not firing — add OS-level cron or visit any front-end URL         |
| A section failed during generation          | Look for **Failed sections** drawer and click **Retry** per entry         |
| The API key test fails                      | Check provider's error message (region, key expiry, firewall)             |
| Published page looks different from preview | Theme may be injecting styles — Naano publishes raw HTML (no theme)       |

---

## Contributing

Pull requests are welcome! Please open an issue first for significant changes.

---

## License

GPL-2.0-or-later © Naano
