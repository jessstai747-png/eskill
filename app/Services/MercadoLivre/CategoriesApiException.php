<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

class CategoriesApiException extends \RuntimeException
{
    private int $statusCode;
    private string $apiErrorCode;
    private array $details;

    public function __construct(
        string $message,
        int $statusCode,
        string $apiErrorCode = 'unknown_error',
        array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->apiErrorCode = $apiErrorCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getApiErrorCode(): string
    {
        return $this->apiErrorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
