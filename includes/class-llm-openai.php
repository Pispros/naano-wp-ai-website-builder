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
            "messages" => $all_messages,
        ];

        $is_reasoning_model = $this->is_reasoning_model($this->model);

        // Reasoning-family models (gpt-5*, o1, o3, o4) reject any `temperature`
        // value other than the default (1) and frequently 400 when it's sent
        // explicitly through OpenAI-compatible gateways. Only send temperature
        // for non-reasoning chat models (gpt-4*, gpt-3.5*, gpt-5-chat-latest…).
        if (!$is_reasoning_model) {
            $payload["temperature"] = 1;
        }

        // The reasoning_effort and verbosity parameters control how long the
        // model "thinks" before producing tokens and how verbose the answer
        // is. Default reasoning effort is typically "medium", which can burn
        // 60-120s of hidden reasoning on a complex HTML generation request —
        // enough to push a single LLM call past a 120s LSAPI ceiling on
        // shared LiteSpeed hosts.
        //
        // For HTML generation with a long, structured system prompt, the
        // marginal quality gain from extended reasoning is small while the
        // wall-clock cost is large. We force the lowest-effort setting so
        // the model emits tokens almost immediately. The valid lowest value
        // depends on the model:
        //   - gpt-5 (original):                       "minimal"
        //   - gpt-5.1, gpt-5.2, gpt-5.4, gpt-5.5:     "none"
        //   - o1 / o3 / o4 series:                    "minimal" (when supported)
        //
        // Verbosity accepts only "low", "medium", "high" — we keep "medium"
        // to avoid runaway over-generation while still producing substantial
        // sections.
        if ($is_reasoning_model) {
            $payload["reasoning_effort"] = $this->lowest_reasoning_effort(
                $this->model,
            );
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
     * Detect whether the given model is a reasoning-family model (gpt-5*,
     * o1*, o3*, o4*, etc.). Reasoning models reject `temperature != 1` and
     * support `reasoning_effort` + `verbosity`. Non-reasoning chat variants
     * such as `gpt-5-chat-latest` and `gpt-5-chat` follow standard chat
     * semantics and accept `temperature`.
     *
     * @param string $model Model identifier.
     * @return bool
     */
    private function is_reasoning_model(string $model): bool
    {
        $lower = strtolower($model);

        // gpt-5-chat / gpt-5-chat-latest are the non-reasoning chat variants.
        if (
            stripos($lower, "gpt-5-chat") === 0 ||
            stripos($lower, "gpt-5.5-chat") === 0
        ) {
            return false;
        }

        return stripos($lower, "gpt-5") === 0 ||
            stripos($lower, "o1") === 0 ||
            stripos($lower, "o3") === 0 ||
            stripos($lower, "o4") === 0;
    }

    /**
     * Return the lowest valid `reasoning_effort` value for the given model.
     *
     * OpenAI changed the accepted values between GPT-5 generations:
     *   - gpt-5 (original):                  minimal | low | medium | high
     *   - gpt-5.1 / 5.2 / 5.4 / 5.5:         none    | low | medium | high (+ xhigh on 5.2+)
     *   - o1 / o3 / o4 series:               minimal | low | medium | high
     *
     * Sending "none" to a model that doesn't accept it returns HTTP 400,
     * and so does sending "minimal" to gpt-5.1+. We pick the right token
     * for each generation.
     *
     * @param string $model Model identifier.
     * @return string
     */
    private function lowest_reasoning_effort(string $model): string
    {
        $lower = strtolower($model);

        // gpt-5.1 and newer ("gpt-5.1", "gpt-5.2", "gpt-5.4", "gpt-5.5", ...)
        // accept "none". A simple prefix check catches all of them while
        // leaving the original "gpt-5" / "gpt-5-mini" / "gpt-5-nano" on
        // "minimal".
        if (preg_match('/^gpt-5\.\d/i', $lower)) {
            return "none";
        }

        return "minimal";
    }

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
