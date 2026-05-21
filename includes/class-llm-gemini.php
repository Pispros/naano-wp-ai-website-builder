<?php
/**
 * Gemini (Google) LLM Adapter
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Adapter for Google Gemini API.
 */
class Naano_LLM_Gemini implements Naano_LLM_Provider_Interface
{
    private const API_BASE = "https://generativelanguage.googleapis.com/v1beta/models/";
    private const DEFAULT_MODEL = "gemini-2.5-flash";
    private const MAX_TOKENS = 40000;
    private const TIMEOUT_SECONDS = 600;

    private string $api_key;
    private string $model;

    /**
     * Constructor.
     *
     * @param string $api_key Google Gemini API key.
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

        $endpoint =
            self::API_BASE .
            rawurlencode($this->model) .
            ":generateContent?key=" .
            rawurlencode($this->api_key);

        $contents = $this->build_contents($messages, $images);

        $payload = [
            "system_instruction" => [
                "parts" => [["text" => $system_prompt]],
            ],
            "contents" => $contents,
            "generationConfig" => [
                "maxOutputTokens" => self::MAX_TOKENS,
                "temperature" => 1.0,
                "responseMimeType" => "text/plain",
            ],
        ];

        $response = $this->request($endpoint, $payload);

        $text =
            $response["candidates"][0]["content"]["parts"][0]["text"] ?? null;
        if (null === $text) {
            throw new RuntimeException(
                "Unexpected Gemini response structure: " .
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
     * Convert our standard messages array into Gemini contents format.
     *
     * @param array $messages Conversation messages.
     * @param array $images   Image data arrays.
     * @return array
     */
    private function build_contents(array $messages, array $images): array
    {
        $role_map = ["user" => "user", "assistant" => "model"];
        $contents = [];

        foreach ($messages as $index => $msg) {
            $parts = [];

            // Attach images to the last user message.
            if (
                $index === array_key_last($messages) &&
                $msg["role"] === "user" &&
                !empty($images)
            ) {
                foreach ($images as $img) {
                    $parts[] = [
                        "inlineData" => [
                            "mimeType" => $img["mime_type"] ?? "image/jpeg",
                            "data" => $img["data"],
                        ],
                    ];
                }
            }

            $parts[] = ["text" => $msg["content"]];

            $contents[] = [
                "role" => $role_map[$msg["role"]] ?? "user",
                "parts" => $parts,
            ];
        }

        return $contents;
    }

    /**
     * Execute the HTTP request via WordPress HTTP API.
     *
     * @param string $endpoint Full URL with key.
     * @param array  $payload  JSON payload.
     * @return array Decoded response.
     * @throws RuntimeException On error.
     */
    private function request(string $endpoint, array $payload): array
    {
        $json_body = wp_json_encode($payload);
        if ($json_body === false) {
            throw new RuntimeException(
                "Gemini request: failed to encode payload as JSON (" .
                    esc_html(json_last_error_msg()) .
                    ").",
            );
        }

        $args = Naano_LLM_Utils::default_request_args(self::TIMEOUT_SECONDS) + [
            "headers" => ["Content-Type" => "application/json"],
            "body" => $json_body,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            throw new RuntimeException("Gemini HTTP error: " . esc_html($error));
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        if (!is_string($body)) {
            throw new RuntimeException(
                "Gemini HTTP: wp_remote_retrieve_body returned non-string (" .
                    esc_html(gettype($body)) .
                    ").",
            );
        }

        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data["error"]["message"] ?? $body;

            // Provide a clear, actionable message for quota / rate-limit errors.
            if ($code === 429) {
                $retry_match = [];
                if (preg_match("/retry in ([\d.]+)s/i", $msg, $retry_match)) {
                    $wait = (int) ceil((float) $retry_match[1]);
                    $hint = sprintf(
                        "Rate limit reached — please retry in %d seconds.",
                        $wait,
                    );
                } elseif (strpos($msg, "free_tier") !== false) {
                    $hint =
                        "Your Gemini API key has exceeded the free-tier quota for this model. " .
                        'Try switching to "gemini-2.5-flash" in Settings → Model Override, ' .
                        "or enable billing at https://aistudio.google.com/.";
                } else {
                    $hint =
                        "Gemini API rate limit reached. Please wait a moment and try again.";
                }
                throw new RuntimeException(esc_html($hint));
            }

            throw new RuntimeException(
                esc_html(sprintf("Gemini API error (HTTP %d): %s", $code, $msg)),
            );
        }

        return (array) $data;
    }
}
