<?php
/**
 * Section Manager – CRUD operations and HTML assembly for page sections.
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Manages the list of HTML sections stored per WordPress page.
 */
class Naano_Section_Manager
{
    private const META_KEY = "_naano_sections";
    private const META_GLOBAL_CSS = "_naano_global_css";
    private const META_FAILED_SECTIONS = "_naano_failed_sections";

    /**
     * Get all sections for a page.
     *
     * @param int $page_id WordPress post ID.
     * @return array Array of section data: [id, type, html, order].
     */
    public function get_sections(int $page_id): array
    {
        $data = get_post_meta($page_id, self::META_KEY, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Get a single section by ID.
     *
     * @param int    $page_id    WordPress post ID.
     * @param string $section_id Section identifier.
     * @return array|null Section data or null if not found.
     */
    public function get_section(int $page_id, string $section_id): ?array
    {
        foreach ($this->get_sections($page_id) as $section) {
            if (($section["id"] ?? "") === $section_id) {
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
    public function update_section(
        int $page_id,
        string $section_id,
        string $html,
        string $type = "",
    ): void {
        $sections = $this->get_sections($page_id);
        $found = false;

        foreach ($sections as &$section) {
            if (($section["id"] ?? "") === $section_id) {
                $section["html"] = $html;
                $section["updated"] = time();
                if ($type) {
                    $section["type"] = $type;
                }
                $found = true;
                break;
            }
        }
        unset($section);

        if (!$found) {
            $sections[] = [
                "id" => $section_id,
                "type" => $type ?: $section_id,
                "html" => $html,
                "order" => count($sections),
                "created" => time(),
                "updated" => time(),
            ];
        }

        $this->save_sections($page_id, $sections);
    }

    /**
     * Delete a section.
     *
     * @param int    $page_id    WordPress post ID.
     * @param string $section_id Section identifier.
     * @return void
     */
    public function delete_section(int $page_id, string $section_id): void
    {
        $sections = array_filter(
            $this->get_sections($page_id),
            static fn($s) => ($s["id"] ?? "") !== $section_id,
        );
        $this->save_sections($page_id, array_values($sections));
    }

    /**
     * Reorder sections according to a provided ordered list of IDs.
     *
     * @param int      $page_id     WordPress post ID.
     * @param string[] $ordered_ids New order of section IDs.
     * @return void
     */
    public function reorder_sections(int $page_id, array $ordered_ids): void
    {
        $sections_map = [];
        foreach ($this->get_sections($page_id) as $section) {
            $sections_map[$section["id"]] = $section;
        }

        $reordered = [];
        foreach ($ordered_ids as $index => $id) {
            if (isset($sections_map[$id])) {
                $sections_map[$id]["order"] = $index;
                $reordered[] = $sections_map[$id];
            }
        }

        // Append any sections not in the ordered list at the end.
        foreach ($sections_map as $id => $section) {
            if (!in_array($id, $ordered_ids, true)) {
                $reordered[] = $section;
            }
        }

        $this->save_sections($page_id, $reordered);
    }

    /**
     * Assemble all sections into a complete HTML5 document.
     *
     * @param int $page_id WordPress post ID.
     * @return string Full HTML document.
     */
    public function get_assembled_html(int $page_id): string
    {
        $sections = $this->get_sections($page_id);
        $title = get_the_title($page_id) ?: "Website";

        $body_parts = [];
        foreach ($sections as $section) {
            $body_parts[] = $section["html"] ?? "";
        }
        $body = implode("\n\n", $body_parts);

        // Reset the default 8px body margin every browser ships with —
        // LLM-generated sections almost always assume a flush-edge layout
        // and the leftover margin shows as ugly white gutters around the
        // page. Same reason we suppress it inside the iframe srcdoc.
        $base_css =
            "html,body{margin:0;padding:0;}body{box-sizing:border-box;}*,*::before,*::after{box-sizing:inherit;}";

        // Per-page custom CSS the user wrote in the "Global CSS" textarea
        // of the builder. Loaded once and applied AFTER base_css so the
        // user can intentionally override box-sizing or add a body margin
        // back if they really want to.
        $global_css = trim($this->get_global_css($page_id));

        $style_block = "<style>" . $base_css;
        if ($global_css !== "") {
            $style_block .= "\n" . $global_css;
        }
        $style_block .= "</style>";

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$title}</title>
        {$style_block}
        </head>
        <body>
        {$body}
        </body>
        </html>
        HTML;
    }

    /**
     * Get the page-level "global CSS" override the user wrote in the
     * builder's "Global CSS" textarea. Applied to the assembled HTML
     * AFTER the base reset so the user can override anything they want.
     *
     * @param int $page_id WordPress post ID.
     * @return string Raw CSS (NOT wrapped in <style>).
     */
    public function get_global_css(int $page_id): string
    {
        $css = get_post_meta($page_id, self::META_GLOBAL_CSS, true);
        return is_string($css) ? $css : "";
    }

    /**
     * Persist the page-level global CSS override.
     *
     * Saved as raw text — sanitization happens at render time (we wrap it
     * in <style> tags after escaping, so it cannot inject HTML). Storing
     * it raw avoids cumulative sanitizer corruption when the user opens,
     * edits, and re-saves repeatedly.
     *
     * @param int    $page_id WordPress post ID.
     * @param string $css     CSS source.
     * @return void
     */
    public function set_global_css(int $page_id, string $css): void
    {
        update_post_meta($page_id, self::META_GLOBAL_CSS, $css);
    }

    /**
     * Get the list of sections that failed during the last generate_site
     * run. Each entry is [section_id, section_type, reason]. Cleared
     * automatically when a section is successfully (re)generated by
     * mark_section_recovered().
     *
     * @param int $page_id WordPress post ID.
     * @return array
     */
    public function get_failed_sections(int $page_id): array
    {
        $data = get_post_meta($page_id, self::META_FAILED_SECTIONS, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Replace the entire failed_sections list. Called by the runner at
     * the end of generate_site to commit whatever failures the worker
     * accumulated during the run.
     *
     * @param int   $page_id WordPress post ID.
     * @param array $failed  List of failed-section entries.
     * @return void
     */
    public function set_failed_sections(int $page_id, array $failed): void
    {
        if (empty($failed)) {
            delete_post_meta($page_id, self::META_FAILED_SECTIONS);
            return;
        }
        update_post_meta($page_id, self::META_FAILED_SECTIONS, $failed);
    }

    /**
     * Drop a single section_id from the failed list. Called from the
     * AJAX update_section endpoint when a failed section is successfully
     * regenerated, so it disappears from the "Failed sections" UI.
     *
     * @param int    $page_id    WordPress post ID.
     * @param string $section_id The recovered section's id.
     * @return void
     */
    public function mark_section_recovered(
        int $page_id,
        string $section_id,
    ): void {
        $failed = $this->get_failed_sections($page_id);
        if (empty($failed)) {
            return;
        }
        $kept = array_values(
            array_filter($failed, static function ($entry) use ($section_id) {
                $id = is_array($entry) ? $entry["section_id"] ?? "" : "";
                return $id !== $section_id;
            }),
        );
        $this->set_failed_sections($page_id, $kept);
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
    private function save_sections(int $page_id, array $sections): void
    {
        update_post_meta($page_id, self::META_KEY, $sections);
    }
}
