<?php
/**
 * Payload Compressor – minimizes token usage for LLM payloads.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helpers for compressing HTML payloads before sending to the LLM.
 */
class Naano_Payload_Compressor {

	/**
	 * Compress a sections array for inclusion in a prompt.
	 *
	 * Non-edited sections become lightweight placeholder comments.
	 * The edited section gets its full HTML wrapped in BEGIN/END markers.
	 *
	 * @param array       $sections          Array of section data [id, type, html, …].
	 * @param string|null $editing_section_id The section currently being edited (full content).
	 * @return string Compressed context string.
	 */
	public static function compress_context( array $sections, ?string $editing_section_id = null ): string {
		$parts = [];

		foreach ( $sections as $section ) {
			$id   = $section['id'] ?? '';
			$type = $section['type'] ?? 'section';
			$html = $section['html'] ?? '';

			if ( $id === $editing_section_id ) {
				// Full content for the section being edited.
				$minified = self::minify_html( self::minify_inline_css( $html ) );
				$parts[]  = "<!-- BEGIN:{$id} -->\n{$minified}\n<!-- END:{$id} -->";
			} else {
				// Placeholder for all other sections.
				$hash    = substr( md5( $html ), 0, 8 );
				$parts[] = "<!-- section:{$id} type={$type} hash={$hash} -->";
			}
		}

		return implode( "\n", $parts );
	}

	/**
	 * Minify HTML string.
	 *
	 * - Strips HTML comments (preserves section BEGIN/END markers).
	 * - Collapses whitespace.
	 * - Removes space between tags.
	 *
	 * @param string $html Raw HTML.
	 * @return string Minified HTML.
	 */
	public static function minify_html( string $html ): string {
		// Preserve section markers before stripping comments.
		$placeholders = [];
		$html         = preg_replace_callback(
			'/<!--\s*(BEGIN:|END:)\S+\s*-->/',
			static function ( array $matches ) use ( &$placeholders ): string {
				$key                  = '__NAANO_MARKER_' . count( $placeholders ) . '__';
				$placeholders[ $key ] = $matches[0];
				return $key;
			},
			$html
		) ?? $html;

		// Strip all other HTML comments.
		$html = preg_replace( '/<!--(?!__NAANO_MARKER_).*?-->/s', '', $html ) ?? $html;

		// Collapse whitespace sequences to single space.
		$html = preg_replace( '/\s+/', ' ', $html ) ?? $html;

		// Remove space between tags.
		$html = preg_replace( '/>\s+</', '><', $html ) ?? $html;

		// Restore section markers.
		$html = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $html );

		return trim( $html );
	}

	/**
	 * Compress CSS inside <style> tags.
	 *
	 * @param string $html HTML containing <style> blocks.
	 * @return string HTML with minified inline CSS.
	 */
	public static function minify_inline_css( string $html ): string {
		return preg_replace_callback(
			'/<style([^>]*)>(.*?)<\/style>/si',
			static function ( array $matches ): string {
				$attrs = $matches[1];
				$css   = $matches[2];
				$css   = self::minify_css_string( $css );
				return "<style{$attrs}>{$css}</style>";
			},
			$html
		) ?? $html;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Minify a raw CSS string.
	 *
	 * @param string $css CSS text.
	 * @return string Minified CSS.
	 */
	private static function minify_css_string( string $css ): string {
		// Remove comments.
		$css = preg_replace( '!/\*.*?\*/!s', '', $css ) ?? $css;
		// Collapse whitespace.
		$css = preg_replace( '/\s+/', ' ', $css ) ?? $css;
		// Remove spaces around symbols.
		$css = preg_replace( '/\s*([{}:;,>~+])\s*/', '$1', $css ) ?? $css;
		// Remove trailing semicolons before closing brace.
		$css = str_replace( ';}', '}', $css );
		return trim( $css );
	}
}
