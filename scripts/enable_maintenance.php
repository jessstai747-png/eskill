<?php
/**
 * Script para Ativar/Desativar Modo Manutenção
 * Uso: php scripts/enable_maintenance.php [on|off]
 */

$action = $argv[1] ?? 'on';
$maintenanceFile = __DIR__ . '/../storage/maintenance.lock';

if ($action === 'on') {
    if (file_exists($maintenanceFile)) {
        echo "⚠️  Modo manutenção já está ativo.\n";
    } else {
        file_put_contents($maintenanceFile, date('Y-m-d H:i:s'));
        echo "✅ Modo manutenção ATIVADO.\n";
        echo "   O sistema mostrará a página de manutenção para todos os usuários.\n";
        echo "   Arquivos de diagnóstico ainda estarão acessíveis.\n";
    }
} elseif ($action === 'off') {
    if (file_exists($maintenanceFile)) {
        unlink($maintenanceFile);
        echo "✅ Modo manutenção DESATIVADO.\n";
        echo "   O sistema está acessível normalmente.\n";
    } else {
        echo "⚠️  Modo manutenção já está desativado.\n";
    }
} else {
    echo "Uso: php scripts/enable_maintenance.php [on|off]\n";
    exit(1);
}
