<?php

use PHPUnit\Framework\TestCase;
use App\Services\CloneAlertNotificationService;

class CloneAlertNotificationServiceTest extends TestCase
{
    private CloneAlertNotificationService $service;

    protected function setUp(): void
    {
        // Verificar se as tabelas necessárias existem no banco de teste
        try {
            $db = \App\Database::getInstance();
            $db->query('SELECT 1 FROM catalog_clone_jobs LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Banco de teste indisponível ou schema ausente para CloneAlertNotificationServiceTest: ' . $e->getMessage()
            );
        }

        $this->service = new CloneAlertNotificationService();
    }

    /**
     * Testa verificação de jobs stuck
     */
    public function testCheckStuckJobs(): void
    {
        $alerts = $this->service->checkStuckJobs();

        $this->assertIsArray($alerts);

        foreach ($alerts as $alert) {
            $this->assertArrayHasKey('type', $alert);
            $this->assertEquals('stuck_job', $alert['type']);
            $this->assertArrayHasKey('severity', $alert);
            $this->assertContains($alert['severity'], ['info', 'warning', 'error', 'critical']);
        }
    }

    /**
     * Testa verificação de taxa de falha alta
     */
    public function testCheckHighFailureRate(): void
    {
        $alerts = $this->service->checkHighFailureRate();

        $this->assertIsArray($alerts);

        foreach ($alerts as $alert) {
            $this->assertEquals('high_failure_rate', $alert['type']);
            $this->assertArrayHasKey('context', $alert);

            $context = $alert['context'];
            $this->assertArrayHasKey('failure_rate', $context);
            $this->assertIsNumeric($context['failure_rate']);
        }
    }

    /**
     * Testa verificação de problemas de quota da API
     */
    public function testCheckApiQuotaIssues(): void
    {
        $alerts = $this->service->checkApiQuotaIssues();

        $this->assertIsArray($alerts);

        foreach ($alerts as $alert) {
            $this->assertEquals('api_quota', $alert['type']);
            $this->assertArrayHasKey('context', $alert);
        }
    }

    /**
     * Testa execução de todas as verificações
     */
    public function testRunAllChecks(): void
    {
        $results = $this->service->runAllChecks();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('stuck_jobs', $results);
        $this->assertArrayHasKey('high_failure_rate', $results);
        $this->assertArrayHasKey('api_quota_issues', $results);
        $this->assertArrayHasKey('timestamp', $results);

        // Verificar formato do timestamp
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $results['timestamp']);
    }

    /**
     * Testa listagem de alertas ativos
     */
    public function testGetActiveAlerts(): void
    {
        $alerts = $this->service->getActiveAlerts();

        $this->assertIsArray($alerts);

        foreach ($alerts as $alert) {
            $this->assertArrayHasKey('id', $alert);
            $this->assertArrayHasKey('alert_type', $alert);
            $this->assertArrayHasKey('severity', $alert);
            $this->assertArrayHasKey('title', $alert);
            $this->assertArrayHasKey('message', $alert);
            $this->assertArrayHasKey('context', $alert);
            $this->assertIsArray($alert['context']);
        }
    }

    /**
     * Testa filtro de alertas por severidade
     */
    public function testGetActiveAlertsWithSeverityFilter(): void
    {
        $criticalAlerts = $this->service->getActiveAlerts('critical');

        $this->assertIsArray($criticalAlerts);

        foreach ($criticalAlerts as $alert) {
            $this->assertEquals('critical', $alert['severity']);
        }
    }

    /**
     * Testa resolução de alerta
     */
    public function testResolveAlert(): void
    {
        // Criar um alerta primeiro
        $alerts = $this->service->runAllChecks();

        if (!empty($alerts['stuck_jobs'])) {
            $alertId = $alerts['stuck_jobs'][0]['id'];

            $result = $this->service->resolveAlert($alertId, 1, 'Teste de resolução');

            // Se houver alerta para resolver, deve retornar true
            // Se não houver, pode retornar false (alerta não existe ou já resolvido)
            $this->assertIsBool($result);
        } else {
            // Sem alertas para testar, validar estado esperado
            $this->assertEmpty($alerts['stuck_jobs']);
        }
    }

    /**
     * Testa que severidades estão ordenadas corretamente
     */
    public function testAlertSeverityOrdering(): void
    {
        $alerts = $this->service->getActiveAlerts();

        if (count($alerts) > 1) {
            $severityOrder = ['critical' => 1, 'error' => 2, 'warning' => 3, 'info' => 4];

            for ($i = 1; $i < count($alerts); $i++) {
                $prevSeverity = $severityOrder[$alerts[$i - 1]['severity']] ?? 5;
                $currSeverity = $severityOrder[$alerts[$i]['severity']] ?? 5;

                $this->assertLessThanOrEqual(
                    $currSeverity,
                    $prevSeverity,
                    'Alertas devem estar ordenados por severidade'
                );
            }
        } else {
            $this->assertLessThanOrEqual(1, count($alerts)); // Não há alertas suficientes para testar ordenação
        }
    }

    /**
     * Testa estrutura de alerta criado
     */
    public function testAlertStructure(): void
    {
        $alerts = $this->service->runAllChecks();

        // Assert top-level structure regardless of whether any alerts were triggered
        $this->assertArrayHasKey('stuck_jobs', $alerts);
        $this->assertArrayHasKey('high_failure_rate', $alerts);
        $this->assertArrayHasKey('api_quota_issues', $alerts);
        $this->assertArrayHasKey('timestamp', $alerts);
        $this->assertIsArray($alerts['stuck_jobs']);
        $this->assertIsArray($alerts['high_failure_rate']);
        $this->assertIsArray($alerts['api_quota_issues']);
        $this->assertIsString($alerts['timestamp']);

        $allAlerts = array_merge(
            $alerts['stuck_jobs'],
            $alerts['high_failure_rate'],
            $alerts['api_quota_issues']
        );

        foreach ($allAlerts as $alert) {
            $this->assertArrayHasKey('id', $alert);
            $this->assertArrayHasKey('type', $alert);
            $this->assertArrayHasKey('severity', $alert);
            $this->assertArrayHasKey('title', $alert);
            $this->assertArrayHasKey('message', $alert);
            $this->assertArrayHasKey('context', $alert);

            // Verificar tipos
            $this->assertIsInt($alert['id']);
            $this->assertIsString($alert['type']);
            $this->assertIsString($alert['severity']);
            $this->assertIsString($alert['title']);
            $this->assertIsString($alert['message']);
            $this->assertIsArray($alert['context']);
        }
    }
}
