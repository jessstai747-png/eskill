<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\ML\LearningEngine;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Learning Engine ---\n";

// 0. Drop mismatching table (Dev/Test only)
$db = Database::getInstance();
$db->exec("DROP TABLE IF EXISTS ai_training_data");

// 1. Initialize (creates tables: ai_training_data, ai_success_patterns...)
// Use dummy account
$accountId = 123456;
$le = new LearningEngine($accountId);
echo "[OK] Learning Engine initialized.\n";

// 2. Clear previous data
$db = Database::getInstance();
$db->exec("DELETE FROM ai_training_data WHERE account_id = $accountId");
$db->exec("DELETE FROM ai_success_patterns WHERE account_id = $accountId");

// 3. Inject Mock Training Data (Successful optimizations)
echo "Injecting training data...\n";

$descriptions = [
    "Produto excelente com garantia • Frete grátis • Envio imediato",
    "Melhor custo benefício • Qualidade premium • Nota fiscal",
    "Lançamento 2025 • Alta performance • Suporte 24h"
];

foreach ($descriptions as $i => $desc) {
    $le->collectTrainingData(
        "ITEM-ML-" . $i,
        'description',
        "Descricao ruim antiga",
        $desc,
        [
            'conversion_before' => 1.0,
            'conversion_after' => 2.5 + ($i * 0.5), // Increasing success
            'category_id' => 'MLB1234'
        ]
    );
}
echo "[OK] Training data injected.\n";

// 4. Analyze Patterns
echo "Analyzing patterns...\n";
$patterns = $le->analyzeSuccessPatterns('MLB1234');

// 5. Verify Results
if (isset($patterns['description']['patterns']['bullets_pct'])) {
    echo "[SUCCESS] Description patterns analyzed.\n";
    echo "Sample Size: " . $patterns['description']['sample_size'] . "\n";
    echo "Has Bullets: " . $patterns['description']['patterns']['bullets_pct'] . "%\n";
} else {
    echo "[FAILURE] No patterns analyzed.\n";
    print_r($patterns);
}

// Cleanup
$db->exec("DELETE FROM ai_training_data WHERE account_id = $accountId");
