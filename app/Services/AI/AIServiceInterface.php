<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * Send a prompt and receive a structured JSON response.
     *
     * @param  string  $systemPrompt  Instructions for the model
     * @param  string  $userPrompt    The actual content to process
     * @return array                  Decoded JSON response from the model
     *
     * @throws \App\Exceptions\AIProviderException
     */
    public function structuredCompletion(string $systemPrompt, string $userPrompt): array;
}
