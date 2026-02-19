<?php
/**
 * Rotas da Fase 8 - Dashboard, Reports, SEO e Progress Tracking
 * 
 * Adicionar ao app/routes.php ou incluir via require
 */

use App\Controllers\CatalogCloneController;

// Router is passed to this file via 'require' inside app/routes.php
// which already has $router available from public/index.php
// $router = App\Router::getInstance(); // REMOVE: Router doesn't have getInstance()
// Assume $router is already in scope from the include
if (!isset($router)) {
    // If not set for some reason, we can't do anything
    return;
}

// ==============================================
// Dashboard Real-Time (SSE)
// ==============================================

// Stream SSE infinito para dashboard real-time
$router->get('/api/catalog/clone/dashboard/stream', CatalogCloneController::class, 'streamDashboard');

// Snapshot único do dashboard (não-streaming)
$router->get('/api/catalog/clone/dashboard/snapshot', CatalogCloneController::class, 'getDashboardSnapshot');

// Widget de progresso individual
$router->get('/api/catalog/clone/progress/{jobId}/widget', CatalogCloneController::class, 'getJobProgressWidget');

// ==============================================
// Export Relatórios
// ==============================================

// Gerar relatório (POST com body JSON)
$router->post('/api/catalog/clone/reports/export', CatalogCloneController::class, 'exportReport');

// Download de relatório gerado
$router->get('/api/catalog/clone/reports/download/{filename}', CatalogCloneController::class, 'downloadReport');

// ==============================================
// SEO Integration
// ==============================================

// Análise SEO pré-clonagem (POST com item data)
$router->post('/api/catalog/clone/seo/analyze', CatalogCloneController::class, 'analyzeSeo');

// Aplicar otimizações SEO (POST com items array)
$router->post('/api/catalog/clone/seo/optimize', CatalogCloneController::class, 'applyOptimizations');

// ==============================================
// Progress Tracking
// ==============================================

// Progresso atual de um job
$router->get('/api/catalog/clone/progress/{jobId}', CatalogCloneController::class, 'getJobProgress');

// Histórico de progresso
$router->get('/api/catalog/clone/progress/{jobId}/history', CatalogCloneController::class, 'getProgressHistory');

// Detalhes das fases
$router->get('/api/catalog/clone/progress/{jobId}/phases', CatalogCloneController::class, 'getJobProgress');

// Progresso de múltiplos jobs (batch)
$router->post('/api/catalog/clone/progress/batch', CatalogCloneController::class, 'getBatchProgress');

// Atualização manual de progresso (para integrações externas)
$router->post('/api/catalog/clone/progress/{jobId}/update', CatalogCloneController::class, 'getJobProgress');

// ==============================================
// Jobs Ativos (auxiliar para dashboard)
// ==============================================

// Lista de jobs ativos/processando
$router->get('/api/catalog/clone/jobs/active', CatalogCloneController::class, 'listActiveJobs');

// NOTE: Please ensure listActiveJobs method exists in CatalogCloneController or alias it to listJobs inside the controller logic if needed. 
// Standard Router implementation doesn't support query param injection via closures easily without container magic.
