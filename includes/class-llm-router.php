<?php
/**
 * LLM Router – selects the correct provider adapter and sanitizes response.
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Routes generation requests to the configured LLM adapter.
 */
class Naano_LLM_Router
{
    private Naano_LLM_Provider_Interface $adapter;

    /**
     * Constructor.
     *
     * @param string $provider One of 'claude', 'gemini', 'kimi', 'openai', 'deepseek'.
     * @param string $api_key  Provider API key.
     * @param array  $options  Optional overrides (model, …).
     * @throws InvalidArgumentException For unknown providers.
     */
    public function __construct(
        string $provider,
        string $api_key,
        array $options = [],
    ) {
        $this->adapter = $this->create_adapter($provider, $api_key, $options);
    }

    /**
     * Return the raw adapter (useful for test_connection).
     *
     * @return Naano_LLM_Provider_Interface
     */
    public function get_adapter(): Naano_LLM_Provider_Interface
    {
        return $this->adapter;
    }

    /**
     * Generate HTML via the LLM, then sanitize.
     *
     * @param string $system_prompt System instructions.
     * @param array  $messages      Conversation history.
     * @param array  $images        Optional image data arrays.
     * @return string Sanitized HTML.
     * @throws RuntimeException On API failure.
     */
    public function generate(
        string $system_prompt,
        array $messages,
        array $images = [],
    ): string {
        $raw = $this->adapter->send($system_prompt, $messages, $images);
        return Naano_HTML_Sanitizer::clean($raw);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Instantiate the correct adapter.
     *
     * @param string $provider Provider name.
     * @param string $api_key  API key.
     * @param array  $options  Extra options.
     * @return Naano_LLM_Provider_Interface
     * @throws InvalidArgumentException For unknown providers.
     */
    private function create_adapter(
        string $provider,
        string $api_key,
        array $options,
    ): Naano_LLM_Provider_Interface {
        $model = $options["model"] ?? "";

        return match (strtolower($provider)) {
            "claude" => new Naano_LLM_Claude($api_key, $model),
            "gemini" => new Naano_LLM_Gemini($api_key, $model),
            "kimi" => new Naano_LLM_Kimi($api_key, $model),
            "openai" => new Naano_LLM_OpenAI($api_key, $model),
            "deepseek" => new Naano_LLM_DeepSeek($api_key, $model),
            default => throw new InvalidArgumentException(
                sprintf("Unknown LLM provider: %s", esc_html($provider)),
            ),
        };
    }
}
