=== Naano AI Website Builder ===
Contributors: pispros
Tags: ai, website builder, claude, gemini, page builder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI-powered section-by-section WordPress website builder using Claude, Gemini, or Kimi. Pure PHP — no external backend needed.

== Description ==

**Naano AI Website Builder** generates complete, production-ready websites inside your WordPress dashboard using the AI model of your choice.

You bring your own API key (Claude, Gemini, or Kimi). There is no external service, no subscription, and no data leaves your server except the prompts sent to the LLM provider.

**Key highlights:**

* Section-by-section editing with token-optimized payloads (70–90% savings per edit)
* Multi-LLM support: Claude (Anthropic), Gemini (Google), Kimi (Moonshot)
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

== Changelog ==

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

= 1.0.0 =
Initial release — no upgrade steps required.
