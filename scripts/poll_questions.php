<?php
/**
 * Script de sincronização de perguntas do Mercado Livre
 * 
 * Sincroniza perguntas recebidas de todas as contas ativas.
 * 
 * Exemplo de CRON (a cada hora):
 * 0 * * * * php /home/eskill/htdocs/eskill.com.br/scripts/poll_questions.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\MercadoLivreClient;
use App\Database;

try {
    $db = Database::getInstance();
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronização de perguntas...\n";
    
    // Buscar contas ativas
    $stmt = $db->query("
        SELECT id, nickname, ml_user_id 
        FROM ml_accounts 
        WHERE status = 'active'
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "Nenhuma conta ativa encontrada.\n";
        exit(0);
    }
    
    $totalSynced = 0;
    $totalErrors = 0;
    
    foreach ($accounts as $account) {
        echo "\n[Conta: {$account['nickname']} (ID: {$account['id']})]\n";
        
        try {
            $client = new MercadoLivreClient((int) $account['id']);
            $mlUserId = $account['ml_user_id'];
            
            // Buscar perguntas não respondidas primeiro
            $offset = 0;
            $limit = 50;
            $synced = 0;
            
            do {
                $response = $client->get("/questions/search", [
                    'seller_id' => $mlUserId,
                    'status' => 'UNANSWERED',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
                
                if (isset($response['error'])) {
                    echo "  ✗ Erro: " . ($response['message'] ?? 'Desconhecido') . "\n";
                    $totalErrors++;
                    break;
                }
                
                $questions = $response['questions'] ?? [];
                
                foreach ($questions as $question) {
                    $saved = saveQuestion($db, $account['id'], $question);
                    if ($saved) $synced++;
                }
                
                $offset += count($questions);
                $total = $response['total'] ?? 0;
                
            } while ($offset < $total && count($questions) > 0);
            
            // Também buscar últimas respondidas (últimos 7 dias)
            $response = $client->get("/questions/search", [
                'seller_id' => $mlUserId,
                'status' => 'ANSWERED',
                'limit' => 50,
                'sort_fields' => 'date_created',
                'sort_types' => 'DESC',
            ]);
            
            if (!isset($response['error'])) {
                foreach ($response['questions'] ?? [] as $question) {
                    $saved = saveQuestion($db, $account['id'], $question);
                    if ($saved) $synced++;
                }
            }
            
            echo "  ✓ Sincronizadas: {$synced} perguntas\n";
            $totalSynced += $synced;
            
        } catch (\Exception $e) {
            echo "  ✗ Exceção: " . $e->getMessage() . "\n";
            $totalErrors++;
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Sincronização concluída.\n";
    echo "Total sincronizadas: {$totalSynced}\n";
    echo "Total de erros: {$totalErrors}\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

function saveQuestion(PDO $db, int $accountId, array $question): bool
{
    try {
        $stmt = $db->prepare("
            INSERT INTO ml_questions (
                question_id, account_id, item_id, seller_id, from_user_id,
                question_text, answer_text, status, date_created, answer_date, updated_at
            ) VALUES (
                :question_id, :account_id, :item_id, :seller_id, :from_user_id,
                :question_text, :answer_text, :status, :date_created, :answer_date, NOW()
            )
            ON DUPLICATE KEY UPDATE
                answer_text = VALUES(answer_text),
                status = VALUES(status),
                answer_date = VALUES(answer_date),
                updated_at = NOW()
        ");
        
        $dateCreated = isset($question['date_created']) 
            ? date('Y-m-d H:i:s', strtotime($question['date_created'])) 
            : null;
        $answerDate = isset($question['answer']['date_created']) 
            ? date('Y-m-d H:i:s', strtotime($question['answer']['date_created'])) 
            : null;
        
        $stmt->execute([
            ':question_id' => $question['id'] ?? null,
            ':account_id' => $accountId,
            ':item_id' => $question['item_id'] ?? null,
            ':seller_id' => $question['seller_id'] ?? null,
            ':from_user_id' => $question['from']['id'] ?? null,
            ':question_text' => $question['text'] ?? null,
            ':answer_text' => $question['answer']['text'] ?? null,
            ':status' => $question['status'] ?? 'UNANSWERED',
            ':date_created' => $dateCreated,
            ':answer_date' => $answerDate,
        ]);
        
        return true;
    } catch (\Exception $e) {
        error_log("Erro ao salvar pergunta: " . $e->getMessage());
        return false;
    }
}
