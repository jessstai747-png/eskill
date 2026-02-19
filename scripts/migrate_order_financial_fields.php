<?php
/**
 * Migration: Adiciona campos financeiros e analíticos aos pedidos
 * 
 * Adiciona:
 * - Custos e taxas detalhadas
 * - Rentabilidade e margens
 * - Características do pedido
 * - Prazos e logística
 * 
 * Uso: php scripts/migrate_order_financial_fields.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = App\Database::getInstance();

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     MIGRATION: Campos Financeiros para Pedidos            ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

try {
    // Verificar se já foi executado
    $stmt = $db->query("SHOW COLUMNS FROM ml_orders LIKE 'ml_commission'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Migration já foi executada anteriormente.\n";
        echo "   Os campos financeiros já existem.\n\n";
        
        $response = readline("Deseja recriar? (s/n): ");
        if (strtolower($response) !== 's') {
            echo "Migration cancelada.\n";
            exit(0);
        }
        
        echo "\n🗑️  Removendo campos existentes...\n";
        $fieldsToRemove = [
            'subtotal', 'ml_commission', 'payment_fee', 'fixed_fee', 'shipping_cost',
            'discount_amount', 'taxes', 'product_cost', 'total_costs', 'net_revenue',
            'gross_margin', 'net_profit', 'roi', 'is_profitable', 'is_full', 'is_flex',
            'free_shipping', 'listing_type', 'payment_method', 'installments',
            'items_count', 'shipped_at', 'delivered_at', 'handling_time', 
            'delivery_time', 'is_delayed'
        ];
        
        foreach ($fieldsToRemove as $field) {
            try {
                $db->exec("ALTER TABLE ml_orders DROP COLUMN $field");
            } catch (Exception $e) {
                // Campo pode não existir
            }
        }
    }
    
    echo "📝 Criando campos financeiros...\n\n";
    
    // 1. Valores financeiros
    echo "1. Valores financeiros... ";
    $db->exec("
        ALTER TABLE ml_orders
        ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0 COMMENT 'Subtotal dos itens',
        ADD COLUMN ml_commission DECIMAL(10,2) DEFAULT 0 COMMENT 'Comissão do ML',
        ADD COLUMN payment_fee DECIMAL(10,2) DEFAULT 0 COMMENT 'Taxa Mercado Pago',
        ADD COLUMN fixed_fee DECIMAL(10,2) DEFAULT 0 COMMENT 'Taxa fixa ML (<R$79)',
        ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0 COMMENT 'Custo do frete',
        ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Cupons/descontos',
        ADD COLUMN taxes DECIMAL(10,2) DEFAULT 0 COMMENT 'Impostos'
    ");
    echo "✅\n";
    
    // 2. Custos (se cadastrados)
    echo "2. Custos operacionais... ";
    $db->exec("
        ALTER TABLE ml_orders
        ADD COLUMN product_cost DECIMAL(10,2) DEFAULT 0 COMMENT 'Custo do produto',
        ADD COLUMN total_costs DECIMAL(10,2) DEFAULT 0 COMMENT 'Total de custos'
    ");
    echo "✅\n";
    
    // 3. Rentabilidade
    echo "3. Indicadores de rentabilidade... ";
    $db->exec("
        ALTER TABLE ml_orders
        ADD COLUMN net_revenue DECIMAL(10,2) DEFAULT 0 COMMENT 'Receita líquida',
        ADD COLUMN gross_margin DECIMAL(5,2) DEFAULT 0 COMMENT 'Margem bruta %',
        ADD COLUMN net_profit DECIMAL(10,2) DEFAULT 0 COMMENT 'Lucro líquido',
        ADD COLUMN roi DECIMAL(5,2) DEFAULT 0 COMMENT 'ROI %',
        ADD COLUMN is_profitable BOOLEAN DEFAULT TRUE COMMENT 'É lucrativo?'
    ");
    echo "✅\n";
    
    // 4. Características
    echo "4. Características do pedido... ";
    $db->exec("
        ALTER TABLE ml_orders
        ADD COLUMN is_full BOOLEAN DEFAULT FALSE COMMENT 'É Full?',
        ADD COLUMN is_flex BOOLEAN DEFAULT FALSE COMMENT 'É Flex?',
        ADD COLUMN free_shipping BOOLEAN DEFAULT FALSE COMMENT 'Frete grátis?',
        ADD COLUMN listing_type VARCHAR(50) COMMENT 'Tipo anúncio (gold, premium)',
        ADD COLUMN payment_method VARCHAR(50) COMMENT 'Forma de pagamento',
        ADD COLUMN installments INT DEFAULT 1 COMMENT 'Parcelas',
        ADD COLUMN items_count INT DEFAULT 1 COMMENT 'Qtd itens'
    ");
    echo "✅\n";
    
    // 5. Prazos e logística
    echo "5. Prazos e logística... ";
    $db->exec("
        ALTER TABLE ml_orders
        ADD COLUMN shipped_at DATETIME COMMENT 'Data de envio',
        ADD COLUMN delivered_at DATETIME COMMENT 'Data de entrega',
        ADD COLUMN handling_time INT COMMENT 'Tempo manuseio (min)',
        ADD COLUMN delivery_time INT COMMENT 'Tempo entrega (min)',
        ADD COLUMN is_delayed BOOLEAN DEFAULT FALSE COMMENT 'Atrasado?'
    ");
    echo "✅\n";
    
    // 6. Índices para performance
    echo "6. Criando índices... ";
    $db->exec("
        ALTER TABLE ml_orders
        ADD INDEX idx_is_profitable (is_profitable),
        ADD INDEX idx_net_profit (net_profit),
        ADD INDEX idx_listing_type (listing_type),
        ADD INDEX idx_is_full (is_full),
        ADD INDEX idx_shipped_at (shipped_at),
        ADD INDEX idx_delivered_at (delivered_at)
    ");
    echo "✅\n";
    
    echo "\n✅ Migration executada com sucesso!\n\n";
    
    // Estatísticas
    $stmt = $db->query("SHOW COLUMNS FROM ml_orders");
    $totalColumns = $stmt->rowCount();
    
    echo "📊 Estatísticas:\n";
    echo "  • Total de colunas: $totalColumns\n";
    echo "  • Campos adicionados: 24\n\n";
    
    echo "📝 Próximos passos:\n";
    echo "  1. php scripts/recalculate_order_metrics.php - Recalcular métricas dos pedidos existentes\n";
    echo "  2. OrderService->syncOrders() já calculará automaticamente nos novos pedidos\n";
    echo "  3. Acesse /dashboard/orders para ver as novas métricas\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
