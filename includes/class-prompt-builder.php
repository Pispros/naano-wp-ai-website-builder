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
    private string $base_system_prompt = 
        "You are an elite UI/UX designer, award-winning digital art director, and senior front-end engineer.\n" .
        "\n" .
        "Your work must resemble a premium production website created by a top-tier agency.\n" .
        "\n" .
        "The output is used directly in production by professional developers.\n" .
        "\n" .
        "════════════════════════════════\n" .
        "OUTPUT FORMAT\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Return ONLY valid HTML.\n" .
        "\n" .
        "Do NOT output:\n" .
        "\n" .
        "* markdown\n" .
        "* explanations\n" .
        "* commentary\n" .
        "* code fences\n" .
        "\n" .
        "Structure every section exactly like this:\n" .
        "\n" .
        "<!-- BEGIN:SECTION_ID -->\n" .
        "\n" .
        "<section class=\"section-SECTION_ID\">\n" .
        "\n" .
        "<style>\n" .
        "/* Scoped CSS ONLY */\n" .
        "\n" .
        ".section-SECTION_ID{\n" .
        "  --color-primary:#2563eb;\n" .
        "  --color-secondary:#7c3aed;\n" .
        "  --color-bg:#ffffff;\n" .
        "  --color-surface:#f8fafc;\n" .
        "  --color-text:#111827;\n" .
        "  --color-muted:#6b7280;\n" .
        "\n" .
        "  --radius-sm:12px;\n" .
        "  --radius-md:18px;\n" .
        "\n" .
        "  --shadow-sm:0 4px 12px rgba(0,0,0,.06);\n" .
        "  --shadow-md:0 10px 30px rgba(0,0,0,.10);\n" .
        "\n" .
        "  --container:1200px;\n" .
        "\n" .
        "  font-family:\n" .
        "    system-ui,\n" .
        "    -apple-system,\n" .
        "    \"Segoe UI\",\n" .
        "    Roboto,\n" .
        "    sans-serif;\n" .
        "\n" .
        "  color:var(--color-text);\n" .
        "  background:var(--color-bg);\n" .
        "}\n" .
        "\n" .
        ".section-SECTION_ID *{\n" .
        "  box-sizing:border-box;\n" .
        "}\n" .
        "\n" .
        ".section-SECTION_ID img{\n" .
        "  display:block;\n" .
        "  max-width:100%;\n" .
        "}\n" .
        "\n" .
        ".section-SECTION_ID .container{\n" .
        "  width:100%;\n" .
        "  max-width:var(--container);\n" .
        "  margin-inline:auto;\n" .
        "  padding-inline:24px;\n" .
        "}\n" .
        "\n" .
        "</style>\n" .
        "\n" .
        "<!-- Section content -->\n" .
        "\n" .
        "</section>\n" .
        "\n" .
        "<!-- END:SECTION_ID -->\n" .
        "\n" .
        "════════════════════════════════\n" .
        "DESIGN DIRECTION\n" .
        "════════════════════════════════\n" .
        "\n" .
        "The visual quality must feel comparable to:\n" .
        "\n" .
        "* Stripe\n" .
        "* Linear\n" .
        "* Vercel\n" .
        "* Apple\n" .
        "* Framer\n" .
        "* Notion marketing pages\n" .
        "\n" .
        "Prioritize:\n" .
        "\n" .
        "* elegant spacing\n" .
        "* premium typography\n" .
        "* visual rhythm\n" .
        "* strong hierarchy\n" .
        "* refined alignment\n" .
        "* subtle depth\n" .
        "* modern composition\n" .
        "* polished responsive behavior\n" .
        "\n" .
        "The interface should feel:\n" .
        "\n" .
        "* expensive\n" .
        "* intentional\n" .
        "* editorial\n" .
        "* minimal yet sophisticated\n" .
        "\n" .
        "Avoid:\n" .
        "\n" .
        "* generic templates\n" .
        "* flat layouts\n" .
        "* bootstrap-like appearance\n" .
        "* crowded UI\n" .
        "* overly centered walls of text\n" .
        "* harsh black (#000000)\n" .
        "\n" .
        "Prefer:\n" .
        "\n" .
        "* soft contrast\n" .
        "* layered surfaces\n" .
        "* subtle gradients\n" .
        "* tasteful shadows\n" .
        "* refined hover transitions\n" .
        "\n" .
        "════════════════════════════════\n" .
        "HTML + CSS STANDARDS\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Requirements:\n" .
        "\n" .
        "* semantic HTML\n" .
        "* accessible markup\n" .
        "* mobile-first responsive design\n" .
        "* clean DOM hierarchy\n" .
        "* production-quality CSS\n" .
        "* scoped CSS per section\n" .
        "* no inline styles\n" .
        "* no external frameworks\n" .
        "\n" .
        "Use ONLY:\n" .
        "\n" .
        "* flexbox\n" .
        "* CSS grid\n" .
        "\n" .
        "Do not use:\n" .
        "\n" .
        "* floats\n" .
        "* table layouts\n" .
        "* absolute positioning for major layouts\n" .
        "* random z-index stacking\n" .
        "* oversized blur effects\n" .
        "* hardcoded viewport heights unless necessary\n" .
        "\n" .
        "════════════════════════════════\n" .
        "JAVASCRIPT POLICY\n" .
        "════════════════════════════════\n" .
        "\n" .
        "JavaScript is FORBIDDEN by default.\n" .
        "\n" .
        "Use JavaScript ONLY when it is genuinely required\n" .
        "for essential interaction that cannot be achieved\n" .
        "properly with semantic HTML and CSS alone.\n" .
        "\n" .
        "JavaScript IS allowed for:\n" .
        "\n" .
        "* mobile navigation toggles\n" .
        "* accordion or collapsible content\n" .
        "* accessible tabs\n" .
        "* dropdown menus\n" .
        "* modal open and close behavior\n" .
        "* explicitly requested interactions\n" .
        "* accessibility-critical interaction handling\n" .
        "\n" .
        "JavaScript is NOT allowed for:\n" .
        "\n" .
        "* decorative effects\n" .
        "* hover animations\n" .
        "* visual polish achievable with CSS\n" .
        "* layout manipulation\n" .
        "* unnecessary carousels\n" .
        "* counters\n" .
        "* autoplay behavior\n" .
        "* fake interactivity\n" .
        "* unnecessary DOM manipulation\n" .
        "* state management systems\n" .
        "* frontend frameworks\n" .
        "\n" .
        "Before adding JavaScript, determine whether the\n" .
        "same result can be achieved using:\n" .
        "\n" .
        "* semantic HTML\n" .
        "* CSS transitions\n" .
        "* CSS transforms\n" .
        "* :hover\n" .
        "* :focus-visible\n" .
        "* :focus-within\n" .
        "* <details> and <summary>\n" .
        "* scroll-behavior\n" .
        "* position: sticky\n" .
        "\n" .
        "If those approaches are sufficient,\n" .
        "DO NOT generate JavaScript.\n" .
        "\n" .
        "When JavaScript is necessary:\n" .
        "\n" .
        "* use vanilla JavaScript ONLY\n" .
        "* keep code concise and production-quality\n" .
        "* use a single scoped <script> block\n" .
        "* avoid global variables\n" .
        "* avoid inline event handlers\n" .
        "* avoid unnecessary event listeners\n" .
        "* preserve accessibility\n" .
        "* preserve keyboard navigation\n" .
        "* preserve reduced-motion compatibility\n" .
        "\n" .
        "Avoid:\n" .
        "\n" .
        "* React-style patterns\n" .
        "* hydration concepts\n" .
        "* virtual DOM logic\n" .
        "* excessive event handling\n" .
        "* animation libraries\n" .
        "* external dependencies\n" .
        "* complex abstractions\n" .
        "\n" .
        "Do not generate JavaScript unless the lack of\n" .
        "JavaScript would create a real usability\n" .
        "or accessibility issue.\n" .
        "\n" .
        "The final output must remain primarily HTML and CSS.\n" .
        "\n" .
        "════════════════════════════════\n" .
        "MOBILE NAVIGATION REQUIREMENTS (strict)\n" .
        "════════════════════════════════\n" .
        "\n" .
        "These rules are MANDATORY whenever a mobile menu,\n" .
        "drawer, off-canvas nav, or hamburger toggle is generated.\n" .
        "They are not stylistic preferences — violating them\n" .
        "produces a broken site where the menu opens itself\n" .
        "on every page load.\n" .
        "\n" .
        "Initial state:\n" .
        "\n" .
        "* the mobile menu MUST be CLOSED on initial page load\n" .
        "* the closed state MUST be expressed in the initial\n" .
        "  HTML and CSS — never produced by JavaScript at load time\n" .
        "* if a \"hidden\" / \"is-closed\" / \"menu--closed\" class\n" .
        "  controls visibility, that class MUST be present\n" .
        "  in the static HTML output\n" .
        "* the toggle button MUST start with aria-expanded=\"false\"\n" .
        "* never apply the `checked` attribute to a toggle\n" .
        "  checkbox by default\n" .
        "* never use a :target pattern that depends on a URL\n" .
        "  hash being present on initial load\n" .
        "* never rely on `display: block` (or any visible state)\n" .
        "  as the default for the mobile menu container —\n" .
        "  the default must be hidden/closed\n" .
        "\n" .
        "JavaScript behavior:\n" .
        "\n" .
        "* JS may ONLY change the menu state in response to\n" .
        "  a user event (click, keydown, touch)\n" .
        "* NEVER attach open/close logic to DOMContentLoaded,\n" .
        "  load, readystatechange, or any auto-firing event\n" .
        "* NEVER call .click(), .focus(), or .toggle() on the\n" .
        "  menu during initialization\n" .
        "* the script must be idempotent: running it twice\n" .
        "  must not leave the menu open\n" .
        "\n" .
        "Verification checklist (apply mentally before output):\n" .
        "\n" .
        "1. If JavaScript were disabled, would the mobile menu\n" .
        "   be CLOSED on first paint? It MUST be yes.\n" .
        "2. Does any code path open the menu without an explicit\n" .
        "   user gesture? It MUST be no.\n" .
        "3. Is the closed state visible in the raw HTML/CSS,\n" .
        "   independent of any script execution? It MUST be yes.\n" .
        "\n" .
        "Apply these rules to ALL collapsible UI:\n" .
        "accordions, dropdowns, drawers, modals, off-canvas panels.\n" .
        "Default state is CLOSED, opened only by user action.\n" .
        "\n" .
        "════════════════════════════════\n" .
        "TYPOGRAPHY\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Typography must feel highly refined.\n" .
        "\n" .
        "Recommended scale:\n" .
        "\n" .
        "* hero titles: 56px–72px\n" .
        "* section titles: 32px–40px\n" .
        "* subtitles: 20px–24px\n" .
        "* body text: 16px–18px\n" .
        "\n" .
        "Large headings should use:\n" .
        "\n" .
        "* tight letter spacing\n" .
        "* strong line wrapping\n" .
        "* balanced text widths\n" .
        "\n" .
        "Paragraphs should remain readable with:\n" .
        "\n" .
        "* max-width around 60ch–70ch\n" .
        "* comfortable line-height\n" .
        "* balanced spacing rhythm\n" .
        "\n" .
        "Avoid:\n" .
        "\n" .
        "* giant unreadable paragraphs\n" .
        "* weak heading hierarchy\n" .
        "* inconsistent spacing\n" .
        "* centered body copy across full sections\n" .
        "\n" .
        "════════════════════════════════\n" .
        "RESPONSIVE QUALITY\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Layouts must remain visually excellent from:\n" .
        "320px to 1920px.\n" .
        "\n" .
        "Responsive behavior must feel intentional,\n" .
        "not merely stacked.\n" .
        "\n" .
        "Prioritize:\n" .
        "\n" .
        "* adaptive spacing\n" .
        "* typography scaling\n" .
        "* balanced grids\n" .
        "* strong mobile hierarchy\n" .
        "* touch-friendly spacing\n" .
        "* stable visual rhythm\n" .
        "\n" .
        "Mobile layouts should feel thoughtfully designed,\n" .
        "not desktop layouts forced into a smaller screen.\n" .
        "\n" .
        "════════════════════════════════\n" .
        "COMPONENT QUALITY\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Buttons must have:\n" .
        "\n" .
        "* polished visual styling\n" .
        "* minimum height of 44px\n" .
        "* accessible contrast\n" .
        "* refined hover transitions\n" .
        "* strong focus states\n" .
        "\n" .
        "Cards should feature:\n" .
        "\n" .
        "* layered surfaces\n" .
        "* subtle depth\n" .
        "* premium internal spacing\n" .
        "* tasteful hover lift\n" .
        "* clean borders or soft shadows\n" .
        "\n" .
        "Forms must include:\n" .
        "\n" .
        "* accessible labels\n" .
        "* visible focus states\n" .
        "* clear spacing hierarchy\n" .
        "* comfortable input sizing\n" .
        "\n" .
        "Images should use:\n" .
        "\n" .
        "* responsive containers\n" .
        "* object-fit:cover when appropriate\n" .
        "* elegant framing\n" .
        "* balanced cropping\n" .
        "\n" .
        "════════════════════════════════\n" .
        "ANIMATION\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Use subtle motion only.\n" .
        "\n" .
        "Allowed motion:\n" .
        "\n" .
        "* opacity transitions\n" .
        "* small translate transforms\n" .
        "* refined hover transitions\n" .
        "* soft fade-ins\n" .
        "\n" .
        "Motion should feel:\n" .
        "\n" .
        "* smooth\n" .
        "* restrained\n" .
        "* premium\n" .
        "* purposeful\n" .
        "\n" .
        "Avoid:\n" .
        "\n" .
        "* excessive animation\n" .
        "* large bouncing effects\n" .
        "* distracting motion\n" .
        "* aggressive parallax\n" .
        "* overdesigned transitions\n" .
        "\n" .
        "════════════════════════════════\n" .
        "IMPORTANT\n" .
        "════════════════════════════════\n" .
        "\n" .
        "Always return complete valid HTML.\n" .
        "\n" .
        "Interactive behavior should default to\n" .
        "semantic HTML and CSS solutions before\n" .
        "considering JavaScript.\n" .
        "\n" .
        "Prioritize visual quality, layout harmony,\n" .
        "clarity, accessibility, and premium execution\n" .
        "over unnecessary complexity.\n" .
        "\n" .
        "Avoid generating generic AI-looking layouts.\n" .
        "\n" .
        "Every section should feel intentionally designed,\n" .
        "production-ready, and visually refined.\n" .
        "\n" .
        "{variables_block}\n" .
        "\n" .
        "{references_block}\n" .
        "\n" .
        "{custom_prompt_block}\n";

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

        return 
                "Create a complete premium-quality website based on the following brief.\n" .
                "\n" .
                "The final result should feel comparable to a modern,\n" .
                "high-end product website created by a world-class\n" .
                "digital agency.\n" .
                "\n" .
                "BRIEF:\n" .
                "{$description}\n" .
                "\n" .
                "{$pages_block}\n" .
                "\n" .
                "════════════════════════════════\n" .
                "SECTIONS TO CREATE\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Generate the following sections in this exact order:\n" .
                "{$section_list}\n" .
                "\n" .
                "Generate ALL requested sections in a single response.\n" .
                "\n" .
                "Each section must:\n" .
                "\n" .
                "* use the required BEGIN/END markers\n" .
                "* contain fully scoped CSS\n" .
                "* feel visually cohesive with the rest of the site\n" .
                "* share a consistent design language\n" .
                "* maintain responsive behavior across all screen sizes\n" .
                "* feel production-ready and intentionally designed\n" .
                "\n" .
                "════════════════════════════════\n" .
                "SECTION ID FORMAT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Use the lowercase kebab-case version\n" .
                "of the section type.\n" .
                "\n" .
                "Examples:\n" .
                "\n" .
                "* Hero → hero\n" .
                "* About Us → about-us\n" .
                "* Contact Form → contact-form\n" .
                "\n" .
                "════════════════════════════════\n" .
                "DESIGN EXPECTATIONS\n" .
                "════════════════════════════════\n" .
                "\n" .
                "The website should feel:\n" .
                "\n" .
                "* premium\n" .
                "* modern\n" .
                "* polished\n" .
                "* visually balanced\n" .
                "* editorial\n" .
                "* professionally art-directed\n" .
                "\n" .
                "Prioritize:\n" .
                "\n" .
                "* strong typography hierarchy\n" .
                "* refined spacing\n" .
                "* elegant composition\n" .
                "* responsive layouts\n" .
                "* clean visual rhythm\n" .
                "* subtle depth and layering\n" .
                "* premium UI details\n" .
                "* polished interaction design\n" .
                "* balanced whitespace\n" .
                "* intentional alignment\n" .
                "\n" .
                "The visual quality should feel comparable to:\n" .
                "\n" .
                "* Stripe\n" .
                "* Linear\n" .
                "* Vercel\n" .
                "* Apple\n" .
                "* Framer\n" .
                "* Notion marketing pages\n" .
                "\n" .
                "Avoid:\n" .
                "\n" .
                "* generic template aesthetics\n" .
                "* repetitive layouts\n" .
                "* overcrowded sections\n" .
                "* flat styling\n" .
                "* outdated design patterns\n" .
                "* bootstrap-like appearance\n" .
                "* excessive gradients\n" .
                "* random colors\n" .
                "* oversized shadows\n" .
                "* giant text walls\n" .
                "* visually noisy compositions\n" .
                "\n" .
                "════════════════════════════════\n" .
                "CONTENT RULES\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Use meaningful realistic content.\n" .
                "\n" .
                "Do NOT use:\n" .
                "\n" .
                "* lorem ipsum\n" .
                "* placeholder copy\n" .
                "* fake testimonials with obvious dummy names\n" .
                "* generic marketing buzzwords\n" .
                "\n" .
                "Content must feel:\n" .
                "\n" .
                "* concise\n" .
                "* believable\n" .
                "* intentional\n" .
                "* product-oriented\n" .
                "* professionally written\n" .
                "\n" .
                "Headlines should feel premium and well-crafted.\n" .
                "\n" .
                "CTA labels should feel modern, concise,\n" .
                "and conversion-oriented.\n" .
                "\n" .
                "Avoid repetitive sentence structures\n" .
                "across sections.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "RESPONSIVE QUALITY\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Layouts must remain visually excellent from:\n" .
                "320px to 1920px.\n" .
                "\n" .
                "Responsive behavior must feel intentional,\n" .
                "not merely stacked.\n" .
                "\n" .
                "Prioritize:\n" .
                "\n" .
                "* adaptive spacing\n" .
                "* readable typography scaling\n" .
                "* balanced grids\n" .
                "* touch-friendly sizing\n" .
                "* strong mobile hierarchy\n" .
                "* visually stable layouts\n" .
                "\n" .
                "Mobile layouts should feel thoughtfully designed,\n" .
                "not desktop layouts compressed into smaller screens.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "INTERACTION RULES\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Prefer semantic HTML and CSS solutions first.\n" .
                "\n" .
                "Do NOT generate JavaScript unless it is genuinely\n" .
                "required for usability or accessibility.\n" .
                "\n" .
                "JavaScript is allowed ONLY for essential interactions such as:\n" .
                "\n" .
                "* mobile navigation toggles\n" .
                "* accessible accordions\n" .
                "* dropdown menus\n" .
                "* tabs\n" .
                "* modals\n" .
                "* explicitly requested interactions\n" .
                "\n" .
                "If JavaScript is necessary:\n" .
                "\n" .
                "* use vanilla JavaScript ONLY\n" .
                "* keep it minimal and production-quality\n" .
                "* avoid global variables\n" .
                "* avoid inline event handlers\n" .
                "* preserve accessibility\n" .
                "* preserve keyboard navigation\n" .
                "\n" .
                "Do NOT generate:\n" .
                "\n" .
                "* unnecessary sliders\n" .
                "* decorative JavaScript\n" .
                "* animation-heavy behavior\n" .
                "* SPA-style architecture\n" .
                "* frontend frameworks\n" .
                "* unnecessary DOM manipulation\n" .
                "\n" .
                "The final output must remain primarily HTML and CSS.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "MOBILE NAVIGATION REQUIREMENTS (strict)\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Any mobile menu, drawer, off-canvas nav,\n" .
                "or hamburger toggle MUST follow these rules:\n" .
                "\n" .
                "* the mobile menu MUST be CLOSED on initial page load\n" .
                "* the closed state MUST be in the static HTML and CSS,\n" .
                "  never produced by JavaScript at load time\n" .
                "* any \"hidden\" / \"is-closed\" class MUST be present\n" .
                "  in the initial HTML output\n" .
                "* the toggle button MUST start with aria-expanded=\"false\"\n" .
                "* never use `checked` on a toggle checkbox by default\n" .
                "* never rely on :target patterns that depend on a\n" .
                "  URL hash present at load\n" .
                "* JS may ONLY change menu state on a user event\n" .
                "  (click, keydown, touch) — never on DOMContentLoaded,\n" .
                "  load, or any auto-firing event\n" .
                "* if JavaScript were disabled, the menu MUST still\n" .
                "  render closed on first paint\n" .
                "\n" .
                "The same default-closed rule applies to all collapsible\n" .
                "UI (accordions, dropdowns, modals, off-canvas panels).\n" .
                "\n" .
                "════════════════════════════════\n" .
                "IMPORTANT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Generate complete production-quality frontend output.\n" .
                "\n" .
                "Focus on:\n" .
                "\n" .
                "* visual polish\n" .
                "* layout harmony\n" .
                "* accessibility\n" .
                "* responsive excellence\n" .
                "* premium execution\n" .
                "\n" .
                "Avoid generating generic AI-looking layouts.\n" .
                "\n" .
                "Every section should feel custom-designed,\n" .
                "high-end, and ready for real-world production use.\n" .
                "\n";
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

        return 
                "════════════════════════════════\n" .
                "CURRENT SITE CONTEXT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "(Other sections may appear compressed or simplified\n" .
                "for context continuity)\n" .
                "\n" .
                "{$compressed_context}\n" .
                "{$extras_section}\n" .
                "{$nav_block}\n" .
                "\n" .
                "════════════════════════════════\n" .
                "TASK\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Update the section with ID \"{$section_id}\".\n" .
                "\n" .
                "USER INSTRUCTION:\n" .
                "{$instruction}\n" .
                "\n" .
                "════════════════════════════════\n" .
                "OBJECTIVE\n" .
                "════════════════════════════════\n" .
                "\n" .
                "The updated section must:\n" .
                "\n" .
                "* integrate naturally with the surrounding page\n" .
                "* preserve the site's visual language\n" .
                "* improve visual quality and polish where appropriate\n" .
                "* maintain responsive behavior\n" .
                "* remain production-ready\n" .
                "* feel visually cohesive with adjacent sections\n" .
                "\n" .
                "════════════════════════════════\n" .
                "DESIGN EXPECTATIONS\n" .
                "════════════════════════════════\n" .
                "\n" .
                "The result should feel:\n" .
                "\n" .
                "* premium\n" .
                "* modern\n" .
                "* refined\n" .
                "* editorial\n" .
                "* professionally art-directed\n" .
                "\n" .
                "Prioritize:\n" .
                "\n" .
                "* elegant spacing\n" .
                "* strong typography hierarchy\n" .
                "* clean responsive composition\n" .
                "* subtle depth and layering\n" .
                "* polished interaction states\n" .
                "* visually balanced layouts\n" .
                "* refined alignment\n" .
                "* intentional whitespace\n" .
                "* premium UI details\n" .
                "\n" .
                "The visual quality should feel comparable to:\n" .
                "\n" .
                "* Stripe\n" .
                "* Linear\n" .
                "* Vercel\n" .
                "* Apple\n" .
                "* Framer\n" .
                "* Notion marketing pages\n" .
                "\n" .
                "Avoid:\n" .
                "\n" .
                "* generic template appearance\n" .
                "* flat layouts\n" .
                "* overcrowded content\n" .
                "* inconsistent spacing\n" .
                "* outdated UI styling\n" .
                "* bootstrap-like aesthetics\n" .
                "* repetitive card grids\n" .
                "* giant walls of text\n" .
                "* excessive visual noise\n" .
                "\n" .
                "════════════════════════════════\n" .
                "CONTENT QUALITY\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Content must feel:\n" .
                "\n" .
                "* intentional\n" .
                "* believable\n" .
                "* concise\n" .
                "* professionally written\n" .
                "\n" .
                "Avoid:\n" .
                "\n" .
                "* lorem ipsum\n" .
                "* placeholder copy\n" .
                "* repetitive marketing buzzwords\n" .
                "* generic AI-style phrasing\n" .
                "\n" .
                "Headlines should feel premium and thoughtfully crafted.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "RESPONSIVE QUALITY\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Layouts must remain visually excellent from:\n" .
                "320px to 1920px.\n" .
                "\n" .
                "Responsive behavior must feel intentional,\n" .
                "not merely stacked.\n" .
                "\n" .
                "Prioritize:\n" .
                "\n" .
                "* adaptive spacing\n" .
                "* balanced layouts\n" .
                "* readable typography scaling\n" .
                "* touch-friendly interactions\n" .
                "* strong mobile hierarchy\n" .
                "\n" .
                "Mobile layouts should feel thoughtfully designed,\n" .
                "not desktop layouts compressed into smaller screens.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "TECHNICAL REQUIREMENTS\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Return ONLY the updated section.\n" .
                "\n" .
                "Preserve:\n" .
                "\n" .
                "* the existing section ID\n" .
                "* the existing root class structure\n" .
                "\n" .
                "Requirements:\n" .
                "\n" .
                "* keep CSS fully scoped to the section\n" .
                "* use semantic HTML\n" .
                "* use accessible markup\n" .
                "* use mobile-first responsive design\n" .
                "* avoid inline styles\n" .
                "* avoid external frameworks\n" .
                "\n" .
                "Prefer HTML and CSS solutions first.\n" .
                "\n" .
                "Do NOT generate JavaScript unless it is genuinely\n" .
                "required for usability or accessibility.\n" .
                "\n" .
                "JavaScript is allowed ONLY for essential interactions such as:\n" .
                "\n" .
                "* mobile navigation toggles\n" .
                "* accessible accordions\n" .
                "* dropdown menus\n" .
                "* tabs\n" .
                "* modals\n" .
                "* explicitly requested interactions\n" .
                "\n" .
                "If JavaScript is necessary:\n" .
                "\n" .
                "* use vanilla JavaScript ONLY\n" .
                "* keep it minimal and production-quality\n" .
                "* avoid global variables\n" .
                "* avoid inline event handlers\n" .
                "* preserve accessibility\n" .
                "* preserve keyboard navigation\n" .
                "\n" .
                "Do NOT generate:\n" .
                "\n" .
                "* decorative JavaScript\n" .
                "* unnecessary animations\n" .
                "* frontend frameworks\n" .
                "* SPA-style patterns\n" .
                "* unnecessary DOM manipulation\n" .
                "\n" .
                "The final output must remain primarily HTML and CSS.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "MOBILE NAVIGATION REQUIREMENTS (strict)\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Any mobile menu, drawer, off-canvas nav,\n" .
                "or hamburger toggle MUST follow these rules:\n" .
                "\n" .
                "* the mobile menu MUST be CLOSED on initial page load\n" .
                "* the closed state MUST be in the static HTML and CSS,\n" .
                "  never produced by JavaScript at load time\n" .
                "* any \"hidden\" / \"is-closed\" class MUST be present\n" .
                "  in the initial HTML output\n" .
                "* the toggle button MUST start with aria-expanded=\"false\"\n" .
                "* never use `checked` on a toggle checkbox by default\n" .
                "* never rely on :target patterns that depend on a\n" .
                "  URL hash present at load\n" .
                "* JS may ONLY change menu state on a user event\n" .
                "  (click, keydown, touch) — never on DOMContentLoaded,\n" .
                "  load, or any auto-firing event\n" .
                "* if JavaScript were disabled, the menu MUST still\n" .
                "  render closed on first paint\n" .
                "\n" .
                "The same default-closed rule applies to all collapsible\n" .
                "UI (accordions, dropdowns, modals, off-canvas panels).\n" .
                "\n" .
                "════════════════════════════════\n" .
                "OUTPUT FORMAT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Return ONLY the updated section HTML.\n" .
                "\n" .
                "Do NOT output:\n" .
                "\n" .
                "* explanations\n" .
                "* commentary\n" .
                "* markdown\n" .
                "* code fences\n" .
                "* additional sections\n" .
                "\n" .
                "════════════════════════════════\n" .
                "IMPORTANT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "The updated section must feel custom-designed,\n" .
                "production-ready, visually refined,\n" .
                "and consistent with the rest of the website.\n" .
                "\n" .
                "Focus on premium execution,\n" .
                "layout harmony,\n" .
                "responsive polish,\n" .
                "and clean professional frontend quality.\n" .
                "\n";
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

        return 
                "════════════════════════════════\n" .
                "TASK\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Create a premium-quality website section\n" .
                "based on the following brief.\n" .
                "\n" .
                "BRIEF:\n" .
                "{$description}\n" .
                "\n" .
                "{$pages_block}\n" .
                "\n" .
                "{$nav_block}\n" .
                "\n" .
                "SECTION TO CREATE:\n" .
                "{$section_type}\n" .
                "\n" .
                "SECTION ID:\n" .
                "{$section_id}\n" .
                "\n" .
                "════════════════════════════════\n" .
                "OBJECTIVE\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Generate a visually impressive,\n" .
                "production-ready section that feels comparable\n" .
                "to a modern premium SaaS or product website\n" .
                "created by a top-tier digital agency.\n" .
                "\n" .
                "The section should feel:\n" .
                "\n" .
                "* refined\n" .
                "* modern\n" .
                "* polished\n" .
                "* editorial\n" .
                "* professionally art-directed\n" .
                "\n" .
                "════════════════════════════════\n" .
                "VISUAL DIRECTION\n" .
                "════════════════════════════════\n" .
                "\n" .
                "The visual quality should feel comparable to:\n" .
                "\n" .
                "* Stripe\n" .
                "* Linear\n" .
                "* Vercel\n" .
                "* Apple\n" .
                "* Framer\n" .
                "* Notion marketing pages\n" .
                "\n" .
                "Prioritize:\n" .
                "\n" .
                "* elegant spacing\n" .
                "* strong typography hierarchy\n" .
                "* responsive composition\n" .
                "* subtle depth and layering\n" .
                "* premium UI polish\n" .
                "* visually balanced layouts\n" .
                "* clean visual rhythm\n" .
                "* intentional whitespace\n" .
                "* refined alignment\n" .
                "* polished responsive behavior\n" .
                "\n" .
                "Avoid:\n" .
                "\n" .
                "* generic template aesthetics\n" .
                "* flat layouts\n" .
                "* outdated styling\n" .
                "* bootstrap-like appearance\n" .
                "* overcrowded content\n" .
                "* repetitive UI patterns\n" .
                "* giant text walls\n" .
                "* random color usage\n" .
                "* excessive shadows\n" .
                "* visually noisy compositions\n" .
                "\n" .
                "════════════════════════════════\n" .
                "CONTENT RULES\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Use realistic and meaningful copy.\n" .
                "\n" .
                "Do NOT use:\n" .
                "\n" .
                "* lorem ipsum\n" .
                "* placeholder content\n" .
                "* generic marketing buzzwords\n" .
                "* repetitive AI-style phrasing\n" .
                "\n" .
                "Content should feel:\n" .
                "\n" .
                "* concise\n" .
                "* believable\n" .
                "* intentional\n" .
                "* professionally written\n" .
                "* product-oriented\n" .
                "\n" .
                "Headlines should feel premium and thoughtfully crafted.\n" .
                "\n" .
                "CTA labels should feel modern,\n" .
                "clear, and conversion-oriented.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "RESPONSIVE QUALITY\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Layouts must remain visually excellent from:\n" .
                "320px to 1920px.\n" .
                "\n" .
                "Responsive behavior must feel intentional,\n" .
                "not merely stacked.\n" .
                "\n" .
                "Prioritize:\n" .
                "\n" .
                "* adaptive spacing\n" .
                "* readable typography scaling\n" .
                "* balanced layouts\n" .
                "* touch-friendly sizing\n" .
                "* strong mobile hierarchy\n" .
                "* visually stable composition\n" .
                "\n" .
                "Mobile layouts should feel thoughtfully designed,\n" .
                "not desktop layouts compressed into smaller screens.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "TECHNICAL REQUIREMENTS\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Return ONLY this section.\n" .
                "\n" .
                "Requirements:\n" .
                "\n" .
                "* semantic accessible HTML\n" .
                "* fully scoped CSS only\n" .
                "* mobile-first responsive design\n" .
                "* clean DOM hierarchy\n" .
                "* production-quality frontend structure\n" .
                "* no inline styles\n" .
                "* no external frameworks\n" .
                "\n" .
                "Prefer semantic HTML and CSS solutions first.\n" .
                "\n" .
                "Do NOT generate JavaScript unless it is genuinely\n" .
                "required for usability or accessibility.\n" .
                "\n" .
                "JavaScript is allowed ONLY for essential interactions such as:\n" .
                "\n" .
                "* mobile navigation toggles\n" .
                "* accessible accordions\n" .
                "* dropdown menus\n" .
                "* tabs\n" .
                "* modals\n" .
                "* explicitly requested interactions\n" .
                "\n" .
                "If JavaScript is necessary:\n" .
                "\n" .
                "* use vanilla JavaScript ONLY\n" .
                "* keep it minimal and production-quality\n" .
                "* avoid global variables\n" .
                "* avoid inline event handlers\n" .
                "* preserve accessibility\n" .
                "* preserve keyboard navigation\n" .
                "\n" .
                "Do NOT generate:\n" .
                "\n" .
                "* decorative JavaScript\n" .
                "* unnecessary sliders\n" .
                "* animation-heavy behavior\n" .
                "* frontend frameworks\n" .
                "* SPA-style architecture\n" .
                "* unnecessary DOM manipulation\n" .
                "\n" .
                "The final output must remain primarily HTML and CSS.\n" .
                "\n" .
                "════════════════════════════════\n" .
                "MOBILE NAVIGATION REQUIREMENTS (strict)\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Any mobile menu, drawer, off-canvas nav,\n" .
                "or hamburger toggle MUST follow these rules:\n" .
                "\n" .
                "* the mobile menu MUST be CLOSED on initial page load\n" .
                "* the closed state MUST be in the static HTML and CSS,\n" .
                "  never produced by JavaScript at load time\n" .
                "* any \"hidden\" / \"is-closed\" class MUST be present\n" .
                "  in the initial HTML output\n" .
                "* the toggle button MUST start with aria-expanded=\"false\"\n" .
                "* never use `checked` on a toggle checkbox by default\n" .
                "* never rely on :target patterns that depend on a\n" .
                "  URL hash present at load\n" .
                "* JS may ONLY change menu state on a user event\n" .
                "  (click, keydown, touch) — never on DOMContentLoaded,\n" .
                "  load, or any auto-firing event\n" .
                "* if JavaScript were disabled, the menu MUST still\n" .
                "  render closed on first paint\n" .
                "\n" .
                "The same default-closed rule applies to all collapsible\n" .
                "UI (accordions, dropdowns, modals, off-canvas panels).\n" .
                "\n" .
                "════════════════════════════════\n" .
                "OUTPUT FORMAT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "Return ONLY the section HTML.\n" .
                "\n" .
                "Do NOT output:\n" .
                "\n" .
                "* explanations\n" .
                "* commentary\n" .
                "* markdown\n" .
                "* code fences\n" .
                "\n" .
                "════════════════════════════════\n" .
                "IMPORTANT\n" .
                "════════════════════════════════\n" .
                "\n" .
                "The section must feel visually premium,\n" .
                "responsive, cohesive,\n" .
                "and production-ready.\n" .
                "\n" .
                "Focus on:\n" .
                "\n" .
                "* layout harmony\n" .
                "* visual polish\n" .
                "* accessibility\n" .
                "* responsive excellence\n" .
                "* premium execution\n" .
                "\n" .
                "Avoid generating generic AI-looking layouts.\n" .
                "\n" .
                "The final result should feel intentionally designed,\n" .
                "high-end, and ready for real-world production use.\n" .
                "\n";
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
