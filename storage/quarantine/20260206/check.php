<?php
/**
 * Verificação Rápida do Sistema
 * Acesse: http://localhost/check.php
 */
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verificação do Sistema</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .ok { color: green; }
        .erro { color: red; }
        .aviso { color: orange; }
        .box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>✅ Verificação do Sistema</h1>
    
    <?php
    $problemas = [];
    
    // 1. PHP
    echo "<div class='box'><h2>1. PHP</h2>";
    echo "Versão: " . phpversion();
    if (version_compare(phpversion(), '8.0.0', '>=')) {
        echo " <span class='ok'>✅</span>";
    } else {
        echo " <span class='erro'>❌ Precisa PHP 8.0+</span>";
        $problemas[] = "PHP 8.0+ necessário";
    }
    echo "</div>";
    
    // 2. Extensões
    echo "<div class='box'><h2>2. Extensões</h2>";
    $exts = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl'];
    foreach ($exts as $ext) {
        $ok = extension_loaded($ext);
        echo $ext . ": " . ($ok ? "<span class='ok'>✅</span>" : "<span class='erro'>❌</span>") . "<br>";
        if (!$ok) $problemas[] = "Extensão {$ext} não encontrada";
    }
    echo "</div>";
    
    // 3. Arquivos
    echo "<div class='box'><h2>3. Arquivos</h2>";
    $arquivos = ['../.env', '../vendor/autoload.php', '../config/app.php'];
    foreach ($arquivos as $arq) {
        $ok = file_exists($arq);
        echo basename($arq) . ": " . ($ok ? "<span class='ok'>✅</span>" : "<span class='erro'>❌</span>") . "<br>";
        if (!$ok) $problemas[] = "Arquivo {$arq} não encontrado";
    }
    echo "</div>";
    
    // 4. Banco
    echo "<div class='box'><h2>4. Banco de Dados</h2>";
    if (file_exists('../.env')) {
        $env = parse_ini_file('../.env');
        try {
            $pdo = new PDO(
                "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($env['DB_NAME'] ?? 'mercadolivre_db'),
                $env['DB_USER'] ?? 'root',
                $env['DB_PASS'] ?? ''
            );
            echo "<span class='ok'>✅ Conexão OK</span><br>";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "Tabelas: " . count($tables) . "<br>";
        } catch (Exception $e) {
            echo "<span class='erro'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>";
            $problemas[] = "Erro de conexão com banco";
        }
    } else {
        echo "<span class='erro'>❌ Arquivo .env não encontrado</span>";
        $problemas[] = "Arquivo .env não encontrado";
    }
    echo "</div>";
    
    // 5. Rotas
    echo "<div class='box'><h2>5. Teste de Acesso</h2>";
    $base = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    echo "<a href='{$base}/auth/login'>Login</a><br>";
    echo "<a href='{$base}/diagnostic.php'>Diagnóstico Completo</a><br>";
    echo "</div>";
    
    // Resumo
    echo "<div class='box'><h2>Resumo</h2>";
    if (empty($problemas)) {
        echo "<p class='ok'><strong>✅ Sistema OK! Pode acessar o sistema.</strong></p>";
        echo "<p><a href='{$base}/auth/login' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Acessar Login</a></p>";
    } else {
        echo "<p class='erro'><strong>❌ Encontrados " . count($problemas) . " problema(s):</strong></p>";
        echo "<ul>";
        foreach ($problemas as $p) {
            echo "<li class='erro'>{$p}</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    ?>
</body>
</html>
