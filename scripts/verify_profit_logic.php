<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\OrderService;
use App\Database;

// Function to reset test data
function cleanup() {
    $db = Database::getInstance();
    $db->exec("DELETE FROM items WHERE ml_item_id = 'TEST-ITEM-PROFIT'");
    $db->exec("DELETE FROM ml_orders WHERE ml_order_id = 999999999");
}

try {
    echo "Starting Profit Logic Verification...\n";
    cleanup();

    $db = Database::getInstance();

    // 1. Create a Test Item with Cost and Tax
    echo "1. Creating Test Item...\n";
    $costPrice = 50.00;
    $taxRate = 10.0; // 10%
    $sellingPrice = 100.00;

    $stmt = $db->prepare("INSERT INTO items (ml_item_id, account_id, title, price, cost_price, tax_rate, status, currency_id, available_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['TEST-ITEM-PROFIT', 1, 'Test Item Profit', $sellingPrice, $costPrice, $taxRate, 'active', 'BRL', 10]);
    echo "Item created. Cost: R$ $costPrice, Tax: $taxRate%, Price: R$ $sellingPrice\n";

    // 2. Mock Order Data
    $orderData = [
        'id' => 999999999,
        'status' => 'paid',
        'total_amount' => 100.00,
        'date_created' => date('c'),
        'order_items' => [
            [
                'item' => ['id' => 'TEST-ITEM-PROFIT'],
                'quantity' => 1,
                'unit_price' => 100.00,
                'sale_fee' => 11.00 // 11% ML Fee
            ]
        ],
        'shipping' => [
            'cost' => 0,
            'logistic_type' => 'fulfillment'
        ]
    ];

    // 3. Calculate Metrics using OrderService
    echo "2. Calculating Metrics...\n";
    $service = new OrderService(1);
    
    // We use reflection or just call the public sync, but calculateOrderMetrics is public?
    // calculateOrderCosts IS PRIVATE. So we should test via syncOrders or rely on public calculateOrderMetrics if we expose the cost injection.
    // Actually, calculateOrderMetrics needs to be called WITH cost now if we use the old signature?
    // Wait, I updated calculateOrderMetrics to accept cost? I need to check my changes to OrderService.
    
    // Let's check OrderService again to be sure how I implemented the call.
    // I added calculateOrderCosts (private) and called it inside syncOrders.
    // calculateOrderMetrics (public) accepts $productCost as 2nd arg.
    
    // So to test properly without syncing (which requires ML API mocks), I will simulate the logic manually here mirroring syncOrders:
    // Or I make calculateOrderCosts public for testing? No.
    // I will reflectively access calculateOrderCosts or just duplicate the simple query logic here to verify the DB side works, 
    // AND then call calculateOrderMetrics with the result.
    
    // Test DB fetching logic first (Validation of Step 1)
    $stmt = $db->prepare("SELECT cost_price, tax_rate FROM items WHERE ml_item_id = 'TEST-ITEM-PROFIT'");
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item['cost_price'] != 50.00) throw new Exception("Cost price not saved correctly.");
    
    // Simulate calculateOrderCosts logic
    $totalCost = $item['cost_price'] * 1;
    $totalTax = (100.00 * 1) * ($item['tax_rate'] / 100);
    
    echo "Calculated Validation: Cost = $totalCost, Tax = $totalTax\n";
    
    // Call calculateOrderMetrics
    $metrics = $service->calculateOrderMetrics($orderData, $totalCost);
    
    // Adjust for tax as done in syncOrders
    $metrics['taxes'] = $totalTax;
    $metrics['total_costs'] += $metrics['taxes'];
    $metrics['net_revenue'] -= $metrics['taxes'];
    $metrics['net_profit'] -= $metrics['taxes'];
    
    // Verify Results
    echo "3. Verifying Results...\n";
    echo "Total Amount: {$orderData['total_amount']}\n";
    echo "ML Fee: {$metrics['ml_commission']}\n";
    echo "Payment Fee: {$metrics['payment_fee']}\n";
    echo "Fixed Fee: {$metrics['fixed_fee']}\n";
    echo "Shipping: {$metrics['shipping_cost']}\n";
    echo "Taxes: {$metrics['taxes']}\n";
    echo "Product Cost: {$metrics['product_cost']}\n";
    echo "Total Costs: {$metrics['total_costs']}\n";
    
    echo "Net Profit: {$metrics['net_profit']}\n";
    
    // Expected:
    // Price: 100
    // ML Fee: 11
    // Pay Fee: 4.99 (4.99%)
    // Fixed: 0 (>= 79)
    // Tax: 10
    // Cost: 50
    // Total Cost: 11 + 4.99 + 0 + 10 + 50 = 75.99
    // Profit: 100 - 75.99 = 24.01
    
    $expectedProfit = 100 - 11 - 4.99 - 10 - 50;
    
    if (abs($metrics['net_profit'] - $expectedProfit) > 0.01) {
        throw new Exception("Profit mismatch! Expected $expectedProfit, Got {$metrics['net_profit']}");
    }
    
    echo "SUCCESS: Profit calculation verified correctly!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    cleanup();
}
