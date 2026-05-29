=== Naano AI Website Builder ===
Contributors: pispros
Tags: ai, website builder, claude, gemini, page builder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag:  2.3.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI-powered section-by-section WordPress website builder using Claude, Gemini, OpenAI, or Kimi. Pure PHP — no external backend needed.

== Description ==

**Naano AI Website Builder** generates complete, production-ready websites inside your WordPress dashboard using the AI model of your choice.

You bring your own API key (Claude, Gemini, OpenAI, or Kimi). The plugin does not run any Naano-hosted service: prompts go directly from your server to the LLM provider you selected. See the **External services** section below for the full list of endpoints, what is sent, and provider terms / privacy policies.

**Key highlights:**

* Section-by-section editing with token-optimized payloads (70–90% savings per edit)
* Multi-LLM support: Claude (Anthropic), Gemini (Google), OpenAI, Kimi (Moonshot)
* Screenshot and URL references per section for visual inspiration
* Custom design variables (colors, fonts, brand name, tone)
* Automatic HTML sanitization — scripts and event handlers stripped from every response
* Responsive preview (desktop / tablet / mobile)
* Export as HTML, copy to clipboard, or save as a WordPress draft page
* Pure PHP with cURL — no Node.js, no build step, no webpack

**How it works:**

1. Go to **Naano AI Builder → Settings** and enter your API key.
2. Add optional design variables (brand colors, fonts, tone, etc.).
3. Go to **New Page**, describe your site, select sections, and click **Generate**.
4. Use the section cards to refine individual sections with natural-language instructions.
5. Preview, export, or publish your site.

== Installation ==

1. Upload the `naano-ai-website-builder` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Naano AI Builder → Settings** to configure your API key.

Or use the built-in plugin installer:

1. Go to **Plugins → Add New Plugin → Upload Plugin**.
2. Upload the plugin zip file.
3. Click **Activate Plugin**.

== Frequently Asked Questions ==

= Which LLM providers are supported? =

Claude (Anthropic), Gemini (Google), and Kimi (Moonshot AI). All three use HTTPS cURL calls directly from PHP — no proxy or backend needed.

= Do I need my own API key? =

Yes. The plugin does not include any API keys. You must create an account with your chosen LLM provider and paste your key into the Settings page.

= Is the generated HTML safe to use? =

Every LLM response is passed through `Naano_HTML_Sanitizer::clean()` which strips `<script>` tags, `on*` event attributes, and `javascript:` URIs before the HTML is stored. The output is then stored in `wp_postmeta` and displayed inside a sandboxed iframe in the admin UI.

= How does token optimization work? =

When you edit a single section, only that section's full HTML is included in the payload. All other sections are replaced with 8-character md5 hash placeholders (e.g. `<!-- section:hero type=hero hash=a1b2c3d4 -->`). This reduces payload size by 70–90% compared to sending the entire page.

= Can I use screenshot references? =

Yes. Click the 🖼️ button on any section card to open the WordPress media library. The selected image is resized to a maximum of 1024 × 1024 px and JPEG-encoded at 75% quality before being base64-encoded and sent to the LLM (Claude / Gemini support inline images; Kimi receives a text note).

= Can I rename or rebrand the plugin? =

Yes — see RENAMING.md in the plugin folder for a full guide with automated `sed` / PowerShell commands and a database migration snippet.

= What PHP extensions are required? =

curl, json, gd, dom, and mbstring. The plugin checks for these on activation and shows an admin notice if any are missing.

== Screenshots ==

1. Settings page — configure your API key, provider, and design variables.
2. Generation form — describe your site and select sections.
3. Builder view — section cards with drag-to-reorder.
4. Edit panel — instruction field with screenshot and URL references.
5. Preview modal — responsive desktop / tablet / mobile preview.

== External services ==

This plugin relies on third-party AI services to generate website HTML. It is "bring your own API key" — no calls are made until **you** paste a key into Settings and choose a provider, and your data is sent **directly** from your WordPress server to the provider you selected (no Naano-hosted proxy).

In addition, an optional Firecrawl integration can be enabled to fetch reference URLs you supply in the builder.

For each service below we list: what the service is, what data is sent, when it is sent, and links to that service's terms and privacy policy.

= Anthropic Claude (default LLM provider) =

* What it is and what it is used for: Anthropic's hosted Claude language model. Used to generate the HTML for each website section and, optionally, to rewrite a section based on a user instruction.
* What data is sent: your Anthropic API key (as an HTTP header), the generation prompt assembled by the plugin, the natural-language site description you typed, the list of section types to generate, any custom design variables you saved in Settings (brand name, colors, fonts, tone), the current HTML of the section being edited when you edit one, the URL/notes of any references you attached, and — if you attached a screenshot reference — that image, downscaled to a maximum of 1024 × 1024 px and JPEG-encoded at 75 % quality, base64-encoded inside the request body.
* When it is sent: only when you click **Generate**, **Update section**, **Enhance prompt**, or **Test connection** in the builder UI; and only if Claude is the currently-selected provider.
* Endpoint: https://api.anthropic.com/v1/messages
* Provider: Anthropic, PBC.
* Terms of Service: https://www.anthropic.com/legal/consumer-terms
* Privacy Policy: https://www.anthropic.com/legal/privacy

= Google Gemini (alternate LLM provider) =

