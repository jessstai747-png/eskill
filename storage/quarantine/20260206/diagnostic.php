<?php
/**
 * Arquivo de Diagnóstico Completo
 * Acesse: http://localhost/diagnostic.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnóstico - Mercado Livre Manager</title>";
echo "<style>body{font-family:Arial;margin:20px;background:#f5f5f5;} .success{color:green;} .error{color:red;} .warning{color:orange;} .section{margin:20px 0;padding:15px;background:white;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);} h2{border-bottom:2px solid #667eea;padding-bottom:10px;}</style></head><body>";
echo "<h1>🔍 Diagnóstico Completo do Sistema</h1>";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar PHP
echo "<div class='section'><h2>1. Versão PHP</h2>";
$phpVersion = phpversion();
echo "<p>Versão: <strong>{$phpVersion}</strong></p>";
if (version_compare($phpVersion, '8.0.0', '>=')) {
    $success[] = "PHP 8.0+ instalado";
    echo "<p class='success'>✅ PHP 8.0+ OK</p>";
} else {
    $errors[] = "PHP 8.0+ necessário";
    echo "<p class='error'>❌ PHP 8.0+ necessário (atual: {$phpVersion})</p>";
}
echo "</div>";

// 2. Verificar Extensões
echo "<div class='section'><h2>2. Extensões PHP</h2>";
$required = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl', 'session'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='success'>✅ {$ext}</p>";
        $success[] = "Extensão {$ext} carregada";
    } else {
        echo "<p class='error'>❌ {$ext} NÃO encontrada</p>";
        $errors[] = "Extensão {$ext} não encontrada";
    }
}
echo "</div>";

// 3. Verificar Arquivos
echo "<div class='section'><h2>3. Arquivos Essenciais</h2>";
$files = [
    '../.env' => 'Arquivo de configuração',
    '../vendor/autoload.php' => 'Autoloader Composer',
    '../config/app.php' => 'Configuração app',
    '../config/database.php' => 'Configuração banco',
    '.htaccess' => 'Configuração Apache',
    'index.php' => 'Entry point',
];
foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ {$desc}: {$file}</p>";
    } else {
        echo "<p class='error'>❌ {$desc}: {$file} NÃO encontrado</p>";
        $errors[] = "Arquivo {$file} não encontrado";
    }
}
echo "</div>";

// 4. Verificar Composer
echo "<div class='section'><h2>4. Dependências Composer</h2>";
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    echo "<p class='success'>✅ Autoloader carregado</p>";
    
    // Verificar classes principais
    $classes = [
        'App\\Router',
        'App\\Database',
        'App\\Services\\UserService',
        'App\\Controllers\\AuthController',
    ];
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "<p class='success'>✅ Classe {$class}</p>";
        } else {
            echo "<p class='error'>❌ Classe {$class} NÃO encontrada</p>";
            $errors[] = "Classe {$class} não encontrada";
        }
    }
} else {
    echo "<p class='error'>❌ Composer não instalado. Execute: composer install</p>";
    $errors[] = "Composer não instalado";
}
echo "</div>";

// 5. Verificar .env
echo "<div class='section'><h2>5. Configuração (.env)</h2>";
if (file_exists('../.env')) {
    $env = parse_ini_file('../.env');
    // Suporte para ambos os formatos de variáveis
    $requiredEnv = [
        'DB_HOST' => ['DB_HOST'],
        'DB_DATABASE' => ['DB_DATABASE', 'DB_NAME'],
        'DB_USERNAME' => ['DB_USERNAME', 'DB_USER'],
    ];
    foreach ($requiredEnv as $label => $keys) {
        $found = false;
        foreach ($keys as $key) {
            if (isset($env[$key]) && !empty($env[$key])) {
                echo "<p class='success'>✅ {$label} configurado</p>";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "<p class='warning'>⚠️ {$label} não configurado</p>";
            $warnings[] = "Variável {$label} não configurada";
        }
    }
    
    if (empty($env['APP_KEY'])) {
        echo "<p class='warning'>⚠️ APP_KEY não configurado (gerar com: php -r \"echo bin2hex(random_bytes(32));\")</p>";
        $warnings[] = "APP_KEY não configurado";
    } else {
        echo "<p class='success'>✅ APP_KEY configurado</p>";
    }
} else {
    echo "<p class='error'>❌ Arquivo .env não encontrado</p>";
    $errors[] = "Arquivo .env não encontrado";
}
echo "</div>";

// 6. Verificar Banco de Dados
echo "<div class='section'><h2>6. Conexão com Banco de Dados</h2>";
if (file_exists('../.env')) {
    $env = parse_ini_file('../.env');
    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? 'mercadolivre_db';
    $user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p class='success'>✅ Conexão com banco OK</p>";
        $success[] = "Conexão com banco OK";
        
        // Verificar tabelas
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tabelas encontradas: <strong>" . count($tables) . "</strong></p>";
        
        $requiredTables = ['users', 'ml_accounts'];
        foreach ($requiredTables as $table) {
            if (in_array($table, $tables)) {
                echo "<p class='success'>✅ Tabela {$table} existe</p>";
            } else {
                echo "<p class='error'>❌ Tabela {$table} NÃO existe</p>";
                $errors[] = "Tabela {$table} não existe";
            }
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Erro ao conectar: " . htmlspecialchars($e->getMessage()) . "</p>";
        $errors[] = "Erro de conexão: " . $e->getMessage();
    }
} else {
    echo "<p class='error'>❌ Não é possível verificar banco sem .env</p>";
}
echo "</div>";

// 7. Verificar Permissões
echo "<div class='section'><h2>7. Permissões de Diretórios</h2>";
$dirs = ['../storage/cache', '../storage/logs'];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        if (@mkdir($dir, 0775, true)) {
            echo "<p class='success'>✅ Diretório criado: {$dir}</p>";
        } else {
            echo "<p class='error'>❌ Não foi possível criar: {$dir}</p>";
            $errors[] = "Não foi possível criar diretório {$dir}";
        }
    } else {
        // Teste real de escrita
        $testFile = $dir . '/.write_test_' . uniqid() . '.tmp';
        if (@file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            echo "<p class='success'>✅ {$dir} é gravável</p>";
        } else {
            echo "<p class='warning'>⚠️ {$dir} NÃO é gravável</p>";
            $warnings[] = "Diretório {$dir} não é gravável";
        }
    }
}
echo "</div>";

// 8. Testar Rotas
echo "<div class='section'><h2>8. Teste de Rotas</h2>";
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
$routes = [
    '/auth/login' => 'Login',
    '/auth/register' => 'Registro',
    '/dashboard' => 'Dashboard',
    '/api/categories' => 'API Categorias',
];
echo "<ul>";
foreach ($routes as $route => $name) {
    echo "<li><a href='{$baseUrl}{$route}' target='_blank'>{$name}</a> ({$baseUrl}{$route})</li>";
}
echo "</ul>";
echo "</div>";

// 9. Resumo
echo "<div class='section'><h2>9. Resumo</h2>";
echo "<p><strong>Sucessos:</strong> " . count($success) . "</p>";
echo "<p><strong>Avisos:</strong> " . count($warnings) . "</p>";
echo "<p><strong>Erros:</strong> " . count($errors) . "</p>";

if (count($errors) === 0) {
    echo "<h3 class='success'>✅ Sistema parece estar funcionando corretamente!</h3>";
    echo "<p><a href='{$baseUrl}/auth/login' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-top:10px;'>Acessar Login</a></p>";
} else {
    echo "<h3 class='error'>❌ Encontrados " . count($errors) . " erro(s) que precisam ser corrigidos:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>{$error}</li>";
    }
    echo "</ul>";
}

if (count($warnings) > 0) {
    echo "<h3 class='warning'>⚠️ Avisos:</h3>";
    echo "<ul>";
    foreach ($warnings as $warning) {
        echo "<li class='warning'>{$warning}</li>";
    }
    echo "</ul>";
}
echo "</div>";

// 10. Informações do Servidor
echo "<div class='section'><h2>10. Informações do Servidor</h2>";
echo "<p><strong>Servidor:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</p>";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</p>";
echo "<p><strong>Script Name:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
echo "</div>";

echo "</body></html>";
?>
