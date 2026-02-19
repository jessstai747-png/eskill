<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Services\QuestionService;
use App\Database;

echo "=============================================\n";
echo "   📥 TESTE UNIFIED INBOX (PHASE 19) \n";
echo "=============================================\n";

$db = Database::getInstance();

// 1. Setup Mock Accounts
$db->exec("INSERT IGNORE INTO ml_accounts (id, user_id, ml_user_id, nickname, status, token_expires_at) VALUES (991, 1, '123', 'Loja Alpha', 'active', NOW())");
$db->exec("INSERT IGNORE INTO ml_accounts (id, user_id, ml_user_id, nickname, status, token_expires_at) VALUES (992, 1, '456', 'Loja Beta', 'active', NOW())");

// 2. Insert Mock Questions
$db->exec("DELETE FROM ml_questions WHERE question_id IN ('123456100', '123456101')");

$db->prepare("INSERT INTO ml_questions (question_id, account_id, question_text, status, date_created, seller_id, item_id) VALUES (?, ?, ?, ?, NOW(), 1, 'MLB000')")
    ->execute(['123456100', 991, 'Pergunta para Alpha', 'UNANSWERED']);

$db->prepare("INSERT INTO ml_questions (question_id, account_id, question_text, status, date_created, seller_id, item_id) VALUES (?, ?, ?, ?, NOW(), 1, 'MLB000')")
    ->execute(['123456101', 992, 'Pergunta para Beta', 'ANSWERED']);

// 3. Test Service Retrieval
$service = new QuestionService(); // No Specific Account
$result = $service->getQuestions([
    'account_id' => 'all', 
    'limit' => 10
]);

echo "Found " . count($result['questions']) . " questions in DB view.\n";

$foundAlpha = false;
$foundBeta = false;

foreach ($result['questions'] as $q) {
    echo "- [{$q['status']}] Account {$q['account']['id']} ({$q['account']['name']}): {$q['text']}\n";
    if ($q['id'] == '123456100') $foundAlpha = true;
    if ($q['id'] == '123456101') $foundBeta = true;
}

if ($foundAlpha && $foundBeta) {
    echo "✅ SUCESSO: Perguntas de ambas as contas encontradas!\n";
} else {
    echo "❌ FALHA: Não encontrou perguntas de ambas as contas.\n";
}

// Cleanup
$db->exec("DELETE FROM ml_accounts WHERE id IN (991, 992)");
$db->exec("DELETE FROM ml_questions WHERE question_id IN ('123456100', '123456101')");
