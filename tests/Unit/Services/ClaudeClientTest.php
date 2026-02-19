<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ClaudeClient;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for ClaudeClient
 *
 * Tests constructor validation, usage stats extraction,
 * and method existence.
 *
 * @covers \App\Services\ClaudeClient
 */
class ClaudeClientTest extends TestCase
{
    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function testConstructorThrowsRuntimeExceptionWithoutKey(): void
    {
        // Ensure no env key
        $original = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        unset($_ENV['ANTHROPIC_API_KEY']);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('ANTHROPIC_API_KEY');
            new ClaudeClient('');
        } finally {
            if ($original !== null) {
                $_ENV['ANTHROPIC_API_KEY'] = $original;
            }
        }
    }

    public function testConstructorAcceptsApiKey(): void
    {
        $client = new ClaudeClient('test-key-123');
        $this->assertInstanceOf(ClaudeClient::class, $client);
    }

    // =========================================================================
    // INSTANTIATION VIA REFLECTION
    // =========================================================================

    public function testServiceHasRequiredPublicMethods(): void
    {
        $ref = new ReflectionClass(ClaudeClient::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $methods = ['complete', 'generateFeatureList', 'implementFeature', 'testConnection', 'getUsageStats'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($instance, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // getUsageStats — pure array extraction
    // =========================================================================

    public function testGetUsageStatsExtractsTokenCounts(): void
    {
        $client = new ClaudeClient('test-key');
        $response = [
            'usage' => [
                'input_tokens' => 150,
                'output_tokens' => 320,
            ],
        ];

        $stats = $client->getUsageStats($response);

        $this->assertSame(150, $stats['input_tokens']);
        $this->assertSame(320, $stats['output_tokens']);
    }

    public function testGetUsageStatsReturns0ForMissingUsage(): void
    {
        $client = new ClaudeClient('test-key');
        $response = [];

        $stats = $client->getUsageStats($response);

        $this->assertSame(0, $stats['input_tokens']);
        $this->assertSame(0, $stats['output_tokens']);
    }

    public function testGetUsageStatsReturns0ForPartialUsage(): void
    {
        $client = new ClaudeClient('test-key');
        $response = ['usage' => ['input_tokens' => 42]];

        $stats = $client->getUsageStats($response);

        $this->assertSame(42, $stats['input_tokens']);
        $this->assertSame(0, $stats['output_tokens']);
    }

    // =========================================================================
    // DEFAULTS
    // =========================================================================

    public function testDefaultPropertiesViaReflection(): void
    {
        $ref = new ReflectionClass(ClaudeClient::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $modelProp = $ref->getProperty('model');
        $modelProp->setAccessible(true);
        $this->assertStringContainsString('claude', $modelProp->getValue($instance));

        $maxTokensProp = $ref->getProperty('maxTokens');
        $maxTokensProp->setAccessible(true);
        $this->assertSame(4096, $maxTokensProp->getValue($instance));

        $versionProp = $ref->getProperty('version');
        $versionProp->setAccessible(true);
        $this->assertSame('2023-06-01', $versionProp->getValue($instance));
    }
}
