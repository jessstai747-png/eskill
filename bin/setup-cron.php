#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ⚙️ SETUP CRON - Configurador Automático de CRON Jobs
 * 
 * Configura automaticamente os cron jobs necessários para o SEO Killer:
 * - Worker de processamento em massa
 * - AutoPilot de otimização automática
 * - Coleta de métricas A/B
 * 
 * Uso:
 *   php bin/setup-cron.php [--install] [--remove] [--list]
 * 
 * Opções:
 *   --install   Instala os cron jobs
 *   --remove    Remove os cron jobs
 *   --list      Lista os cron jobs atuais
 *   --user      Usuário do cron (padrão: www-data)
 */

$action = 'list';
$cronUser = 'www-data';

foreach ($argv as $arg) {
    if ($arg === '--install') $action = 'install';
    if ($arg === '--remove') $action = 'remove';
    if ($arg === '--list') $action = 'list';
    if (strpos($arg, '--user=') === 0) {
        $cronUser = str_replace('--user=', '', $arg);
        // Validate cron user to prevent command injection
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cronUser)) {
            echo "❌ Usuário inválido: contém caracteres não permitidos\n";
            exit(1);
        }
    }
}

$basePath = dirname(__DIR__);
$phpBin = '/usr/bin/php';

// Cron jobs a serem configurados
$cronJobs = [
    [
        'name' => 'SEO Worker (Bulk Optimizer)',
        'schedule' => '*/5 * * * *', // A cada 5 minutos
        'command' => "{$phpBin} {$basePath}/bin/seo-worker.php --once >> {$basePath}/storage/logs/seo-worker.log 2>&1",
        'description' => 'Processa jobs de otimização em massa'
    ],
    [
        'name' => 'AutoPilot (Otimização Automática)',
        'schedule' => '0 2 * * *', // Todo dia às 2h da manhã
        'command' => "{$phpBin} {$basePath}/bin/ai-worker.php >> {$basePath}/storage/logs/autopilot.log 2>&1",
        'description' => 'Executa otimizações automáticas configuradas'
    ],
    [
        'name' => 'A/B Test Updater',
        'schedule' => '0 3 * * *', // Todo dia às 3h da manhã
        'command' => "{$phpBin} {$basePath}/bin/ab-test-updater.php >> {$basePath}/storage/logs/ab-test.log 2>&1",
        'description' => 'Atualiza testes A/B e coleta métricas'
    ],
];

echo "\n🔥 SEO KILLER - Setup de CRON Jobs\n";
echo str_repeat("=", 70) . "\n\n";

