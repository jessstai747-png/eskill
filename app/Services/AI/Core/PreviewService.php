<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

/**
 * Preview Service
 * Allows previewing optimizations before applying
 */
class PreviewService
{
    private AuditLogService $auditLog;
    
    public function __construct()
    {
        $this->auditLog = new AuditLogService();
    }
    
    /**
     * Generate preview of optimization
     * 
     * @param string $itemId
     * @param array $optimizationResult
     * @return array Preview data
     */
    public function generatePreview(string $itemId, array $optimizationResult): array
    {
        $preview = [
            'item_id' => $itemId,
            'preview_id' => $this->generatePreviewId(),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'changes' => [],
            'score' => [
                'before' => $optimizationResult['score_before'] ?? 0,
                'after' => $optimizationResult['score_after'] ?? 0,
                'improvement' => $optimizationResult['improvement'] ?? 0,
            ],
            'cost' => $optimizationResult['cost'] ?? 0,
        ];
        
        // Build changes comparison
        if (isset($optimizationResult['optimizations']['title'])) {
            $preview['changes']['title'] = $this->buildTitlePreview(
                $optimizationResult['optimizations']['title']
            );
        }
        
        if (isset($optimizationResult['optimizations']['description'])) {
            $preview['changes']['description'] = $this->buildDescriptionPreview(
                $optimizationResult['optimizations']['description']
            );
        }
        
        if (isset($optimizationResult['optimizations']['attributes'])) {
            $preview['changes']['attributes'] = $this->buildAttributesPreview(
                $optimizationResult['optimizations']['attributes']
            );
        }
        
        // Log preview action
        $this->auditLog->logAction($itemId, 'preview', $preview['changes'], [
            'cost' => $preview['cost'],
            'ai_provider' => $optimizationResult['ai_provider'] ?? null,
            'ai_model' => $optimizationResult['ai_model'] ?? null,
        ]);
        
        return $preview;
    }
    
    /**
     * Build title preview
     * 
     * @param array $titleOptimization
     * @return array
     */
    private function buildTitlePreview(array $titleOptimization): array
    {
        return [
            'type' => 'title',
            'before' => $titleOptimization['original_title'] ?? '',
            'after' => $titleOptimization['optimized_title'] ?? '',
            'score_before' => $titleOptimization['score_before'] ?? 0,
            'score_after' => $titleOptimization['score'] ?? 0,
            'improvements' => $titleOptimization['improvements'] ?? [],
            'alternatives' => $titleOptimization['alternatives'] ?? [],
            'char_count' => [
                'before' => mb_strlen($titleOptimization['original_title'] ?? ''),
                'after' => $titleOptimization['char_count'] ?? 0,
            ],
        ];
    }
    
    /**
     * Build description preview
     * 
     * @param array $descOptimization
     * @return array
     */
    private function buildDescriptionPreview(array $descOptimization): array
    {
        return [
            'type' => 'description',
            'before' => $descOptimization['original_description'] ?? '',
            'after' => $descOptimization['description'] ?? '',
            'score_before' => $descOptimization['score_before'] ?? 0,
            'score_after' => $descOptimization['score'] ?? 0,
            'highlights' => $descOptimization['highlights'] ?? [],
            'char_count' => [
                'before' => mb_strlen($descOptimization['original_description'] ?? ''),
                'after' => $descOptimization['char_count'] ?? 0,
            ],
        ];
    }
    
    /**
     * Build attributes preview
     * 
     * @param array $attrOptimization
     * @return array
     */
    private function buildAttributesPreview(array $attrOptimization): array
    {
        return [
            'type' => 'attributes',
            'completeness_before' => $attrOptimization['completeness_before'] ?? 0,
            'completeness_after' => $attrOptimization['completeness'] ?? 0,
            'suggestions' => $attrOptimization['suggestions'] ?? [],
            'missing_required' => $attrOptimization['missing_required'] ?? [],
        ];
    }
    
    /**
     * Apply previewed changes
     * 
     * @param string $previewId
     * @param string $itemId
     * @param array $selectedChanges Which changes to apply
     * @return array
     */
    public function applyPreview(string $previewId, string $itemId, array $selectedChanges): array
    {
        // In real implementation, would retrieve preview from cache/db
        // For now, return structure
        
        $applied = [
            'success' => true,
            'item_id' => $itemId,
            'preview_id' => $previewId,
            'applied_changes' => $selectedChanges,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        // Log apply action
        $this->auditLog->logAction($itemId, 'apply', $selectedChanges);
        
        return $applied;
    }
    
    /**
     * Generate preview comparison HTML/JSON
     * 
     * @param array $preview
     * @return array
     */
    public function formatForDisplay(array $preview): array
    {
        $formatted = [
            'preview_id' => $preview['preview_id'],
            'item_id' => $preview['item_id'],
            'score_improvement' => $preview['score']['improvement'],
            'cost' => $preview['cost'],
            'changes' => [],
        ];
        
        foreach ($preview['changes'] as $type => $change) {
            $formatted['changes'][] = [
                'type' => $type,
                'label' => ucfirst($type),
                'before' => $change['before'] ?? '',
                'after' => $change['after'] ?? '',
                'score_improvement' => ($change['score_after'] ?? 0) - ($change['score_before'] ?? 0),
                'selectable' => true,
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Generate unique preview ID
     * 
     * @return string
     */
    private function generatePreviewId(): string
    {
        return 'preview_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
    }
}
