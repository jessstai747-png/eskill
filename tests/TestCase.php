<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Gracefully skip when DB is unavailable (constant only defined in Integration-suite bootstrap path,
        // so this guard is a no-op for Unit and Feature suites).
        if (defined('PHPUNIT_DB_AVAILABLE') && !PHPUNIT_DB_AVAILABLE) {
            $this->markTestSkipped(
                'Database unavailable: ' . ($GLOBALS['phpunit_db_error'] ?? 'connection refused')
            );
        }

        // Iniciar sessão para testes que dependem dela
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        // Limpar sessão após cada teste
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
        }

        parent::tearDown();
    }
}
