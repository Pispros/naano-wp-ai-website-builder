# Naano AI Website Builder — Product Website Brief

> **Purpose:** Feed this document to any AI website generator to produce a marketing/product website for the Naano AI Website Builder WordPress plugin.

---

## Product Name

**Naano AI Website Builder**

## Tagline

Build stunning websites inside WordPress — powered by AI, controlled by you.

## One-Liner

An AI-powered, section-by-section WordPress website builder that uses Claude, Gemini, or Kimi to generate production-ready pages from a simple text description — pure PHP, no external backend, bring your own API key.

---

## What It Is

Naano AI Website Builder is a WordPress plugin that lets anyone generate complete, responsive, agency-quality websites directly from the WordPress dashboard. You describe what you want in plain English, pick the sections you need (header, hero, features, pricing, contact, footer, etc.), and the AI generates polished, scoped HTML + CSS for each one. Every section can then be individually refined with follow-up instructions, visual references, screenshots, and a built-in element inspector — all without leaving the builder.

There is no SaaS subscription, no external service, and no data stored outside your server. You bring your own API key from Anthropic (Claude), Google (Gemini), or Moonshot (Kimi), and the plugin calls the LLM directly via PHP cURL.

---

## Target Audience

- **Freelancers and small agencies** who need to ship client sites fast
- **WordPress site owners** who want professional-looking pages without hiring a developer
- **Developers** who want an AI-assisted starting point they can refine and customize
- **Non-technical users** who can describe what they want but can't code it themselves

---

## Key Features

### AI-Powered Generation
- Describe your site in plain text, pick sections, and generate a full page in one click
- Per-section refinement — update any section with natural-language instructions
- Add new sections to an existing page at any time
- AI prompt enhancer — let the AI improve your description or instruction before generating

### Visual Builder
- Live preview in a sandboxed iframe — see changes instantly
- Responsive viewport toggle — Desktop, Tablet (768px), Mobile (375px)
- Elementor-style element inspector — click any element to select it
- Visual style panel — edit typography, background, size, padding, margin, border, and border-radius without code
- Custom CSS tab — write freeform CSS scoped to the selected element
- Live apply — style changes update the preview in real time, no regeneration needed
- Drag-and-drop section reordering

### Multi-LLM Support
- **Claude** (Anthropic) — supports inline image vision for screenshot-based design
- **Gemini** (Google) — supports inline image vision, generous free tier
- **Kimi** (Moonshot AI) — text-based, great for content-heavy pages
- Configurable model override — use any model variant your API key supports
- Test Connection button — validate your key before generating

### References & Context
- **Screenshot references** — upload images from the WordPress media library as visual inspiration; auto-resized to 1024px JPEG and base64-encoded for the AI
- **URL references** — paste any website URL with notes; the page content is scraped (via Firecrawl or built-in fallback) and injected into the prompt so the AI can replicate design patterns
- **Page assets** — add numbered image/URL assets and reference them in instructions (e.g. "use asset #1 as the hero background")
- **URL redirections** — define named links (e.g. "CTA → /signup") so the AI uses your real site URLs
- **WordPress navigation menus** — select any registered WordPress menu to inject into header/footer sections with real links and labels

### Token-Optimized Architecture
- Section-by-section editing — only the section you're editing is sent in full
- All other sections are compressed to ~8-character hash placeholders — 70–90% token savings per edit
- HTML is minified, CSS compressed, conversation history is auto-trimmed
- Configurable refinement passes for both initial generation and per-section edits

### Import & Reuse
- Import header/footer from any previously built Naano page — no regeneration needed
- HTML is fetched server-side for security (never transported through the browser)

### Multilingual
- Add unlimited languages in Settings (code + label)
- Duplicate any page for translation — creates a child page at `/<slug>/<lang>/` with all sections pre-copied
- In-builder language switcher to jump between translation variants
- Language badges in the Pages List with direct links to each translation

### Web Scraping Integration (Firecrawl)
- Optional Firecrawl integration for high-quality URL reference scraping
- Cached results (90-day TTL) to avoid redundant API calls
- Automatic fallback to built-in scraper when Firecrawl is unavailable
- Test Connection button in Settings

### Export & Publishing
- Export assembled HTML as a downloadable file
- Copy full HTML to clipboard in one click
- Publish as a standalone WordPress page (raw HTML, no theme wrapping)
- Set any page as the WordPress homepage from the builder
- "Edit with Naano AI" link in the WordPress admin bar for quick access

