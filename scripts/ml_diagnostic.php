#!/usr/bin/env php
<?php
/**
 * Diagnóstico Completo - Integração Mercado Livre
 * 
 * Verifica:
 * - Tokens e autenticação
 * - APIs do ML funcionando
 * - Sincronização de dados
 * - Performance e erros
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Database;
use App\Services\MercadoLivreClient;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      🛒 DIAGNÓSTICO MERCADO LIVRE - ML MANAGER              ║\n";
echo "║      " . date('Y-m-d H:i:s') . "                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$errors = [];
$warnings = [];

try {
    $db = Database::getInstance();
    
    // ==========================================
    // 1. CONTAS E TOKENS
    // ==========================================
    echo "📊 1. CONTAS E TOKENS\n";
    echo "────────────────────────────────────────\n";
    
    $stmt = $db->query("
        SELECT id, nickname, ml_user_id, status, 
            token_expires_at,
            TIMESTAMPDIFF(HOUR, NOW(), token_expires_at) as hours_left,
            TIMESTAMPDIFF(MINUTE, NOW(), token_expires_at) as minutes_left
        FROM ml_accounts
        ORDER BY status DESC, id
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $activeAccounts = [];
    foreach ($accounts as $acc) {
        $statusIcon = $acc['status'] === 'active' ? '✅' : '❌';
        $tokenStatus = '';
        
        if ($acc['status'] === 'active') {
            $activeAccounts[] = $acc;
            
            if ($acc['hours_left'] < 1) {
                $tokenStatus = "⚠️ Expira em {$acc['minutes_left']}min!";
                $warnings[] = "Token da conta {$acc['nickname']} expira em breve";
            } elseif ($acc['hours_left'] < 2) {
                $tokenStatus = "⚠️ {$acc['hours_left']}h restantes";
            } else {
                $tokenStatus = "✅ {$acc['hours_left']}h restantes";
            }
        } else {
            $tokenStatus = "N/A";
        }
        
        printf("  %s %-20s (ML:%s) | %s | %s\n", 
            $statusIcon, 
            $acc['nickname'],
            $acc['ml_user_id'],
            $acc['status'],
            $tokenStatus
        );
    }
    
    echo "\n";
    
    // ==========================================
    // 2. TESTE DE API POR CONTA
    // ==========================================
    echo "🔌 2. TESTE DE CONEXÃO API\n";
    echo "────────────────────────────────────────\n";
    
    foreach ($activeAccounts as $acc) {
        echo "  [{$acc['nickname']}] ";
        
        try {
            $client = new MercadoLivreClient((int)$acc['id']);
            $startTime = microtime(true);
            
            // Testar endpoint de usuário
            $user = $client->get("/users/{$acc['ml_user_id']}");
            $responseTime = round((microtime(true) - $startTime) * 1000);
            
            if ($user && isset($user['id'])) {
                echo "✅ OK ({$responseTime}ms)";
                
                // Verificar reputation
                $sellerRep = $user['seller_reputation'] ?? null;
                if ($sellerRep) {
                    $level = $sellerRep['level_id'] ?? 'N/A';
                    $sales = $sellerRep['transactions']['total'] ?? 0;
                    echo " | Vendas: {$sales} | Level: {$level}";
                }
            } else {
                echo "⚠️ Resposta inesperada";
                $warnings[] = "Resposta inesperada da API para {$acc['nickname']}";
            }
        } catch (\Exception $e) {
            echo "❌ Erro: " . $e->getMessage();
            $errors[] = "API Error [{$acc['nickname']}]: " . $e->getMessage();
        }
        
        echo "\n";
    }
    
    echo "\n";
    
    // ==========================================
    // 3. ESTATÍSTICAS DOS DADOS
    // ==========================================
    echo "📈 3. ESTATÍSTICAS DOS DADOS\n";
    echo "────────────────────────────────────────\n";
    
    // Pedidos
    $stmt = $db->query("
        SELECT 
            ml_account_id,
            COUNT(*) as total,
            SUM(CASE WHEN date_created > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as last_24h,
            SUM(CASE WHEN date_created > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_7d,
            SUM(total_amount) as total_amount
        FROM ml_orders
        GROUP BY ml_account_id
    ");
    $orderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  📦 PEDIDOS:\n";
    foreach ($orderStats as $stat) {
        $accName = 'Conta ' . $stat['ml_account_id'];
        foreach ($accounts as $a) {
            if ($a['id'] == $stat['ml_account_id']) {
                $accName = $a['nickname'];
                break;
            }
        }
        printf("     %-20s Total: %d | 24h: %d | 7d: %d | R$ %.2f\n",
            $accName,
            $stat['total'],
            $stat['last_24h'],
            $stat['last_7d'],
            $stat['total_amount']
        );
    }
    
    // Anúncios
    echo "\n  📝 ANÚNCIOS:\n";
    $stmt = $db->query("
        SELECT account_id, status, COUNT(*) as total
        FROM items
        GROUP BY account_id, status
        ORDER BY account_id, status
    ");
    $itemStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $itemsByAccount = [];
    foreach ($itemStats as $stat) {
        $aid = $stat['account_id'];
        if (!isset($itemsByAccount[$aid])) {
            $itemsByAccount[$aid] = [];
        }
        $itemsByAccount[$aid][$stat['status']] = $stat['total'];
    }
    
    foreach ($itemsByAccount as $aid => $statuses) {
        $accName = 'Conta ' . $aid;
        foreach ($accounts as $a) {
            if ($a['id'] == $aid) {
                $accName = $a['nickname'];
                break;
            }
        }
        
        $parts = [];
        foreach ($statuses as $status => $count) {
            $icon = $status === 'active' ? '🟢' : ($status === 'paused' ? '⏸️' : '🔸');
            $parts[] = "{$icon}{$status}: {$count}";
        }
        
        echo "     {$accName}: " . implode(' | ', $parts) . "\n";
    }
    
    // Perguntas
    echo "\n  ❓ PERGUNTAS:\n";
    $stmt = $db->query("
        SELECT 
            account_id,
            status,
            COUNT(*) as total,
            SUM(CASE WHEN date_created > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as last_24h
        FROM ml_questions
        GROUP BY account_id, status
    ");
    $questionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questionStats)) {
        echo "     Nenhuma pergunta sincronizada\n";
    } else {
        foreach ($questionStats as $stat) {
            $accName = 'Conta ' . $stat['account_id'];
            foreach ($accounts as $a) {
                if ($a['id'] == $stat['account_id']) {
                    $accName = $a['nickname'];
                    break;
                }
            }
            printf("     %-20s %s: %d (24h: %d)\n",
                $accName,
                $stat['status'],
                $stat['total'],
                $stat['last_24h']
            );
        }
    }
    
    echo "\n";
    
    // ==========================================
    // 4. JOBS E FILAS
    // ==========================================
    echo "⚙️ 4. FILA DE JOBS\n";
    echo "────────────────────────────────────────\n";
    
    $stmt = $db->query("
        SELECT status, COUNT(*) as total
        FROM jobs
        GROUP BY status
    ");
    $jobStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($jobStats as $stat) {
        $icon = match($stat['status']) {
            'pending' => '⏳',
            'processing' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            default => '🔸'
        };
        echo "  {$icon} {$stat['status']}: {$stat['total']}\n";
    }
    
    // Verificar jobs com erro
    $stmt = $db->query("
        SELECT type, error_message, COUNT(*) as total
        FROM jobs
        WHERE status = 'failed'
        GROUP BY type, error_message
        ORDER BY total DESC
        LIMIT 5
    ");
    $failedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($failedJobs)) {
        echo "\n  ⚠️ Jobs com erro mais comuns:\n";
        foreach ($failedJobs as $job) {
            $msg = substr($job['error_message'] ?? 'N/A', 0, 50);
            echo "     [{$job['type']}] {$msg}... ({$job['total']}x)\n";
        }
    }
    
    echo "\n";
    
    // ==========================================
    // 5. ÚLTIMA SINCRONIZAÇÃO
    // ==========================================
    echo "🕐 5. ÚLTIMA SINCRONIZAÇÃO\n";
    echo "────────────────────────────────────────\n";
    
    // Último pedido
    $stmt = $db->query("SELECT MAX(synced_at) as last_sync FROM ml_orders");
    $lastOrderSync = $stmt->fetch()['last_sync'];
    echo "  📦 Pedidos: " . ($lastOrderSync ?? 'Nunca') . "\n";
    
    // Último anúncio
    $stmt = $db->query("SELECT MAX(updated_at) as last_sync FROM items");
    $lastItemSync = $stmt->fetch()['last_sync'];
    echo "  📝 Anúncios: " . ($lastItemSync ?? 'Nunca') . "\n";
    
    // Última pergunta
    $stmt = $db->query("SELECT MAX(updated_at) as last_sync FROM ml_questions");
    $lastQuestionSync = $stmt->fetch()['last_sync'];
    echo "  ❓ Perguntas: " . ($lastQuestionSync ?? 'Nunca') . "\n";
    
    echo "\n";
    
    // ==========================================
    // 6. CRONS ATIVOS
    // ==========================================
    echo "⏰ 6. CRONS CONFIGURADOS\n";
    echo "────────────────────────────────────────\n";
    
    $cronOutput = shell_exec('crontab -l 2>/dev/null | grep -E "poll_|renew_|sync_" | head -10');
    if ($cronOutput) {
        $lines = explode("\n", trim($cronOutput));
        foreach ($lines as $line) {
            if (preg_match('/\*\/(\d+).*poll_orders/', $line, $m)) {
                echo "  ✅ Pedidos: a cada {$m[1]} minutos\n";
            } elseif (preg_match('/(\d+)\s+\*\/(\d+).*poll_items/', $line, $m)) {
                echo "  ✅ Anúncios: a cada {$m[2]} horas\n";
            } elseif (preg_match('/poll_questions/', $line)) {
                echo "  ✅ Perguntas: configurado\n";
            } elseif (preg_match('/renew_tokens/', $line)) {
                echo "  ✅ Tokens: renovação automática\n";
            }
        }
    } else {
        echo "  ⚠️ Nenhum cron encontrado\n";
        $warnings[] = "Crons de sincronização não configurados";
    }
    
    echo "\n";
    
    // ==========================================
    // RESUMO FINAL
    // ==========================================
    echo "════════════════════════════════════════════════════════════════\n";
    echo "📋 RESUMO DO DIAGNÓSTICO\n";
    echo "════════════════════════════════════════════════════════════════\n\n";
    
    $totalActive = count($activeAccounts);
    $totalItems = array_sum(array_map(fn($a) => array_sum($a), $itemsByAccount));
    $totalOrders = array_sum(array_column($orderStats, 'total'));
    
    echo "  Contas Ativas:    {$totalActive}\n";
    echo "  Total Anúncios:   {$totalItems}\n";
    echo "  Total Pedidos:    {$totalOrders}\n";
    
    echo "\n";
    
    if (empty($errors) && empty($warnings)) {
        echo "  ✅ SISTEMA 100% OPERACIONAL\n";
    } else {
        if (!empty($errors)) {
            echo "  ❌ ERROS (" . count($errors) . "):\n";
            foreach ($errors as $err) {
                echo "     - {$err}\n";
            }
        }
        if (!empty($warnings)) {
            echo "  ⚠️ AVISOS (" . count($warnings) . "):\n";
            foreach ($warnings as $warn) {
                echo "     - {$warn}\n";
            }
        }
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
