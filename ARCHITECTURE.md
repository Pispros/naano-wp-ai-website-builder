# Naano AI Website Builder Plugin Architecture

> **Version**: 2.3.6  
> **License**: GPL-2.0-or-later  
> **Author**: Naano

---

## Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              WordPress Dashboard                                     │
│  ┌─────────────────────────────────────────────────────────────────────────────┐   │
│  │                    Naano AI Website Builder UI                              │   │
│  │  ┌───────────────────────────────────────────────────────────────────────┐   │   │
│  │  │  Toolbar (Preview, Export, Save, Publish)                            │   │   │
│  │  │  Left Drawer (Global CSS, References, Failed Sections)               │   │   │
│  │  │  Live Preview Iframe (Desktop/Tablet/Mobile viewports)               │   │   │
│  │  │  Floating Element Editor (top-right, Elementor-style)                │   │   │
│  │  └───────────────────────────────────────────────────────────────────────┘   │   │
│  │                                    ▲                                          │   │
│  │                                    │ postMessage                              │   │
│  └────────────────────────────────────┼──────────────────────────────────────────┘   │
│                                       │                                             │
│  ┌────────────────────────────────────▼──────────────────────────────────────────┐   │
│  │                  AJAX Handler (class-ajax-handler.php)                      │   │
│  │              wp_ajax_naano_* endpoints with nonce validation                │   │
│  └────────────────────────────────────┬──────────────────────────────────────────┘   │
└───────────────────────────────────────┼──────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                           Job Management Layer                                       │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                  Job Manager (class-job-manager.php)                         │   │
│  │  • Creates transient-based job state                                         │   │
│  │  • Schedules first WP-Cron tick                                              │   │
│  │  • Persists job cursor and intermediate state                               │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
│                                        ▲                                            │
│                                        │                                            │
│  ┌────────────────────────────────────▼──────────────────────────────────────────┐   │
│  │                  Job Runner (class-job-runner.php)                           │   │
│  │  • Runs ONE LLM call per WP-Cron tick                                        │   │
│  │  • Implements "skip-on-fail" policy for section recovery                     │   │
│  │  • Manages retry logic (max 2 attempts for update/enhance)                   │   │
│  │  • Persists state between ticks for LSAPI compliance                         │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         Payload Processing Layer                                     │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │             Payload Compressor (class-payload-compressor.php)                │   │
│  │  • Minifies HTML & CSS                                                       │   │
│  │  • Replaces unchanged sections with hash placeholders (~70-90% compression) │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
│                                        ▲                                            │
│                                        │                                            │
│  ┌────────────────────────────────────▼──────────────────────────────────────────┐   │
│  │               Prompt Builder (class-prompt-builder.php)                      │   │
│  │  • Injects design variables (primary_color, brand_name, etc.)                │   │
│  │  • Injects URL references and screenshot assets                              │   │
│  │  • Injects language configuration                                            │   │
│  │  • Injects section references                                                │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                           LLM Router Layer                                           │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                 LLM Router (class-llm-router.php)                            │   │
│  │  • Routes to appropriate provider adapter                                    │   │
│  │  • Runs HTML through sanitizer after response                                │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
│                                        ▲                                            │
│        ┌───────────────────────────────┼───────────────────────────────┐            │
│        ▼                               ▼                               ▼            │
│  ┌───────────────┐           ┌───────────────┐           ┌───────────────┐          │
│  │ Claude Adapter│           │  Gemini       │           │  OpenAI       │          │
│  │               │           │  Adapter      │           │  Adapter      │          │
│  │ class-llm-    │           │ class-llm-    │           │ class-llm-    │          │
│  │ claude.php    │           │ gemini.php    │           │ openai.php    │          │
│  │               │           │               │           │               │          │
│  │ Supports:     │           │ Supports:     │           │ GPT-5.x with  │          │
│  │ - Inline      │           │ - Inline      │           │ - reasoning_  │          │
│  │   base64      │           │   base64      │           │   effort:none │          │
│  │   images      │           │   images      │           │   for shared  │          │
│  │               │           │               │           │   hosts       │          │
│  └───────────────┘           └───────────────┘           └───────────────┘          │
│                                                                                      │
│  ┌───────────────┐           ┌───────────────┐                                        │
│  │  Kimi Adapter │           │ DeepSeek      │                                        │
│  │  Adapter      │           │  Adapter      │                                        │
│  │ class-llm-    │           │ class-llm-    │                                        │
│  │ kimi.php      │           │ deepseek.php  │                                        │
│  │               │           │               │                                        │
│  │ Uses:         │           │ Uses:         │                                        │
│  │ - Kimi-K3     │           │ - Text-based  │                                        │
│  │   temp=1      │           │ - Competitive │                                        │
│  │ - Kimi-K2.5   │           │   pricing     │                                        │
│  │   temp=0.6    │           │               │                                        │
│  └───────────────┘           └───────────────┘                                        │
│                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        Response Processing Layer                                     │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │            HTML Sanitizer (class-html-sanitizer.php)                         │   │
│  │  • Strips <script> tags                                                      │   │
│  │  • Removes on* event attributes                                              │   │
│  │  • Removes javascript: URIs                                                  │   │
│  │  • Fixes malformed HTML                                                      │   │
│  │  • Applies naano_sanitized_html filter                                       │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          Persistence Layer                                           │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │             Section Manager (class-section-manager.php)                      │   │
│  │  • Stores section HTML in wp_postmeta (_naano_sections)                      │   │
│  │  • Stores global CSS (_naano_global_css)                                     │   │
│  │  • Tracks failed sections (_naano_failed_sections)                           │   │
│  │  • Assembles final HTML for publish                                          │   │
│  │  • Manages section reordering                                                │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          Data Storage Layer                                          │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                      WordPress wp_postmeta table                             │   │
│  │                                                                                │   │
│  │  Meta Key              | Post Type    | Value                                │   │
│  │  ─────────────────────────────────────────────────────────────────────────   │   │
│  │  _naano_sections       | naano_page     Array of [id, type, html, order]     │   │
│  │  _naano_global_css     | naano_page     Raw CSS string                       │   │
│  │  _naano_failed_sections| naano_page     List of [section_id, type, reason]   │   │
│  │  _naano_lang           | naano_page     Language code                        │   │
│  │  _naano_translation_of | translations   Original page post ID                │   │
│  │  _naano_page_html      | naano_page     Assembled HTML (post publish)        │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                           UI Update Layer                                            │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                     JSON Response to UI (builder.js)                         │   │
│  │  • Live preview iframe refresh (srcdoc)                                      │   │
│  │    - Body margin reset: html, body { margin: 0; padding: 0 }                │   │
│  │    - Global CSS injection (since 2.3.4, pre-filled on first render)          │   │
│  │  • Element Editor updates via postMessage                                    │   │
│  │  • Toolbar state (Save/Publish buttons)                                      │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## Architecture by Layer

