<?php
/**
 * Claude (Anthropic) LLM Adapter
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Adapter for Anthropic Claude API.
 */
class Naano_LLM_Claude implements Naano_LLM_Provider_Interface
{
    private const API_ENDPOINT = "https://api.anthropic.com/v1/messages";
    private const API_VERSION = "2023-06-01";
    private const DEFAULT_MODEL = "claude-sonnet-4-20250514";
    private const MAX_TOKENS = 40000;
    private const TIMEOUT_SECONDS = 600;

    private string $api_key;
    private string $model;

    /**
     * Constructor.
     *
     * @param string $api_key Anthropic API key.
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

        // Build message array; inject images into last user message.
        $built_messages = $this->build_messages($messages, $images);

        $payload = [
            "model" => $this->model,
            "max_tokens" => self::MAX_TOKENS,
            "temperature" => 1,
            "system" => $system_prompt,
            "messages" => $built_messages,
        ];

        $response = $this->request($payload);

        if (isset($response["content"][0]["text"])) {
            return (string) $response["content"][0]["text"];
        }

        throw new RuntimeException(
            "Unexpected Claude response structure: " .
                wp_json_encode($response),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection(): array
    {
        $start = microtime(true);
        try {
            $result = $this->send("You are a helpful assistant.", [
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
     * Build the messages array, injecting images as base64 content blocks.
     *
     * @param array $messages Conversation messages.
     * @param array $images   Image data arrays.
     * @return array
     */
    private function build_messages(array $messages, array $images): array
    {
        if (empty($images)) {
            return $messages;
        }

        // Attach images to the last user message as content array.
        $built = [];
        foreach ($messages as $index => $msg) {
            if (
                $index === array_key_last($messages) &&
                $msg["role"] === "user"
            ) {
                $content = [];
                foreach ($images as $img) {
                    $content[] = [
                        "type" => "image",
                        "source" => [
                            "type" => "base64",
                            "media_type" => $img["mime_type"] ?? "image/jpeg",
                            "data" => $img["data"],
                        ],
                    ];
                }
                $content[] = [
                    "type" => "text",
                    "text" => $msg["content"],
                ];
                $built[] = [
                    "role" => "user",
                    "content" => $content,
                ];
            } else {
                $built[] = $msg;
            }
        }
        return $built;
    }

    /**
     * Execute the HTTP request via WordPress HTTP API.
     *
     * @param array $payload JSON payload.
     * @return array Decoded response array.
     * @throws RuntimeException On HTTP error.
     */
    private function request(array $payload): array
    {
        $json_body = wp_json_encode($payload);
        if ($json_body === false) {
            throw new RuntimeException(
                "Claude request: failed to encode payload as JSON (" .
                    esc_html(json_last_error_msg()) .
                    ").",
            );
        }

        $args = Naano_LLM_Utils::default_request_args(self::TIMEOUT_SECONDS) + [
            "headers" => [
                "Content-Type" => "application/json",
                "x-api-key" => $this->api_key,
                "anthropic-version" => self::API_VERSION,
            ],
            "body" => $json_body,
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            throw new RuntimeException("Claude HTTP error: " . esc_html($error));
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        if (!is_string($body)) {
            throw new RuntimeException(
                "Claude HTTP: wp_remote_retrieve_body returned non-string (" .
                    esc_html(gettype($body)) .
                    ").",
            );
        }

        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data["error"]["message"] ?? $body;
            throw new RuntimeException(
                esc_html(sprintf("Claude API error (HTTP %d): %s", $code, $msg)),
            );
        }

        return (array) $data;
    }
}