if ($action === 'list') {
    echo "📋 Cron jobs configurados:\n\n";

    // Listar cron atual
    $escapedUser = escapeshellarg($cronUser);
    exec("crontab -u {$escapedUser} -l 2>/dev/null", $currentCron, $returnCode);

    if ($returnCode !== 0 || empty($currentCron)) {
        echo "❌ Nenhum cron job configurado para o usuário '{$cronUser}'\n\n";
    } else {
        foreach ($currentCron as $line) {
            if (
                strpos($line, 'seo-worker') !== false ||
                strpos($line, 'ai-worker') !== false ||
                strpos($line, 'ab-test') !== false
            ) {
                echo "  ✓ {$line}\n";
            }
        }
        echo "\n";
    }

    echo "📌 Cron jobs recomendados:\n\n";
    foreach ($cronJobs as $job) {
        echo "  {$job['name']}\n";
        echo "  Schedule: {$job['schedule']}\n";
        echo "  Command: {$job['command']}\n";
        echo "  Descrição: {$job['description']}\n\n";
    }
} elseif ($action === 'install') {
    echo "⚙️ Instalando cron jobs...\n\n";

    // Criar diretório de logs se não existir
    $logDir = "{$basePath}/storage/logs";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
        echo "  ✓ Diretório de logs criado: {$logDir}\n";
    }

    // Tornar scripts executáveis
    $scripts = [
        "{$basePath}/bin/seo-worker.php",
        "{$basePath}/bin/ai-worker.php",
    ];

    foreach ($scripts as $script) {
        if (file_exists($script)) {
            chmod($script, 0755);
            echo "  ✓ Script marcado como executável: " . basename($script) . "\n";
        }
    }

    echo "\n";

    // Obter cron atual
    $escapedUser = escapeshellarg($cronUser);
    exec("crontab -u {$escapedUser} -l 2>/dev/null", $currentCron);

    // Remover linhas antigas do SEO Killer
    $currentCron = array_filter($currentCron, function ($line) {
        return strpos($line, 'seo-worker') === false &&
            strpos($line, 'ai-worker') === false &&
            strpos($line, 'ab-test') === false;
    });

    // Adicionar header
    $currentCron[] = "";
    $currentCron[] = "# SEO Killer - Cron Jobs (Instalado em " . date('Y-m-d H:i:s') . ")";

    // Adicionar novos jobs
    foreach ($cronJobs as $job) {
        $currentCron[] = "# {$job['name']} - {$job['description']}";
        $currentCron[] = "{$job['schedule']} {$job['command']}";
    }

    // Salvar novo crontab
    $tempFile = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tempFile, implode("\n", $currentCron) . "\n");

    $escapedTempFile = escapeshellarg($tempFile);
    exec("crontab -u {$escapedUser} {$escapedTempFile}", $output, $returnCode);
    unlink($tempFile);

    if ($returnCode === 0) {
        echo "✅ Cron jobs instalados com sucesso!\n\n";

        foreach ($cronJobs as $job) {
            echo "  ✓ {$job['name']}\n";
            echo "    {$job['schedule']}\n";
        }

        echo "\n📝 Logs serão salvos em:\n";
        echo "  {$basePath}/storage/logs/\n\n";

        echo "💡 Para verificar:\n";
        echo "  crontab -u {$cronUser} -l\n\n";
    } else {
        echo "❌ Erro ao instalar cron jobs\n";
        echo "Output: " . implode("\n", $output) . "\n\n";
        exit(1);
    }
} elseif ($action === 'remove') {
    echo "🗑️  Removendo cron jobs do SEO Killer...\n\n";

    // Obter cron atual
    $escapedUser = escapeshellarg($cronUser);
    exec("crontab -u {$escapedUser} -l 2>/dev/null", $currentCron);

    if (empty($currentCron)) {
        echo "ℹ️  Nenhum cron job encontrado\n\n";
        exit(0);
    }

    // Remover linhas do SEO Killer
    $newCron = [];
    $removing = false;
    $removed = 0;

    foreach ($currentCron as $line) {
        if (strpos($line, '# SEO Killer') !== false) {
            $removing = true;
            $removed++;
            continue;
        }

        if ($removing && (strpos($line, 'seo-worker') !== false ||
            strpos($line, 'ai-worker') !== false ||
            strpos($line, 'ab-test') !== false)) {
            $removed++;
            continue;
        }

        if ($removing && trim($line) === '') {
            $removing = false;
            continue;
        }

        $newCron[] = $line;
    }

    if ($removed > 0) {
        // Salvar novo crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tempFile, implode("\n", $newCron) . "\n");

        $escapedTempFile = escapeshellarg($tempFile);
        exec("crontab -u {$escapedUser} {$escapedTempFile}", $output, $returnCode);
        unlink($tempFile);

        if ($returnCode === 0) {
            echo "✅ {$removed} entradas removidas com sucesso\n\n";
        } else {
            echo "❌ Erro ao remover cron jobs\n\n";
            exit(1);
        }
    } else {
        echo "ℹ️  Nenhum cron job do SEO Killer encontrado\n\n";
    }
}

echo "✅ Operação concluída\n\n";
exit(0);