### 1. Presentation Layer (Frontend)

#### Main Files
- `assets/css/builder.css` - Full builder styles
- `assets/js/builder.js` - UI, AJAX, inspector, drag-drop
- `assets/js/preview.js` - Preview modal with responsive viewports
- `assets/images/naano-icon.svg` - Custom sidebar icon

#### Main Components
1. **Toolbar** (top)
   - Preview (desktop/tablet/mobile viewports)
   - Export HTML (download)
   - Copy HTML (copy to clipboard)
   - Save changes (orange button, draft mode)
   - Publish (primary button)

2. **Left Drawer** (left sidebar menu)
   - Global CSS textarea
   - URL References
   - Screenshot references (🖼️ button)
   - Failed sections list with Retry

3. **Live Preview Iframe**
   - Sandbox rendering
   - Inspect mode enabled by default
   - Click-to-edit on all elements

4. **Floating Element Editor**
   - Position: top-right of canvas
   - Style tab: typography, background, border, radius
   - Spacing tab: width/height/padding/margin
   - Classes tab: CSS class management
   - Link tab: href, target, rel (for `<a>` elements)
   - Custom CSS tab: scoped CSS
   - **JS tab** (since 2.3.2): click handlers for buttons

#### postMessage Events (Parent ↔ Iframe)

