#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron script to synchronize items from Mercado Livre accounts.
 *
 * This script should be run periodically (e.g., every few hours) via cron.
 * Example cron entry:
 *  0 * /3 * * * /usr/bin/php /path/to/your/project/bin/sync-items.php >> /path/to/your/project/storage/logs/cron.log 2>&1
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';

// Load environment variables (if available)
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

use App\Services\ItemSyncService;
use App\Helpers\SessionHelper; // Using this helper to easily get account IDs

$options = getopt('', [
    'account::',
    'help',
]);

if (isset($options['help'])) {
    echo "Uso:\n";
    echo "  php bin/sync-items.php\n";
    echo "  php bin/sync-items.php --account=2\n\n";
    echo "Opções:\n";
    echo "  --account=ID    Sincroniza somente a conta (ml_accounts.id)\n";
    echo "  --help          Mostra esta ajuda\n";
    exit(0);
}

echo "=================================================\n";
echo "Starting Item Sync Job at " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n";

try {
    // We need to bootstrap the session to get user info if SessionHelper depends on it
    // For a cron script, it's better if SessionHelper can work without a live session.
    // Let's assume we can get account IDs directly.
    
    $db = \App\Database::getInstance();

    $accountIds = [];
    if (isset($options['account']) && $options['account'] !== false && $options['account'] !== '') {
        $accountId = (int)$options['account'];
        if ($accountId <= 0) {
            throw new \InvalidArgumentException('Parâmetro --account inválido.');
        }

        $stmt = $db->prepare("SELECT id FROM ml_accounts WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => $accountId]);
        $found = (int)$stmt->fetchColumn();
        if ($found <= 0) {
            echo "Conta {$accountId} não encontrada ou não está ativa.\n";
            exit(2);
        }
        $accountIds = [$accountId];
    } else {
        $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active'");
        $accountIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (empty($accountIds)) {
        echo "No active accounts found to sync.\n";
        exit(0);
    }

    echo "Found " . count($accountIds) . " active accounts to sync.\n";

    $itemSyncService = new ItemSyncService();
    $totalStats = ['total_found' => 0, 'total_synced' => 0, 'total_accounts' => 0];

    foreach ($accountIds as $accountId) {
        echo "\n--- Syncing Account ID: {$accountId} ---\n";
        try {
            $stats = $itemSyncService->syncForAccount($accountId);
            echo "Sync completed for account {$accountId}.\n";
            echo "  - Found: {$stats['total_found']}\n";
            echo "  - Synced: {$stats['total_synced']}\n";
            echo "  - Batches: {$stats['batches']}\n";

            $totalStats['total_found'] += $stats['total_found'];
            $totalStats['total_synced'] += $stats['total_synced'];
            $totalStats['total_accounts']++;

        } catch (\Exception $e) {
            echo "Error syncing account {$accountId}: " . $e->getMessage() . "\n";
            error_log("Item Sync cron failed for account {$accountId}: " . $e->getMessage());
        }
    }

    echo "\n=================================================\n";
    echo "Item Sync Job Finished at " . date('Y-m-d H:i:s') . "\n";
    echo "Synced {$totalStats['total_accounts']} accounts. Total items found: {$totalStats['total_found']}, Total items synced: {$totalStats['total_synced']}.\n";
    echo "=================================================\n";

} catch (\Exception $e) {
    echo "A critical error occurred: " . $e->getMessage() . "\n";
    error_log("Item Sync cron job failed critically: " . $e->getMessage());
    exit(1);
}

exit(0);
