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
        logger()->info('Starting AutoAnswerJob', ['job' => 'AutoAnswerJob']);

        // 1. Get Settings (Database with Fallback)
        $settings = $this->getSettings();
        $autoEnabled = $settings['enabled'] ?? false;
        $minConfidence = $settings['min_confidence'] ?? 90;

        if (!$autoEnabled) {
            logger()->info('Auto-Answer disabled', ['settings' => $settings]);
            return;
        }

        // 2. Fetch Pending Questions
        $result = $this->questionService->getQuestions(['status' => 'UNANSWERED', 'limit' => 50]);
        $questions = $result['questions'] ?? [];
        
        $count = 0;
        foreach ($questions as $q) {
            // Check if already has a draft
            if (empty($q['draft_answer'])) {
                continue;
            }

            // Check Confidence
            $confidence = $q['confidence_score'] ?? 0;
            
            if ($confidence >= $minConfidence) {
                logger()->info('Auto-sending answer', [
                    'question_id' => $q['id'],
                    'confidence' => $confidence
                ]);
                
                // Send!
                try {
                    $this->questionService->answerQuestion($q['id'], $q['draft_answer']);
                    $count++;
                } catch (\Exception $e) {
                    logger()->error('Failed to send answer', [
                        'question_id' => $q['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                logger()->debug('Skipping low confidence answer', [
                    'question_id' => $q['id'],
                    'confidence' => $confidence,
                    'min_required' => $minConfidence
                ]);
            }
        }
        
        logger()->info('AutoAnswerJob finished', [
            'sent' => $count,
            'total_questions' => count($questions)
        ]);
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