| Parent → Iframe | Iframe → Parent |
|-----------------|-----------------|
| `naano-inspect-mode` | `naano-element-selected` |
| `naano-update-section` | `naano-element-deselected` |
| `naano-highlight-section` | `naano-element-html-updated` |
| `naano-loading-section` | `naano-insert-custom-html-above` |
| `naano-apply-element-style` | `naano-element-recycle-click` (2.3.1) |
| `naano-apply-element-classes` |  |
| `naano-apply-element-link` |  |
| `naano-apply-element-image` |  |
| `naano-apply-element-js` (2.3.2) |  |
| `naano-delete-element` |  |
| `naano-replace-element-with-widget` (2.3.1) |  |
| `naano-deselect-element` |  |

---

### 2. AJAX Layer (class-ajax-handler.php)

#### WordPress Endpoints (wp_ajax_*)
| Action | Method | Parameters | Response |
|--------|--------|------------|----------|
| `naano_generate_site` | POST | `page_id`, `page_name`, `description`, `sections[]`, `imported_sections` | `{job_id}` |
| `naano_update_section` | POST | `page_id`, `section_id`, `instruction`, `assets`, `redirects` | `{job_id}` |
| `naano_poll_job` | POST | `job_id` | `{status, data?, partial?, log?}` |
| `naano_save_section_html` | POST | `page_id`, `sections`, `global_css` | `{saved_count, page_id, global_css_saved}` |
| `naano_get_failed_sections` | POST | `page_id` | `{page_id, failed_sections, global_css}` |
| `naano_test_connection` | POST | `provider`, `api_key`, `model` | `{success, model, latency_ms}` |
| `naano_add_reference` | POST | `page_id`, `section_id`, `type`, `url`, `attachment_id`, `notes` | `{references}` |
| `naano_remove_reference` | POST | `page_id`, `section_id`, `index` | `{references}` |
| `naano_delete_section` | POST | `page_id`, `section_id` | `{}` |
| `naano_reorder_sections` | POST | `page_id`, `order[]` | `{}` |
| `naano_export_html` | POST | `page_id` | `{html}` |
| `naano_save_as_page` | POST | `page_id`, `title`, `html` | `{page_id, edit_url, view_url, title}` |
| `naano_set_homepage` | POST | `page_id` | `{}` |
| `naano_duplicate_for_translation` | POST (admin-post.php) | `page_id`, `lang` | Redirect to builder |

**All endpoints require a valid `naano_builder_nonce`.**

---

### 3. Job Management Layer

#### class-job-manager.php
- Creates transient-based job state
- Schedules first WP-Cron tick
- Persists job cursor and intermediate state
- Manages job logs

#### class-job-runner.php
- Executes **ONE LLM call per WP-Cron tick**
- Implements "skip-on-fail" policy for section recovery
- Manages retry logic (max 2 attempts for `update_section`/`enhance_prompt`)
- LSAPI compliance (max 60-300s)
- Persists state between ticks

**Job Workflow:**
```
generate_site:
  1. Initial generation (1 LLM call per section)
  2. Refinement passes (1 LLM call per section)
  3. Persist and next tick

update_section:
  1. Enhance prompt (retry max 2)
  2. LLM call (retry max 2)
  3. Persist and next tick
```

---

### 4. Payload Processing Layer

#### class-payload-compressor.php
- HTML & CSS minification (~15-25% compression)
- Replaces unchanged sections with hash placeholders (~70-90% compression)
- Conversation message optimization

#### class-prompt-builder.php
- Injects design variables:
  - `primary_color`, `secondary_color`
  - `brand_name`, `font_family`
  - `tone`, `industry`, `target_audience`
  - Any custom key/value pairs
- Injects URL references
- Injects screenshots (base64 after resize to 1024px JPEG 75%)
- Injects language configuration

---

### 5. LLM Router Layer

#### Interface: class-llm-router.php
- Routes to appropriate provider adapter
- Runs HTML through sanitizer after response
- Implements `Naano_LLM_Provider_Interface`

#### Adapters (class-llm-*.php)
| Provider | Adapter | Endpoint | Auth | Features |
|----------|---------|----------|------|----------|
| Claude | `class-llm-claude.php` | `api.anthropic.com` | `x-api-key` | Inline base64 images |
| Gemini | `class-llm-gemini.php` | `generativelanguage.googleapis.com` | `key` | Inline base64 images |
| OpenAI | `class-llm-openai.php` | `api.openai.com` | `Authorization: Bearer` | GPT-5.x with `reasoning_effort: none` |
| Kimi | `class-llm-kimi.php` | `api.moonshot.cn` | `Authorization: Bearer` | Kimi-K3: temp=1, Kimi-K2.5: temp=0.6 |
| DeepSeek | `class-llm-deepseek.php` | `api.deepseek.com/v1/chat/completions` | `Authorization: Bearer` | Text-based |

