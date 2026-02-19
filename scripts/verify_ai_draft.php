<?php
// Script to verify AI Draft Generation
require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\QuestionService;
use App\Database;

echo "\n🤖 Testing AI Auto-Responder Draft...\n\n";

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
    $accountId = $stmt->fetchColumn();

    if ($accountId) {
        $service = new QuestionService((int)$accountId);
        
        // We need a real question ID to test context fetching.
        // Let's list questions first.
        echo "1️⃣  Listing Questions to find a candidate...\n";
        $questions = $service->getQuestions(['status' => 'UNANSWERED', 'limit' => 1]);
        
        if (empty($questions['results'])) {
             echo "⚠️  No UNANSWERED questions found. Trying ANSWERED...\n";
             $questions = $service->getQuestions(['status' => 'ANSWERED', 'limit' => 1]);
        }
        
        if (!empty($questions['results'][0])) {
            $q = $questions['results'][0];
            $qId = $q['id'];
            echo "Found Question ID: $qId\n";
            echo "Text: " . $q['text'] . "\n";
            echo "Item ID: " . $q['item_id'] . "\n\n";
            
            echo "2️⃣  Generating Draft...\n";
            $draft = $service->generateDraftAnswer((string)$qId);
            
            if ($draft['success']) {
                echo "✅ Draft Generated:\n";
                echo "---------------------------------------------------\n";
                echo $draft['draft'] . "\n";
                echo "---------------------------------------------------\n";
                echo "Model Used: " . $draft['model'] . "\n";
            } else {
                echo "❌ Error: " . ($draft['error'] ?? 'Unknown') . "\n";
            }
            
        } else {
            echo "❌ No questions found to test.\n";
        }
        
    } else {
        echo "❌ No active account found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
