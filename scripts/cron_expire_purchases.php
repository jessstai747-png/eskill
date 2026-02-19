<?php

/**
 * Cron Job: Expirar compras pendentes e liberar EANs reservados
 * 
 * Executar a cada 5 minutos:
 * 0/5 * * * * php /home/eskill/htdocs/eskill.com.br/scripts/cron_expire_purchases.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Models\EanPurchase;
use App\Models\EanInventory;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$logFile = __DIR__ . '/../storage/logs/ean_cron_' . date('Y-m-d') . '.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

try {
    logMessage("=== Iniciando cron de expiração de compras ===", $logFile);

    $db = Database::getInstance();
    $purchaseModel = new EanPurchase();
    $inventoryModel = new EanInventory();

    // 1. Buscar compras pendentes expiradas
    $expiredPurchases = $purchaseModel->getExpiredPending();

    if (empty($expiredPurchases)) {
        logMessage("Nenhuma compra expirada encontrada", $logFile);
    } else {
        logMessage("Encontradas " . count($expiredPurchases) . " compras expiradas", $logFile);

        foreach ($expiredPurchases as $purchase) {
            $db->beginTransaction();

            try {
                // Cancelar a compra
                $purchaseModel->cancel($purchase['id']);

                // Buscar EANs reservados para esta compra (se houver)
                $stmt = $db->prepare("
                    SELECT inventory_id FROM ean_assignments 
                    WHERE purchase_id = :purchase_id
                ");
                $stmt->execute(['purchase_id' => $purchase['id']]);
                $reservedEans = $stmt->fetchAll();

                if (!empty($reservedEans)) {
                    $eanIds = array_column($reservedEans, 'inventory_id');
                    $inventoryModel->releaseReserved($eanIds);
                    logMessage("  Liberados " . count($eanIds) . " EANs da compra #{$purchase['id']}", $logFile);
                }

                $db->commit();
                logMessage("  Compra #{$purchase['id']} cancelada com sucesso", $logFile);
            } catch (Exception $e) {
                $db->rollBack();
                logMessage("  ERRO na compra #{$purchase['id']}: " . $e->getMessage(), $logFile);
            }
        }
    }

    // 2. Limpar EANs reservados há mais de 1 hora (sem compra associada)
    $stmt = $db->query("
        SELECT COUNT(*) as total FROM ean_inventory 
        WHERE status = 'reserved' 
        AND reserved_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $orphanedReserved = (int) $stmt->fetch()['total'];

    if ($orphanedReserved > 0) {
        $stmt = $db->query("
            UPDATE ean_inventory 
            SET status = 'available', reserved_at = NULL 
            WHERE status = 'reserved' 
            AND reserved_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        logMessage("Liberados $orphanedReserved EANs órfãos (reservados há mais de 1h)", $logFile);
    }

    // 3. Estatísticas atuais
    $stmt = $db->query("
        SELECT status, COUNT(*) as total 
        FROM ean_inventory 
        GROUP BY status
    ");
    $stats = [];
    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = $row['total'];
    }

    logMessage("Inventário atual: " .
        "disponíveis=" . ($stats['available'] ?? 0) . ", " .
        "reservados=" . ($stats['reserved'] ?? 0) . ", " .
        "vendidos=" . ($stats['sold'] ?? 0), $logFile);

    logMessage("=== Cron finalizado ===", $logFile);
} catch (Exception $e) {
    logMessage("ERRO FATAL: " . $e->getMessage(), $logFile);
    exit(1);
}
