<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CentralizedLogService
 *
 * Thin logging wrapper used by AI services (DecisionEngineService, etc.).
 * Delegates to LoggerService for actual log writing.
 */
class CentralizedLogService
{
    private LoggerService $logger;

    public function __construct()
    {
        $this->logger = new LoggerService('ai');
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->log('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->log('warning', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->log('debug', $message, $context);
    }
}
