<!DOCTYPE html>
<html>
<head>
    <title>Teste de Sessão - Eskill</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 0 0;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Diagnóstico de Sessão</h1>
        
        <?php
        session_start();
        
        echo "<h3>Estado da Sessão:</h3>";
        
        // 1. Verificar se está logado
        if (isset($_SESSION['user_id'])) {
            echo "<p class='success'>✅ <strong>Você ESTÁ logado!</strong></p>";
            echo "<ul>";
            echo "<li>ID do Usuário: <strong>{$_SESSION['user_id']}</strong></li>";
            echo "<li>Email: <strong>" . ($_SESSION['user_email'] ?? 'N/A') . "</strong></li>";
            echo "<li>Conta Ativa: <strong>" . ($_SESSION['active_ml_account_id'] ?? 'Nenhuma selecionada') . "</strong></li>";
            echo "</ul>";
            
            // Buscar contas
            require_once __DIR__ . '/../vendor/autoload.php';
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
            
            $db = App\Database::getInstance();
            $stmt = $db->prepare("SELECT id, nickname, status FROM ml_accounts WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($accounts)) {
                echo "<h3>Contas Vinculadas:</h3>";
                echo "<ul>";
                foreach ($accounts as $acc) {
                    $stmt2 = $db->prepare("SELECT COUNT(*) FROM ml_orders WHERE ml_account_id = ?");
                    $stmt2->execute([$acc['id']]);
                    $orderCount = $stmt2->fetchColumn();
                    echo "<li>ID {$acc['id']}: <strong>{$acc['nickname']}</strong> ({$acc['status']}) - {$orderCount} pedidos</li>";
                }
                echo "</ul>";
            } else {
                echo "<p class='warning'>⚠️ Você não tem contas do Mercado Livre vinculadas.</p>";
            }
            
            // Testar API
            echo "<h3>Teste da API:</h3>";
            echo "<pre id='api-test'>Carregando...</pre>";
            
            echo "<script>
                fetch('/api/orders/all?limit=5')
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('api-test').textContent = JSON.stringify(data, null, 2);
                        
                        if (data.results && data.results.length > 0) {
                            document.getElementById('api-test').innerHTML += '\\n\\n<span class=\"success\">✅ API funcionando! Retornou ' + data.results.length + ' pedidos.</span>';
                        } else if (data.error) {
                            document.getElementById('api-test').innerHTML += '\\n\\n<span class=\"error\">❌ Erro: ' + data.error + '</span>';
                        } else {
                            document.getElementById('api-test').innerHTML += '\\n\\n<span class=\"warning\">⚠️ API respondeu mas sem pedidos.</span>';
                        }
                    })
                    .catch(err => {
                        document.getElementById('api-test').innerHTML = '<span class=\"error\">❌ Erro ao chamar API: ' + err.message + '</span>';
                    });
            </script>";
            
        } else {
            echo "<p class='error'>❌ <strong>Você NÃO está logado!</strong></p>";
            echo "<p>A sessão está vazia ou expirou.</p>";
            echo "<p>Variáveis de sessão disponíveis:</p>";
            echo "<pre>" . print_r($_SESSION, true) . "</pre>";
        }
        
        echo "<h3>Informações Adicionais:</h3>";
        echo "<ul>";
        echo "<li>Session ID: <code>" . session_id() . "</code></li>";
        echo "<li>Session Status: <code>" . (session_status() === PHP_SESSION_ACTIVE ? 'Ativa' : 'Inativa') . "</code></li>";
        echo "<li>Cookie Path: <code>" . session_get_cookie_params()['path'] . "</code></li>";
        echo "<li>Cookie Domain: <code>" . (session_get_cookie_params()['domain'] ?: '(padrão)') . "</code></li>";
        echo "</ul>";
        
        echo "<hr>";
        echo "<a href='/auth/login' class='btn'>🔑 Fazer Login</a>";
        echo "<a href='/dashboard/orders' class='btn'>📦 Ver Pedidos</a>";
        echo "<a href='/auth/logout' class='btn' style='background:#dc3545'>🚪 Logout</a>";
        ?>
        
    </div>
</body>
</html>
