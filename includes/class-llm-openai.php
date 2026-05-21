<?php
/**
 * OpenAI LLM Adapter
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Adapter for OpenAI API (chat/completions).
 */
class Naano_LLM_OpenAI implements Naano_LLM_Provider_Interface
{
    private const API_ENDPOINT = "https://api.openai.com/v1/chat/completions";
    private const DEFAULT_MODEL = "gpt-5.5";
    private const MAX_TOKENS = 40000;
    private const TIMEOUT_SECONDS = 600;

    private string $api_key;
    private string $model;

    /**
     * Constructor.
     *
     * @param string $api_key OpenAI API key.
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
            $this->build_messages($messages, $images),
        );

        $payload = [
            "model" => $this->model,
            "max_completion_tokens" => self::MAX_TOKENS,
            "temperature" => 1,
            "messages" => $all_messages,
        ];

        // GPT-5.x family (gpt-5, gpt-5.5, gpt-5-pro, etc.) supports a
        // `reasoning_effort` param that controls how long the model thinks
        // before producing tokens. Default is typically "medium" which can
        // burn 60-120s of hidden reasoning on a complex HTML generation
        // request — enough to push a single LLM call past a 120s LSAPI
        // ceiling on shared LiteSpeed hosts.
        //
        // For HTML generation with a long, structured system prompt, the
        // marginal quality gain from extended reasoning is small while the
        // wall-clock cost is large. We force "minimal" so the model emits
        // tokens almost immediately. The system prompt's design rules already
        // do most of the heavy lifting; reasoning_effort=minimal still
        // produces production-quality HTML in our tests.
        //
        // The param is silently ignored by older non-reasoning models
        // (gpt-4o, gpt-4-turbo, etc.) so it's safe to send unconditionally
        // for any model whose name starts with "gpt-5".
        if (
            stripos($this->model, "gpt-5") === 0 ||
            stripos($this->model, "o1") === 0 ||
            stripos($this->model, "o3") === 0 ||
            stripos($this->model, "o4") === 0
        ) {
            $payload["reasoning_effort"] = "none";
            // verbosity governs response length tendency; "none" keeps
            // sections substantial without runaway over-generation.
            $payload["verbosity"] = "medium";
        }

        $response = $this->request($payload);

        $text = $response["choices"][0]["message"]["content"] ?? null;
        if (null === $text) {
            throw new RuntimeException(
                "Unexpected OpenAI response structure: " .
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
     * Build messages with image support via OpenAI Vision format.
     *
     * Images are injected into the last user message as content parts
     * using the `image_url` type with base64-encoded data URIs.
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

        $built = [];
        foreach ($messages as $index => $msg) {
            if (
                $index === array_key_last($messages) &&
                $msg["role"] === "user"
            ) {
                $content = [];

                // Add text first (OpenAI recommends text after images, but either works).
                $content[] = [
                    "type" => "text",
                    "text" => $msg["content"],
                ];

                // Attach images as base64 data URIs.
                foreach ($images as $img) {
                    $mime = $img["mime_type"] ?? "image/jpeg";
                    $content[] = [
                        "type" => "image_url",
                        "image_url" => [
                            "url" =>
                                "data:" . $mime . ";base64," . $img["data"],
                            "detail" => "high",
                        ],
                    ];
                }

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
                "OpenAI request: failed to encode payload as JSON (" .
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
            throw new RuntimeException("OpenAI HTTP error: " . esc_html($error));
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        if (!is_string($body)) {
            throw new RuntimeException(
                "OpenAI HTTP: wp_remote_retrieve_body returned non-string (" .
                    esc_html(gettype($body)) .
                    ").",
            );
        }

        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data["error"]["message"] ?? $body;

            // Provide clear guidance for common OpenAI errors.
            if ($code === 429) {
                throw new RuntimeException(
                    "OpenAI rate limit reached. Please wait a moment and try again. " .
                        "Check your usage at https://platform.openai.com/usage.",
                );
            }

            if ($code === 401) {
                throw new RuntimeException(
                    "OpenAI authentication failed. Please verify your API key at https://platform.openai.com/api-keys.",
                );
            }

            throw new RuntimeException(
                esc_html(sprintf("OpenAI API error (HTTP %d): %s", $code, $msg)),
            );
        }

        return (array) $data;
    }
}
