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
You are an expert web designer and front-end developer.

RULES (strictly follow):
1. Output ONLY valid HTML with all CSS embedded in a single <style> tag at the top of each section.
2. Do NOT output any explanation, markdown, or non-HTML text.
3. Use semantic HTML5 elements.
4. Make every section fully responsive (mobile-first, use CSS media queries).
5. Wrap each section's HTML between marker comments: <!-- BEGIN:SECTION_ID --> … <!-- END:SECTION_ID -->
6. Use the design variables provided below to ensure brand consistency.
7. Do NOT use JavaScript — pure HTML/CSS only.
8. Produce clean, production-ready code.

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

		$pages_block = '';
		if ( ! empty( $this->site_pages ) ) {
			$lines = [ "\nSITE PAGES (use these exact URLs for navigation links):" ];
			foreach ( $this->site_pages as $p ) {
				$lines[] = '- ' . $p['title'] . ': ' . $p['url'];
			}
			$pages_block = implode( "\n", $lines ) . "\n";
		}

		return <<<MSG
Create a complete, professional website based on the following description:

DESCRIPTION:
{$description}
{$pages_block}
SECTIONS (create each in order, wrap each in BEGIN/END markers):
{$section_list}

For each section use the section type name as its SECTION_ID (lowercase, hyphens for spaces, e.g. "hero", "about-us").
Produce all sections sequentially in one response.
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
		$context_extras  = trim( $assets_block . ( $assets_block && $redirects_block ? "\n\n" : '' ) . $redirects_block );

		$extras_section = $context_extras ? "\n\n" . $context_extras : '';

		return <<<MSG
CURRENT SITE CONTEXT (compressed — other sections shown as placeholders):
{$compressed_context}{$extras_section}

TASK:
Update the section with ID "{$section_id}" according to the following instruction:
{$instruction}

Output ONLY the updated section wrapped in:
<!-- BEGIN:{$section_id} -->
…updated HTML here…
<!-- END:{$section_id} -->

Do NOT output any other sections.
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
	 * @return string Formatted references block, or empty string.
	 */
	private function build_references_block(): string {
		if ( empty( $this->reference_links ) ) {
			return '';
		}

		$lines = [ 'REFERENCE WEBSITES (study these for inspiration and style):' ];
		foreach ( $this->reference_links as $ref ) {
			$url   = $ref['url'] ?? '';
			$notes = $ref['notes'] ?? '';
			$line  = "- {$url}";
			if ( $notes ) {
				$line .= " ({$notes})";
			}
			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}
}
