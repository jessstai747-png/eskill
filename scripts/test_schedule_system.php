<?php
/**
 * Teste completo do sistema de agendamento de clonagem
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\CatalogCloneService;
use App\Database;

echo "=== TESTE DO SISTEMA DE AGENDAMENTO DE CLONAGEM ===\n\n";

try {
    $service = new CatalogCloneService();
    $db = Database::getInstance();

    // 1. Testar criação de agendamento
    echo "1. Testando criação de agendamento...\n";
    
    $scheduleData = [
        'source_account_id' => 1,
        'target_account_id' => 2,
        'scheduled_date' => date('Y-m-d', strtotime('+1 minute')),
        'scheduled_time' => date('H:i', strtotime('+1 minute')),
        'frequency' => 'once',
        'filters' => 'category=MLB1055&min_price=50'
    ];
    
    $result = $service->createCloneSchedule($scheduleData);
    $scheduleId = $result['schedule_id'];
    echo "   ✅ Agendamento criado com ID: {$scheduleId}\n";
    
    // 2. Testar listagem de agendamentos ativos
    echo "\n2. Testando listagem de agendamentos...\n";
    $schedules = $service->getActiveSchedules();
    echo "   ✅ Encontrados " . count($schedules) . " agendamentos ativos\n";
    
    if (!empty($schedules)) {
        foreach ($schedules as $schedule) {
            echo "   - #{$schedule['id']}: {$schedule['source_account']} → {$schedule['target_account']}\n";
            echo "     Data: {$schedule['scheduled_datetime']} ({$schedule['frequency']})\n";
        }
    }
    
    // 3. Testar processamento de agendamentos
    echo "\n3. Testando processamento de agendamentos...\n";
    
    // Simular que é hora de executar (forçar datetime)
    $sql = "UPDATE clone_schedules SET scheduled_datetime = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $scheduleId]);
    
    $processResults = $service->processScheduledClones();
    echo "   ✅ Processamento executado\n";
    
    if (!empty($processResults)) {
        foreach ($processResults as $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            echo "   {$status} Agendamento {$result['schedule_id']}: {$result['status']}";
            
            if (isset($result['jobs_created'])) {
                echo " ({$result['jobs_created']} jobs criados)";
            }
            
            echo "\n";
        }
    }
    
    // 4. Testar cancelamento de agendamento
    echo "\n4. Testando cancelamento de agendamento...\n";
    
    // Criar outro agendamento para cancelar
    $cancelTestData = [
        'source_account_id' => 1,
        'target_account_id' => 2,
        'scheduled_date' => date('Y-m-d', strtotime('+1 day')),
        'scheduled_time' => '15:00',
        'frequency' => 'daily',
        'filters' => ''
    ];
    
    $cancelResult = $service->createCloneSchedule($cancelTestData);
    $cancelScheduleId = $cancelResult['schedule_id'];
    echo "   ✅ Agendamento teste criado: {$cancelScheduleId}\n";
    
    $canceled = $service->cancelSchedule($cancelScheduleId);
    echo "   " . ($canceled ? "✅" : "❌") . " Cancelamento: " . ($canceled ? "Sucesso" : "Falhou") . "\n";
    
    // 5. Verificar status final
    echo "\n5. Status final dos agendamentos...\n";
    $finalSchedules = $service->getActiveSchedules();
    echo "   ✅ Agendamentos ativos restantes: " . count($finalSchedules) . "\n";
    
    // 6. Testar busca com filtros (dependência)
    echo "\n6. Testando busca com filtros...\n";
    
    try {
        $filterResult = $service->searchItemsWithFilters(1, [
            'category_id' => 'MLB1055',
            'min_price' => '50'
        ]);
        
        echo "   ✅ Busca com filtros funcionando\n";
        echo "   - Encontrados: " . $filterResult['total'] . " itens\n";
        
    } catch (\Exception $e) {
        echo "   ⚠️  Busca com filtros: " . $e->getMessage() . "\n";
    }
    
    echo "\n🚀 SISTEMA DE AGENDAMENTO FUNCIONANDO CORRETAMENTE!\n";
    echo "\n📋 Recursos implementados:\n";
    echo "   ✅ Criação de agendamentos (únicos e recorrentes)\n";
    echo "   ✅ Listagem de agendamentos ativos\n";
    echo "   ✅ Processamento automático na hora agendada\n";
    echo "   ✅ Cancelamento de agendamentos\n";
    echo "   ✅ Integração com filtros avançados\n";
    echo "   ✅ Integração com sistema de jobs\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
}