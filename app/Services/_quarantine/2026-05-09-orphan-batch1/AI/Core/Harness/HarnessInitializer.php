<?php

declare(strict_types=1);

namespace App\Services\AI\Core\Harness;

use App\Database;
use Exception;

/**
 * Validates the environment before the Agent Harness starts.
 * Based on the 'Initializer' concept from Anthropic's 'Effective Harnesses'.
 */
class HarnessInitializer
{
    /**
     * Run all checks.
     * @return bool True if environment is healthy.
     */
    public function validateEnvironment(): bool
    {
        echo "🔍 [Harness] Initializing environment checks...\n";

        $checks = [
            'database' => fn() => $this->checkDatabase(),
            'migrations' => fn() => $this->checkMigrations(),
            'configuration' => fn() => $this->checkConfiguration(),
            'apis' => fn() => $this->checkApiConnectivity()
        ];

        $allPassed = true;

        foreach ($checks as $name => $check) {
            echo "   Checking {$name}... ";
            if ($check()) {
                echo "✅\n";
            } else {
                echo "❌ FAILED\n";
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    private function checkDatabase(): bool
    {
        try {
            $db = Database::getInstance();
            return true;
        } catch (Exception $e) {
            echo "(" . $e->getMessage() . ") ";
            return false;
        }
    }

    private function checkMigrations(): bool
    {
        try {
            $db = Database::getInstance();
            $tables = [
                'ai_optimization_queue',
                'ai_ab_tests',
                'ai_audit_log'
            ];

            foreach ($tables as $table) {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                $result = $db->query("SHOW TABLES LIKE " . $db->quote($safeTable))->fetch();
                if (!$result) return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkConfiguration(): bool
    {
        // Check loaded environment variables
        if (!empty($_ENV['OPENAI_API_KEY']) || !empty($_ENV['ANTHROPIC_API_KEY'])) {
            return true;
        }

        // Logic adapted from ai-setup-check.php
        $envFile = __DIR__ . '/../../../../../../.env.ai';
        if (!file_exists($envFile) && file_exists(__DIR__ . '/../../../../../../.env')) {
            // Fallback to main .env if .env.ai doesn't exist (older setups)
            $envFile = __DIR__ . '/../../../../../../.env';
        }

        if (!file_exists($envFile)) return false;

        $content = file_get_contents($envFile);
        $hasKeys = strpos($content, 'OPENAI_API_KEY') !== false || strpos($content, 'ANTHROPIC_API_KEY') !== false;

        return $hasKeys;
    }

    private function checkApiConnectivity(): bool
    {
        // Simple connectivity check (google.com) to ensure network is up
        $connected = @fsockopen("www.google.com", 80);
        if ($connected) {
            fclose($connected);
            return true;
        }
        return false;
    }
}
