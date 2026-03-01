<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MonitoringAlertNotificationService;
use App\Services\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;

class MonitoringAlertNotificationServiceTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $keys = [
            'ML_MONITOR_ALERT_NOTIFY_ENABLED',
            'ML_MONITOR_ALERT_NOTIFY_MIN_SEVERITY',
            'ML_MONITOR_ALERT_NOTIFY_COOLDOWN_MINUTES',
            'ML_MONITOR_ALERT_NOTIFY_MAX_PER_RUN',
        ];

        foreach ($keys as $key) {
            $this->envBackup[$key] = getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }

        parent::tearDown();
    }

    public function testDispatchUsesCooldownToAvoidAlertSpam(): void
    {
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_ENABLED', 'true');
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_MIN_SEVERITY', 'HIGH');
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_COOLDOWN_MINUTES', '30');
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_MAX_PER_RUN', '5');

        $db = $this->createSqliteDatabase();
        $fakeNotifier = new class extends NotificationService {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function sendAlert(string $title, string $message, string $severity = 'HIGH'): array
            {
                $this->calls++;
                return ['email' => true];
            }
        };

        $service = new MonitoringAlertNotificationService($db, $fakeNotifier);
        $alerts = [[
            'type' => 'ml_webhook_failed_backlog',
            'severity' => 'HIGH',
            'message' => 'Backlog de webhooks ML falhos: 30',
            'value' => 30,
            'threshold' => 20,
        ]];

        $first = $service->dispatchMlOperationalAlerts($alerts);
        $second = $service->dispatchMlOperationalAlerts($alerts);

        $this->assertSame(1, $first['sent']);
        $this->assertSame(0, $first['skipped_due_to_cooldown']);
        $this->assertSame(0, $first['failed']);

        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, $second['skipped_due_to_cooldown']);
        $this->assertSame(1, $fakeNotifier->calls);
    }

    public function testDispatchSkipsAlertsBelowSeverityThreshold(): void
    {
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_ENABLED', 'true');
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_MIN_SEVERITY', 'CRITICAL');
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_COOLDOWN_MINUTES', '30');
        $this->setEnv('ML_MONITOR_ALERT_NOTIFY_MAX_PER_RUN', '5');

        $db = $this->createSqliteDatabase();
        $fakeNotifier = new class extends NotificationService {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function sendAlert(string $title, string $message, string $severity = 'HIGH'): array
            {
                $this->calls++;
                return ['email' => true];
            }
        };

        $service = new MonitoringAlertNotificationService($db, $fakeNotifier);
        $alerts = [[
            'type' => 'ml_job_retry_backlog',
            'severity' => 'WARNING',
            'message' => 'Jobs aguardando retry: 90',
            'value' => 90,
            'threshold' => 80,
        ]];

        $result = $service->dispatchMlOperationalAlerts($alerts);

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped_due_to_severity']);
        $this->assertSame(0, $fakeNotifier->calls);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }

    private function createSqliteDatabase(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec(
            'CREATE TABLE notification_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type VARCHAR(20) NOT NULL,
                recipient VARCHAR(255) NOT NULL,
                subject TEXT,
                status VARCHAR(20) NOT NULL,
                error_message TEXT,
                metadata TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );

        return $db;
    }
}

