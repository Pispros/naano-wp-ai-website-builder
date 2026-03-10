<?php
/**
 * Prompt Builder – assembles system and user prompts for the LLM.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds structured prompts with variable injection and reference context.
 */
class Naano_Prompt_Builder {

	/** @var array<string,string> Custom design variables. */
	private array $variables = [];

	/** @var array URL references. */
	private array $reference_links = [];

	/** @var array<array{url:string,desc:string}> Page-level assets. */
	private array $assets = [];

	/** @var array<array{label:string,url:string}> Page-level URL redirections. */
	private array $redirects = [];

	/** @var array<array{title:string,url:string}> Other pages in this site. */
	private array $site_pages = [];

	/** @var string Base system prompt template. */
	private string $base_system_prompt = <<<'PROMPT'
You are a world-class UI/UX designer and senior front-end engineer.

Your HTML is used directly in production by professional developers.
The code must be clean, scalable, responsive, and visually equivalent to
a high-end agency website.

════════════════════════════════
CRITICAL OUTPUT RULES
════════════════════════════════

Return ONLY valid HTML.

DO NOT output:
- explanations
- markdown
- comments outside required markers
- text outside HTML

Each section MUST follow this structure:

<!-- BEGIN:SECTION_ID -->
<section class="section-SECTION_ID">
<style>
/* scoped styles */
.section-SECTION_ID { }
</style>

<!-- section markup -->

</section>
<!-- END:SECTION_ID -->

Rules:
1. CSS MUST exist in ONE `<style>` tag at the top of the section.
2. CSS must be scoped using `.section-SECTION_ID`.
3. NO JavaScript.
4. NO external CSS frameworks.
5. No inline styles anywhere.
6. HTML must validate without errors.

If any rule cannot be satisfied, return nothing.

════════════════════════════════
HTML STRUCTURE STANDARDS
════════════════════════════════

Use semantic HTML:

header
section
nav
main
article
figure
footer
button
ul/li
form/label/input

Accessibility is mandatory:

- All images require alt text
- Buttons must be `<button>` not `<div>`
- Forms must use `<label for="">`
- Interactive elements must have visible focus states

════════════════════════════════
DESIGN SYSTEM (REQUIRED)
════════════════════════════════

Each section must internally define a minimal design token system:

:root-like variables scoped to the section.

Example:

--color-primary
--color-secondary
--color-bg
--color-surface
--color-text
--color-muted
--radius
--shadow-sm
--shadow-md
--container

Use these tokens consistently.

════════════════════════════════
TYPOGRAPHY
════════════════════════════════

Font stack:

system-ui, -apple-system, Segoe UI, Roboto, sans-serif

Scale:

Hero title: 48–64px  
Section title: 30–36px  
Subtitle: 20–24px  
Body: 16–18px  
Caption: 12–14px  

Rules:

line-height:
- headings: 1.15–1.25
- body: 1.6–1.75

max text width:
65ch

Large headings must use:
letter-spacing: -0.02em

════════════════════════════════
LAYOUT SYSTEM
════════════════════════════════

Use a centered container pattern.

.container {
max-width:1200px;
margin:auto;
padding:0 24px;
}

Spacing rhythm:
multiples of 8px.

Section padding:
desktop: 96px
mobile: 56px

Use ONLY:

flexbox
grid

NEVER use:
floats
tables
absolute positioning for layout

════════════════════════════════
COMPONENT QUALITY
════════════════════════════════

Buttons:

height ≥ 44px  
padding: 12px 20px  
border-radius: 8px  
transition: all .2s ease

Primary button:
solid background

Secondary button:
outline

Cards:

border-radius: 12px  
shadow  
hover lift:

transform: translateY(-4px)

Images:

object-fit: cover  
responsive containers

════════════════════════════════
RESPONSIVE SYSTEM
════════════════════════════════

Mobile-first.

Breakpoints:

640px
768px
1024px
1280px

Rules:

mobile:
single column

tablet:
2 columns

desktop:
multi-column

Layout must remain visually correct from 320px → 1920px.

════════════════════════════════
VISUAL POLISH
════════════════════════════════

Encourage:

soft gradients  
glass blur navigation  
subtle shadows  
hover transitions  

Avoid:

flat generic UI
large walls of centered text
unstyled links
harsh black (#000)

Use instead:
#0f0f0f or #111827

════════════════════════════════
ANIMATION RULES
════════════════════════════════

Max 1 animation per section.

Allowed:

fade-in
subtle transform
hover transitions

Example:

opacity 0 → 1
translateY(12px → 0)

Duration:
.4s–.6s

════════════════════════════════
ANTI-PATTERNS (NEVER)
════════════════════════════════

Do NOT produce:

Lorem ipsum
unstyled anchors
missing alt attributes
fixed widths that break mobile
inline CSS
generic grey placeholder images

If images are needed,
use gradient placeholders or SVG patterns.

════════════════════════════════
QUALITY EXPECTATION
════════════════════════════════

The output must resemble a modern SaaS landing page built by a premium design agency.

Visual references:

Stripe
Linear
Vercel
Apple marketing pages

════════════════════════════════

{variables_block}

{references_block}
PROMPT;

	/**
	 * Set custom design variables.
	 *
	 * @param array<string,string> $variables Key/value design variables.
	 * @return static Fluent interface.
	 */
	public function set_variables( array $variables ): static {
		$this->variables = $variables;
		return $this;
	}

	/**
	 * Set URL references to include in the prompt.
	 *
	 * @param array $references Array of reference data.
	 * @return static Fluent interface.
	 */
	public function set_references( array $references ): static {
		$this->reference_links = $references;
		return $this;
	}

	/**
	 * Build the full system prompt, replacing placeholders.
	 *
	 * @return string Assembled system prompt.
	 */
	public function build_system_prompt(): string {
		$vars_block = $this->build_variables_block();
		$refs_block = $this->build_references_block();

		return str_replace(
			[ '{variables_block}', '{references_block}' ],
			[ $vars_block, $refs_block ],
			$this->base_system_prompt
		);
	}

	/**
	 * Set page-level assets for the prompt.
	 *
	 * @param array<array{url:string,desc:string}> $assets
	 * @return static
	 */
	public function set_assets( array $assets ): static {
		$this->assets = $assets;
		return $this;
	}

	/**
	 * Set page-level URL redirections for the prompt.
	 *
	 * @param array<array{label:string,url:string}> $redirects
	 * @return static
	 */
	public function set_redirects( array $redirects ): static {
		$this->redirects = $redirects;
		return $this;
	}

	/**
	 * Set other site pages so the LLM can generate correct nav links.
	 *
	 * @param array<array{title:string,url:string}> $pages
	 * @return static
	 */
	public function set_site_pages( array $pages ): static {
		$this->site_pages = $pages;
		return $this;
	}

	/**
	 * Build the user message for generating a complete new site.
	 *
	 * @param string   $description   Site description from user.
	 * @param string[] $section_types Ordered list of section type names.
	 * @return string User message content.
	 */
	public function build_initial_message( string $description, array $section_types ): string {
		$section_list = implode( ', ', $section_types );
		$refs_block   = $this->build_references_block();

		$pages_block = '';
		if ( ! empty( $this->site_pages ) ) {
			$lines = [ "\nSITE PAGES (use these exact URLs for navigation links):" ];
			foreach ( $this->site_pages as $p ) {
				$lines[] = '- ' . $p['title'] . ': ' . $p['url'];
			}
			$pages_block = implode( "\n", $lines ) . "\n";
		}

		$refs_section = $refs_block ? "\n\n{$refs_block}" : '';

		return <<<MSG
Create a complete, visually stunning, agency-quality website based on the following brief.

BRIEF:
{$description}
{$pages_block}
SECTIONS TO CREATE (in order, each wrapped in BEGIN/END markers):
{$section_list}

For each section, the SECTION_ID is the section type name in lowercase with hyphens (e.g. "hero", "about-us", "contact").
Produce ALL sections in a single response, one after the other.

QUALITY CHECKLIST — before finalising each section confirm:
✓ Typography follows the scale defined in the system prompt
✓ Spacing uses the 8px grid — no arbitrary px values
✓ Colors match the design variables and have sufficient contrast
✓ Every interactive element has a hover/focus state
✓ Layout is fully responsive from 375px to 1280px+
✓ No placeholder "Lorem ipsum" text
✓ No missing alt attributes
✓ CSS is scoped to the section root class{$refs_section}
MSG;
	}

	/**
	 * Build the user message for editing / updating a single section.
	 *
	 * @param string $section_id         Section to edit.
	 * @param string $instruction        User's editing instruction.
	 * @param string $compressed_context Full compressed site context.
	 * @return string User message content.
	 */
	public function build_section_message( string $section_id, string $instruction, string $compressed_context ): string {
		$assets_block    = $this->build_assets_block();
		$redirects_block = $this->build_redirects_block();
		$refs_block      = $this->build_references_block();

		$extra_parts = array_filter( [ $assets_block, $redirects_block, $refs_block ] );
		$context_extras = implode( "\n\n", $extra_parts );

		$extras_section = $context_extras ? "\n\n" . $context_extras : '';

		return <<<MSG
CURRENT SITE CONTEXT (compressed — other sections shown as placeholders to preserve context):
{$compressed_context}{$extras_section}

TASK:
Redesign/update the section with ID "{$section_id}" following this instruction precisely:
{$instruction}

QUALITY CHECKLIST — your output must satisfy ALL of these:
✓ Typography: use the defined scale — no default browser font sizes
✓ Spacing: 8px grid baseline, generous whitespace (≥80px vertical padding on desktop)
✓ Colors: brand variables applied, WCAG AA contrast maintained
✓ Hover/focus states on every interactive element
✓ Fully responsive: tested mentally from 375px → 1280px+
✓ CSS scoped to the section root class — no global selectors
✓ No placeholder text, no missing alt attributes, no JavaScript
✓ The updated section must integrate visually with the rest of the page context

Output ONLY the updated section:
<!-- BEGIN:{$section_id} -->
…complete updated HTML here…
<!-- END:{$section_id} -->

Do NOT output any other sections or any text outside the markers.
MSG;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the variables block for the system prompt.
	 *
	 * @return string Formatted variables block, or empty string.
	 */
	private function build_variables_block(): string {
		if ( empty( $this->variables ) ) {
			return '';
		}

		$lines = [ 'DESIGN VARIABLES (use these consistently throughout the site):' ];
		foreach ( $this->variables as $key => $value ) {
			$lines[] = "- {$key}: {$value}";
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the assets block for the prompt.
	 *
	 * @return string Formatted assets block, or empty string.
	 */
	private function build_assets_block(): string {
		if ( empty( $this->assets ) ) {
			return '';
		}

		$lines = [ 'PAGE ASSETS (use these URLs when instructed — reference by number):' ];
		foreach ( $this->assets as $i => $asset ) {
			$line = '- Asset #' . ( $i + 1 ) . ': ' . $asset['url'];
			if ( ! empty( $asset['desc'] ) ) {
				$line .= ' (' . $asset['desc'] . ')';
			}
			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the URL redirections block for the prompt.
	 *
	 * @return string Formatted redirections block, or empty string.
	 */
	private function build_redirects_block(): string {
		if ( empty( $this->redirects ) ) {
			return '';
		}

		$lines = [ 'URL REDIRECTIONS (use these exact URLs for the named links):' ];
		foreach ( $this->redirects as $redirect ) {
			$lines[] = '- ' . $redirect['label'] . ': ' . $redirect['url'];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the references block for the system prompt.
	 *
	 * Each URL reference now includes a server-fetched page content excerpt
	 * so the LLM can actually replicate layout, wording and structure.
	 *
	 * @return string Formatted references block, or empty string.
	 */
	private function build_references_block(): string {
		if ( empty( $this->reference_links ) ) {
			return '';
		}

		$lines = [
			'REFERENCE WEBSITES — study these closely and replicate their visual quality, layout patterns, color palette, typography choices, and content structure:',
		];
		foreach ( $this->reference_links as $ref ) {
			$url     = $ref['url']     ?? '';
			$notes   = $ref['notes']   ?? '';
			$content = $ref['content'] ?? '';

			$line = "- Reference: {$url}";
			if ( $notes ) {
				$line .= "\n  Focus: {$notes}";
			}
			$lines[] = $line;

			if ( $content ) {
				$lines[] = '  EXTRACTED PAGE CONTENT (use the real copy, headings, CTAs and structure from this excerpt — do not invent generic text when real content is available):';
				$lines[] = '  ' . str_replace( "\n", "\n  ", $content );
			}
		}

		return implode( "\n", $lines );
	}
}
