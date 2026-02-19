<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ItemService;
use App\Services\PromotionService;
use App\Services\ClaimsService;
use App\Services\FlexService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mock Session
$_SESSION['user_id'] = 1;
$_SESSION['active_ml_account_id'] = 1;

echo "=== Verifying Real API Connections (Phase 7) ===\n\n";

// 1. Catalog
echo "[Catalog] Testing Real API (expecting fallback/error without scope)...\n";
$itemService = new ItemService(1);
$cat = $itemService->getCatalogDetails('MLB123456'); // Fake ID
echo "Result: " . ($cat['error'] ?? 'Success') . "\n";

// 2. Promotions
echo "\n[Promo] Testing Real API...\n";
$promoService = new PromotionService(1);
$promos = $promoService->getPromotions();
echo "Found: " . count($promos) . " (Mock fallback expected if no scope)\n";

// 3. Claims
echo "\n[Claims] Testing Real API...\n";
$claimsService = new ClaimsService(1);
$claims = $claimsService->getClaims();
echo "Found: " . count($claims) . "\n";

// 4. Flex
echo "\n[Flex] Testing Real API...\n";
$flexService = new FlexService(1);
$flex = $flexService->getFlexOrders();
echo "Found: " . count($flex) . "\n";

echo "\n=== Verification Complete ===\n";
