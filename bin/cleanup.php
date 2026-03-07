#!/usr/bin/env php
<?php
/**
 * Limpador Automático de Cache e Logs
 * 
 * Remove automaticamente:
 * - Logs antigos (>30 dias)
 * - Cache expirado
 * - Arquivos temporários
 * - Sessions expiradas
 * 
 * Uso: php bin/cleanup.php [--dry-run] [--verbose]
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv) || in_array('-d', $argv);
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

echo "🧹 Limpador Automático - " . date('Y-m-d H:i:s') . "\n";
echo ($dryRun ? "🔍 MODO DRY-RUN (nenhum arquivo será deletado)\n" : "⚠️  MODO REAL (arquivos serão deletados!)\n");
echo str_repeat("=", 80) . "\n\n";

$stats = [
    'logs_deleted' => 0,
    'logs_size' => 0,
    'cache_deleted' => 0,
    'cache_size' => 0,
    'temp_deleted' => 0,
    'temp_size' => 0,
    'sessions_deleted' => 0,
];

// 1. Limpar logs antigos (> 30 dias)
echo "📋 LOGS ANTIGOS (> 30 dias)\n";
echo str_repeat("-", 80) . "\n";

$logsDir = __DIR__ . '/../storage/logs';
$logFiles = glob($logsDir . '/*.log');
$cutoffTime = time() - (30 * 24 * 60 * 60); // 30 dias

foreach ($logFiles as $file) {
    $mtime = filemtime($file);
    
    if ($mtime < $cutoffTime) {
        $size = filesize($file);
        $age = round((time() - $mtime) / 86400);
        $sizeMB = round($size / 1024 / 1024, 2);
        
        echo "  🗑️  " . basename($file) . " ({$age} dias, {$sizeMB} MB)\n";
        
        $stats['logs_deleted']++;
        $stats['logs_size'] += $size;
        
        if (!$dryRun) {
            unlink($file);
        }
    }
}

if ($stats['logs_deleted'] === 0) {
    echo "  ✅ Nenhum log antigo encontrado\n";
}
echo "\n";

// 2. Limpar cache expirado
echo "💾 CACHE EXPIRADO\n";
echo str_repeat("-", 80) . "\n";

$cacheDirs = [
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/framework/cache',
];

foreach ($cacheDirs as $cacheDir) {
    if (!is_dir($cacheDir)) {
        continue;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        
        $mtime = $file->getMTime();
        $age = time() - $mtime;
        
        // Cache > 7 dias
        if ($age > (7 * 24 * 60 * 60)) {
            $size = $file->getSize();
            
            if ($verbose) {
                echo "  🗑️  " . $file->getFilename() . " (" . round($age / 86400) . " dias)\n";
            }
            
            $stats['cache_deleted']++;
            $stats['cache_size'] += $size;
            
            if (!$dryRun) {
                unlink($file->getPathname());
            }
        }
    }
}

if ($stats['cache_deleted'] === 0) {
    echo "  ✅ Nenhum cache expirado encontrado\n";
} else {
    echo "  🗑️  {$stats['cache_deleted']} arquivo(s) de cache removidos\n";
}
echo "\n";

// 3. Limpar arquivos temporários
echo "📦 ARQUIVOS TEMPORÁRIOS\n";
echo str_repeat("-", 80) . "\n";

$tempDirs = [
    __DIR__ . '/../storage/tmp',
    __DIR__ . '/../storage/temp',
    sys_get_temp_dir() . '/eskill_*',
];

foreach ($tempDirs as $pattern) {
    $tempFiles = glob($pattern);
    
    foreach ($tempFiles as $file) {
        if (!is_file($file)) {
            continue;
        }
        
        $mtime = filemtime($file);
        $age = time() - $mtime;
        
        // Temp > 24 horas
        if ($age > 86400) {
            $size = filesize($file);
            
            if ($verbose) {
                echo "  🗑️  " . basename($file) . "\n";
            }
            
            $stats['temp_deleted']++;
            $stats['temp_size'] += $size;
            
            if (!$dryRun) {
                unlink($file);
            }
        }
    }
}

if ($stats['temp_deleted'] === 0) {
    echo "  ✅ Nenhum arquivo temporário antigo encontrado\n";
} else {
    echo "  🗑️  {$stats['temp_deleted']} arquivo(s) temporários removidos\n";
}
echo "\n";

// 4. Limpar sessions antigas
echo "🔐 SESSIONS EXPIRADAS\n";
echo str_repeat("-", 80) . "\n";

$sessionsPath = session_save_path() ?: '/tmp';
$sessionFiles = glob($sessionsPath . '/sess_*');

foreach ($sessionFiles as $file) {
    $mtime = filemtime($file);
    $age = time() - $mtime;
    
    // Sessions > 24 horas
    if ($age > 86400) {
        $size = filesize($file);
        
        if ($verbose) {
            echo "  🗑️  " . basename($file) . " (" . round($age / 3600) . "h)\n";
        }
        
        $stats['sessions_deleted']++;
        
        if (!$dryRun) {
            unlink($file);
        }
    }
}

if ($stats['sessions_deleted'] === 0) {
    echo "  ✅ Nenhuma session expirada encontrada\n";
} else {
    echo "  🗑️  {$stats['sessions_deleted']} session(s) removidas\n";
}
echo "\n";

// 5. Resumo
echo "📊 RESUMO\n";
echo str_repeat("=", 80) . "\n";

$totalFiles = $stats['logs_deleted'] + $stats['cache_deleted'] + $stats['temp_deleted'] + $stats['sessions_deleted'];
$totalSize = $stats['logs_size'] + $stats['cache_size'] + $stats['temp_size'];
$totalSizeMB = round($totalSize / 1024 / 1024, 2);

echo "Logs removidos:      {$stats['logs_deleted']} (" . round($stats['logs_size'] / 1024 / 1024, 2) . " MB)\n";
echo "Cache removido:      {$stats['cache_deleted']} (" . round($stats['cache_size'] / 1024 / 1024, 2) . " MB)\n";
echo "Temp removido:       {$stats['temp_deleted']} (" . round($stats['temp_size'] / 1024 / 1024, 2) . " MB)\n";
echo "Sessions removidas:  {$stats['sessions_deleted']}\n";
echo str_repeat("-", 80) . "\n";
echo "TOTAL: {$totalFiles} arquivo(s), {$totalSizeMB} MB liberados\n";

if ($dryRun) {
    echo "\n🔍 Modo dry-run: Nenhum arquivo foi realmente deletado.\n";
    echo "   Execute sem --dry-run para deletar os arquivos listados.\n";
}

echo "\n✅ Limpeza concluída em " . date('Y-m-d H:i:s') . "\n";

// Opcional: Comprimir logs antigos ao invés de deletar
if (!$dryRun && $stats['logs_deleted'] === 0) {
    echo "\n💡 Dica: Execute com --dry-run primeiro para ver o que seria deletado.\n";
}
