<?php
/**
 * Kimi (Moonshot) LLM Adapter
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Adapter for Moonshot Kimi API (OpenAI-compatible format).
 */
class Naano_LLM_Kimi implements Naano_LLM_Provider_Interface
{
    private const API_ENDPOINT = "https://api.moonshot.cn/v1/chat/completions";
    private const DEFAULT_MODEL = "kimi-k2.5";
    private const MAX_TOKENS = 40000;
    private const TIMEOUT_SECONDS = 600;

    private string $api_key;
    private string $model;

    /**
     * Constructor.
     *
     * @param string $api_key Moonshot API key.
     * @param string $model   Override model string.
     */
    public function __construct(string $api_key, string $model = "")
    {
        $this->api_key = $api_key;
        $this->model = $model ?: self::DEFAULT_MODEL;
    }

    /**
     * {@inheritdoc}
     */
    public function send(
        string $system_prompt,
        array $messages,
        array $images = [],
    ): string {
        Naano_LLM_Utils::prepare_long_running_request();

        $all_messages = array_merge(
            [["role" => "system", "content" => $system_prompt]],
            $this->convert_messages($messages, $images),
        );

        $payload = [
            "model" => $this->model,
            "max_completion_tokens" => self::MAX_TOKENS,
            "messages" => $all_messages,
        ];

        // kimi-k2.5 does not allow modifying temperature.
        if (stripos($this->model, "kimi-k2.5") === false) {
            $payload["temperature"] = 0.6;
        }

        $response = $this->request($payload);

        $text = $response["choices"][0]["message"]["content"] ?? null;
        if (null === $text) {
            throw new RuntimeException(
                "Unexpected Kimi response structure: " .
                    wp_json_encode($response),
            );
        }

        return (string) $text;
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection(): array
    {
        $start = microtime(true);
        try {
            $this->send("You are a helpful assistant.", [
                ["role" => "user", "content" => "Reply with: OK"],
            ]);
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                "success" => true,
                "model" => $this->model,
                "latency_ms" => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                "success" => false,
                "model" => $this->model,
                "latency_ms" => 0,
                "error" => $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert messages; Kimi supports text-only in standard OpenAI format.
     * Images are appended as text description notes since Kimi is text-first.
     *
     * @param array $messages Conversation messages.
     * @param array $images   Image data arrays (used as context note).
     * @return array
     */
    private function convert_messages(array $messages, array $images): array
    {
        $converted = [];
        foreach ($messages as $index => $msg) {
            $content = $msg["content"];

            // For the last user message, note attached images if any.
            if (
                $index === array_key_last($messages) &&
                $msg["role"] === "user" &&
                !empty($images)
            ) {
                $count = count($images);
                $content .= "\n\n[Note: {$count} reference image(s) are attached. Please consider their style and layout in your design.]";
            }

            $converted[] = [
                "role" => $msg["role"],
                "content" => $content,
            ];
        }
        return $converted;
    }

    /**
     * Execute the cURL request.
     *
     * @param array $payload JSON payload.
     * @return array Decoded response.
     * @throws RuntimeException On error.
     */
    private function request(array $payload): array
    {
        $json_body = wp_json_encode($payload);
        if ($json_body === false) {
            throw new RuntimeException(
                "Kimi request: failed to encode payload as JSON (" .
                    esc_html(json_last_error_msg()) .
                    ").",
            );
        }

        $args = Naano_LLM_Utils::default_request_args(self::TIMEOUT_SECONDS) + [
            "headers" => [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $this->api_key,
            ],
            "body" => $json_body,
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            throw new RuntimeException("Kimi HTTP error: " . esc_html($error));
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        if (!is_string($body)) {
            throw new RuntimeException(
                "Kimi HTTP: wp_remote_retrieve_body returned non-string (" .
                    esc_html(gettype($body)) .
                    ").",
            );
        }

        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data["error"]["message"] ?? $body;
            throw new RuntimeException(
                esc_html(sprintf("Kimi API error (HTTP %d): %s", $code, $msg)),
            );
        }

        return (array) $data;
    }
}