* What it is and what it is used for: Google's hosted Gemini language model. Used to generate the HTML for each website section and, optionally, to rewrite a section based on a user instruction.
* What data is sent: your Google API key (as a URL query parameter), the generation prompt, the natural-language site description, the list of section types, any custom design variables you saved in Settings, the current HTML of the section being edited when you edit one, the URL/notes of any references you attached, and — if you attached a screenshot reference — that image, downscaled to a maximum of 1024 × 1024 px and JPEG-encoded at 75 % quality, base64-encoded inside the request body.
* When it is sent: only when you click **Generate**, **Update section**, **Enhance prompt**, or **Test connection** in the builder UI; and only if Gemini is the currently-selected provider.
* Endpoint: https://generativelanguage.googleapis.com/v1beta/models/
* Provider: Google LLC.
* Terms of Service: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy
* Additional Gemini API terms: https://ai.google.dev/gemini-api/terms

= Moonshot AI Kimi (alternate LLM provider) =

* What it is and what it is used for: Moonshot AI's hosted Kimi language model. Used to generate the HTML for each website section and, optionally, to rewrite a section based on a user instruction.
* What data is sent: your Moonshot API key (as an HTTP Authorization header), the generation prompt, the natural-language site description, the list of section types, any custom design variables you saved in Settings, the current HTML of the section being edited when you edit one, the URL/notes of any references you attached. Screenshot references are sent to Kimi as a text note (the image itself is not uploaded — Kimi does not accept inline images on this endpoint).
* When it is sent: only when you click **Generate**, **Update section**, **Enhance prompt**, or **Test connection** in the builder UI; and only if Kimi is the currently-selected provider.
* Endpoint: https://api.moonshot.cn/v1/chat/completions
* Provider: Moonshot AI (Beijing).
* Terms of Service: https://platform.moonshot.cn/docs/agreement/serviceAgreement
* Privacy Policy: https://platform.moonshot.cn/docs/agreement/privacyPolicy

= OpenAI (alternate LLM provider) =

* What it is and what it is used for: OpenAI's hosted GPT models. Used to generate the HTML for each website section and, optionally, to rewrite a section based on a user instruction.
* What data is sent: your OpenAI API key (as an HTTP Authorization header), the generation prompt, the natural-language site description, the list of section types, any custom design variables you saved in Settings, the current HTML of the section being edited when you edit one, the URL/notes of any references you attached, and — if you attached a screenshot reference — that image, downscaled to a maximum of 1024 × 1024 px and JPEG-encoded at 75 % quality, base64-encoded inside the request body.
* When it is sent: only when you click **Generate**, **Update section**, **Enhance prompt**, or **Test connection** in the builder UI; and only if OpenAI is the currently-selected provider.
* Endpoint: https://api.openai.com/v1/chat/completions
* Provider: OpenAI, L.L.C.
* Terms of Service: https://openai.com/policies/row-terms-of-use/
* Privacy Policy: https://openai.com/policies/row-privacy-policy/

= Firecrawl (optional URL scraper) =

* What it is and what it is used for: Firecrawl is a hosted web-scraping API. The plugin optionally calls it to fetch the readable content of any reference URL you paste in the builder, so the chosen LLM provider can use that page as design / copy inspiration.
* What data is sent: your Firecrawl API key (as an HTTP Authorization header) and the reference URL you typed. No WordPress content, user data, or visitor data is sent.
* When it is sent: only when (a) you paste a reference URL in the builder and (b) you have saved a Firecrawl API key in Settings → Firecrawl. If you leave the Firecrawl key blank, the plugin never contacts Firecrawl. Results are cached locally in a WordPress transient for 90 days so the same URL is not scraped more than once during that window.
* Endpoint: https://api.firecrawl.dev/v2/scrape
* Provider: Firecrawl, Inc.
* Terms of Service: https://www.firecrawl.dev/terms-of-service
* Privacy Policy: https://www.firecrawl.dev/privacy-policy

No data is sent to any of these services without an explicit user action (clicking a builder button while a corresponding API key is configured). The plugin does not phone home, does not collect telemetry, and does not contact any Naano- or developer-controlled server.

== Changelog ==

= 2.1.3 =
* Documented all external services (Claude, Gemini, OpenAI, Kimi, Firecrawl) in the readme.
* Moved every inline `<style>` / `<script>` block in admin screens to enqueued CSS/JS files (or `wp_add_inline_style`).
* Removed runtime `ini_set()` calls for `max_execution_time`, `max_input_time`, and `default_socket_timeout`. Only the function-scoped `set_time_limit(0)` remains on the LLM request path.
* Removed the redundant `load_plugin_textdomain()` call (WP 4.6+ auto-loads plugin translations).
* Added explicit nonce + capability verification inside the `register_setting()` sanitize callbacks for `naano_variables` and `naano_languages`.
* Section-save and "save as page" AJAX handlers now run all user-submitted HTML through `Naano_HTML_Sanitizer::clean()` (previously only `<script>` was stripped in the fallback path).
* The page-level Global CSS is now passed through `wp_strip_all_tags()` before storage.
* Updated the Plugin URI to point to the wordpress.org plugin page (the previous GitHub URL returned 404).

= 1.0.0 =
* Initial release.
* Support for Claude, Gemini, and Kimi providers.
* Section-based generation and editing.
* Token-optimized compressed payloads.
* Screenshot and URL references per section.
* Custom design variables.
* HTML sanitization.
* Responsive preview modal.
* Export as HTML, copy to clipboard, save as WP page.
* Conversation history with automatic trimming.

== Upgrade Notice ==

= 2.1.3 =
Compliance fixes for the WordPress.org plugin review (asset enqueueing, external-service disclosure, sanitization). Recommended for everyone.

= 1.0.0 =
Initial release — no upgrade steps required.
