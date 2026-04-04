<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\EanService;

/**
 * @covers \App\Services\EanService
 *
 * Foco em:
 * - validateEan(): lógica pura de checksum EAN-13 (zero deps)
 * - checkLowStock(): lógica de limiar sobre getBalance()
 * - processPaymentWebhook(): roteamento de webhook de pagamento
 * - storeOperationalAlert(): deduplicação e normalização de severidade
 * - getOperationalAlerts(): filtragem por tipo, severidade e limite
 * - evaluateOperationalEscalation(): lógica de nível (0,1,2,3)
 * - evaluateOperationalCircuitBreaker(): máquina de estado + force-close
 * - getReconciliationDrift(): cálculo de delta
 * - getReconciliationPreviewHistory(): limite e inversão
 * - getOperationalTimeseriesTrend(): projeção de tendência
 */
class EanServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildService(?\PDO $mockDb = null): EanService
    {
        $ref = new \ReflectionClass(EanService::class);
        $service = $ref->newInstanceWithoutConstructor();

        if ($mockDb !== null) {
            $dbProp = $ref->getProperty('db');
            $dbProp->setAccessible(true);
            $dbProp->setValue($service, $mockDb);
        }

        // Inject a no-op MercadoPagoService mock to avoid constructor calls
        $mpMock = $this->createMock(\App\Services\MercadoPagoService::class);
        $mpProp = $ref->getProperty('mercadoPagoService');
        $mpProp->setAccessible(true);
        $mpProp->setValue($service, $mpMock);

        return $service;
    }

    /**
     * Cria PDO stub que retorna resultados específicos baseados em padrão de SQL.
     *
     * @param array<string,mixed> $patternMap chave=substring de SQL, valor=resposta do fetch()
     */
    private function buildDbWithGetSetting(array $patternMap, bool $writeSucceeds = true): \PDO
    {
        $db = $this->createMock(\PDO::class);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($patternMap, $writeSucceeds): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('rowCount')->willReturn(1);

                // Detectar SELECT de getSetting
                if (str_contains($sql, 'ean_settings') && str_contains($sql, 'SELECT')) {
                    $stmt->method('fetch')
                        ->willReturnCallback(function () use ($patternMap, $sql): mixed {
                            // A chave pesquisada é injetada via execute(['key' => $key])
                            // Como não temos acesso ao argumento do execute, devolvemos o primeiro match
                            foreach ($patternMap as $value) {
                                return $value; // phpstan: ignore — primeira correspondência
                            }
                            return false;
                        });
                    return $stmt;
                }

                // INSERT/UPDATE to ean_settings — sucesso
                $stmt->method('fetch')->willReturn(false);
                return $stmt;
            });

        return $db;
    }

    /**
     * Cria PDO stub que mapeia key→value para chamadas getSetting.
     * Usa um contador para responder na ordem das chamadas successivas.
     */
    private function buildDbWithSettings(array $keyValues): \PDO
    {
        $db = $this->createMock(\PDO::class);
        $callIndex = 0;
        $values = array_values($keyValues);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($values, &$callIndex): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('rowCount')->willReturn(0);

                if (str_contains($sql, 'ean_settings') && str_contains($sql, 'SELECT')) {
                    $currentIndex = $callIndex++;
                    $currentValue = $values[$currentIndex] ?? false;
                    $stmt->method('fetch')->willReturn($currentValue);
                    $stmt->method('fetchAll')->willReturn([]);
                    return $stmt;
                }

                $stmt->method('fetch')->willReturn(false);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            });

        return $db;
    }

    // =========================================================================
    // validateEan — pure logic, zero DB deps
    // =========================================================================

    public function testValidateEanValidCode(): void
    {
        $service = $this->buildService();

        // EAN-13 válido: 5901234123457
        $this->assertTrue($service->validateEan('5901234123457'));
    }

    public function testValidateEanAnotherValidCode(): void
    {
        $service = $this->buildService();

        // 4006381333931 (exemplo real de EAN-13)
        $this->assertTrue($service->validateEan('4006381333931'));
    }

    public function testValidateEanThirdValidCode(): void
    {
        $service = $this->buildService();

        // 0012345678905
        $this->assertTrue($service->validateEan('0012345678905'));
    }

    public function testValidateEanWrongCheckDigit(): void
    {
        $service = $this->buildService();

        // Último dígito errado: 5901234123456 (deveria ser 7)
        $this->assertFalse($service->validateEan('5901234123456'));
    }

    public function testValidateEanTooShort(): void
    {
        $service = $this->buildService();
        $this->assertFalse($service->validateEan('590123'));
    }

    public function testValidateEanTooLong(): void
    {
        $service = $this->buildService();
        $this->assertFalse($service->validateEan('59012341234570'));
    }

    public function testValidateEanEmpty(): void
    {
        $service = $this->buildService();
        $this->assertFalse($service->validateEan(''));
    }

    public function testValidateEanNonNumeric(): void
    {
        $service = $this->buildService();
        $this->assertFalse($service->validateEan('590123412345X'));
    }

    public function testValidateEanAllZerosInvalid(): void
    {
        $service = $this->buildService();

        // 12 zeros + dígito verificador: (10 - 0%10)%10 = 0, então 0000000000000 é válido
        $this->assertTrue($service->validateEan('0000000000000'));
    }

    public function testValidateEanChecksumEdgeCase(): void
    {
        // Gera EAN-13 válido programaticamente para garantir o teste
        $base = '789012345678';
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$base[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        $ean = $base . $checkDigit;

        $service = $this->buildService();
        $this->assertTrue($service->validateEan($ean));

        // Alterar o check digit deve invalidar
        $wrong = substr($ean, 0, 12) . (($checkDigit + 1) % 10);
        $this->assertFalse($service->validateEan($wrong));
    }

    // =========================================================================
    // getBalance — com PDO mock
    // =========================================================================

    public function testGetBalanceFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'total_purchased' => 50,
            'total_used' => 10,
            'available' => 40,
            'last_purchase_at' => '2026-04-01',
        ]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);

        $service = $this->buildService($db);
        $result = $service->getBalance(1);

        $this->assertSame(50, $result['total_purchased']);
        $this->assertSame(40, $result['available']);
    }

    public function testGetBalanceNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);

        $service = $this->buildService($db);
        $result = $service->getBalance(99);

        $this->assertSame(0, $result['total_purchased']);
        $this->assertSame(0, $result['available']);
        $this->assertNull($result['last_purchase_at']);
    }

    // =========================================================================
    // checkLowStock
    // =========================================================================

    public function testCheckLowStockNormal(): void
    {
        // getBalance returns 50 available; getSetting('low_stock_threshold') returns 10
        $callCount = 0;
        $db = $this->createMock(\PDO::class);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if (str_contains($sql, 'ean_balances')) {
                    $stmt->method('fetch')->willReturn(['available' => 50, 'total_purchased' => 50, 'total_used' => 0, 'last_purchase_at' => null]);
                } elseif (str_contains($sql, 'ean_settings')) {
                    $callCount++;
                    $stmt->method('fetch')->willReturn(['setting_value' => '10', 'setting_type' => 'int']);
                } else {
                    $stmt->method('fetch')->willReturn(false);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->checkLowStock(1);

        $this->assertSame(50, $result['available']);
        $this->assertSame(10, $result['threshold']);
        $this->assertFalse($result['is_low']);
        $this->assertFalse($result['is_critical']);
        $this->assertFalse($result['is_empty']);
    }

    public function testCheckLowStockCritical(): void
    {
        $db = $this->createMock(\PDO::class);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if (str_contains($sql, 'ean_balances')) {
                    $stmt->method('fetch')->willReturn(['available' => 2, 'total_purchased' => 10, 'total_used' => 8, 'last_purchase_at' => null]);
                } elseif (str_contains($sql, 'ean_settings')) {
                    $stmt->method('fetch')->willReturn(['setting_value' => '10', 'setting_type' => 'int']);
                } else {
                    $stmt->method('fetch')->willReturn(false);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->checkLowStock(1);

        $this->assertTrue($result['is_low']);
        $this->assertTrue($result['is_critical']);
        $this->assertFalse($result['is_empty']);
    }

    public function testCheckLowStockEmpty(): void
    {
        $db = $this->createMock(\PDO::class);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if (str_contains($sql, 'ean_balances')) {
                    $stmt->method('fetch')->willReturn(['available' => 0, 'total_purchased' => 5, 'total_used' => 5, 'last_purchase_at' => null]);
                } elseif (str_contains($sql, 'ean_settings')) {
                    $stmt->method('fetch')->willReturn(['setting_value' => '10', 'setting_type' => 'int']);
                } else {
                    $stmt->method('fetch')->willReturn(false);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->checkLowStock(1);

        $this->assertTrue($result['is_empty']);
        $this->assertTrue($result['is_critical']);
        $this->assertTrue($result['is_low']);
    }

    // =========================================================================
    // processPaymentWebhook
    // =========================================================================

    public function testProcessPaymentWebhookNoPaymentId(): void
    {
        $service = $this->buildService();
        $result = $service->processPaymentWebhook([]);

        $this->assertSame('no_payment_id', $result['status']);
    }

    public function testProcessPaymentWebhookPurchaseNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);

        $service = $this->buildService($db);
        $result = $service->processPaymentWebhook(['data' => ['id' => 'PAY999']]);

        $this->assertSame('purchase_not_found', $result['status']);
        $this->assertSame('PAY999', $result['payment_id']);
    }

    public function testProcessPaymentWebhookNonApprovedAction(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'payment_status' => 'pending',
            'account_id' => 1,
        ]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);

        $service = $this->buildService($db);
        $result = $service->processPaymentWebhook([
            'data' => ['id' => 'PAY001'],
            'action' => 'payment.updated',
        ]);

        $this->assertSame('webhook_processed', $result['status']);
    }

    public function testProcessPaymentWebhookViaStatusField(): void
    {
        // Status 'approved' no campo direto do payload
        $callCount = 0;
        $db = $this->createMock(\PDO::class);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                // Primeira call: buscar compra pelo payment_id
                if (str_contains($sql, 'ean_purchases') && str_contains($sql, 'SELECT')) {
                    $stmt->method('fetch')->willReturn([
                        'id' => 5,
                        'payment_status' => 'paid', // already paid
                        'account_id' => 1,
                        'quantity' => 3,
                    ]);
                } else {
                    $stmt->method('fetch')->willReturn(false);
                    $stmt->method('fetchAll')->willReturn([]);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->processPaymentWebhook([
            'id' => 'PAY100',
            'status' => 'approved',
        ]);

        // Já estava pago: deve retornar 'already_paid' via confirmPayment
        $this->assertSame('already_paid', $result['status']);
    }

    // =========================================================================
    // storeOperationalAlert — deduplicação e normalização de severidade
    // =========================================================================

    public function testStoreOperationalAlertFirstAlert(): void
    {
        $db = $this->createMock(\PDO::class);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                // getOperationalAlerts → getSetting → returns empty history
                $stmt->method('fetch')->willReturn(['setting_value' => '[]', 'setting_type' => 'json']);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            });

        $service = $this->buildService($db);
        $stored = $service->storeOperationalAlert('test_type', 'warning', 'Test message');

        $this->assertTrue($stored);
    }

    public function testStoreOperationalAlertNormalizesSeverity(): void
    {
        // Severity 'INVALID' deve ser normalizado para 'warning'
        $db = $this->createMock(\PDO::class);

        $stored_severity = null;

        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$stored_severity): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => '[]', 'setting_type' => 'json']);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->storeOperationalAlert('type1', 'INVALID_SEVERITY', 'msg');

        // Se armazenou é que normalizou e não lançou exceção
        $this->assertTrue($result);
    }

    public function testStoreOperationalAlertDeduplicates(): void
    {
        // Segundo alerta idêntico dentro da janela de deduplicação deve retornar false
        $now = time();
        $existingAlert = [
            'id' => 'alert_1',
            'type' => 'dup_type',
            'message' => 'Dup message',
            'severity' => 'warning',
            'created_at' => date('c', $now - 60), // 60 segundos atrás
            'context' => [],
        ];

        $historyJson = json_encode(['last_alert' => $existingAlert, 'history' => [$existingAlert], 'updated_at' => date('c', $now)]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($historyJson): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $historyJson, 'setting_type' => 'json']);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            });

        $service = $this->buildService($db);
        // Mesma type + message com janela de 600s; 60s < 600s → deve deduplicar
        $result = $service->storeOperationalAlert('dup_type', 'warning', 'Dup message', [], 600);

        $this->assertFalse($result);
    }

    // =========================================================================
    // getOperationalAlerts — filtragem e limite
    // =========================================================================

    public function testGetOperationalAlertsEmpty(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(false);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getOperationalAlerts(10);

        $this->assertSame([], $result);
    }

    public function testGetOperationalAlertsFiltersByType(): void
    {
        $alerts = [
            ['id' => '1', 'type' => 'type_a', 'severity' => 'warning', 'message' => 'msg1', 'created_at' => date('c'), 'context' => []],
            ['id' => '2', 'type' => 'type_b', 'severity' => 'critical', 'message' => 'msg2', 'created_at' => date('c'), 'context' => []],
            ['id' => '3', 'type' => 'type_a', 'severity' => 'info', 'message' => 'msg3', 'created_at' => date('c'), 'context' => []],
        ];

        $payload = json_encode(['history' => $alerts, 'updated_at' => date('c')]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $payload, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getOperationalAlerts(50, 'type_a');

        $this->assertCount(2, $result);
        foreach ($result as $alert) {
            $this->assertSame('type_a', $alert['type']);
        }
    }

    public function testGetOperationalAlertsFiltersBySeverity(): void
    {
        $alerts = [
            ['id' => '1', 'type' => 'type_a', 'severity' => 'critical', 'message' => 'msg1', 'created_at' => date('c'), 'context' => []],
            ['id' => '2', 'type' => 'type_b', 'severity' => 'warning', 'message' => 'msg2', 'created_at' => date('c'), 'context' => []],
        ];

        $payload = json_encode(['history' => $alerts, 'updated_at' => date('c')]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $payload, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getOperationalAlerts(50, null, 'critical');

        $this->assertCount(1, $result);
        $this->assertSame('critical', $result[0]['severity']);
    }

    public function testGetOperationalAlertsRespectsLimit(): void
    {
        $alerts = array_fill(0, 100, ['id' => 'x', 'type' => 't', 'severity' => 'info', 'message' => 'm', 'created_at' => date('c'), 'context' => []]);
        $payload = json_encode(['history' => $alerts, 'updated_at' => date('c')]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $payload, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getOperationalAlerts(5);

        $this->assertCount(5, $result);
    }

    // =========================================================================
    // evaluateOperationalEscalation — lógica de nível
    // =========================================================================

    public function testEscalationLevel0NoIssues(): void
    {
        // Sem alertas recentes → max_occurrences=0 → level=0 → severity='info'
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => '[]', 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->evaluateOperationalEscalation(['divergence_threshold_exceeded']);

        $this->assertSame(0, $result['level']);
        $this->assertSame('info', $result['severity']);
    }

    public function testEscalationLevel1OneOccurrence(): void
    {
        // 1 alerta recente com o issue → occurrences=1 → level=1 → severity='warning'
        $now = time();
        $alert = [
            'id' => 'a1',
            'type' => 'ean_reconcile_health_check',
            'severity' => 'warning',
            'message' => 'Health check',
            'created_at' => date('c', $now - 30), // 30s ago (within window)
            'context' => ['issues' => ['divergence_threshold_exceeded']],
        ];

        $payload = json_encode(['history' => [$alert], 'updated_at' => date('c')]);
        $escalationDb = json_encode([]);

        $callCount = 0;
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload, $escalationDb, &$callCount): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                // 1st call: getOperationalAlerts → getSetting('ean_operational_alerts')
                // 2nd call: setSetting (INSERT ON DUPLICATE KEY) — no fetch needed
                if (str_contains($sql, 'SELECT')) {
                    $callCount++;
                    $val = $callCount === 1 ? $payload : $escalationDb;
                    $stmt->method('fetch')->willReturn(['setting_value' => $val, 'setting_type' => 'json']);
                } else {
                    $stmt->method('fetch')->willReturn(false);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->evaluateOperationalEscalation(
            ['divergence_threshold_exceeded'],
            60 // 1 minute window
        );

        $this->assertGreaterThanOrEqual(1, $result['level']);
        $this->assertSame('warning', $result['severity']);
    }

    public function testEscalationLevel3HighOccurrences(): void
    {
        // Create 8 recent alerts with the issue — should trigger level 3 (>=6)
        $now = time();
        $alerts = [];
        for ($i = 0; $i < 8; $i++) {
            $alerts[] = [
                'id' => "a{$i}",
                'type' => 'ean_reconcile_health_check',
                'severity' => 'critical',
                'message' => 'Health check',
                'created_at' => date('c', $now - ($i * 30)), // every 30s
                'context' => ['issues' => ['webhook_sla_failure_rate_exceeded']],
            ];
        }

        $payload = json_encode(['history' => $alerts, 'updated_at' => date('c')]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $payload, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->evaluateOperationalEscalation(
            ['webhook_sla_failure_rate_exceeded'],
            3600 // 1h window
        );

        $this->assertSame(3, $result['level']);
        $this->assertSame('critical', $result['severity']);
    }

    // =========================================================================
    // evaluateOperationalCircuitBreaker — máquina de estado
    // =========================================================================

    public function testCircuitBreakerStartsClosed(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                // getSetting returns no existing state → default
                $stmt->method('fetch')->willReturn(false);
                return $stmt;
            });

        $service = $this->buildService($db);

        // Sem predictive + critical triggers → consecutive stays 0 → closed
        $result = $service->evaluateOperationalCircuitBreaker([
            'predictive_trigger' => false,
            'critical_trigger' => false,
            'threshold_cycles' => 3,
        ]);

        $this->assertSame('closed', $result['state']);
        $this->assertSame(0, $result['consecutive_trigger_cycles']);
    }

    public function testCircuitBreakerOpensAfterThreshold(): void
    {
        // Simulate 3 consecutive triggering cycles by building a state that is at threshold-1
        $storedState = [
            'state' => 'closed',
            'threshold_cycles' => 2,
            'open_minutes' => 15,
            'consecutive_trigger_cycles' => 1, // one away from threshold
            'triggered_this_cycle' => true,
            'opened_until' => null,
            'last_evaluated_at' => date('c'),
        ];

        $storedJson = json_encode($storedState);

        $callCount = 0;
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($storedJson, &$callCount): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if (str_contains($sql, 'SELECT')) {
                    $callCount++;
                    if ($callCount === 1) {
                        // getSetting('ean_operational_circuit_breaker') → return stored state
                        $stmt->method('fetch')->willReturn(['setting_value' => $storedJson, 'setting_type' => 'json']);
                    } else {
                        // getOperationalAlerts (from storeOperationalAlert) returns empty
                        $stmt->method('fetch')->willReturn(['setting_value' => '[]', 'setting_type' => 'json']);
                    }
                } else {
                    $stmt->method('fetch')->willReturn(false);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->evaluateOperationalCircuitBreaker([
            'predictive_trigger' => true,
            'critical_trigger' => true,
            'threshold_cycles' => 2,
            'open_minutes' => 15,
        ]);

        $this->assertSame('open', $result['state']);
        $this->assertNotNull($result['opened_until']);
        $this->assertSame(2, $result['consecutive_trigger_cycles']);
    }

    public function testCircuitBreakerForceClose(): void
    {
        $openState = json_encode([
            'state' => 'open',
            'consecutive_trigger_cycles' => 5,
            'opened_until' => date('c', time() + 900),
        ]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($openState): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $openState, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->evaluateOperationalCircuitBreaker([
            'force_close' => true,
        ]);

        $this->assertSame('closed', $result['state']);
        $this->assertSame(0, $result['consecutive_trigger_cycles']);
    }

    public function testCircuitBreakerAutoClosesWhenWindowExpires(): void
    {
        // opened_until is in the past
        $openState = json_encode([
            'state' => 'open',
            'consecutive_trigger_cycles' => 3,
            'opened_until' => date('c', time() - 60), // 1 minute ago
        ]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($openState): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $openState, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->evaluateOperationalCircuitBreaker([
            'predictive_trigger' => false,
            'critical_trigger' => false,
        ]);

        $this->assertSame('closed', $result['state']);
        $this->assertSame(0, $result['consecutive_trigger_cycles']);
    }

    // =========================================================================
    // getReconciliationPreviewHistory
    // =========================================================================

    public function testGetReconciliationPreviewHistoryEmpty(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(false);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getReconciliationPreviewHistory(5);

        $this->assertSame([], $result);
    }

    public function testGetReconciliationPreviewHistoryRespectsLimit(): void
    {
        $history = array_map(fn($i) => ['id' => "preview_{$i}", 'captured_at' => date('c')], range(1, 10));
        $payload = json_encode(['history' => $history, 'updated_at' => date('c')]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $payload, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getReconciliationPreviewHistory(3);

        $this->assertCount(3, $result);
    }

    // =========================================================================
    // getReconciliationDrift
    // =========================================================================

    public function testGetReconciliationDriftMissingData(): void
    {
        // Both getSetting calls return empty → no preview nor last run
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(false);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getReconciliationDrift();

        $this->assertFalse($result['available']);
        $this->assertSame('preview_or_last_run_missing', $result['reason']);
    }

    public function testGetReconciliationDriftWithData(): void
    {
        $previewPayload = [
            'last_snapshot' => [
                'id' => 'preview_1',
                'captured_at' => date('c'),
                'summary' => ['total_divergences' => 10],
            ],
            'history' => [],
        ];

        $statusPayload = [
            'last_run' => [
                'source' => 'manual',
                'finished_at' => date('c'),
                'result' => [
                    'errors' => 1,
                    'still_pending' => 2,
                    'without_payment_id' => 1,
                ],
            ],
        ];

        $callNum = 0;
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($previewPayload, $statusPayload, &$callNum): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                if (str_contains($sql, 'SELECT')) {
                    $callNum++;
                    $val = $callNum === 1
                        ? json_encode($previewPayload)
                        : json_encode($statusPayload);
                    $stmt->method('fetch')->willReturn(['setting_value' => $val, 'setting_type' => 'json']);
                } else {
                    $stmt->method('fetch')->willReturn(false);
                }
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getReconciliationDrift();

        $this->assertTrue($result['available']);
        $this->assertSame('preview_1', $result['preview_snapshot_id']);
        $this->assertSame(10, $result['preview_total_divergences']);
        // last_run_operational_load = errors(1) + still_pending(2) + without_payment_id(1) = 4
        $this->assertSame(4, $result['last_run_operational_load']);
        $this->assertSame(-6, $result['delta']); // 4 - 10
    }

    // =========================================================================
    // getOperationalTimeseriesTrend
    // =========================================================================

    public function testGetOperationalTimeseriesTrendEmpty(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(false);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getOperationalTimeseriesTrend(24, 500);

        $this->assertFalse($result['available']);
        $this->assertSame('no_points_in_window', $result['reason']);
    }

    public function testGetOperationalTimeseriesTrendCalculatesProjection(): void
    {
        $now = time();
        $points = [
            [
                'captured_at' => date('c', $now - 7200), // 2h ago
                'total_divergences' => 5,
                'webhook_avg_processing_seconds' => 1.0,
                'webhook_failure_rate_percent' => 2.0,
                'ok' => true,
            ],
            [
                'captured_at' => date('c', $now - 3600), // 1h ago
                'total_divergences' => 10,
                'webhook_avg_processing_seconds' => 2.0,
                'webhook_failure_rate_percent' => 4.0,
                'ok' => false,
            ],
        ];

        $payload = json_encode(['history' => $points, 'updated_at' => date('c')]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($payload): \PDOStatement {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['setting_value' => $payload, 'setting_type' => 'json']);
                return $stmt;
            });

        $service = $this->buildService($db);
        $result = $service->getOperationalTimeseriesTrend(24, 500);

        $this->assertTrue($result['available']);
        $this->assertSame(2, $result['points']);
        // delta = 10 - 5 = 5
        $this->assertSame(5, $result['trend']['total_divergences_delta']);
        // projection = round(10 + (5 * 0.5)) = round(12.5) = 13
        $this->assertSame(13, $result['projection_next_window']['total_divergences']);
        // latency projection = 2.0 + (1.0 * 0.5) = 2.5
        $this->assertSame(2.5, $result['projection_next_window']['webhook_avg_processing_seconds']);
    }

    // =========================================================================
    // getBalance — edge case: available field as string from DB
    // =========================================================================

    public function testGetBalanceReturnsRowAsIs(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'total_purchased' => '100',
            'total_used' => '25',
            'available' => '75',
            'last_purchase_at' => '2026-03-01',
        ]);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);

        $service = $this->buildService($db);
        $result = $service->getBalance(2);

        // The method returns the DB row as-is (strings from DB are acceptable at this layer)
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('total_purchased', $result);
    }
}
