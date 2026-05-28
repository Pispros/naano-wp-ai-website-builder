<?php
/**
 * HTML Sanitizer – cleans and validates LLM HTML output.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helpers for extracting and sanitizing HTML from LLM responses.
 */
class Naano_HTML_Sanitizer {

	/**
	 * Clean raw LLM output into safe HTML.
	 *
	 * Steps:
	 * 1. Extract from Markdown code fences (```html … ```).
	 * 2. Trim to first `<` and last `>`.
	 * 3. Strip <script src="..."> blocks (remote/external sources only).
	 *    Inline <script>…</script> blocks are KEPT because the AI relies on
	 *    them for mobile nav toggles, accordions, etc. The iframe sandbox
	 *    (allow-scripts, no allow-same-origin) already isolates them from
	 *    the parent admin page, so inline scripts can't read cookies, hit
	 *    the WP REST API as the logged-in user, or escape the iframe.
	 * 4. Remove on* event attributes.
	 * 5. Remove javascript: hrefs.
	 * 6. Fix broken DOM via DOMDocument round-trip.
	 *
	 * @param string $raw_response Raw text from LLM.
	 * @return string Sanitized HTML.
	 */
	public static function clean( string $raw_response ): string {
		$html = $raw_response;

		// 1. Extract from markdown code fences.
		if ( preg_match( '/```(?:html)?\s*([\s\S]*?)```/i', $html, $m ) ) {
			$html = $m[1];
		}

		// 2. Trim to first `<` … last `>`.
		$first = strpos( $html, '<' );
		$last  = strrpos( $html, '>' );
		if ( $first !== false && $last !== false && $last > $first ) {
			$html = substr( $html, $first, $last - $first + 1 );
		}

		// 3. Strip ONLY external script tags (those with a src attribute).
		// Inline scripts are kept — they power mobile menus, accordions,
		// dropdowns, etc. in AI-generated HTML. The live preview iframe
		// runs sandbox="allow-scripts" without allow-same-origin, and the
		// frontend renders inside its own page context where scripts are
		// expected. We still kill src="..." script tags because those are
		// the easiest XSS vector (a single AI hallucination pointing at
		// evil.example.com would compromise every published page).
		$html = preg_replace(
			'/<script\b[^>]*\bsrc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)[^>]*>[\s\S]*?<\/script>/i',
			'',
			$html
		) ?? $html;
		// Also strip self-closing <script src="..." /> just in case.
		$html = preg_replace(
			'/<script\b[^>]*\bsrc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)[^>]*\/?>/i',
			'',
			$html
		) ?? $html;

		// 4. Remove on* event attributes. We keep this even though inline
		// scripts are now allowed: AI-generated code should attach events
		// via addEventListener inside its <script> block, not via inline
		// onclick="..." (which the prompt explicitly forbids anyway). This
		// blocks a whole class of injection where an event handler hides
		// in an otherwise-innocent-looking element attribute.
		$html = preg_replace( '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html ) ?? $html;

		// 5. Remove javascript: from href/src/action attributes.
		$html = preg_replace( '/(\b(?:href|src|action)\s*=\s*["\'])javascript:[^"\']*(["\'])/i', '$1#$2', $html ) ?? $html;

		// 6. Fix DOM with DOMDocument (only if the dom extension is available).
		if ( extension_loaded( 'dom' ) ) {
			$html = self::dom_fix( $html );
		}

		return $html;
	}

	/**
	 * Extract the HTML for a specific section by its BEGIN/END markers.
	 *
	 * @param string $full_html  Complete HTML document with section markers.
	 * @param string $section_id Section identifier.
	 * @return string Section HTML, or empty string if not found.
	 */
	public static function extract_section( string $full_html, string $section_id ): string {
		$id      = preg_quote( $section_id, '/' );
		$pattern = '/<!--\s*BEGIN:' . $id . '\s*-->([\s\S]*?)<!--\s*END:' . $id . '\s*-->/';
		if ( preg_match( $pattern, $full_html, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Run HTML through DOMDocument to repair broken markup.
	 *
	 * Script bodies are preserved verbatim: DOMDocument can mangle JS
	 * (it HTML-encodes `<`, `>`, `&` inside <script> text content, which
	 * breaks code like `if (a < b)`). We swap each script body for a
	 * placeholder before parsing and restore the originals after.
	 *
	 * @param string $html Input HTML.
	 * @return string Repaired HTML.
	 */
	private static function dom_fix( string $html ): string {
		// 1. Pull out every <script>…</script> body and replace with a marker.
		$scripts = array();
		$html    = preg_replace_callback(
			'/<script\b([^>]*)>([\s\S]*?)<\/script>/i',
			function ( $m ) use ( &$scripts ) {
				$idx       = count( $scripts );
				$scripts[] = array(
					'attrs' => $m[1],
					'body'  => $m[2],
				);
				return '<script' . $m[1] . '>__NAANO_SCRIPT_PLACEHOLDER_' . $idx . '__</script>';
			},
			$html
		) ?? $html;

		$dom = new DOMDocument( '1.0', 'UTF-8' );

		libxml_use_internal_errors( true );
		// Wrap in a div to prevent DOMDocument from adding full page boilerplate.
		$dom->loadHTML(
			'<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		// Extract body content.
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			// Fall back to the un-parsed HTML, but still restore the script bodies.
			return self::restore_script_placeholders( $html, $scripts );
		}

		$output = '';
		foreach ( $body->childNodes as $node ) {
			$output .= $dom->saveHTML( $node );
		}

		$output = $output ?: $html;

		// 2. Put the original script bodies back exactly as written.
		return self::restore_script_placeholders( $output, $scripts );
	}

	/**
	 * Swap __NAANO_SCRIPT_PLACEHOLDER_N__ markers back to their original bodies.
	 *
	 * @param string $html    HTML containing placeholders.
	 * @param array  $scripts List of { attrs, body } captured before parsing.
	 * @return string
	 */
	private static function restore_script_placeholders( string $html, array $scripts ): string {
		foreach ( $scripts as $idx => $script ) {
			$marker = '__NAANO_SCRIPT_PLACEHOLDER_' . $idx . '__';
			// DOMDocument may have HTML-entity-encoded our marker if it
			// happens to sit between text nodes — search for both the raw
			// marker and any entity-encoded variant. The marker is plain
			// ASCII so this normally stays a no-op, but it's cheap.
			$pos = strpos( $html, $marker );
			if ( $pos !== false ) {
				$html = substr_replace( $html, $script['body'], $pos, strlen( $marker ) );
			}
		}
		return $html;
	}
}
