#!/bin/bash
# Script de teste para verificar pedidos

echo "=== TESTE DE PEDIDOS - DIAGNÓSTICO COMPLETO ==="
echo ""

# 1. Verificar banco de dados
echo "1. Verificando dados no banco..."
php -r "
require_once 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->safeLoad();

\$db = App\Database::getInstance();

// Pedidos
\$count = \$db->query('SELECT COUNT(*) FROM ml_orders')->fetchColumn();
echo \"   ✓ Total de pedidos: \$count\n\";

// Contas
\$count = \$db->query('SELECT COUNT(*) FROM ml_accounts WHERE status = 'active'')->fetchColumn();
echo \"   ✓ Contas ativas: \$count\n\";

// Usuários
\$count = \$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo \"   ✓ Usuários: \$count\n\";
"
echo ""

# 2. Testar API com sessão simulada
echo "2. Testando API com sessão..."
php -r '
require_once "vendor/autoload.php";
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

session_start();
$_SESSION["user_id"] = 1;

$controller = new App\Controllers\OrderController();

ob_start();
$controller->all();
$response = ob_get_clean();

$data = json_decode($response, true);
echo "   ✓ Status: " . (isset($data["error"]) ? "ERRO - " . $data["error"] : "OK") . "\n";
echo "   ✓ Total retornado: " . ($data["total"] ?? 0) . "\n";
echo "   ✓ Pedidos: " . count($data["results"] ?? []) . "\n";
'
echo ""

# 3. Verificar logs
echo "3. Últimas linhas do log de erros:"
if [ -f "storage/logs/error.log" ]; then
    tail -n 5 storage/logs/error.log 2>/dev/null || echo "   (sem erros recentes)"
else
    echo "   (arquivo de log não existe)"
fi
echo ""

# 4. Verificar permissões
echo "4. Verificando permissões de storage..."
ls -la storage/logs/ 2>/dev/null | head -5 || echo "   (diretório não existe)"
echo ""

# 5. Status do servidor
echo "5. Status do servidor:"
ps aux | grep -E 'php|apache|nginx' | grep -v grep | head -3
echo ""

echo "=== FIM DO DIAGNÓSTICO ==="
echo ""
echo "PRÓXIMOS PASSOS:"
echo "1. Acesse https://eskill.com.br/dashboard/orders"
echo "2. Abra o Console do navegador (F12)"
echo "3. Verifique mensagens de erro"
echo "4. Se aparecer 'Usuário não autenticado', faça login novamente"
