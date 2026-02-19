<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\LLMService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "\n=============================================\n";
echo "   🔍 DIAGNÓSTICO DO SISTEMA (ESKILL) 🔍   \n";
echo "=============================================\n\n";

// 1. Database
echo "[1] Banco de Dados (MySQL)... ";
try {
    $db = Database::getInstance();
    $repo = $db->query("SELECT count(*) FROM items")->fetchColumn();
    echo "✅ CONECTADO ($repo itens encontrados)\n";
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

// 2. Redis/Cache
echo "[2] Cache (Redis)............ ";
if (class_exists('Redis')) {
    try {
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', $_ENV['REDIS_PORT'] ?? 6379);
        $redis->set('test_key', 'ok');
        echo "✅ CONECTADO\n";
    } catch (Exception $e) {
        echo "⚠️  AVISO (Não crítico): " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  Redis não instalado (Usando File Cache)\n";
}

// 3. Mercado Livre API (Core)
echo "[3] API Mercado Livre....... ";
$mlClient = new MercadoLivreClient();
// We need a token. We'll try to use the one in DB if available, or .env
// This specific check tries to call /users/me
try {
    // Hack: Assuming Client handles token refresh internally if configured
    // We will verify if we have *any* token first
    $hasToken = !empty($_ENV['MERCADO_LIVRE_ACCESS_TOKEN']); 
    
    if (!$hasToken) {
        echo "⚠️  TOKEN NÃO CONFIGURADO (.env)\n";
    } else {
        try {
            $me = $mlClient->get('/users/me');
            echo "✅ ONLINE (Usuário: " . ($me['nickname'] ?? 'Unknown') . ")\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '403') !== false || strpos($e->getMessage(), '401') !== false) {
                 echo "❌ ERRO DE PERMISSÃO (Token Inválido ou Expirado)\n";
            } else {
                 echo "⚠️  FALLBACK ATIVO (Mock em uso ou Erro de Rede)\n";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ ERRO FATAL NO CLIENTE\n";
}

// 4. Intelligence (LLM)
echo "[4] Inteligência Artificial.. ";
if (empty($_ENV['ANTHROPIC_API_KEY'])) {
    echo "⭕ DESATIVADO (Sem API Key)\n";
} else {
    try {
        // Just check if class instantiates, actual call wastes money/credits
        $llm = new LLMService();
        echo "✅ CONFIGURADO (Pronto para uso)\n";
    } catch (Exception $e) {
         echo "❌ ERRO: " . $e->getMessage() . "\n";
    }
}

// 5. File System (PDF/Logs)
echo "[5] Sistema de Arquivos...... ";
$logPath = __DIR__ . '/../storage/logs';
if (is_writable($logPath)) {
    echo "✅ OK (Permissão de Escrita)\n";
} else {
    echo "❌ SEM PERMISSÃO em storage/logs\n";
}

echo "\n---------------------------------------------\n";
echo "RESUMO DOS MÓDULOS:\n";
echo "📦 Catálogo/Items:   " . (file_exists(__DIR__ . '/../app/Services/ItemService.php') ? "✅ Código Real" : "❌") . "\n";
echo "💰 Financeiro:       " . (file_exists(__DIR__ . '/../app/Services/FinancialService.php') ? "✅ Código Real" : "❌") . "\n";
echo "🏷️ Etiquetas (Flex): " . (file_exists(__DIR__ . '/../app/Services/ShippingService.php') ? "✅ Código Real" : "❌") . "\n";
echo "🤖 Auto-Responder:   " . (file_exists(__DIR__ . '/../app/Jobs/AutoAnswerJob.php') ? "✅ Código Real" : "❌") . "\n";
echo "📊 Relatórios PDF:   " . (file_exists(__DIR__ . '/../app/Services/ReportService.php') ? "✅ Código Real" : "❌") . "\n";
echo "🛡️ Segurança (Logs): " . (file_exists(__DIR__ . '/../app/Services/AuditService.php') ? "✅ Código Real" : "❌") . "\n";

echo "---------------------------------------------\n";
echo "NOTA: Para funcionamento 100% REAL, certifique-se que o TOKEN do Mercado Livre\n";
echo "está atualizado e possui as permissões (scopes) necessárias.\n";
echo "=============================================\n";
