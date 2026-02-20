<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RealTimeNotificationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * @covers \App\Services\RealTimeNotificationService
 */
class RealTimeNotificationServiceTest extends TestCase
{
    /**
     * Test getAvailableSounds returns expected structure
     */
    public function testGetAvailableSoundsReturnsExpectedStructure(): void
    {
        $sounds = RealTimeNotificationService::getAvailableSounds();

        $this->assertIsArray($sounds);
        $this->assertNotEmpty($sounds);

        foreach ($sounds as $sound) {
            $this->assertArrayHasKey('id', $sound);
            $this->assertArrayHasKey('name', $sound);
            $this->assertArrayHasKey('file', $sound);
            $this->assertStringEndsWith('.mp3', $sound['file']);
        }
    }

    /**
     * Test getAvailableSounds contains required notification types
     */
    public function testGetAvailableSoundsContainsRequiredTypes(): void
    {
        $sounds = RealTimeNotificationService::getAvailableSounds();
        $soundIds = array_column($sounds, 'id');

        $requiredTypes = [
            'order_notification',
            'question_notification',
            'message_notification',
            'alert_notification',
        ];

        foreach ($requiredTypes as $type) {
            $this->assertContains($type, $soundIds, "Missing required sound type: {$type}");
        }
    }

    /**
     * Test getSettings returns default values when no DB row exists
     */
    public function testGetSettingsReturnsDefaultsWhenNoRow(): void
    {
        $service = $this->createMockServiceWithDbResult(false);

        $settings = $service->getSettings(999);

        $this->assertTrue($settings['sound_enabled']);
        $this->assertSame(80, $settings['sound_volume']);
        $this->assertSame('order_notification', $settings['sound_order']);
        $this->assertSame('question_notification', $settings['sound_question']);
        $this->assertTrue($settings['desktop_enabled']);
        $this->assertSame(30, $settings['polling_interval']);
        $this->assertTrue($settings['email_orders']);
        $this->assertTrue($settings['email_questions']);
        $this->assertFalse($settings['whatsapp_orders']);
    }

    /**
     * Test getSettings returns converted boolean and integer fields from DB
     */
    public function testGetSettingsConvertsDbTypes(): void
    {
        $dbRow = [
            'sound_enabled' => 1,
            'sound_volume' => '50',
            'sound_order' => 'custom_sound',
            'sound_question' => 'question_notification',
            'sound_message' => 'message_notification',
            'desktop_enabled' => 0,
            'polling_interval' => '60',
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '07:00:00',
            'email_orders' => 1,
            'email_questions' => 0,
            'whatsapp_orders' => 1,
            'whatsapp_questions' => 0,
            'whatsapp_low_stock' => 1,
        ];

        $service = $this->createMockServiceWithDbResult($dbRow);
        $settings = $service->getSettings(123);

        // Booleans should be converted
        $this->assertIsBool($settings['sound_enabled']);
        $this->assertTrue($settings['sound_enabled']);
        $this->assertFalse($settings['desktop_enabled']);
        $this->assertTrue($settings['email_orders']);
        $this->assertFalse($settings['email_questions']);

        // Integers should be converted
        $this->assertSame(50, $settings['sound_volume']);
        $this->assertSame(60, $settings['polling_interval']);
    }

    /**
     * Test isQuietHours returns false when not in quiet hours range
     */
    public function testIsQuietHoursReturnsFalseWhenNotInRange(): void
    {
        $service = $this->getMockBuilder(RealTimeNotificationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSettings'])
            ->getMock();

        $service->method('getSettings')
            ->willReturn([
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
            ]);

        $result = $service->isQuietHours(1);
        $this->assertFalse($result);
    }

    /**
     * Test notification types enum values
     */
    public function testNotificationTypesExist(): void
    {
        $reflection = new ReflectionClass(RealTimeNotificationService::class);

        // Check if class has expected methods for different notification types
        $this->assertTrue($reflection->hasMethod('getSettings'));
        $this->assertTrue($reflection->hasMethod('getAvailableSounds'));
    }

    /**
     * Test polling interval has sensible bounds
     */
    public function testPollingIntervalDefaultIsSensible(): void
    {
        $service = $this->createMockServiceWithDbResult(false);
        $settings = $service->getSettings(1);

        $this->assertGreaterThanOrEqual(10, $settings['polling_interval']);
        $this->assertLessThanOrEqual(300, $settings['polling_interval']);
    }

    /**
     * Test volume default is within valid range
     */
    public function testVolumeDefaultIsValid(): void
    {
        $service = $this->createMockServiceWithDbResult(false);
        $settings = $service->getSettings(1);

        $this->assertGreaterThanOrEqual(0, $settings['sound_volume']);
        $this->assertLessThanOrEqual(100, $settings['sound_volume']);
    }

    /**
     * Helper to create mock service with PDO returning specific result
     */
    private function createMockServiceWithDbResult(mixed $fetchResult): RealTimeNotificationService
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchResult);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = $this->getMockBuilder(RealTimeNotificationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Inject mock PDO via reflection
        $reflection = new ReflectionProperty(RealTimeNotificationService::class, 'db');
        $reflection->setAccessible(true);
        $reflection->setValue($service, $pdo);

        return $service;
    }
}
