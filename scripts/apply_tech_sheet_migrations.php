<?php
/**
 * Apply Tech Sheet Migrations
 * Aplica todas as migrations de ficha técnica
 */

// Carregar variáveis de ambiente
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Carregar autoload do Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                           ║\n";
echo "║           APLICAR MIGRATIONS - TECH SHEET (FICHA TÉCNICA)                ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = App\Database::getInstance();

    echo "✅ Conectado ao banco de dados\n\n";

    // Lista de migrations SQL para aplicar (paths relativos ao root do projeto)
    $rootDir = dirname(__DIR__);
    $migrations = [
        $rootDir . '/database/migrations/2026_01_01_000001_create_tech_sheet_tables.sql',
        $rootDir . '/database/migrations/2026_01_01_create_tech_sheet_scheduled_jobs.sql',
        $rootDir . '/database/migrations/2026_01_01_create_tech_sheet_execution_log.sql',
        $rootDir . '/database/migrations/2026_01_01_create_tech_sheet_webhooks_alerts.sql'
    ];

    $applied = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($migrations as $index => $migrationFile) {
        $num = $index + 1;
        $total = count($migrations);
        $filename = basename($migrationFile);

        echo "┌" . str_repeat("─", 77) . "┐\n";
        echo "│ MIGRATION {$num}/{$total}: " . str_pad($filename, 61) . "│\n";
        echo "└" . str_repeat("─", 77) . "┘\n\n";

        if (!file_exists($migrationFile)) {
            echo "⚠️  Arquivo não encontrado: {$migrationFile}\n";
            $skipped++;
            echo "\n";
            continue;
        }

        $sql = file_get_contents($migrationFile);

        if (empty($sql)) {
            echo "⚠️  Arquivo vazio\n";
            $skipped++;
            echo "\n";
            continue;
        }

        // Dividir em statements individuais
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($s) { return !empty($s) && stripos($s, 'CREATE TABLE') !== false; }
        );

        echo "📝 Statements encontrados: " . count($statements) . "\n\n";

        foreach ($statements as $idx => $statement) {
            // Extrair nome da tabela
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];

                // Verificar se tabela já existe
                $checkStmt = $db->prepare("SHOW TABLES LIKE '{$tableName}'");
                $checkStmt->execute();
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($exists) {
                    echo "  ⏭️  Tabela '{$tableName}' já existe (pulando)\n";
                    continue;
                }

                try {
                    $db->exec($statement . ';');
                    echo "  ✅ Tabela '{$tableName}' criada com sucesso\n";
                    $applied++;
                } catch (Exception $e) {
                    echo "  ❌ Erro ao criar tabela '{$tableName}': " . $e->getMessage() . "\n";
                    $errors++;
                }
            }
        }

        echo "\n";
    }

    echo str_repeat("═", 79) . "\n\n";

    // Verificar tabelas criadas
    echo "🔍 VERIFICANDO TABELAS CRIADAS\n";
    echo str_repeat("─", 79) . "\n";

    $expectedTables = [
        'tech_sheet_item_summary',
        'tech_sheet_suggestions',
        'tech_sheet_scheduled_jobs',
        'tech_sheet_execution_log',
        'tech_sheet_webhooks',
        'tech_sheet_alerts'
    ];

    $createdCount = 0;

    foreach ($expectedTables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE '{$table}'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM {$table}");
            $countStmt->execute();
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            echo "✅ {$table}: {$count} registros\n";
            $createdCount++;
        } else {
            echo "❌ {$table}: NÃO ENCONTRADA\n";
        }
    }

    echo "\n";
    echo str_repeat("═", 79) . "\n\n";

    // Resumo final
    echo "📊 RESUMO\n";
    echo str_repeat("─", 79) . "\n";
    echo "Tabelas criadas: {$applied}\n";
    echo "Migrations aplicadas: " . ($applied > 0 ? count($migrations) : 0) . "/" . count($migrations) . "\n";
    echo "Tabelas verificadas: {$createdCount}/" . count($expectedTables) . "\n";
    echo "Erros: {$errors}\n";

    echo "\n";

    if ($createdCount >= 2) {
        echo "🎉 SUCESSO! Tabelas principais de Tech Sheet criadas\n";
        echo "\n";
        echo "✅ Sistema de Ficha Técnica pronto para integração!\n";
    } else {
        echo "⚠️  ATENÇÃO: Algumas tabelas não foram criadas\n";
        echo "   Verifique os erros acima\n";
    }

} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                           ║\n";
echo "║                    ✅ PROCESSO CONCLUÍDO!                                 ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
