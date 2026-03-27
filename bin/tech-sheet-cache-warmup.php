#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tech Sheet Cache Warmup
 * 
 * Pré-carrega atributos das categorias mais usadas no cache
 * para melhorar performance da Ficha Técnica
 * 
 * Uso:
 *   php bin/tech-sheet-cache-warmup.php [--categories=10] [--force]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/CacheHelper.php';

use App\Database;
use App\Services\MercadoLivreClient;

// Parse CLI arguments
$options = getopt('', ['categories::', 'force', 'help']);

if (isset($options['help'])) {
    echo <<<HELP

🔥 Tech Sheet Cache Warmup
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Pré-carrega atributos das categorias mais usadas para melhorar
performance da análise de Ficha Técnica.

OPÇÕES:
  --categories=N    Número de categorias para processar (padrão: 20)
  --force           Força atualização mesmo com cache válido
  --help            Mostra esta mensagem

EXEMPLOS:
  php bin/tech-sheet-cache-warmup.php
  php bin/tech-sheet-cache-warmup.php --categories=50
  php bin/tech-sheet-cache-warmup.php --force

HELP;
    exit(0);
}

$maxCategories = (int)($options['categories'] ?? 20);
$forceRefresh = isset($options['force']);

$maxCategoriesSql = max(1, min(200, $maxCategories));

echo "\n";
echo "🔥 Tech Sheet Cache Warmup\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Categorias: {$maxCategories}\n";
echo "Força refresh: " . ($forceRefresh ? 'SIM' : 'NÃO') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $db = Database::getInstance();
    
    // Buscar categorias mais usadas
    $stmt = $db->prepare("
        SELECT 
            category_id,
            COUNT(*) as item_count,
            COUNT(DISTINCT account_id) as account_count
        FROM items
        WHERE category_id IS NOT NULL
          AND status = 'active'
        GROUP BY category_id
        ORDER BY item_count DESC
                LIMIT {$maxCategoriesSql}
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categories)) {
        echo "❌ Nenhuma categoria encontrada nos itens\n";
        exit(1);
    }
    
    echo "✅ Encontradas " . count($categories) . " categorias\n\n";
    
    $stats = [
        'total' => count($categories),
        'cached' => 0,
        'fetched' => 0,
        'errors' => 0,
        'time_total' => microtime(true),
    ];
    
    // Processar cada categoria
    foreach ($categories as $idx => $cat) {
        $categoryId = $cat['category_id'];
        $itemCount = $cat['item_count'];
        $num = $idx + 1;
        
        echo "[{$num}/{$stats['total']}] {$categoryId} ({$itemCount} itens)... ";
        
        $cacheKey = "ml_category_attributes_{$categoryId}";
        $ttl = 86400; // 24h
        
        // Verificar cache existente
        if (!$forceRefresh) {
            $cached = cache()->get($cacheKey);
            if ($cached !== null) {
                echo "✅ CACHE\n";
                $stats['cached']++;
                continue;
            }
        }
        
        // Buscar da API (usar primeira conta disponível)
        $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            echo "❌ Nenhuma conta ativa\n";
            $stats['errors']++;
            continue;
        }
        
        try {
            $startTime = microtime(true);
            $mlClient = new MercadoLivreClient($account['id']);
            $attributes = $mlClient->getCategoryAttributes($categoryId);
            $elapsed = round((microtime(true) - $startTime) * 1000);
            
            if (isset($attributes['error'])) {
                echo "❌ ERRO: {$attributes['error']}\n";
                $stats['errors']++;
                continue;
            }
            
            // Salvar no cache
            cache()->set($cacheKey, $attributes, $ttl);
            echo "✅ FETCHED ({$elapsed}ms)\n";
            $stats['fetched']++;
            
            // Rate limiting - evitar sobrecarga na API do ML
            usleep(200000); // 200ms entre requests
            
        } catch (Exception $e) {
            echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    $stats['time_total'] = round(microtime(true) - $stats['time_total'], 2);
    
    // Resumo final
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 RESUMO\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Total processado:    {$stats['total']}\n";
    echo "Cache aproveitado:   {$stats['cached']}\n";
    echo "Buscados da API:     {$stats['fetched']}\n";
    echo "Erros:              {$stats['errors']}\n";
    echo "Tempo total:        {$stats['time_total']}s\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if ($stats['errors'] > 0) {
        exit(1);
    }
    
    echo "\n✅ Cache warmup concluído com sucesso!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO FATAL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
