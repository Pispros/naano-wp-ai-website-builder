<?php
/**
 * Settings Page Template
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

$naano_provider = get_option("naano_provider", "claude");
$naano_api_key = get_option("naano_api_key", "");
$naano_model = get_option("naano_model", "");
$naano_variables = get_option("naano_variables", []);
if (!is_array($naano_variables)) {
    $naano_variables = [];
}
$naano_languages = get_option("naano_languages", []);
if (!is_array($naano_languages)) {
    $naano_languages = [];
}
$naano_custom_prompt = get_option("naano_custom_prompt", "");
$naano_default_lang_label = get_option("naano_default_lang_label", "");
$naano_initial_refine = get_option("naano_initial_refinement_passes", 1);
$naano_update_refine = get_option("naano_update_refinement_passes", 1);
?>
<div class="wrap naano-builder-wrap">
	<h1 class="naano-page-title">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 340 340" aria-hidden="true" focusable="false"><rect x="54" y="54" width="232" height="232" rx="26" ry="26" fill="none" stroke="#2060F0" stroke-width="18"/><line x1="115" y1="54" x2="115" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="54" x2="170" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="54" x2="225" y2="26" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="115" y1="286" x2="115" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="170" y1="286" x2="170" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="225" y1="286" x2="225" y2="314" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="115" x2="26" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="170" x2="26" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="54" y1="225" x2="26" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="115" x2="314" y2="115" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="170" x2="314" y2="170" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="286" y1="225" x2="314" y2="225" stroke="#2060F0" stroke-width="17" stroke-linecap="round"/><line x1="106" y1="106" x2="106" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="234" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/><line x1="106" y1="106" x2="234" y2="234" stroke="#2060F0" stroke-width="22" stroke-linecap="round"/></svg>
		<?php esc_html_e("Naano AI Builder — Settings", "naano-ai-website-builder"); ?>
	</h1>

	<?php settings_errors(); ?>

	<nav class="nav-tab-wrapper naano-settings-tab-nav" style="margin-bottom:0;border-bottom:1px solid #c3c4c7;">
		<button type="button" class="nav-tab nav-tab-active" data-naano-tab="llm"><?php esc_html_e(
      "LLM Provider",
      "naano-ai-website-builder",
  ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="variables"><?php esc_html_e(
      "Prompt Global Variables",
      "naano-ai-website-builder",
  ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="global"><?php esc_html_e(
      "Global Configurations",
      "naano-ai-website-builder",
  ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="firecrawl"><?php esc_html_e(
      "References / Website Scraping",
      "naano-ai-website-builder",
  ); ?></button>
		<button type="button" class="nav-tab" data-naano-tab="translation"><?php esc_html_e(
      "Translation",
      "naano-ai-website-builder",
  ); ?></button>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields("naano_settings_group"); ?>

		<!-- ============================================================
		     LLM PROVIDER
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-llm">
		<div class="naano-card">
			<h2><?php esc_html_e("LLM Provider", "naano-ai-website-builder"); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano_provider"><?php esc_html_e(
          "Provider",
          "naano-ai-website-builder",
      ); ?></label>
					</th>
					<td>
						<select name="naano_provider" id="naano_provider">
							<option value="claude"  <?php selected(
           $naano_provider,
           "claude",
       ); ?>>Claude (Anthropic)</option>
							<option value="gemini"  <?php selected(
           $naano_provider,
           "gemini",
       ); ?>>Gemini (Google)</option>
							<option value="openai"  <?php selected($naano_provider, "openai"); ?>>OpenAI</option>
							<option value="kimi"    <?php selected(
           $naano_provider,
           "kimi",
       ); ?>>Kimi (Moonshot)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano_api_key"><?php esc_html_e(
          "API Key",
          "naano-ai-website-builder",
      ); ?></label>
					</th>
					<td>
						<input type="password"
							   name="naano_api_key"
							   id="naano_api_key"
							   class="regular-text"
							   value="<?php echo esc_attr($naano_api_key); ?>"
							   autocomplete="new-password">
						<button type="button" class="button" id="naano-save-api-key-btn" style="margin-left:8px;">
							<?php esc_html_e("Save Key", "naano-ai-website-builder"); ?>
						</button>
						<span class="naano-loading" id="naano-save-key-loading" style="display:none;">
							<span class="spinner is-active"></span>
						</span>
						<span id="naano-save-key-result" style="display:none;margin-left:8px;"></span>
						<p class="description">
							<?php esc_html_e(
           "Your API key is stored in the WordPress options table. Use a read-only key when possible.",
           "naano-ai-website-builder",
       ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano_model"><?php esc_html_e(
          "Model Override",
          "naano-ai-website-builder",
      ); ?></label>
					</th>
					<td>
						<input type="text"
							   name="naano_model"
							   id="naano_model"
							   class="regular-text"
							   value="<?php echo esc_attr($naano_model); ?>"
							   placeholder="<?php esc_attr_e(
              "Leave blank for default model",
              "naano-ai-website-builder",
          ); ?>">
						<p class="description">
							<?php esc_html_e(
           "Defaults: Claude → claude-sonnet-4-6 | Gemini → gemini-2.5-flash | Kimi → kimi-k2-0711-preview | OpenAI → gpt-5.5",
           "naano-ai-website-builder",
       ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="naano-test-connection-row">
				<button type="button" class="button" id="naano-test-connection-btn">
					<?php esc_html_e("Test Connection", "naano-ai-website-builder"); ?>
				</button>
				<span class="naano-loading" id="naano-test-loading" style="display:none;">
					<span class="spinner is-active"></span>
				</span>
				<div id="naano-test-result" class="naano-test-result" style="display:none;"></div>
			</div>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#llm -->

		<!-- ============================================================
		     GLOBAL CONFIG
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-global" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e(
       "Global Configuration",
       "naano-ai-website-builder",
   ); ?></h2>
			<p class="description">
				<?php esc_html_e(
        "Control how many self-review refinement passes the LLM runs after generation. More passes improve quality but take longer.",
        "naano-ai-website-builder",
    ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano_initial_refinement_passes"><?php esc_html_e(
          "Initial Generation Refinements",
          "naano-ai-website-builder",
      ); ?></label>
					</th>
					<td>
						<input type="number"
							   name="naano_initial_refinement_passes"
							   id="naano_initial_refinement_passes"
							   class="small-text"
							   value="<?php echo esc_attr($naano_initial_refine); ?>"
							   min="0"
							   max="10">
						<p class="description">
							<?php esc_html_e(
           "Number of refinement passes when generating a new site. Each pass adds one extra LLM call per section. (Default: 1)",
           "naano-ai-website-builder",
       ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="naano_update_refinement_passes"><?php esc_html_e(
          "Section Update Refinements",
          "naano-ai-website-builder",
      ); ?></label>
					</th>
					<td>
						<input type="number"
							   name="naano_update_refinement_passes"
							   id="naano_update_refinement_passes"
							   class="small-text"
							   value="<?php echo esc_attr($naano_update_refine); ?>"
							   min="0"
							   max="10">
						<p class="description">
							<?php esc_html_e(
           "Number of refinement passes when updating an existing section. Each pass adds one extra LLM call. (Default: 1)",
           "naano-ai-website-builder",
       ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div style="margin-top:10px;">
				<button type="button" class="button button-primary" id="naano-save-global-config-btn">
					<?php esc_html_e("Save Global Config", "naano-ai-website-builder"); ?>
				</button>
				<span class="naano-loading" id="naano-save-global-loading" style="display:none;">
					<span class="spinner is-active"></span>
				</span>
				<span id="naano-save-global-result" style="display:none;margin-left:8px;"></span>
			</div>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#global -->

		<!-- ============================================================
		     DESIGN VARIABLES
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-variables" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e(
       "Custom Design Variables",
       "naano-ai-website-builder",
   ); ?></h2>
			<p class="description">
				<?php esc_html_e(
        "These variables are injected into every AI prompt to ensure brand consistency.",
        "naano-ai-website-builder",
    ); ?>
				<?php esc_html_e(
        "Examples: primary_color, secondary_color, brand_name, font_family, tone, industry, target_audience.",
        "naano-ai-website-builder",
    ); ?>
			</p>

			<table class="form-table">
				<thead>
					<tr>
						<th><?php esc_html_e("Variable Name", "naano-ai-website-builder"); ?></th>
						<th><?php esc_html_e("Value", "naano-ai-website-builder"); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="naano-variables-tbody">
					<?php foreach ($naano_variables as $naano_key => $naano_val): ?>
					<tr class="naano-variable-row">
						<td>
							<input type="text"
								   name="naano_vars_keys[]"
								   class="regular-text"
								   value="<?php echo esc_attr($naano_key); ?>"
								   placeholder="<?php esc_attr_e(
               "e.g. primary_color",
               "naano-ai-website-builder",
           ); ?>">
						</td>
						<td>
							<input type="text"
								   name="naano_vars_values[]"
								   class="regular-text"
								   value="<?php echo esc_attr($naano_val); ?>"
								   placeholder="<?php esc_attr_e(
               "e.g. #3B82F6",
               "naano-ai-website-builder",
           ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-variable">
								<?php esc_html_e("Remove", "naano-ai-website-builder"); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php if (empty($naano_variables)): ?>
					<tr class="naano-variable-row">
						<td>
							<input type="text" name="naano_vars_keys[]" class="regular-text"
								   placeholder="<?php esc_attr_e(
               "e.g. primary_color",
               "naano-ai-website-builder",
           ); ?>">
						</td>
						<td>
							<input type="text" name="naano_vars_values[]" class="regular-text"
								   placeholder="<?php esc_attr_e(
               "e.g. #3B82F6",
               "naano-ai-website-builder",
           ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-variable">
								<?php esc_html_e("Remove", "naano-ai-website-builder"); ?>
							</button>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<button type="button" class="button" id="naano-add-variable-btn">
				+ <?php esc_html_e("Add Variable", "naano-ai-website-builder"); ?>
			</button>
		</div><!-- /.naano-card -->

	<div class="naano-card" style="margin-top:20px;">
		<h2><?php esc_html_e(
      "Custom System Prompt",
      "naano-ai-website-builder",
  ); ?></h2>
		<p class="description">
			<?php esc_html_e(
       "Additional instructions appended to every system prompt. Use this to enforce brand tone, content rules, or any site-specific constraints.",
       "naano-ai-website-builder",
   ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="naano_custom_prompt"><?php esc_html_e(
         "Additional Instructions",
         "naano-ai-website-builder",
     ); ?></label>
				</th>
				<td>
					<textarea
						name="naano_custom_prompt"
						id="naano_custom_prompt"
						class="large-text"
						rows="6"
						placeholder="<?php esc_attr_e(
          "e.g. Always write copy in a friendly, conversational tone. Avoid formal language. The brand voice is warm and approachable.",
          "naano-ai-website-builder",
      ); ?>"><?php echo esc_textarea($naano_custom_prompt); ?></textarea>
					<p class="description"><?php esc_html_e(
         "These instructions are appended to the system prompt for every LLM call. You can also modify the full assembled prompt programmatically via the naano_system_prompt WordPress filter.",
         "naano-ai-website-builder",
     ); ?></p>
				</td>
			</tr>
		</table>
	</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#variables -->

		<!-- ============================================================
		     FIRECRAWL
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-firecrawl" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e(
       "Firecrawl — Web Scraping",
       "naano-ai-website-builder",
   ); ?></h2>
			<p class="description">
				<?php esc_html_e(
        "Firecrawl turns any website URL into clean, structured HTML that the AI can actually read.",
        "naano-ai-website-builder",
    ); ?>
				<?php esc_html_e(
        "When you add a URL reference to a section, the built-in scraper only grabs basic text. Firecrawl renders JavaScript, handles SPAs, and returns rich HTML — giving the AI much better context about layouts, components, and design patterns from the reference site.",
        "naano-ai-website-builder",
    ); ?>
			</p>
			<p class="description" style="margin-top:8px;">
				<?php printf(
        /* translators: %s = link to firecrawl.dev */
        esc_html__(
            "Get your free API key at %s (500 credits/month on the free plan).",
            "naano-ai-website-builder",
        ),
        '<a href="https://www.firecrawl.dev" target="_blank" rel="noopener">firecrawl.dev</a>',
    ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="naano_firecrawl_api_key"><?php esc_html_e(
          "Firecrawl API Key",
          "naano-ai-website-builder",
      ); ?></label>
					</th>
					<td>
						<input type="password"
							   name="naano_firecrawl_api_key"
							   id="naano_firecrawl_api_key"
							   class="regular-text"
							   value="<?php echo esc_attr(get_option("naano_firecrawl_api_key", "")); ?>"
							   autocomplete="new-password">
						<button type="button" class="button" id="naano-save-firecrawl-key-btn" style="margin-left:8px;">
							<?php esc_html_e("Save Key", "naano-ai-website-builder"); ?>
						</button>
						<span class="naano-loading" id="naano-save-firecrawl-loading" style="display:none;">
							<span class="spinner is-active"></span>
						</span>
						<span id="naano-save-firecrawl-result" style="display:none;margin-left:8px;"></span>
						<p class="description">
							<?php esc_html_e(
           "When set, URL references will be scraped with Firecrawl instead of the basic built-in fetcher. Results are cached so the same URL is never scraped twice.",
           "naano-ai-website-builder",
       ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="naano-test-connection-row">
				<button type="button" class="button" id="naano-test-firecrawl-btn">
					<?php esc_html_e("Test Connection", "naano-ai-website-builder"); ?>
				</button>
				<span class="naano-loading" id="naano-test-firecrawl-loading" style="display:none;">
					<span class="spinner is-active"></span>
				</span>
				<div id="naano-test-firecrawl-result" class="naano-test-result" style="display:none;"></div>
			</div>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#firecrawl -->

		<!-- ============================================================
		     TRANSLATION
		     ============================================================ -->
		<div class="naano-settings-panel" id="naano-panel-translation" style="display:none;">
		<div class="naano-card" style="margin-top:20px;">
			<h2><?php esc_html_e(
       "Translation Languages",
       "naano-ai-website-builder",
   ); ?></h2>
			<p class="description">
				<?php esc_html_e(
        "Define the languages you want to translate your pages into. Each language gets its own URL variant (e.g. /my-page/es/).",
        "naano-ai-website-builder",
    ); ?>
			</p>

			<table class="form-table" style="margin-bottom:16px;">
				<tr>
					<th scope="row"><?php esc_html_e(
         "Default Language Label",
         "naano-ai-website-builder",
     ); ?></th>
					<td>
						<input type="text"
							   name="naano_default_lang_label"
							   id="naano_default_lang_label"
							   class="regular-text"
							   value="<?php echo esc_attr($naano_default_lang_label); ?>"
							   placeholder="<?php esc_attr_e(
              "e.g. English",
              "naano-ai-website-builder",
          ); ?>">
						<p class="description"><?php esc_html_e(
          "Label shown for the original (default) language in language switchers. Leave blank to show &ldquo;Default&rdquo;.",
          "naano-ai-website-builder",
      ); ?></p>
					</td>
				</tr>
			</table>

			<table class="form-table">
				<thead>
					<tr>
						<th><?php esc_html_e("Language Code", "naano-ai-website-builder"); ?></th>
						<th><?php esc_html_e("Language Label", "naano-ai-website-builder"); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="naano-languages-tbody">
					<?php foreach ($naano_languages as $naano_lang): ?>
					<tr class="naano-language-row">
						<td>
							<input type="text"
								   name="naano_lang_codes[]"
								   class="regular-text"
								   value="<?php echo esc_attr($naano_lang["code"] ?? ""); ?>"
								   placeholder="<?php esc_attr_e("e.g. es", "naano-ai-website-builder"); ?>"
								   style="max-width:100px;">
						</td>
						<td>
							<input type="text"
								   name="naano_lang_labels[]"
								   class="regular-text"
								   value="<?php echo esc_attr($naano_lang["label"] ?? ""); ?>"
								   placeholder="<?php esc_attr_e(
               "e.g. Spanish",
               "naano-ai-website-builder",
           ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-language">
								<?php esc_html_e("Remove", "naano-ai-website-builder"); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php if (empty($naano_languages)): ?>
					<tr class="naano-language-row">
						<td>
							<input type="text" name="naano_lang_codes[]" class="regular-text"
								   placeholder="<?php esc_attr_e(
               "e.g. es",
               "naano-ai-website-builder",
           ); ?>" style="max-width:100px;">
						</td>
						<td>
							<input type="text" name="naano_lang_labels[]" class="regular-text"
								   placeholder="<?php esc_attr_e(
               "e.g. Spanish",
               "naano-ai-website-builder",
           ); ?>">
						</td>
						<td>
							<button type="button" class="button naano-remove-language">
								<?php esc_html_e("Remove", "naano-ai-website-builder"); ?>
							</button>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<button type="button" class="button" id="naano-add-language-btn">
				+ <?php esc_html_e("Add Language", "naano-ai-website-builder"); ?>
			</button>
		</div><!-- /.naano-card -->
		</div><!-- /.naano-settings-panel#translation -->

		<?php submit_button(__("Save Settings", "naano-ai-website-builder")); ?>
	</form>
</div>
