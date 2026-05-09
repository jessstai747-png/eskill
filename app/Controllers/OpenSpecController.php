<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Router;
use App\Helpers\ViewHelper;

class OpenSpecController
{
    /**
     * Display OpenSpec dashboard
     */
    public function index(): void
    {
        $projectPath = dirname(__DIR__, 2) . '/openspec';
        
        // Get project info
        $projectFile = $projectPath . '/project.md';
        $projectInfo = file_exists($projectFile) ? file_get_contents($projectFile) : 'No project.md found';
        
        // Count specs
        $specsPath = $projectPath . '/specs';
        $specsCount = 0;
        if (is_dir($specsPath)) {
            $specsCount = count(glob($specsPath . '/*', GLOB_ONLYDIR));
        }
        
        // Count changes
        $changesPath = $projectPath . '/changes';
        $changesCount = 0;
        $recentChanges = [];
        
        if (is_dir($changesPath)) {
            $changeDirs = glob($changesPath . '/*', GLOB_ONLYDIR);
            $changesCount = count($changeDirs);
            
            // Get recent changes (last 10)
            usort($changeDirs, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            foreach (array_slice($changeDirs, 0, 10) as $changeDir) {
                $changeId = basename($changeDir);
                $proposalFile = $changeDir . '/proposal.md';
                
                $change = [
                    'id' => $changeId,
                    'path' => $changeDir,
                    'modified' => file_exists($proposalFile) ? filemtime($proposalFile) : filemtime($changeDir),
                    'has_proposal' => file_exists($proposalFile),
                    'has_tasks' => file_exists($changeDir . '/tasks.md'),
                    'has_design' => file_exists($changeDir . '/design.md'),
                ];
                
                // Try to extract title from proposal
                if ($change['has_proposal']) {
                    $proposalContent = file_get_contents($proposalFile);
                    if (preg_match('/^# (.+)$/m', $proposalContent, $matches)) {
                        $change['title'] = $matches[1];
                    } else {
                        $change['title'] = $changeId;
                    }
                } else {
                    $change['title'] = $changeId;
                }
                
                $recentChanges[] = $change;
            }
        }
        
        $data = [
            'projectInfo' => $projectInfo,
            'specsCount' => $specsCount,
            'changesCount' => $changesCount,
            'recentChanges' => $recentChanges,
        ];
        
        ViewHelper::render('dashboard/openspec/index', $data);
    }
    
    /**
     * List all changes
     */
    public function listChanges(): void
    {
        $projectPath = dirname(__DIR__, 2) . '/openspec';
        $changesPath = $projectPath . '/changes';
        
        $changes = [];
        
        if (is_dir($changesPath)) {
            $changeDirs = glob($changesPath . '/*', GLOB_ONLYDIR);
            
            foreach ($changeDirs as $changeDir) {
                $changeId = basename($changeDir);
                $proposalFile = $changeDir . '/proposal.md';
                $tasksFile = $changeDir . '/tasks.md';
                
                $change = [
                    'id' => $changeId,
                    'path' => $changeDir,
                    'modified' => file_exists($proposalFile) ? filemtime($proposalFile) : filemtime($changeDir),
                    'has_proposal' => file_exists($proposalFile),
                    'has_tasks' => file_exists($tasksFile),
                    'has_design' => file_exists($changeDir . '/design.md'),
                ];
                
                // Extract title
                if ($change['has_proposal']) {
                    $proposalContent = file_get_contents($proposalFile);
                    if (preg_match('/^# (.+)$/m', $proposalContent, $matches)) {
                        $change['title'] = $matches[1];
                    } else {
                        $change['title'] = $changeId;
                    }
                } else {
                    $change['title'] = $changeId;
                }
                
                // Count tasks if available
                if ($change['has_tasks']) {
                    $tasksContent = file_get_contents($tasksFile);
                    $totalTasks = preg_match_all('/^- \[[ x\/]\]/m', $tasksContent);
                    $completedTasks = preg_match_all('/^- \[x\]/m', $tasksContent);
                    $change['tasks_total'] = $totalTasks;
                    $change['tasks_completed'] = $completedTasks;
                } else {
                    $change['tasks_total'] = 0;
                    $change['tasks_completed'] = 0;
                }
                
                $changes[] = $change;
            }
            
            // Sort by modification time (newest first)
            usort($changes, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
        }
        
        header('Content-Type: application/json');
        echo json_encode(['changes' => $changes]);
    }
    
    /**
     * Show change details
     */
    public function showChange(Router $router): void
    {
        $id = $router->getParam('id');

        // Segurança: prevenir path traversal
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', (string) $id)) {
            http_response_code(400);
            echo "Invalid change ID format";
            return;
        }

        $projectPath = dirname(__DIR__, 2) . '/openspec';
        $changePath = $projectPath . '/changes/' . $id;
        
        if (!is_dir($changePath)) {
            http_response_code(404);
            echo "Change not found";
            return;
        }
        
        $change = [
            'id' => $id,
            'path' => $changePath,
        ];
        
        // Load proposal
        $proposalFile = $changePath . '/proposal.md';
        if (file_exists($proposalFile)) {
            $change['proposal'] = file_get_contents($proposalFile);
        }
        
        // Load tasks
        $tasksFile = $changePath . '/tasks.md';
        if (file_exists($tasksFile)) {
            $change['tasks'] = file_get_contents($tasksFile);
        }
        
        // Load design
        $designFile = $changePath . '/design.md';
        if (file_exists($designFile)) {
            $change['design'] = file_get_contents($designFile);
        }
        
        // List spec deltas
        $specsPath = $changePath . '/specs';
        $change['specs'] = [];
        if (is_dir($specsPath)) {
            $specDirs = glob($specsPath . '/*', GLOB_ONLYDIR);
            foreach ($specDirs as $specDir) {
                $specFile = $specDir . '/spec.md';
                if (file_exists($specFile)) {
                    $change['specs'][] = [
                        'name' => basename($specDir),
                        'content' => file_get_contents($specFile),
                    ];
                }
            }
        }
        
        ViewHelper::render('dashboard/openspec/change_detail', ['change' => $change]);
    }
    
    /**
     * Validate a change (calls openspec validate)
     */
    public function validateChange(Router $router): void
    {
        $id = $router->getParam('id');

        // Segurança: prevenir path traversal — aceitar apenas alfanuméricos, hífens e underscores
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', (string) $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid change ID format']);
            return;
        }

        $projectPath = dirname(__DIR__, 2) . '/openspec';
        $changePath = $projectPath . '/changes/' . $id;
        
        if (!is_dir($changePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Change not found']);
            return;
        }
        
        // Execute openspec validate command
        $output = [];
        $returnCode = 0;
        
        chdir(dirname(__DIR__, 2));
        // Security: sanitize $id to prevent command injection
        $safeId = escapeshellarg($id);
        exec("openspec validate {$safeId} --strict --no-interactive 2>&1", $output, $returnCode);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
        ]);
    }
    
    /**
     * Create proposal form
     */
    public function createProposal(): void
    {
        ViewHelper::render('dashboard/openspec/create_proposal');
    }
}
