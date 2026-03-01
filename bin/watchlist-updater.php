#!/usr/bin/env php
<?php

/**
 * 🔖 Competitor Watchlist Updater Worker
 *
 * Atualiza todos os itens da watchlist e gera alertas automáticos
 *
 * CRON: Executar a cada 6 horas
 * 0 * / 6 * * * php /path/to/bin/watchlist-updater.php >> /path/to/storage/logs/watchlist.log 2>&1
 *
 * @author AI Development Team
 * @version 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\AI\SEO\CompetitorSpy;

echo "\n=====================================\n";
echo "🔖 WATCHLIST UPDATER WORKER\n";
echo "=====================================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$startTime = microtime(true);

try {
    $db = Database::getInstance();

    // Get all active watchlist items grouped by account
    $stmt = $db->query("
        SELECT DISTINCT account_id,
               COUNT(*) as item_count
        FROM competitor_watchlist
        WHERE status = 'active'
        GROUP BY account_id
    ");

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($accounts) . " accounts with watchlist items\n\n";

    $totalUpdated = 0;
    $totalChanges = 0;
    $totalErrors = 0;

    foreach ($accounts as $accountData) {
        $accountId = $accountData['account_id'];
        $itemCount = $accountData['item_count'];

        echo "Processing Account #{$accountId} ({$itemCount} items)...\n";

        // Get all watchlist items for this account
        $stmt = $db->prepare("
            SELECT id, competitor_item_id, title
            FROM competitor_watchlist
            WHERE account_id = :account_id
              AND status = 'active'
            ORDER BY last_checked_at ASC
        ");

        $stmt->execute(['account_id' => $accountId]);
        $watchlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spy = new CompetitorSpy($accountId);

        foreach ($watchlistItems as $item) {
            try {
                echo "  - Updating {$item['competitor_item_id']}... ";

                $result = $spy->updateWatchlistItem($item['id']);

                if ($result['success']) {
                    $changesCount = $result['changes_detected'];
                    echo "✅ ({$changesCount} changes)\n";

                    $totalUpdated++;
                    $totalChanges += $changesCount;

                    if ($changesCount > 0) {
                        echo "    Changes: ";
                        foreach ($result['changes'] as $change) {
                            echo "{$change['field']} ({$change['type']}), ";
                        }
                        echo "\n";
                    }
                } else {
                    echo "❌ Error: {$result['error']}\n";
                    $totalErrors++;
                }

                // Rate limiting: wait 500ms between requests
                usleep(500000);
            } catch (Exception $e) {
                echo "❌ Exception: {$e->getMessage()}\n";
                $totalErrors++;

                // If item not found (404), mark as inactive
                if (strpos($e->getMessage(), '404') !== false) {
                    $db->prepare("
                        UPDATE competitor_watchlist
                        SET status = 'inactive'
                        WHERE id = :id
                    ")->execute(['id' => $item['id']]);

                    echo "    Item marked as inactive (not found)\n";
                }
            }
        }

        echo "\n";
    }

    $duration = round(microtime(true) - $startTime, 2);

    echo "=====================================\n";
    echo "SUMMARY\n";
    echo "=====================================\n";
    echo "Total Updated: {$totalUpdated}\n";
    echo "Total Changes Detected: {$totalChanges}\n";
    echo "Total Errors: {$totalErrors}\n";
    echo "Duration: {$duration}s\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    echo "=====================================\n\n";

    // Log summary to database
    $db->prepare("
        INSERT INTO system_logs (type, message, metadata, created_at)
        VALUES ('watchlist_update', 'Watchlist updated successfully', :metadata, NOW())
    ")->execute([
        'metadata' => json_encode([
            'accounts_processed' => count($accounts),
            'items_updated' => $totalUpdated,
            'changes_detected' => $totalChanges,
            'errors' => $totalErrors,
            'duration' => $duration,
        ])
    ]);
} catch (Exception $e) {
    echo "❌ FATAL ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
