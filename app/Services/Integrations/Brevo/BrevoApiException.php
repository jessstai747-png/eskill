<?php

namespace App\Services\Integrations\Brevo;

class BrevoApiException extends \RuntimeException
{
    private int $statusCode;
    private array $details;

    public function __construct(string $message, int $statusCode, array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}

