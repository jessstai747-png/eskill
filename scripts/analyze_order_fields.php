#!/usr/bin/env php
<?php
/**
 * Análise completa dos dados financeiros disponíveis nos pedidos
 * e proposta de campos adicionais úteis
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = App\Database::getInstance();

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   ANÁLISE FINANCEIRA - PEDIDOS MERCADO LIVRE              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Buscar um pedido recente com dados completos
$stmt = $db->query("
    SELECT order_data 
    FROM ml_orders 
    WHERE order_data IS NOT NULL 
    ORDER BY date_created DESC 
    LIMIT 1
");

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "❌ Nenhum pedido encontrado no banco\n";
    exit(1);
}

$data = json_decode($order['order_data'], true);

echo "📊 PARTE 1: Campos Disponíveis na API do ML\n";
echo str_repeat("─", 60) . "\n\n";

// Valores principais
echo "💰 VALORES BÁSICOS:\n";
echo "  • total_amount: R$ " . number_format($data['total_amount'] ?? 0, 2, ',', '.') . "\n";
echo "  • paid_amount: R$ " . number_format($data['paid_amount'] ?? 0, 2, ',', '.') . "\n";
echo "  • currency_id: " . ($data['currency_id'] ?? 'BRL') . "\n\n";

// Pagamentos
if (!empty($data['payments'])) {
    echo "💳 PAGAMENTOS (" . count($data['payments']) . "):\n";
    foreach ($data['payments'] as $i => $payment) {
        echo "  Pagamento " . ($i+1) . ":\n";
        echo "    • transaction_amount: R$ " . number_format($payment['transaction_amount'] ?? 0, 2, ',', '.') . "\n";
        echo "    • total_paid_amount: R$ " . number_format($payment['total_paid_amount'] ?? 0, 2, ',', '.') . "\n";
        echo "    • shipping_cost: R$ " . number_format($payment['shipping_cost'] ?? 0, 2, ',', '.') . "\n";
        echo "    • coupon_amount: R$ " . number_format($payment['coupon_amount'] ?? 0, 2, ',', '.') . "\n";
        echo "    • marketplace_fee: R$ " . number_format($payment['marketplace_fee'] ?? 0, 2, ',', '.') . "\n";
        echo "    • payment_type: " . ($payment['payment_type'] ?? 'N/A') . "\n";
        echo "    • installments: " . ($payment['installments'] ?? 1) . "x\n";
        echo "    • status: " . ($payment['status'] ?? 'N/A') . "\n\n";
    }
}

// Itens
if (!empty($data['order_items'])) {
    echo "📦 ITENS DO PEDIDO:\n";
    $subtotalItems = 0;
    $totalSaleFee = 0;
    
    foreach ($data['order_items'] as $i => $item) {
        $unitPrice = $item['unit_price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $itemTotal = $unitPrice * $quantity;
        $saleFee = $item['sale_fee'] ?? 0;
        
        echo "  Item " . ($i+1) . ": " . ($item['item']['title'] ?? 'N/A') . "\n";
        echo "    • unit_price: R$ " . number_format($unitPrice, 2, ',', '.') . "\n";
        echo "    • quantity: {$quantity}\n";
        echo "    • item_total: R$ " . number_format($itemTotal, 2, ',', '.') . "\n";
        echo "    • sale_fee (comissão ML): R$ " . number_format($saleFee, 2, ',', '.') . "\n";
        echo "    • listing_type_id: " . ($item['item']['listing_type_id'] ?? 'N/A') . "\n\n";
        
        $subtotalItems += $itemTotal;
        $totalSaleFee += $saleFee;
    }
    
    echo "  Subtotal: R$ " . number_format($subtotalItems, 2, ',', '.') . "\n";
    echo "  Comissão ML Total: R$ " . number_format($totalSaleFee, 2, ',', '.') . "\n\n";
}

// Frete
if (!empty($data['shipping'])) {
    echo "🚚 FRETE:\n";
    $shipping = $data['shipping'];
    echo "  • cost: R$ " . number_format($shipping['cost'] ?? 0, 2, ',', '.') . "\n";
    echo "  • mode: " . ($shipping['mode'] ?? 'N/A') . "\n";
    echo "  • shipping_mode: " . ($shipping['shipping_mode'] ?? 'N/A') . "\n";
    echo "  • shipping_method: " . ($shipping['shipping_method_id'] ?? 'N/A') . "\n";
    echo "  • logistic_type: " . ($shipping['logistic_type'] ?? 'N/A') . "\n";
    echo "  • substatus: " . ($shipping['substatus'] ?? 'N/A') . "\n\n";
}

// Tags (indicam características especiais)
if (!empty($data['tags'])) {
    echo "🏷️  TAGS: " . implode(', ', $data['tags']) . "\n\n";
}

echo "\n" . str_repeat("═", 60) . "\n\n";

echo "💡 PARTE 2: Campos Calculáveis que Podemos Adicionar\n";
echo str_repeat("─", 60) . "\n\n";

echo "1. 💸 CUSTOS E TAXAS:\n";
echo "   ✅ Comissão ML (sale_fee) - JÁ DISPONÍVEL\n";
echo "   ✅ Custo de frete (shipping_cost) - JÁ DISPONÍVEL\n";
echo "   ➕ Taxa Mercado Pago (~4.99%)\n";
echo "   ➕ Taxa fixa ML (R$ 6 se < R$ 79)\n";
echo "   ➕ Impostos (se vendedor PJ)\n";
echo "   ➕ Custo do produto (preço de custo)\n\n";

echo "2. 💰 RENTABILIDADE:\n";
echo "   ➕ Receita líquida = total - comissões - taxas - frete\n";
echo "   ➕ Margem bruta = (receita líquida / total) * 100\n";
echo "   ➕ Lucro líquido = receita líquida - custo produto\n";
echo "   ➕ ROI = (lucro / custo) * 100\n";
echo "   ➕ Prejuízo (se negativo)\n\n";

echo "3. 📊 ANÁLISE DE DESEMPENHO:\n";
echo "   ➕ Ticket médio\n";
echo "   ➕ Quantidade de itens por pedido\n";
echo "   ➕ Valor médio por item\n";
echo "   ➕ Taxa de conversão (se houver dados)\n\n";

echo "4. ⏱️  PRAZOS E LOGÍSTICA:\n";
echo "   ➕ Tempo até envio (date_created -> shipped)\n";
echo "   ➕ Tempo de entrega (shipped -> delivered)\n";
echo "   ➕ Tempo total (created -> delivered)\n";
echo "   ➕ Atraso (se houver)\n\n";

echo "5. 💳 ANÁLISE DE PAGAMENTO:\n";
echo "   ➕ Forma de pagamento principal\n";
echo "   ➕ Parcelamento (quantidade parcelas)\n";
echo "   ➕ Taxa por parcelamento\n";
echo "   ➕ Uso de cupom/desconto\n\n";

echo "6. 🎯 INDICADORES ESTRATÉGICOS:\n";
echo "   ➕ É Full? (fulfillment)\n";
echo "   ➕ É Flex? (cross_docking)\n";
echo "   ➕ Frete grátis?\n";
echo "   ➕ Tipo de anúncio (gold, premium, clássico)\n";
echo "   ➕ Região do comprador\n";
echo "   ➕ Cliente recorrente?\n\n";

echo "\n" . str_repeat("═", 60) . "\n\n";

echo "📋 PARTE 3: Estrutura de Tabela Sugerida\n";
echo str_repeat("─", 60) . "\n\n";

echo "Campos adicionais para ml_orders:\n\n";

echo "-- Valores financeiros\n";
echo "ALTER TABLE ml_orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN ml_commission DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN payment_fee DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN fixed_fee DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN taxes DECIMAL(10,2) DEFAULT 0;\n\n";

echo "-- Custos (se cadastrados)\n";
echo "ALTER TABLE ml_orders ADD COLUMN product_cost DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN total_costs DECIMAL(10,2) DEFAULT 0;\n\n";

echo "-- Rentabilidade\n";
echo "ALTER TABLE ml_orders ADD COLUMN net_revenue DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN gross_margin DECIMAL(5,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN net_profit DECIMAL(10,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN roi DECIMAL(5,2) DEFAULT 0;\n";
echo "ALTER TABLE ml_orders ADD COLUMN is_profitable BOOLEAN DEFAULT TRUE;\n\n";

echo "-- Características\n";
echo "ALTER TABLE ml_orders ADD COLUMN is_full BOOLEAN DEFAULT FALSE;\n";
echo "ALTER TABLE ml_orders ADD COLUMN is_flex BOOLEAN DEFAULT FALSE;\n";
echo "ALTER TABLE ml_orders ADD COLUMN free_shipping BOOLEAN DEFAULT FALSE;\n";
echo "ALTER TABLE ml_orders ADD COLUMN listing_type VARCHAR(50);\n";
echo "ALTER TABLE ml_orders ADD COLUMN payment_method VARCHAR(50);\n";
echo "ALTER TABLE ml_orders ADD COLUMN installments INT DEFAULT 1;\n";
echo "ALTER TABLE ml_orders ADD COLUMN items_count INT DEFAULT 1;\n\n";

echo "-- Prazos\n";
echo "ALTER TABLE ml_orders ADD COLUMN shipped_at DATETIME;\n";
echo "ALTER TABLE ml_orders ADD COLUMN delivered_at DATETIME;\n";
echo "ALTER TABLE ml_orders ADD COLUMN handling_time INT; -- minutos\n";
echo "ALTER TABLE ml_orders ADD COLUMN delivery_time INT; -- minutos\n";
echo "ALTER TABLE ml_orders ADD COLUMN is_delayed BOOLEAN DEFAULT FALSE;\n\n";

echo "\n" . str_repeat("═", 60) . "\n\n";

echo "✅ Análise concluída!\n\n";
echo "📝 Próximos passos:\n";
echo "  1. Criar migration para adicionar campos\n";
echo "  2. Modificar OrderService->syncOrders() para calcular valores\n";
echo "  3. Atualizar dashboard para mostrar métricas\n";
echo "  4. Criar relatório financeiro completo\n";