**All adapters use PHP cURL and implement `Naano_LLM_Provider_Interface`.**

---

### 6. HTML Validation Layer

#### class-html-sanitizer.php
- Strips `<script>` tags
- Removes `on*` event attributes
- Removes `javascript:` URIs
- Fixes malformed HTML
- Applies `naano_sanitized_html` filter

---

### 7. Persistence Layer

#### class-section-manager.php
- Stores sections in `wp_postmeta`:
  - `_naano_sections`: Array `[id, type, html, order]`
  - `_naano_global_css`: Raw CSS string
  - `_naano_failed_sections`: List `[section_id, type, reason]`
  - `_naano_lang`: Language code
  - `_naano_translation_of`: Original page post ID
  - `_naano_page_html`: Assembled HTML (publish)
- Manages section reordering
- Assembles final HTML for publishing

---

### 8. Reference Management

#### class-reference-manager.php
- Image uploads from WordPress media library
- Resize to 1024px JPEG 75% via GD
- Base64 encoding for LLM messages
- URL references management

---

### 9. Conversation Management

#### class-conversation.php
- Persists last 3 exchanges for context
- Automatic trimming of older messages
- Prevents runaway costs

---

### 10. Admin Interface

#### class-admin-page.php
- WordPress menus (Naano AI Builder → Settings / AI Pages)
- Settings page (LLM provider, API key, model, design variables)
- Pages list page (stats, status, actions)
- Asset enqueue (JS, CSS)
- Translations (`.po`/`.mo`/`.pot`)

---

## Detailed Data Flows

### Site Generation Flow

```
User clicks "Generate Full Website"
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ AJAX: naano_generate_site                               │
│  - page_id                                              │
│  - page_name                                            │
│  - description                                          │
│  - sections[] (selected sections)                       │
│  - imported_sections (JSON)                             │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Job Manager creates transient + schedules first tick    │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ WP-Cron tick: Job Runner                                │
│                                                         │
│ 1. Payload Compressor:                                  │
│    - Minify HTML/CSS                                    │
│    - Replace unchanged sections with hashes            │
│                                                         │
│ 2. Prompt Builder:                                      │
│    - Inject design variables                            │
│    - Inject URL references                              │
│    - Inject screenshots (base64)                        │
│    - Inject language config                             │
│                                                         │
│ 3. LLM Router:                                          │
│    - Route to provider adapter                          │
│                                                         │
│ 4. Provider Adapter:                                    │
│    - Build prompt (system + user)                       │
│    - Call LLM API via cURL                              │
│                                                         │
│ 5. HTML Sanitizer:                                      │
│    - Strip scripts, events, javascript:                 │
│    - Fix malformed DOM                                  │
│                                                         │
│ 6. Section Manager:                                     │
│    - Store section HTML in wp_postmeta                  │
│    - Update job cursor                                  │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ AJAX Response: {job_id}                                 │
│ UI starts polling naano_poll_job                        │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Polling loop (until job complete/failed)                │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Job complete → UI updates with sections                 │
│ Job failed → Failed sections drawer shows errors        │
└─────────────────────────────────────────────────────────┘
```

### Section Editing Flow

```
User clicks element in preview
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Iframe → postMessage: naano-element-selected            │
│  - elId, sectionId, tagName, breadcrumb                 │
│  - computed styles, classes                             │
│  - isButton, customJs (2.3.2)                           │
│  - linkInfo, imageInfo                                  │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Builder: Floating Element Editor slides up              │
│  - Style tab (color, size, weight, align)              │
│  - Spacing tab (width, height, padding, margin)        │
│  - Classes tab (user classes, AI preserved)            │
│  - Link tab (href, target, rel, anchor picker)         │
│  - Custom CSS tab (scoped to element)                  │
│  - JS tab (2.3.2, button only)                         │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ User edits → Apply (or Done)                            │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ postMessage: naano-apply-element-* (depending on tab)  │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Iframe: Real-time style application                     │
│  - Inline styles                                       │
│  - Scoped <style> blocks                               │
│  - Class updates                                       │
│  - Link attribute updates                              │
│  - Button JS binder regenerate (2.3.2)                 │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Toolbar: Save changes button appears (orange)           │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ User clicks "Save changes"                              │
│  AJAX: naano_save_section_html                          │
│  - page_id                                              │
│  - sections[]                                           │
│  - global_css (optional)                                │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Section Manager persists to wp_postmeta                 │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ JSON Response → UI (draft saved)                        │
└─────────────────────────────────────────────────────────┘
```

