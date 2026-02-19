<?php
/**
 * Teste Rápido - Acesse: http://localhost''/quick_test.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste Rápido</h1>";

// Teste 1: PHP
echo "<h2>1. PHP</h2>";
echo "Versão: " . phpversion() . "<br>";

// Teste 2: Sessão
echo "<h2>2. Sessão</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Sessão iniciada: ✅<br>";
$_SESSION['test'] = 'ok';
echo "Sessão funcionando: " . (isset($_SESSION['test']) ? '✅' : '❌') . "<br>";

// Teste 3: Autoloader
echo "<h2>3. Autoloader</h2>";
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    echo "Autoloader carregado: ✅<br>";
} else {
    echo "Autoloader NÃO encontrado: ❌<br>";
}

// Teste 4: .env
echo "<h2>4. Arquivo .env</h2>";
if (file_exists('../.env')) {
    echo ".env existe: ✅<br>";
    $env = parse_ini_file('../.env');
    echo "DB_HOST: " . ($env['DB_HOST'] ?? 'não configurado') . "<br>";
    echo "DB_NAME: " . ($env['DB_NAME'] ?? 'não configurado') . "<br>";
} else {
    echo ".env NÃO existe: ❌<br>";
}

// Teste 5: Banco
echo "<h2>5. Banco de Dados</h2>";
if (file_exists('../.env')) {
    $env = parse_ini_file('../.env');
    try {
        $pdo = new PDO(
            "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($env['DB_NAME'] ?? 'mercadolivre_db'),
            $env['DB_USER'] ?? 'root',
            $env['DB_PASS'] ?? ''
        );
        echo "Conexão OK: ✅<br>";
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage() . " ❌<br>";
    }
}

// Teste 6: Rotas
echo "<h2>6. Teste de Rotas</h2>";
$base = 'http://' . $_SERVER['HTTP_HOST'] . '''';
echo "<a href='{$base}/auth/login'>Login</a><br>";
echo "<a href='{$base}/auth/register'>Registro</a><br>";
echo "<a href='{$base}/dashboard'>Dashboard</a><br>";
echo "<a href='{$base}/diagnostic.php'>Diagnóstico Completo</a><br>";

echo "<h2>✅ Teste Concluído</h2>";
?>
