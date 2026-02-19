<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
