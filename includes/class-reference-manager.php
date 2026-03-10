<?php
/**
 * Reference Manager – screenshot uploads and URL references per section.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages screenshot and URL references attached to page sections.
 */
class Naano_Reference_Manager {

	private const META_KEY      = '_naano_references';
	private const MAX_IMAGE_PX  = 1024;
	private const JPEG_QUALITY  = 75;

	/**
	 * Add a reference to a section.
	 *
	 * @param int    $page_id    WordPress post/page ID.
	 * @param string $section_id Section identifier.
	 * @param array  $ref_data   Reference data (type, url, attachment_id, notes).
	 * @return void
	 */
	public function add_reference( int $page_id, string $section_id, array $ref_data ): void {
		$all = $this->load_all( $page_id );

		if ( ! isset( $all[ $section_id ] ) ) {
			$all[ $section_id ] = [];
		}

		$all[ $section_id ][] = array_merge(
			[
				'type'          => 'url',
				'url'           => '',
				'attachment_id' => 0,
				'notes'         => '',
				'target_section'=> $section_id,
				'timestamp'     => time(),
			],
			$ref_data
		);

		$this->save_all( $page_id, $all );
	}

	/**
	 * Get all references for a section.
	 *
	 * @param int    $page_id    WordPress post/page ID.
	 * @param string $section_id Section identifier.
	 * @return array
	 */
	public function get_references( int $page_id, string $section_id ): array {
		$all = $this->load_all( $page_id );
		return $all[ $section_id ] ?? [];
	}

	/**
	 * Remove a reference by its index.
	 *
	 * @param int    $page_id    WordPress post/page ID.
	 * @param string $section_id Section identifier.
	 * @param int    $index      Zero-based index.
	 * @return void
	 */
	public function remove_reference( int $page_id, string $section_id, int $index ): void {
		$all = $this->load_all( $page_id );

		if ( isset( $all[ $section_id ][ $index ] ) ) {
			array_splice( $all[ $section_id ], $index, 1 );
			$this->save_all( $page_id, $all );
		}
	}

