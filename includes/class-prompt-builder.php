<?php
/**
 * Prompt Builder – assembles system and user prompts for the LLM.
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Builds structured prompts with variable injection and reference context.
 */
class Naano_Prompt_Builder
{
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
    	You are an elite UI/UX designer, award-winning digital art director, and senior front-end engineer.

    Your work must resemble a premium production website created by a top-tier agency.

    The output is used directly in production by professional developers.

    ════════════════════════════════
    OUTPUT FORMAT
    ════════════════════════════════

    Return ONLY valid HTML.

    Do NOT output:

    * markdown
    * explanations
    * commentary
    * code fences

    Structure every section exactly like this:

    <!-- BEGIN:SECTION_ID -->

    <section class="section-SECTION_ID">

    <style>
    /* Scoped CSS ONLY */

    .section-SECTION_ID{
      --color-primary:#2563eb;
      --color-secondary:#7c3aed;
      --color-bg:#ffffff;
      --color-surface:#f8fafc;
      --color-text:#111827;
      --color-muted:#6b7280;

      --radius-sm:12px;
      --radius-md:18px;

      --shadow-sm:0 4px 12px rgba(0,0,0,.06);
      --shadow-md:0 10px 30px rgba(0,0,0,.10);

      --container:1200px;

      font-family:
        system-ui,
        -apple-system,
        Segoe UI,
        Roboto,
        sans-serif;

      color:var(--color-text);
    }

    .section-SECTION_ID *{
      box-sizing:border-box;
    }

    .section-SECTION_ID img{
      max-width:100%;
      display:block;
    }

    .section-SECTION_ID .container{
      width:100%;
      max-width:var(--container);
      margin:auto;
      padding:0 24px;
    }

    </style>

    <!-- Section content -->

    </section>
    <!-- END:SECTION_ID -->

    ════════════════════════════════
    DESIGN DIRECTION
    ════════════════════════════════

    The visual quality must feel comparable to:

    * Stripe
    * Linear
    * Vercel
    * Apple
    * Framer
    * Notion marketing pages

    Prioritize:

    * elegant spacing
    * premium typography
    * visual rhythm
    * strong hierarchy
    * refined alignment
    * subtle depth
    * modern composition
    * polished responsive behavior

    The interface should feel:

    * expensive
    * intentional
    * editorial
    * minimal yet sophisticated

    Avoid:

