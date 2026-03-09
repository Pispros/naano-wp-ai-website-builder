<?php
/**
 * Section Manager – CRUD operations and HTML assembly for page sections.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the list of HTML sections stored per WordPress page.
 */
class Naano_Section_Manager {

	private const META_KEY = '_naano_sections';

	/**
	 * Get all sections for a page.
	 *
	 * @param int $page_id WordPress post ID.
	 * @return array Array of section data: [id, type, html, order].
	 */
	public function get_sections( int $page_id ): array {
		$data = get_post_meta( $page_id, self::META_KEY, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Get a single section by ID.
	 *
	 * @param int    $page_id    WordPress post ID.
	 * @param string $section_id Section identifier.
	 * @return array|null Section data or null if not found.
	 */
	public function get_section( int $page_id, string $section_id ): ?array {
		foreach ( $this->get_sections( $page_id ) as $section ) {
			if ( ( $section['id'] ?? '' ) === $section_id ) {
				return $section;
			}
		}
		return null;
	}

	/**
	 * Add or update a section.
	 *
	 * @param int    $page_id    WordPress post ID.
	 * @param string $section_id Section identifier.
	 * @param string $html       Section HTML content.
	 * @param string $type       Section type label (e.g. 'hero', 'footer').
	 * @return void
	 */
	public function update_section( int $page_id, string $section_id, string $html, string $type = '' ): void {
		$sections = $this->get_sections( $page_id );
		$found    = false;

		foreach ( $sections as &$section ) {
			if ( ( $section['id'] ?? '' ) === $section_id ) {
				$section['html']    = $html;
				$section['updated'] = time();
				if ( $type ) {
					$section['type'] = $type;
				}
				$found = true;
				break;
			}
		}
		unset( $section );

		if ( ! $found ) {
			$sections[] = [
				'id'      => $section_id,
				'type'    => $type ?: $section_id,
				'html'    => $html,
				'order'   => count( $sections ),
				'created' => time(),
				'updated' => time(),
			];
		}

		$this->save_sections( $page_id, $sections );
	}

	/**
	 * Delete a section.
	 *
	 * @param int    $page_id    WordPress post ID.
	 * @param string $section_id Section identifier.
	 * @return void
	 */
	public function delete_section( int $page_id, string $section_id ): void {
		$sections = array_filter(
			$this->get_sections( $page_id ),
			static fn( $s ) => ( $s['id'] ?? '' ) !== $section_id
		);
		$this->save_sections( $page_id, array_values( $sections ) );
	}

	/**
	 * Reorder sections according to a provided ordered list of IDs.
	 *
	 * @param int      $page_id     WordPress post ID.
	 * @param string[] $ordered_ids New order of section IDs.
	 * @return void
	 */
	public function reorder_sections( int $page_id, array $ordered_ids ): void {
		$sections_map = [];
		foreach ( $this->get_sections( $page_id ) as $section ) {
			$sections_map[ $section['id'] ] = $section;
		}

		$reordered = [];
		foreach ( $ordered_ids as $index => $id ) {
			if ( isset( $sections_map[ $id ] ) ) {
				$sections_map[ $id ]['order'] = $index;
				$reordered[]                  = $sections_map[ $id ];
			}
		}

		// Append any sections not in the ordered list at the end.
		foreach ( $sections_map as $id => $section ) {
			if ( ! in_array( $id, $ordered_ids, true ) ) {
				$reordered[] = $section;
			}
		}

		$this->save_sections( $page_id, $reordered );
	}

	/**
	 * Assemble all sections into a complete HTML5 document.
	 *
	 * @param int $page_id WordPress post ID.
	 * @return string Full HTML document.
	 */
	public function get_assembled_html( int $page_id ): string {
		$sections = $this->get_sections( $page_id );
		$title    = get_the_title( $page_id ) ?: 'Website';

		$body_parts = [];
		foreach ( $sections as $section ) {
			$body_parts[] = ( $section['html'] ?? '' );
		}
		$body = implode( "\n\n", $body_parts );

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body>
{$body}
</body>
</html>
HTML;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Persist sections to post meta.
	 *
	 * @param int   $page_id  WordPress post ID.
	 * @param array $sections Sections array.
	 * @return void
	 */
	private function save_sections( int $page_id, array $sections ): void {
		update_post_meta( $page_id, self::META_KEY, $sections );
	}
}