	/**
	 * Load screenshot references and return them as base64 image data arrays.
	 *
	 * Images are resized to max 1024px and JPEG-compressed to reduce token usage.
	 *
	 * @param int    $page_id    WordPress post/page ID.
	 * @param string $section_id Section identifier.
	 * @return array Array of ['data' => base64, 'mime_type' => 'image/jpeg'].
	 */
	public function prepare_images_for_llm( int $page_id, string $section_id ): array {
		$refs   = $this->get_references( $page_id, $section_id );
		$images = [];

		foreach ( $refs as $ref ) {
			if ( ( $ref['type'] ?? '' ) !== 'screenshot' ) {
				continue;
			}
			$attachment_id = (int) ( $ref['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			$base64 = $this->resize_and_encode( $file_path );
			if ( $base64 ) {
				$images[] = [
					'data'      => $base64,
					'mime_type' => 'image/jpeg',
				];
			}
		}

		return $images;
	}

	/**
	 * Get URL-type references ready for prompt injection.
	 * Each URL's page content is fetched server-side so the LLM
	 * actually receives useful context instead of a bare URL string.
	 *
	 * @param int    $page_id    WordPress post/page ID.
	 * @param string $section_id Section identifier.
	 * @return array Array of url reference arrays, each optionally containing 'content'.
	 */
	public function prepare_url_references( int $page_id, string $section_id ): array {
		$refs = $this->get_references( $page_id, $section_id );
		$url_refs = array_values(
			array_filter(
				$refs,
				static fn( $r ) => ( $r['type'] ?? '' ) === 'url'
			)
		);

		// Fetch each URL's content server-side so the LLM can use it.
		foreach ( $url_refs as &$ref ) {
			if ( ! empty( $ref['url'] ) ) {
				$ref['content'] = self::fetch_url_text( $ref['url'] );
			}
		}
		unset( $ref );

		return $url_refs;
	}

	/**
	 * Fetch the text content of a URL via cURL for LLM context injection.
	 *
	 * Strips scripts, styles, and HTML tags; normalises whitespace; truncates
	 * to $max_chars to stay within token budgets.
	 *
	 * @param string $url       A valid http(s) URL.
	 * @param int    $max_chars Maximum characters to return.
	 * @return string Plain-text excerpt, or empty string on failure.
	 */
	public static function fetch_url_text( string $url, int $max_chars = 3500 ): string {
		// Only fetch http/https URLs.
		if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
			return '';
		}

		if ( ! function_exists( 'curl_init' ) ) {
			return '';
		}

		$ch = curl_init();
		curl_setopt_array( $ch, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 12,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NaanoBot/1.0; +https://naanocorp.tech)',
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_HTTPHEADER     => [
				'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.5',
			],
		] );

		$html = curl_exec( $ch );
		$err  = curl_errno( $ch );
		curl_close( $ch );

		if ( $err || ! $html || ! is_string( $html ) ) {
			return '';
		}

		// Remove scripts, styles, and their content.
		$text = preg_replace( '/<(script|style|noscript)\b[^>]*>[\s\S]*?<\/\1>/i', ' ', $html ) ?? $html;

		// Keep alt attributes of images — useful visual context.
		$text = preg_replace_callback( '/<img\b[^>]*alt=["\']([^"\']*)["\'][^>]*>/i', static function ( $m ) {
			return $m[1] ? '[IMG: ' . $m[1] . ']' : '';
		}, $text ) ?? $text;

		$text = strip_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
		$text = trim( $text );

		return mb_substr( $text, 0, $max_chars );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resize an image file to max dimensions and return base64-encoded JPEG.
	 *
	 * @param string $file_path Absolute path to image file.
	 * @return string|null Base64 string or null on failure.
	 */
	private function resize_and_encode( string $file_path ): ?string {
		if ( ! extension_loaded( 'gd' ) ) {
			// Fallback: encode original file without resizing.
			$raw = file_get_contents( $file_path );
			return $raw ? base64_encode( $raw ) : null;
		}

		$info = @getimagesize( $file_path );
		if ( ! $info ) {
			return null;
		}

		[ $orig_w, $orig_h, $type ] = $info;

		$src = match ( $type ) {
			IMAGETYPE_JPEG => @imagecreatefromjpeg( $file_path ),
			IMAGETYPE_PNG  => @imagecreatefrompng( $file_path ),
			IMAGETYPE_GIF  => @imagecreatefromgif( $file_path ),
			IMAGETYPE_WEBP => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file_path ) : false,
			default        => false,
		};

		if ( ! $src ) {
			return null;
		}

		// Calculate new dimensions.
		$max  = self::MAX_IMAGE_PX;
		$ratio = min( $max / $orig_w, $max / $orig_h, 1.0 );
		$new_w = (int) round( $orig_w * $ratio );
		$new_h = (int) round( $orig_h * $ratio );

		$dst = imagecreatetruecolor( $new_w, $new_h );
		// Preserve transparency for PNG.
		if ( $type === IMAGETYPE_PNG ) {
			imagealphablending( $dst, false );
			imagesavealpha( $dst, true );
		}
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h );
		imagedestroy( $src );

		// Capture JPEG output.
		ob_start();
		imagejpeg( $dst, null, self::JPEG_QUALITY );
		$jpeg_data = ob_get_clean();
		imagedestroy( $dst );

		return $jpeg_data ? base64_encode( $jpeg_data ) : null;
	}

	/**
	 * Load all references for a page.
	 *
	 * @param int $page_id WordPress post ID.
	 * @return array
	 */
	private function load_all( int $page_id ): array {
		$data = get_post_meta( $page_id, self::META_KEY, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Save all references for a page.
	 *
	 * @param int   $page_id WordPress post ID.
	 * @param array $data    Full references array.
	 * @return void
	 */
	private function save_all( int $page_id, array $data ): void {
		update_post_meta( $page_id, self::META_KEY, $data );
	}
}