    * generic templates
    * flat layouts
    * overly centered walls of text
    * bootstrap-like appearance
    * crowded UI
    * harsh black (#000000)

    Prefer:

    * soft contrast
    * layered surfaces
    * subtle gradients
    * tasteful shadows
    * refined hover transitions

    ════════════════════════════════
    HTML + CSS STANDARDS
    ════════════════════════════════

    Requirements:

    * semantic HTML
    * accessible markup
    * mobile-first responsive design
    * clean DOM hierarchy
    * production-quality CSS
    * no inline styles
    * no JavaScript
    * no external frameworks

    Use ONLY:

    * flexbox
    * CSS grid

    Do not use:

    * floats
    * table layouts

    ════════════════════════════════
    TYPOGRAPHY
    ════════════════════════════════

    Typography must feel highly refined.

    Recommended scale:

    * hero titles: 56–72px
    * section titles: 32–40px
    * subtitles: 20–24px
    * body text: 16–18px

    Large headings should use:

    * tight letter spacing
    * strong line wrapping
    * balanced widths

    Paragraphs should remain readable:

    * max-width around 60–70ch
    * comfortable line-height

    ════════════════════════════════
    RESPONSIVE QUALITY
    ════════════════════════════════

    Layouts must remain visually excellent from:
    320px → 1920px.

    Responsive behavior must feel intentional,
    not merely stacked.

    Prioritize:

    * spacing adaptation
    * readable typography scaling
    * balanced grids
    * strong mobile hierarchy

    ════════════════════════════════
    COMPONENT QUALITY
    ════════════════════════════════

    Buttons:

    * visually polished
    * minimum height 44px
    * refined hover transitions
    * accessible contrast

    Cards:

    * layered surfaces
    * soft shadows
    * subtle hover lift
    * premium spacing

    Forms:

    * accessible labels
    * strong focus states
    * clean visual hierarchy

    Images:

    * responsive containers
    * object-fit:cover
    * elegant framing

    ════════════════════════════════
    ANIMATION
    ════════════════════════════════

    Use subtle motion only.

    Allowed:

    * fade-in
    * opacity transitions
    * small translate transforms
    * hover transitions

    Motion should feel:

    * smooth
    * restrained
    * premium

    Avoid excessive animation.

    ════════════════════════════════
    IMPORTANT
    ════════════════════════════════

    Always return complete valid HTML.

    Prioritize visual quality, layout harmony,
    and premium execution over excessive complexity.

    {variables_block}

    {references_block}

    {custom_prompt_block}
    PROMPT;

    /**
     * Set custom design variables.
     *
     * @param array<string,string> $variables Key/value design variables.
     * @return static Fluent interface.
     */
    public function set_variables(array $variables): static
    {
        $this->variables = $variables;
        return $this;
    }

    /**
     * Set URL references to include in the prompt.
     *
     * @param array $references Array of reference data.
     * @return static Fluent interface.
     */
    public function set_references(array $references): static
    {
        $this->reference_links = $references;
        return $this;
    }

    /**
     * Build the full system prompt, replacing placeholders.
     *
     * Applies the `naano_system_prompt` filter so external code can modify
     * the assembled prompt before it is sent to the LLM.
     *
     * @return string Assembled system prompt.
     */
    public function build_system_prompt(): string
    {
        $vars_block = $this->build_variables_block();
        $refs_block = $this->build_references_block();
        $custom_block = $this->build_custom_prompt_block();

        $prompt = str_replace(
            [
                "{variables_block}",
                "{references_block}",
                "{custom_prompt_block}",
            ],
            [$vars_block, $refs_block, $custom_block],
            $this->base_system_prompt,
        );

        return (string) apply_filters("naano_system_prompt", $prompt);
    }

    /**
     * Set page-level assets for the prompt.
     *
     * @param array<array{url:string,desc:string}> $assets
     * @return static
     */
    public function set_assets(array $assets): static
    {
        $this->assets = $assets;
        return $this;
    }

    /**
     * Set page-level URL redirections for the prompt.
     *
     * @param array<array{label:string,url:string}> $redirects
     * @return static
     */
    public function set_redirects(array $redirects): static
    {
        $this->redirects = $redirects;
        return $this;
    }

    /**
     * Set other site pages so the LLM can generate correct nav links.
     *
     * @param array<array{title:string,url:string}> $pages
     * @return static
     */
    public function set_site_pages(array $pages): static
    {
        $this->site_pages = $pages;
        return $this;
    }

    /** @var string Rendered WordPress nav menu HTML. */
    private string $nav_menu_html = "";

    /**
     * Set the rendered WordPress navigation menu HTML for injection
     * into header/footer section prompts.
     *
     * @param string $html Rendered menu HTML from wp_nav_menu().
     * @return static
     */
    public function set_nav_menu(string $html): static
    {
        $this->nav_menu_html = $html;
        return $this;
    }

    /**

    * Build the user message for generating a complete new site.
    *
    * @param string   $description   Site description from user.
    * @param string[] $section_types Ordered list of section type names.
    * @return string User message content.
      */
    public function build_initial_message(
        string $description,
        array $section_types,
    ): string {
        $section_list = implode(", ", $section_types);

        $pages_block = "";
        if (!empty($this->site_pages)) {
            $lines = [
                "\nSITE PAGES (use these exact URLs for navigation links):",
            ];

            foreach ($this->site_pages as $p) {
                $lines[] = "- " . $p["title"] . ": " . $p["url"];
            }

            $pages_block = implode("\n", $lines) . "\n";
        }

        return <<<MSG
          Create a complete premium-quality website based on the following brief.

        The final result should feel comparable to a modern high-end product website created by a professional digital agency.

        BRIEF:
        {$description}

        {$pages_block}

        SECTIONS TO CREATE
        Generate the following sections in this exact order:
        {$section_list}

        Each section must:

        * use the required BEGIN/END markers
        * contain fully scoped CSS
        * feel visually cohesive with the rest of the site
        * share a consistent design language
        * maintain responsive behavior across all screen sizes

        SECTION ID FORMAT
        Use the lowercase kebab-case version of the section type.

        Examples:

        * Hero → hero
        * About Us → about-us
        * Contact Form → contact-form

        DESIGN EXPECTATIONS

        The website should feel:

        * premium
        * modern
        * polished
        * visually balanced
        * professionally art-directed

        Prioritize:

        * strong typography hierarchy
        * refined spacing
        * elegant composition
        * responsive layouts
        * clean visual rhythm
        * subtle depth and layering
        * premium UI details

        Avoid:

        * generic template aesthetics
        * repetitive layouts
        * overcrowded sections
        * excessive text blocks
        * flat or outdated styling

        CONTENT RULES

        * Use meaningful realistic content
        * No lorem ipsum
        * Keep copy concise and believable
        * Headlines should feel intentional and high-quality
        * CTA labels should feel modern and product-oriented

        RESPONSIVE QUALITY
        Layouts must remain visually excellent from mobile to large desktop screens.

        Mobile layouts should feel intentionally designed,
        not simply stacked desktop layouts.

        IMPORTANT
        Generate ALL requested sections in a single response.

        Focus on producing visually impressive, production-quality frontend output.

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
    public function build_section_message(
        string $section_id,
        string $instruction,
        string $compressed_context,
    ): string {
        $assets_block = $this->build_assets_block();
        $redirects_block = $this->build_redirects_block();
        $refs_block = $this->build_references_block();

        $extra_parts = array_filter([
            $assets_block,
            $redirects_block,
            $refs_block,
        ]);

        $context_extras = implode("\n\n", $extra_parts);

        $extras_section = $context_extras ? "\n\n" . $context_extras : "";

        $nav_block = $this->build_nav_menu_block($section_id);

        return <<<MSG
        CURRENT SITE CONTEXT
        (Other sections may appear compressed or simplified for context continuity)

        {$compressed_context}
        {$extras_section}
        {$nav_block}

        TASK

        Update the section with ID "{$section_id}".

        USER INSTRUCTION:
        {$instruction}

        OBJECTIVE

        The updated section must:

        integrate naturally with the surrounding page
        preserve the site's visual language
        improve visual quality and polish where appropriate
        maintain responsive behavior
        remain production-ready

        DESIGN EXPECTATIONS

        The result should feel:

        premium
        modern
        refined
        professionally art-directed

        Prioritize:

        elegant spacing
        strong typography hierarchy
        clean responsive composition
        subtle depth and layering
        polished interaction states
        visually balanced layouts

        Avoid:

        generic template appearance
        flat layouts
        overcrowded content
        inconsistent spacing
        outdated UI styling

        TECHNICAL REQUIREMENTS

        Return ONLY the updated section
        Preserve the existing section ID and root class
        Keep CSS fully scoped to the section
        No JavaScript
        No external frameworks
        No inline styles
        Semantic and accessible HTML only

        OUTPUT FORMAT

        ...updated section HTML...

        IMPORTANT

        Do not output any other sections.
        Do not include explanations or markdown.

        MSG;
    }

    /**
     * Build the user message for generating a single section in the initial
     * site-generation flow (one LLM call per section to avoid timeouts).
     *
     * References are already injected into the system prompt via {references_block},
     * so they are NOT repeated here.
     *
     * @param string $description  Site description / brief from user.
     * @param string $section_type Section type name (e.g. "hero", "features").
     * @return string User message content.
     */
    public function build_single_section_message(
        string $description,
        string $section_type,
    ): string {
        $section_id = sanitize_title($section_type);

        $pages_block = "";

        if (!empty($this->site_pages)) {
            $lines = [
                "\nSITE PAGES (use these exact URLs for navigation links):",
            ];

            foreach ($this->site_pages as $p) {
                $lines[] = "- " . $p["title"] . ": " . $p["url"];
            }

            $pages_block = implode("\n", $lines) . "\n";
        }

        $nav_block = $this->build_nav_menu_block($section_id);

        return <<<MSG
        Create a premium-quality website section based on the following brief.

        BRIEF:
        {$description}

        {$pages_block}

        {$nav_block}

        SECTION TO CREATE:
        {$section_type}

        SECTION ID:
        {$section_id}

        OBJECTIVE

        Generate a visually impressive, production-ready section that feels comparable to a modern premium SaaS or product website.

        The section should feel:

        refined
        modern
        polished
        professionally art-directed

        Visual inspiration:

        Stripe
        Linear
        Vercel
        Apple
        Framer
        Notion

        DESIGN EXPECTATIONS

        Prioritize:

        elegant spacing
        strong typography hierarchy
        responsive composition
        subtle depth and layering
        premium UI polish
        visually balanced layouts
        clean visual rhythm

        Avoid:

        generic template aesthetics
        flat layouts
        outdated styling
        overcrowded content
        repetitive UI patterns

        CONTENT RULES

        Use realistic and meaningful copy
        No lorem ipsum
        Keep content concise and intentional
        CTA labels should feel modern and product-oriented

        TECHNICAL REQUIREMENTS

        Return ONLY this section
        Use semantic accessible HTML
        Fully scoped CSS only
        No inline styles
        No JavaScript
        No external frameworks
        Responsive design required
        Mobile-first layout

        OUTPUT FORMAT

        IMPORTANT

        The section must feel visually premium,
        responsive, and production-ready.

        MSG;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the navigation menu block for header/footer sections.
     *
     * @param string $section_id Section being generated/edited.
     * @return string Nav menu prompt block, or empty string.
     */
    private function build_nav_menu_block(string $section_id): string
    {
        if (empty($this->nav_menu_html)) {
            return "";
        }

        // Only inject for header/footer sections.
        if (
            strpos($section_id, "header") === false &&
            strpos($section_id, "footer") === false
        ) {
            return "";
        }

        return "\n\nWORDPRESS NAVIGATION MENU (use these exact links and labels for the nav):\n" .
            $this->nav_menu_html .
            "\n";
    }

    /**
     * Build the variables block for the system prompt.
     *
     * @return string Formatted variables block, or empty string.
     */
    private function build_variables_block(): string
    {
        if (empty($this->variables)) {
            return "";
        }

        $lines = [
            "DESIGN VARIABLES (use these consistently throughout the site):",
        ];
        foreach ($this->variables as $key => $value) {
            $lines[] = "- {$key}: {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build the assets block for the prompt.
     *
     * @return string Formatted assets block, or empty string.
     */
    private function build_assets_block(): string
    {
        if (empty($this->assets)) {
            return "";
        }

        $lines = [
            "PAGE ASSETS (use these URLs when instructed — reference by number):",
        ];
        foreach ($this->assets as $i => $asset) {
            $line = "- Asset #" . ($i + 1) . ": " . $asset["url"];
            if (!empty($asset["desc"])) {
                $line .= " (" . $asset["desc"] . ")";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Build the URL redirections block for the prompt.
     *
     * @return string Formatted redirections block, or empty string.
     */
    private function build_redirects_block(): string
    {
        if (empty($this->redirects)) {
            return "";
        }

        $lines = [
            "URL REDIRECTIONS (use these exact URLs for the named links):",
        ];
        foreach ($this->redirects as $redirect) {
            $lines[] = "- " . $redirect["label"] . ": " . $redirect["url"];
        }

        return implode("\n", $lines);
    }

    /**
     * Build the references block for the system prompt.
     *
     * Each URL reference now includes a server-fetched page content excerpt
     * so the LLM can actually replicate layout, wording and structure.
     *
     * @return string Formatted references block, or empty string.
     */
    private function build_references_block(): string
    {
        if (empty($this->reference_links)) {
            return "";
        }

        $lines = [
            "REFERENCE WEBSITES — study these closely and replicate their visual quality, layout patterns, color palette, typography choices, and content structure:",
        ];
        foreach ($this->reference_links as $ref) {
            $url = $ref["url"] ?? "";
            $notes = $ref["notes"] ?? "";
            $content = $ref["content"] ?? "";

            $line = "- Reference: {$url}";
            if ($notes) {
                $line .= "\n  Focus: {$notes}";
            }
            $lines[] = $line;

            if ($content) {
                $lines[] =
                    "  EXTRACTED PAGE CONTENT (use the real copy, headings, CTAs and structure from this excerpt — do not invent generic text when real content is available):";
                $lines[] = "  " . str_replace("\n", "\n  ", $content);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build the custom system prompt block from the saved option.
     *
     * @return string Formatted custom prompt block, or empty string.
     */
    private function build_custom_prompt_block(): string
    {
        $custom = get_option("naano_custom_prompt", "");
        $custom = is_string($custom) ? trim($custom) : "";
        if (!$custom) {
            return "";
        }

        return "════════════════════════════════\nADDITIONAL INSTRUCTIONS\n════════════════════════════════\n\n" .
            $custom;
    }
}
