<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DeepResearchService;
use App\Services\MercadoLivreClient; // We will mock this
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Deep Research Service ---\n";

// 1. Mock Client
class MockMLClient extends MercadoLivreClient {
    public function __construct(?int $accountId = null) {
        // Bypass parent constructor to avoid DB/API calls
    }
    
    public function getAccountId(): ?int { return 12345; }
    
    public function get(string $endpoint, array $params = [], ?int $cacheTtl = null): array {
        // Mock Search Response
        if (strpos($endpoint, '/search') !== false) {
            return [
                'paging' => ['total' => 2],
                'results' => [
                    [
                        'id' => 'MLB1001',
                        'title' => 'Item 1 Brand X',
                        'price' => 100.00,
                        'original_price' => 120.00,
                        'condition' => 'new',
                        'permalink' => 'http://ml.com/item1',
                        'thumbnail' => 'http://img.com/1.jpg',
                        'seller' => [
                            'id' => 999,
                            'nickname' => 'SellerA',
                            'permalink' => 'http://ml.com/sellerA',
                            'seller_reputation' => ['level_id' => '5_green']
                        ],
                        'shipping' => ['free_shipping' => true, 'logistic_type' => 'fulfillment'],
                        'attributes' => [['id' => 'BRAND', 'value_name' => 'Brand X']],
                        'listing_type_id' => 'gold_pro',
                        'sold_quantity' => 50,
                        'available_quantity' => 10,
                        'date_created' => date('Y-m-d', strtotime('-30 days')),
                        'last_updated' => date('Y-m-d')
                    ],
                    [
                        'id' => 'MLB1002',
                        'title' => 'Item 2 Brand X',
                        'price' => 90.00,
                        'condition' => 'new',
                        'permalink' => 'http://ml.com/item2',
                        'thumbnail' => 'http://img.com/2.jpg',
                        'seller' => [
                            'id' => 888,
                            'nickname' => 'SellerB',
                            'permalink' => 'http://ml.com/sellerB',
                            'seller_reputation' => ['level_id' => '3_yellow']
                        ],
                        'shipping' => ['free_shipping' => false, 'logistic_type' => 'cross_docking'],
                        'attributes' => [['id' => 'BRAND', 'value_name' => 'Brand X']],
                        'listing_type_id' => 'gold_special',
                        'sold_quantity' => 20,
                        'available_quantity' => 5,
                        'date_created' => date('Y-m-d', strtotime('-15 days')),
                        'last_updated' => date('Y-m-d')
                    ]
                ]
            ];
        }
        
        // Mock User Info
        if ($endpoint === '/users/me') {
            return ['id' => 12345, 'nickname' => 'Me'];
        }

        // Mock specific item details if needed
        if (strpos($endpoint, '/items/') === 0) {
            return ['id' => 'MLB1001', 'sold_quantity' => 50]; 
        }
        
        return [];
    }
}

// 2. Initialize Service
// We ensure we pass a dummy account ID
$service = new DeepResearchService(12345);

// 3. Inject Mock via Reflection
$reflection = new ReflectionClass($service);
$clientProp = $reflection->getProperty('client');
$clientProp->setAccessible(true);
$clientProp->setValue($service, new MockMLClient());

echo "[OK] Service initialized and Mock Client injected.\n";

// 4. Run Research
echo "Running Brand Research...\n";
try {
    $result = $service->researchBrand('MLB1000', 'Brand X', [
        'include_seller_details' => true,
        'analyze_shipping' => true
    ]);

    // 5. Verify Results
    if ($result['status'] === 'completed') {
        echo "[SUCCESS] Research Completed.\n";
        echo "Items Found (Summary): " . $result['summary']['total_listings'] . "\n";
        echo "Avg Price: " . ($result['pricing']['overall']['avg'] ?? 'N/A') . "\n";
        
        if ($result['summary']['total_listings'] === 2) {
             echo "[SUCCESS] Count Verification.\n";
        } else {
             echo "[FAILURE] Count mismatch. Expected 2, got " . $result['summary']['total_listings'] . "\n";
        }
        
        if (isset($result['sales_velocity'])) {
            echo "[SUCCESS] Sales Velocity Analysis Present.\n";
        }
        
    } else {
        echo "[FAILURE] Status: " . $result['status'] . "\n";
        echo "Error: " . ($result['error'] ?? 'Unknown') . "\n";
    }

} catch (Exception $e) {
    echo "[CRITICAL FAILURE] Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