### Security
- Every LLM response is sanitized — `<script>` tags, `on*` event handlers, and `javascript:` URIs are stripped
- All preview rendering happens inside a sandboxed iframe
- No data stored externally — API keys live in `wp_options`, sections in `wp_postmeta`
- WordPress nonce verification on every AJAX endpoint

---

## Technical Stack

| Component | Technology |
|-----------|-----------|
| Platform | WordPress 6.0+ |
| Language | PHP 8.1+ (pure PHP, no Node.js, no build step) |
| LLM Communication | PHP cURL → HTTPS to Anthropic / Google / Moonshot APIs |
| Frontend | Vanilla JavaScript + jQuery (bundled with WordPress) |
| CSS | Custom CSS, no frameworks |
| Storage | `wp_options` (settings), `wp_postmeta` (sections, references) |
| Caching | WordPress Transients API (for Firecrawl results) |

### Required PHP Extensions
`curl`, `json`, `gd`, `dom`, `mbstring`

---

## How It Works (User Flow)

1. **Install & Configure** — Upload the plugin, activate it, go to Settings, paste your API key (Claude, Gemini, or Kimi).
2. **Set Design Variables** — Optionally define brand colors, fonts, tone, industry, and target audience for consistent output.
3. **Create a Page** — Click "New Page", describe your site in plain text, select sections (Header, Hero, Features, About, Services, Pricing, Testimonials, Contact, Footer, or add custom ones).
4. **Optionally add context** — Select a WordPress navigation menu, add URL references with notes, upload screenshot references.
5. **Generate** — Click "Generate Full Website". The AI creates each section independently with scoped CSS, respecting your design variables.
6. **Refine** — Click any section in the live preview. Enter natural-language instructions ("make the hero darker", "add a CTA button", "change the pricing to 3 tiers"). Use the element inspector to visually tweak styles.
7. **Preview & Publish** — Toggle responsive viewports, export HTML, copy to clipboard, or publish as a WordPress page with one click.

---

## Design System (Built Into Every Generated Page)

The AI follows a strict design system baked into the system prompt:

- **Typography** — Font scale from 48-64px (hero) down to 14px (small), consistent weight hierarchy
- **Spacing** — 8px grid baseline, ≥80px vertical padding on desktop
- **Colors** — CSS custom properties, WCAG AA contrast compliance
- **Layout** — 1200px max-width container, responsive breakpoints at 640/768/1024/1280px
- **Components** — 44px min button height, card hover-lift effects, proper focus states
- **Accessibility** — Semantic HTML (header, nav, section, article, footer), alt text on images, form labels, button elements
- **Scoped CSS** — Every section has its own `<style>` block scoped to `.section-{id}`, preventing conflicts

---

## Why Naano AI

| Problem | Naano's Solution |
|---------|-----------------|
| Page builders are complex and slow | Describe what you want → get it instantly |
| AI tools send your data to external servers | Everything runs on your WordPress server — you own your data |
| AI-generated pages look generic | Screenshot + URL references, design variables, and refinement passes ensure brand-accurate output |
| LLM costs are unpredictable with SaaS tools | Bring your own API key — pay only for what you use, at provider rates |
| Generated code is bloated | Token-optimized architecture with section compression, HTML minification, and CSS scoping |
| One-shot generation can't be refined | Section-by-section editing with conversation history — refine any part without regenerating the whole page |
| AI doesn't know your brand | Custom design variables (colors, fonts, tone, audience) are injected into every prompt |

---

## Pricing & Licensing

- **Free plugin** — GPL-2.0-or-later
- **Bring your own API key** — pay your LLM provider directly at their published rates
- No subscription, no premium tier, no usage fees

---

## Website Design Guidelines

When generating a website for this product, consider:

- **Tone:** Professional, modern, developer-friendly but accessible to non-technical users
- **Color Palette:** Dark/navy primary background with electric blue or teal accents. Clean white text. Use subtle gradients.
- **Style:** Minimal, spacious, with code-editor aesthetics (dark panels, monospace accents for feature labels)
- **Hero Section:** Bold headline, animated code/AI generation visualization or mockup of the builder interface, clear CTA ("Get Started Free" / "Download on WordPress.org")
- **Features Section:** Grid or card layout showcasing key features with icons
- **How It Works:** 3-step visual flow (Configure → Describe → Generate)
- **LLM Providers:** Show logos/badges for Claude, Gemini, and Kimi with a brief note about each
- **Testimonials/Social Proof:** Placeholder for user quotes
- **FAQ Section:** Address common questions (cost, data privacy, supported LLMs, PHP requirements)
- **Footer:** Links, WordPress.org badge, GPL license note
- **Responsive:** Must look great from 375px to 1440px+
