<?php

declare(strict_types=1);

namespace App\Services\AI\Core\Harness;

use App\Services\AI\Core\BatchOptimizationQueue;

/**
 * Manages the "Feature List" (Job Queue) for the Agent Harness.
 * Ensures agents always have a clear "Next Step".
 */
class FeatureManager
{
    private BatchOptimizationQueue $queue;

    public function __construct()
    {
        $this->queue = new BatchOptimizationQueue();
    }

    /**
     * Get the next highest priority feature (job) to work on.
     * @return array|null Job data or null if empty.
     */
    /**
     * Get the next highest priority feature (job) to work on.
     * @return array|null Job data or null if empty.
     */
    public function getNextFeature(): ?array
    {
        // 1. Check primary optimization queue
        $job = $this->queue->processNext();
        if ($job) {
            return $job;
        }

        // 2. Poll for Questions (Questions Bot)
        // This is a "virtual" queue item created on the fly
        $questionId = $this->findPendingQuestion();
        if ($questionId) {
            return [
                'item_id' => "question_{$questionId}",
                'type' => 'question_answer',
                'question_id' => $questionId,
                'success' => true // Will be updated by execution
            ];
        }

        return null;
    }

    /**
     * Find a question that needs answering.
     */
    private function findPendingQuestion(): ?string
    {
        try {
            // Check for questions with status 'UNANSWERED'
            // This relies on QuestionService being available
            if (class_exists(\App\Services\QuestionService::class)) {
                $service = new \App\Services\QuestionService();
                // Force DB check across all accounts
                $questions = $service->getQuestions(['status' => 'UNANSWERED', 'limit' => 1, 'account_id' => 'all']);
                if (!empty($questions['questions'])) {
                    return $questions['questions'][0]['question_id'] ?? $questions['questions'][0]['id'];
                }
            }
        } catch (\Exception $e) {
            // Ignore errors here to not block the harness
        }
        return null;
    }

    /**
     * Get current stats of the feature list.
     */
    public function getStats(): array
    {
        return $this->queue->getQueueStats();
    }
}
