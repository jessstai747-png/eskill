<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\QuestionService;
use App\Services\LLMService;

class AutoAnswerJob
{
    private QuestionService $questionService;
    private LLMService $llmService;

    public function __construct()
    {
        $this->questionService = new QuestionService();
        $this->llmService = new LLMService(); // Helper to check limits
    }

    public function run(): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting AutoAnswerJob...\n";

        // 1. Get Settings (Database with Fallback)
        $settings = $this->getSettings();
        $autoEnabled = $settings['enabled'] ?? false;
        $minConfidence = $settings['min_confidence'] ?? 90;

        if (!$autoEnabled) {
            echo "Auto-Answer disabled (Check system_settings).\n";
            return;
        }

        // 2. Fetch Pending Questions
        // Logic: Get questions status='UNANSWERED'
        $result = $this->questionService->getQuestions(['status' => 'UNANSWERED', 'limit' => 50]);
        $questions = $result['questions'] ?? [];
        
        $count = 0;
        foreach ($questions as $q) {
            // Check if already has a draft
            if (empty($q['draft_answer'])) {
                // Generate Draft (if not exists)
                // In real scenario, this would be queue-based. 
                // Here we skip generation to avoid blocking, assume draft was made on arrival.
                continue;
            }

            // Check Confidence
            $confidence = $q['confidence_score'] ?? 0;
            
            if ($confidence >= $minConfidence) {
                echo "Auto-Sending answer for Q: {$q['id']} (Confidence: $confidence%)\n";
                
                // Send!
                try {
                    $this->questionService->answerQuestion($q['id'], $q['draft_answer']);
                    $count++;
                } catch (\Exception $e) {
                    echo "Error sending Q {$q['id']}: " . $e->getMessage() . "\n";
                }
            } else {
                echo "Skipping Q: {$q['id']} (Confidence $confidence% < $minConfidence%)\n";
            }
        }
        
        echo "AutoAnswerJob Finished. Sent: $count\n";
    }

    private function getSettings(): array
    {
        try {
            $db = \App\Database::getInstance();
            // Try to fetch from generic settings table. Modify table/column names as per actual schema.
            // Assuming per-account generic settings or global system settings.
            // Using a resilient check.
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('auto_answer_enabled', 'auto_answer_confidence')");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $config = [];
            foreach ($rows as $r) {
                $config[$r['setting_key']] = $r['setting_value'];
            }
            
            return [
                'enabled' => filter_var($config['auto_answer_enabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'min_confidence' => (int)($config['auto_answer_confidence'] ?? 90)
            ];
        } catch (\Exception $e) {
            // Table doesn't exist or DB error -> Safe Default
            // Log if needed: error_log("AutoAnswerJob Settings Error: " . $e->getMessage());
            return [
                'enabled' => false, // Default to disabled to prevent accidents
                'min_confidence' => 90
            ];
        }
    }
}
