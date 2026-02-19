<?php

namespace App\Controllers;

use App\Services\SEO\SEOAuditService;
use App\Services\SEO\CompetitorAnalysisService;
use App\Services\SEO\HiddenAttributesDetector;
use App\Services\SEO\VersioningService;
use App\Database;
use Exception;

/**
 * @deprecated This controller's functionality is consolidated in SEOKillerController.
 * API endpoints remain functional but new features should go in SEOKillerController.
 * 
 * SEO Controller - Handles SEO Intelligence Module API endpoints
 */
class SEOController extends BaseController
{
    private \PDO $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /api/seo/dashboard
     * 
     * Get SEO dashboard overview
     */
    public function dashboard(): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            // Get overall statistics
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(DISTINCT sa.item_id) as total_audited,
                    AVG(sa.overall_score) as avg_score,
                    SUM(CASE WHEN sa.overall_score >= 80 THEN 1 ELSE 0 END) as excellent_count,
                    SUM(CASE WHEN sa.overall_score >= 60 AND sa.overall_score < 80 THEN 1 ELSE 0 END) as good_count,
                    SUM(CASE WHEN sa.overall_score >= 40 AND sa.overall_score < 60 THEN 1 ELSE 0 END) as fair_count,
                    SUM(CASE WHEN sa.overall_score < 40 THEN 1 ELSE 0 END) as poor_count
                 FROM seo_audits sa
                 WHERE sa.account_id = :account_id
                 AND sa.audit_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $stmt->execute(['account_id' => $accountId]);
            $stats = $stmt->fetchAll();
            
            // Get top opportunities (lowest scores)
            $stmtOpp = $this->db->prepare(
                "SELECT 
                    sa.item_id,
                    i.title,
                    sa.overall_score,
                    sa.recommendations
                 FROM seo_audits sa
                 JOIN items i ON i.ml_item_id = sa.item_id
                 WHERE sa.account_id = :account_id
                 AND sa.overall_score < 70
                 ORDER BY sa.overall_score ASC
                 LIMIT 10"
            );
            $stmtOpp->execute(['account_id' => $accountId]);
            $opportunities = $stmtOpp->fetchAll();
            
            // Format opportunities
            foreach ($opportunities as &$opp) {
                $opp['recommendations'] = json_decode($opp['recommendations'], true) ?? [];
            }
            
            // Get recent optimizations
            $stmtOpt = $this->db->prepare(
                "SELECT 
                    oh.item_id,
                    i.title,
                    oh.change_type,
                    oh.changed_by,
                    oh.applied_at
                 FROM seo_optimization_history oh
                 JOIN items i ON i.ml_item_id = oh.item_id
                 WHERE oh.account_id = :account_id
                 ORDER BY oh.applied_at DESC
                 LIMIT 10"
            );
            $stmtOpt->execute(['account_id' => $accountId]);
            $recentOptimizations = $stmtOpt->fetchAll();
            
            // Get automation status
            $stmtAuto = $this->db->prepare(
                "SELECT mode, enabled 
                 FROM seo_automation_config 
                 WHERE account_id = :account_id"
            );
            $stmtAuto->execute(['account_id' => $accountId]);
            $automationConfig = $stmtAuto->fetchAll();
            
