<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AIProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isRetryable(): bool
    {
        // 429 (rate limit) and 5xx (server errors) are transient and safe to retry
        return $this->statusCode === 429 || $this->statusCode >= 500;
    }
}
