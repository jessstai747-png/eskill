<?php

use PHPUnit\Framework\TestCase;
use App\Services\CloneRetryStrategyService;
use App\Database;

class CloneRetryStrategyServiceTest extends TestCase
{
    private CloneRetryStrategyService $service;
    private PDO $db;
    
    protected function setUp(): void
    {
        $this->db = Database::getInstance();

        // Verificar se as tabelas necessárias existem no banco de teste
        try {
            $this->db->query('SELECT 1 FROM catalog_clone_jobs LIMIT 1');
        } catch (\PDOException $e) {
            $this->markTestSkipped('Tabela catalog_clone_jobs não existe no banco de teste. Execute as migrations.');
        }

        $this->service = new CloneRetryStrategyService();
    }
    
    /**
     * Testa estratégia para erro 403 (Forbidden) - não deve fazer retry
     */
    public function testForbiddenErrorShouldNotRetry(): void
    {
        $result = $this->service->shouldRetry('403', 0);
        
        $this->assertFalse($result['should_retry']);
        $this->assertEquals('skipped', $result['final_status']);
        $this->assertStringContainsString('sem permissão', $result['reason']);
    }
    
    /**
     * Testa estratégia para erro 429 (Rate Limit) - deve fazer retry com backoff
     */
    public function testRateLimitShouldRetryWithBackoff(): void
    {
        $result1 = $this->service->shouldRetry('429', 0);
        $this->assertTrue($result1['should_retry']);
        $this->assertGreaterThan(0, $result1['delay']);
        
        // Segunda tentativa deve ter delay maior (backoff exponencial)
        $result2 = $this->service->shouldRetry('429', 1);
        $this->assertTrue($result2['should_retry']);
        $this->assertGreaterThan($result1['delay'] * 1.5, $result2['delay']);
    }
    
    /**
     * Testa estratégia para erro 500 (Server Error) - deve fazer retry limitado
     */
    public function testServerErrorShouldRetryLimited(): void
    {
        $result1 = $this->service->shouldRetry('500', 0);
        $this->assertTrue($result1['should_retry']);
        
        $result2 = $this->service->shouldRetry('500', 1);
        $this->assertTrue($result2['should_retry']);
        
        $result3 = $this->service->shouldRetry('500', 2);
        $this->assertTrue($result3['should_retry']);
        
        // 4ª tentativa deve falhar (max 3 attempts)
        $result4 = $this->service->shouldRetry('500', 3);
        $this->assertFalse($result4['should_retry']);
        $this->assertStringContainsString('Max attempts', $result4['reason']);
    }
    
    /**
     * Testa estratégia para erro 400 (Bad Request) - não deve fazer retry
     */
    public function testBadRequestShouldNotRetry(): void
    {
        $result = $this->service->shouldRetry('400', 0);
        
        $this->assertFalse($result['should_retry']);
        $this->assertEquals('failed', $result['final_status']);
        $this->assertStringContainsString('Bad Request', $result['reason']);
    }
    
    /**
     * Testa identificação de erro timeout
     */
    public function testTimeoutErrorIdentification(): void
    {
        $result = $this->service->shouldRetry('Request timeout', 0);
        
        $this->assertTrue($result['should_retry']);
        $this->assertEquals('pending', $result['next_status']);
    }
    
    /**
     * Testa identificação de erro de network
     */
    public function testNetworkErrorIdentification(): void
    {
        $result = $this->service->shouldRetry('Network connection failed', 0);
        
        $this->assertTrue($result['should_retry']);
    }
    
    /**
     * Testa backoff exponencial com jitter
     */
    public function testExponentialBackoffWithJitter(): void
    {
        $delays = [];
        
        for ($i = 0; $i < 5; $i++) {
            $result = $this->service->shouldRetry('429', $i);
            if ($result['should_retry']) {
                $delays[] = $result['delay'];
            }
        }
        
        // Delays devem crescer exponencialmente
        for ($i = 1; $i < count($delays); $i++) {
            $this->assertGreaterThan($delays[$i - 1] * 1.5, $delays[$i]);
        }
    }
    
    /**
     * Testa relatório de erros
     */
    public function testErrorReport(): void
    {
        $report = $this->service->getErrorReport(null, 24);
        
        $this->assertIsArray($report);
        
        foreach ($report as $error) {
            $this->assertArrayHasKey('error_code', $error);
            $this->assertArrayHasKey('error_count', $error);
            $this->assertArrayHasKey('retry_enabled', $error);
            $this->assertArrayHasKey('max_attempts', $error);
        }
    }
    
    /**
     * Testa que erros HTTP são extraídos corretamente de mensagens
     */
    public function testHttpCodeExtraction(): void
    {
        $testCases = [
            'HTTP 429 Too Many Requests' => true,  // Deve retry
            'Error 403: Forbidden' => false,        // Não deve retry
            'HTTP Error 500' => true,               // Deve retry
            'Bad Request (400)' => false,           // Não deve retry
        ];
        
        foreach ($testCases as $errorMessage => $shouldRetry) {
            $result = $this->service->shouldRetry($errorMessage, 0);
            $this->assertEquals($shouldRetry, $result['should_retry'], 
                "Erro '{$errorMessage}' deveria " . ($shouldRetry ? '' : 'não ') . "fazer retry");
        }
    }
}