            $this->jsonSuccess([
                'data' => [
                    'statistics' => $stats[0] ?? [],
                    'top_opportunities' => $opportunities,
                    'recent_optimizations' => $recentOptimizations,
                    'automation' => $automationConfig[0] ?? ['mode' => 'manual', 'enabled' => false],
                ],
            ]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * GET /api/seo/listings
     * 
     * Get list of listings with SEO scores
     */
    public function listings(): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            // Get filters
            $minScore = $this->request->getInt('min_score', 0);
            $maxScore = $this->request->getInt('max_score', 100);
            $status = $this->request->get('status');
            $limit = $this->request->getIntClamped('limit', 1, 100, 50);
            $offset = max(0, min(1000000, (int)$this->request->getInt('offset', 0)));
            
            // Build query
            $params = [
                'account_id' => $accountId,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'limit' => $limit,
                'offset' => $offset,
            ];
            
            $statusFilter = '';
            if ($status) {
                $statusFilter = "AND i.status = :status";
                $params['status'] = $status;
            }
            
            $stmtListings = $this->db->prepare(
                "SELECT 
                    i.ml_item_id as item_id,
                    i.title,
                    i.price,
                    i.status,
                    i.available_quantity,
                    sa.overall_score,
                    sa.audit_date,
                    sa.title_score,
                    sa.description_score,
                    sa.attributes_score,
                    sa.images_score
                 FROM items i
                 LEFT JOIN seo_audits sa ON sa.item_id = i.ml_item_id 
                    AND sa.audit_date = (
                        SELECT MAX(audit_date) 
                        FROM seo_audits 
                        WHERE item_id = i.ml_item_id
                    )
                 WHERE i.account_id = :account_id
                 {$statusFilter}
                 HAVING (sa.overall_score IS NULL OR (sa.overall_score >= :min_score AND sa.overall_score <= :max_score))
                 ORDER BY sa.overall_score ASC NULLS LAST
                  LIMIT {$limit} OFFSET {$offset}"
            );
              unset($params['limit'], $params['offset']);
              $stmtListings->execute($params);
            $listings = $stmtListings->fetchAll();
            
            // Get total count
            $stmtCount = $this->db->prepare(
                "SELECT COUNT(*) as total 
                 FROM items i
                 WHERE i.account_id = :account_id
                 {$statusFilter}"
            );
            $stmtCount->execute(array_intersect_key($params, array_flip(['account_id', 'status'])));
            $countResult = $stmtCount->fetchAll();
            
            $this->jsonSuccess([
                'data' => [
                    'listings' => $listings,
                    'total' => $countResult[0]['total'] ?? 0,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * GET /api/seo/listings/{itemId}
     * 
     * Get detailed SEO analysis for a listing
     */
    public function listingDetail(string $itemId): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            // Get latest audit
            $auditService = new SEOAuditService($accountId);
            $audit = $auditService->auditListing($itemId);
            
            // Get competitors
            $competitorService = new CompetitorAnalysisService($accountId);
            $competitors = $competitorService->getStoredCompetitors($itemId);
            
            // Get hidden attributes
            $hiddenAttrService = new HiddenAttributesDetector($accountId);
            $hiddenAttributes = $hiddenAttrService->getStoredHiddenAttributes($itemId);
            
            // Get version history
            $versioningService = new VersioningService($accountId);
            $history = $versioningService->getHistory($itemId, 20);
            
            $this->jsonSuccess([
                'data' => [
                    'audit' => $audit,
                    'competitors' => $competitors,
                    'hidden_attributes' => $hiddenAttributes,
                    'history' => $history,
                ],
            ]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * POST /api/seo/audit/{itemId}
     * 
     * Trigger SEO audit for a listing
     */
    public function auditListing(string $itemId): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            $forceRefresh = $this->request->postInt('force_refresh', 0) !== 0;
            
            $auditService = new SEOAuditService($accountId);
            $audit = $auditService->auditListing($itemId, $forceRefresh);
            
            $this->jsonSuccess(['data' => $audit]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * POST /api/seo/competitors/{itemId}/refresh
     * 
     * Refresh competitor analysis
     */
    public function refreshCompetitors(string $itemId): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            $competitorService = new CompetitorAnalysisService($accountId);
            $analysis = $competitorService->analyzeCompetitors($itemId, 20, true);
            
            $this->jsonSuccess(['data' => $analysis]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * POST /api/seo/hidden-attributes/{itemId}/detect
     * 
     * Detect hidden attributes
     */
    public function detectHiddenAttributes(string $itemId): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            $hiddenAttrService = new HiddenAttributesDetector($accountId);
            $result = $hiddenAttrService->detectHiddenAttributes($itemId, true);
            
            $this->jsonSuccess(['data' => $result]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * POST /api/seo/hidden-attributes/{itemId}/apply
     * 
     * Apply a hidden attribute
     */
    public function applyHiddenAttribute(string $itemId): void
    {
        try {
            $accountId = $this->getAccountId();
            $userId = $this->getUserId();
            
            if (!$accountId || !$userId) {
                $this->jsonError('Authentication required', 401);
                return;
            }
            
            // Accept both form-encoded and JSON bodies
            $attributeId = $this->request->input('attribute_id', '');
            $value = $this->request->input('value', '');
            
            if (empty($attributeId) || empty($value)) {
                $this->jsonError('Missing required fields', 400);
                return;
            }
            
            $hiddenAttrService = new HiddenAttributesDetector($accountId);
            $success = $hiddenAttrService->applyHiddenAttribute($itemId, $attributeId, $value, $userId);
            
            if ($success) {
                $this->jsonSuccess(['message' => 'Attribute applied successfully']);
            } else {
                $this->jsonError('Failed to apply attribute');
            }
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * POST /api/seo/rollback/{itemId}/{versionId}
     * 
     * Rollback to a previous version
     */
    public function rollback(string $itemId, int $versionId): void
    {
        try {
            $accountId = $this->getActiveAccountId();
            $userId = $this->getUserId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            // Accept both form-encoded and JSON bodies
            $reason = $this->request->input('reason', '');
            if (trim($reason) === '') {
                $reason = 'User requested rollback';
            }
            
            $versioningService = new VersioningService($accountId);
            $success = $versioningService->rollback($itemId, $versionId, $reason, is_numeric($userId) ? (int)$userId : null, 'user');
            
            if ($success) {
                $this->jsonSuccess(['message' => 'Rollback completed successfully']);
            } else {
                $this->jsonError('Rollback failed');
            }
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * GET /api/seo/history/{itemId}
     * 
     * Get version history for an item
     */
    public function history(string $itemId): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            $limit = $this->request->getIntClamped('limit', 1, 100, 50);
            
            $versioningService = new VersioningService($accountId);
            $history = $versioningService->getHistory($itemId, $limit);
            
            $this->jsonSuccess(['data' => $history]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
    
    /**
     * POST /api/seo/intelligence/audit/batch
     * 
     * Batch audit multiple listings
     */
    public function batchAudit(): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            $input = $this->request->json() ?? [];
            $itemIds = $input['item_ids'] ?? [];
            $options = $input['options'] ?? [];
            
            if (empty($itemIds) || !is_array($itemIds)) {
                $this->jsonError('item_ids array is required', 400);
                return;
            }
            
            // Limit batch size
            $itemIds = array_slice($itemIds, 0, 100);
            
            $auditService = new SEOAuditService($accountId);
            $db = \App\Database::getInstance();
            
            // Create batch job
            $jobId = 'batch_audit_' . uniqid();
            $stmt = $db->prepare("
                INSERT INTO seo_bulk_jobs (id, account_id, job_type, status, total_items, processed_items, created_at)
                VALUES (:id, :account_id, 'audit', 'processing', :total, 0, NOW())
            ");
            $stmt->execute([
                'id' => $jobId,
                'account_id' => $accountId,
                'total' => count($itemIds)
            ]);
            
            // Process in background if large batch, or immediately if small
            if (count($itemIds) > 10) {
                // Queue for background processing
                foreach ($itemIds as $itemId) {
                    $stmt = $db->prepare("
                        INSERT INTO seo_bulk_jobs (id, account_id, job_type, status, item_id, parent_job_id, created_at)
                        VALUES (:id, :account_id, 'audit_item', 'pending', :item_id, :parent_job_id, NOW())
                    ");
                    $stmt->execute([
                        'id' => 'item_' . uniqid(),
                        'account_id' => $accountId,
                        'item_id' => $itemId,
                        'parent_job_id' => $jobId
                    ]);
                }
                
                $this->jsonSuccess([
                    'job_id' => $jobId,
                    'total_items' => count($itemIds),
                    'status' => 'queued',
                    'message' => 'Batch audit queued for background processing'
                ]);
            } else {
                // Process immediately for small batches
                $results = [];
                $issuesFound = 0;
                
                foreach ($itemIds as $itemId) {
                    try {
                        $audit = $auditService->auditListing($itemId, $options['force_refresh'] ?? false);
                        $results[] = [
                            'item_id' => $itemId,
                            'success' => true,
                            'score' => $audit['score'] ?? 0,
                            'issues' => count($audit['issues'] ?? [])
                        ];
                        $issuesFound += count($audit['issues'] ?? []);
                    } catch (\Exception $e) {
                        $results[] = [
                            'item_id' => $itemId,
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                // Update job as completed
                $stmt = $db->prepare("
                    UPDATE seo_bulk_jobs 
                    SET status = 'completed', processed_items = :processed, completed_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'processed' => count($itemIds),
                    'id' => $jobId
                ]);
                
                $this->jsonSuccess([
                    'job_id' => $jobId,
                    'audited' => count(array_filter($results, fn($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                    'issues_found' => $issuesFound,
                    'results' => $results
                ]);
            }
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * GET /api/seo/intelligence/audit/status/{jobId}
     * 
     * Get batch audit job status
     */
    public function getAuditJobStatus(string $jobId): void
    {
        try {
            $accountId = $this->getAccountId();
            
            if (!$accountId) {
                $this->jsonError('No account selected', 400);
                return;
            }
            
            $db = \App\Database::getInstance();
            
            $stmt = $db->prepare("
                SELECT id, status, total_items, processed_items, created_at, completed_at
                FROM seo_bulk_jobs 
                WHERE id = :id AND account_id = :account_id AND job_type = 'audit'
            ");
            $stmt->execute([
                'id' => $jobId,
                'account_id' => $accountId
            ]);
            
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$job) {
                $this->jsonError('Job not found', 404);
                return;
            }
            
            $progress = $job['total_items'] > 0 
                ? round(($job['processed_items'] / $job['total_items']) * 100) 
                : 0;
            
            $this->jsonSuccess([
                'job_id' => $job['id'],
                'status' => $job['status'],
                'total_items' => (int)$job['total_items'],
                'processed_items' => (int)$job['processed_items'],
                'progress' => $progress,
                'created_at' => $job['created_at'],
                'completed_at' => $job['completed_at']
            ]);
            
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
}
