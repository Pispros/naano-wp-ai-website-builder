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
	 * 3. Remove <script> blocks.
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

		// 3. Remove script tags (case-insensitive, include attributes).
		$html = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html ) ?? $html;

		// 4. Remove on* event attributes.
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
	 * @param string $html Input HTML.
	 * @return string Repaired HTML.
	 */
	private static function dom_fix( string $html ): string {
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
			return $html;
		}

		$output = '';
		foreach ( $body->childNodes as $node ) {
			$output .= $dom->saveHTML( $node );
		}

		return $output ?: $html;
	}
}
