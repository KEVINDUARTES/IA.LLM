<?php

namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService implements AIServiceInterface
{
    private const API_URL         = 'https://api.groq.com/openai/v1/chat/completions';
    private const DEFAULT_MODEL   = 'llama-3.3-70b-versatile';
    private const TIMEOUT_SECONDS = 60;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function structuredCompletion(string $systemPrompt, string $userPrompt): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::API_URL, [
                    'model'           => $this->model,
                    'temperature'     => 0,  // deterministic output for structured extraction
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                ]);

            if ($response->failed()) {
                $statusCode = $response->status();
                $body       = $response->json();
                $message    = $body['error']['message'] ?? "OpenAI API error (HTTP {$statusCode})";

                Log::warning('OpenAI API error', [
                    'status'  => $statusCode,
                    'message' => $message,
                ]);

                throw new AIProviderException($message, $statusCode);
            }

            $content = $response->json('choices.0.message.content');

            if (empty($content)) {
                throw new AIProviderException('OpenAI returned an empty response content.');
            }

            $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (ConnectionException $e) {
            // Network timeout — treat as retryable 503
            throw new AIProviderException(
                "Connection to OpenAI timed out: {$e->getMessage()}",
                503,
                $e,
            );
        } catch (RequestException $e) {
            throw new AIProviderException(
                "HTTP request to OpenAI failed: {$e->getMessage()}",
                $e->response->status(),
                $e,
            );
        } catch (\JsonException $e) {
            throw new AIProviderException(
                "Failed to parse OpenAI JSON response: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }
}