### Publishing Flow

```
User clicks "Publish" in toolbar
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Modal: Set as Homepage? (checkbox)                      │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ AJAX: naano_save_as_page                                │
│  - page_id                                              │
│  - title                                                │
│  - html (optional, server prefers DB-assembled)         │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Server: Assembles final HTML                            │
│  - Body margin reset: html, body { margin: 0; padding: 0 } │
│  - Global CSS from wp_postmeta                          │
│  - All sections in order                                │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ WordPress: Create/Update page as raw HTML               │
│  - post_type: page                                      │
│  - post_content: raw assembled HTML                     │
│  - No theme wrapping                                    │
│  - show_on_front/page_on_front (if homepage)            │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Response: {page_id, edit_url, view_url, title}         │
└─────────────────────────────────────────────────────────┘
```

---

## Security

| Feature | Description |
|---------|-------------|
| **HTML sanitization** | Scripts, events, javascript: URIs stripped |
| **Nonce protection** | `naano_builder_nonce` on all AJAX endpoints |
| **API key storage** | `wp_options`, never exposed to browser |
| **Input validation** | Server-side validation of all inputs |
| **Escaping** | Auto-HTML-escaping for data attributes |

---

## Performance

| Optimization | Impact |
|--------------|--------|
| Section placeholders (hash compression) | ~70-90% on section updates |
| HTML minification | ~15-25% |
| Inline CSS minification | ~10-20% |
| Conversation trimming (last 3 exchanges) | Prevents runaway costs |
| Image resizing to 1024px JPEG 75% | ~50-80% on vision tokens |
| Single-section LLM responses | ~60-80% on response size |
| WP-Cron tick-based execution | LSAPI compliance (60-300s) |

---

## Extensions and Customization

### Hooks (Actions)
```php
naano_before_generate        // Before LLM call (page_id, sections[])
naano_after_generate         // After sections stored (page_id, parsed_sections[])
naano_before_update_section  // Before section update (page_id, section_id)
naano_after_update_section   // After section updated (page_id, section_id, html)
```

### Filters
```php
naano_system_prompt          // Modify system prompt before sending
naano_user_message           // Modify user message before sending
naano_sanitized_html         // Modify cleaned HTML after sanitization
```

---

## Deployment

### Minimum Requirements
| Component | Version |
|-----------|---------|
| PHP | 8.1 |
| WordPress | 6.0 |
| PHP Extensions | curl, json, gd, dom, mbstring |
| Outbound HTTPS | Port 443 to LLM provider domains |

### Installation (ZIP)
1. Download release from [GitHub](https://github.com/Pispros/naano-wp-ai-website-builder/releases)
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose zip file → Install Now → Activate

### Installation (Manual FTP)
1. Upload `naano-ai-website-builder/` folder to `/wp-content/plugins/`
2. WordPress Admin → Plugins → Activate

### Updating
1. Deactivate previous version
2. Install new zip
3. **Hard-reload** builder page (`Cmd/Ctrl + Shift + R`) to fetch new `builder.js`

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Recycle button not visible | Hard-reload (`Cmd/Ctrl + Shift + R`) or bump `NAANO_VERSION` |
| Generation stuck on "queued" | WP-Cron not firing → add OS-level cron or visit frontend |
| Section failed during generation | Failed sections drawer → Retry button |
| API key test fails | Check provider error (region, key expiry, firewall) |
| Published page looks different from preview | Theme injects styles → Naano publishes raw HTML (no theme) |

---

## Contributors

Pull requests are welcome. Please open an issue first for major changes.

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes
4. Push and open a pull request

---

**Version**: 2.3.6  
**License**: GPL-2.0-or-later  
**Documentation**: https://github.com/Pispros/naano-wp-ai-website-builder
