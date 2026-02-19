#!/usr/bin/env php
<?php
/**
 * Recalcula métricas financeiras de todos os pedidos existentes
 * 
 * Este script:
 * 1. Busca todos os pedidos do banco
 * 2. Calcula métricas financeiras com base no order_data
 * 3. Atualiza os campos calculados
 * 
 * Uso: php scripts/recalculate_order_metrics.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = App\Database::getInstance();

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     RECALCULAR MÉTRICAS FINANCEIRAS DOS PEDIDOS           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

try {
    // Contar total de pedidos
    $stmt = $db->query("SELECT COUNT(*) FROM ml_orders");
    $total = $stmt->fetchColumn();
    
    if ($total == 0) {
        echo "⚠️  Nenhum pedido encontrado no banco.\n";
        echo "   Execute primeiro: php scripts/cron_sync_orders.php\n";
        exit(0);
    }
    
    echo "📊 Total de pedidos a processar: $total\n\n";
    
    $response = readline("Continuar? (s/n): ");
    if (strtolower($response) !== 's') {
        echo "Operação cancelada.\n";
        exit(0);
    }
    
    echo "\n⚙️  Processando pedidos...\n\n";
    
    // Processar em lotes de 100
    $batchSize = 100;
    $offset = 0;
    $processed = 0;
    $updated = 0;
    $errors = 0;
    
    $startTime = microtime(true);
    
    while ($offset < $total) {
        $limitSql = max(1, min(1000, (int)$batchSize));
        $offsetSql = max(0, (int)$offset);
        $stmt = $db->prepare(
            "SELECT id, order_data\n"
            . "FROM ml_orders\n"
            . "LIMIT {$limitSql} OFFSET {$offsetSql}"
        );
        $stmt->execute();
        
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $order) {
            try {
                $orderData = json_decode($order['order_data'], true);
                
                if (!$orderData) {
                    $errors++;
                    continue;
                }
                
                // Calcular métricas
                $metrics = calculateOrderMetricsFromOrderData($orderData);
                
                // Converter booleanos para inteiros
                $metrics['is_profitable'] = $metrics['is_profitable'] ? 1 : 0;
                $metrics['is_full'] = $metrics['is_full'] ? 1 : 0;
                $metrics['is_flex'] = $metrics['is_flex'] ? 1 : 0;
                $metrics['free_shipping'] = $metrics['free_shipping'] ? 1 : 0;
                $metrics['is_delayed'] = $metrics['is_delayed'] ? 1 : 0;
                
                // Atualizar no banco
                $updateStmt = $db->prepare("
                    UPDATE ml_orders 
                    SET 
                        subtotal = :subtotal,
                        ml_commission = :ml_commission,
                        payment_fee = :payment_fee,
                        fixed_fee = :fixed_fee,
                        shipping_cost = :shipping_cost,
                        discount_amount = :discount_amount,
                        taxes = :taxes,
                        product_cost = :product_cost,
                        total_costs = :total_costs,
                        net_revenue = :net_revenue,
                        gross_margin = :gross_margin,
                        net_profit = :net_profit,
                        roi = :roi,
                        is_profitable = :is_profitable,
                        is_full = :is_full,
                        is_flex = :is_flex,
                        free_shipping = :free_shipping,
                        listing_type = :listing_type,
                        payment_method = :payment_method,
                        installments = :installments,
                        items_count = :items_count,
                        shipped_at = :shipped_at,
                        delivered_at = :delivered_at,
                        handling_time = :handling_time,
                        delivery_time = :delivery_time,
                        is_delayed = :is_delayed
                    WHERE id = :id
                ");
                
                $updateStmt->execute(array_merge($metrics, ['id' => $order['id']]));
                
                $updated++;
                $processed++;
                
                // Progress bar
                $progress = round(($processed / $total) * 100);
                $bar = str_repeat('█', (int)($progress / 2)) . str_repeat('░', 50 - (int)($progress / 2));
                echo "\r[{$bar}] {$progress}% ({$processed}/{$total})";
                
            } catch (Exception $e) {
                $errors++;
                error_log("Erro ao processar pedido {$order['id']}: " . $e->getMessage());
            }
        }
        
        $offset += $batchSize;
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "\n\n✅ Processamento concluído!\n\n";
    
    echo "📊 Estatísticas:\n";
    echo "  • Total processados: $processed\n";
    echo "  • Atualizados com sucesso: $updated\n";
    echo "  • Erros: $errors\n";
    echo "  • Tempo de execução: {$duration}s\n";
    echo "  • Média: " . round($processed / $duration, 2) . " pedidos/s\n\n";
    
    // Estatísticas gerais
    echo "📈 Análise Geral:\n";
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(is_profitable) as profitable,
            AVG(gross_margin) as avg_margin,
            AVG(net_profit) as avg_profit,
            SUM(ml_commission) as total_commission,
            SUM(payment_fee) as total_payment_fee,
            SUM(is_full) as full_count,
            SUM(free_shipping) as free_shipping_count
        FROM ml_orders
    ");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $profitablePercent = ($stats['total'] > 0) 
        ? round(($stats['profitable'] / $stats['total']) * 100, 1) 
        : 0;
    
    echo "  • Pedidos lucrativos: {$stats['profitable']}/{$stats['total']} ({$profitablePercent}%)\n";
    echo "  • Margem média: " . round($stats['avg_margin'], 2) . "%\n";
    echo "  • Lucro médio: R$ " . number_format($stats['avg_profit'], 2, ',', '.') . "\n";
    echo "  • Comissão ML total: R$ " . number_format($stats['total_commission'], 2, ',', '.') . "\n";
    echo "  • Taxa pagamento total: R$ " . number_format($stats['total_payment_fee'], 2, ',', '.') . "\n";
    echo "  • Pedidos Full: {$stats['full_count']}\n";
    echo "  • Pedidos com frete grátis: {$stats['free_shipping_count']}\n\n";
    
    echo "🎉 Pronto! Acesse /dashboard/orders para ver as métricas atualizadas.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

function calculateOrderMetricsFromOrderData(array $orderData): array
{
    $totalAmount = (float)($orderData['total_amount'] ?? 0);
    $items = $orderData['order_items'] ?? [];
    $payments = $orderData['payments'] ?? [];
    $shipping = $orderData['shipping'] ?? [];

    $subtotal = 0.0;
    $mlCommission = 0.0;
    $itemsCount = 0;
    $listingType = 'unknown';

    foreach ($items as $item) {
        $quantity = (int)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['unit_price'] ?? 0);
        $saleFee = (float)($item['sale_fee'] ?? 0);

        $subtotal += ($unitPrice * $quantity);
        $mlCommission += $saleFee;
        $itemsCount += max(1, $quantity);

        if ($listingType === 'unknown' && !empty($item['listing_type_id'])) {
            $listingType = (string)$item['listing_type_id'];
        }
    }

    if ($subtotal <= 0) {
        $subtotal = $totalAmount;
    }

    $paymentFee = 0.0;
    $paymentMethod = 'unknown';
    $installments = 1;

    foreach ($payments as $payment) {
        $paymentFee += (float)($payment['marketplace_fee'] ?? 0);

        if ($paymentMethod === 'unknown' && !empty($payment['payment_method_id'])) {
            $paymentMethod = (string)$payment['payment_method_id'];
        }

        $installments = max($installments, (int)($payment['installments'] ?? 1));
    }

    $fixedFee = 0.0;
    $shippingCost = (float)($shipping['cost'] ?? $shipping['shipping_cost'] ?? 0);
    $discountAmount = (float)($orderData['discount_amount'] ?? 0);
    $taxes = (float)($orderData['taxes'] ?? 0);
    $productCost = (float)($orderData['product_cost'] ?? 0);

    $totalCosts = $mlCommission + $paymentFee + $fixedFee + $shippingCost + $discountAmount + $taxes + $productCost;
    $netRevenue = $subtotal - ($mlCommission + $paymentFee + $fixedFee + $shippingCost + $discountAmount + $taxes);
    $netProfit = $subtotal - $totalCosts;
    $grossMargin = $subtotal > 0 ? (($netProfit / $subtotal) * 100) : 0.0;
    $roi = $totalCosts > 0 ? (($netProfit / $totalCosts) * 100) : 0.0;

    $logisticType = strtolower((string)($shipping['logistic_type'] ?? ''));
    $isFull = in_array($logisticType, ['fulfillment', 'full'], true);
    $isFlex = $logisticType === 'flex';
    $freeShipping = (bool)($shipping['free_shipping'] ?? false);

    $shippedAt = normalizeDateTime($shipping['date_shipped'] ?? null);
    $deliveredAt = normalizeDateTime($shipping['date_delivered'] ?? null);
    $createdAt = normalizeDateTime($orderData['date_created'] ?? null);

    $handlingTime = null;
    if ($createdAt !== null && $shippedAt !== null) {
        $handlingTime = max(0, (int)round((strtotime($shippedAt) - strtotime($createdAt)) / 3600));
    }

    $deliveryTime = null;
    if ($shippedAt !== null && $deliveredAt !== null) {
        $deliveryTime = max(0, (int)round((strtotime($deliveredAt) - strtotime($shippedAt)) / 3600));
    }

    $shippingStatus = strtolower((string)($shipping['status'] ?? ''));
    $isDelayed = $shippingStatus === 'delayed' || (($handlingTime ?? 0) > 48);

    return [
        'subtotal' => round($subtotal, 2),
        'ml_commission' => round($mlCommission, 2),
        'payment_fee' => round($paymentFee, 2),
        'fixed_fee' => round($fixedFee, 2),
        'shipping_cost' => round($shippingCost, 2),
        'discount_amount' => round($discountAmount, 2),
        'taxes' => round($taxes, 2),
        'product_cost' => round($productCost, 2),
        'total_costs' => round($totalCosts, 2),
        'net_revenue' => round($netRevenue, 2),
        'gross_margin' => round($grossMargin, 2),
        'net_profit' => round($netProfit, 2),
        'roi' => round($roi, 2),
        'is_profitable' => $netProfit >= 0,
        'is_full' => $isFull,
        'is_flex' => $isFlex,
        'free_shipping' => $freeShipping,
        'listing_type' => $listingType,
        'payment_method' => $paymentMethod,
        'installments' => $installments,
        'items_count' => $itemsCount > 0 ? $itemsCount : 1,
        'shipped_at' => $shippedAt,
        'delivered_at' => $deliveredAt,
        'handling_time' => $handlingTime,
        'delivery_time' => $deliveryTime,
        'is_delayed' => $isDelayed,
    ];
}

function normalizeDateTime($value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}
