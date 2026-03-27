<?php

declare(strict_types=1);

/**
 * OpenSpec Dashboard
 * Main interface for managing OpenSpec changes and specifications
 */
$pageTitle = 'OpenSpec - Gerenciador de Especificações';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-file-earmark-code text-primary me-2"></i>
                OpenSpec Manager
            </h1>
            <p class="text-muted mb-0">Gerenciamento de especificações e mudanças</p>
        </div>
        <div>
            <a href="/dashboard/openspec/create" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>
                Novo Proposal
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-file-text text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total de Specs</h6>
                            <h3 class="mb-0"><?= $specsCount ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-folder text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Changes Totais</h6>
                            <h3 class="mb-0"><?= $changesCount ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Status</h6>
                            <h3 class="mb-0 text-success">Ativo</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Changes -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Mudanças Recentes
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentChanges)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    <p class="mb-0">Nenhuma mudança encontrada</p>
                    <small>Crie um novo proposal para começar</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID da Mudança</th>
                                <th>Título</th>
                                <th>Documentos</th>
                                <th>Última Modificação</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentChanges as $change): ?>
                                <tr>
                                    <td>
                                        <code class="text-primary"><?= htmlspecialchars($change['id']) ?></code>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($change['title']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($change['has_proposal']): ?>
                                            <span class="badge bg-primary me-1">
                                                <i class="bi bi-file-text"></i> Proposal
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($change['has_tasks']): ?>
                                            <span class="badge bg-info me-1">
                                                <i class="bi bi-list-check"></i> Tasks
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($change['has_design']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-diagram-3"></i> Design
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', $change['modified']) ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <a href="/dashboard/openspec/change/<?= urlencode($change['id']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($recentChanges)): ?>
            <div class="card-footer bg-white border-top text-center">
                <a href="/dashboard/openspec/changes" class="text-decoration-none">
                    Ver todas as mudanças <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-lightning-charge text-warning me-2"></i>
                        Ações Rápidas
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="/dashboard/openspec/create" class="btn btn-outline-primary text-start">
                            <i class="bi bi-plus-circle me-2"></i>
                            Criar Novo Proposal
                        </a>
                        <a href="/dashboard/openspec/changes" class="btn btn-outline-secondary text-start">
                            <i class="bi bi-folder2-open me-2"></i>
                            Listar Todas as Mudanças
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-info-circle text-info me-2"></i>
                        Informações do Projeto
                    </h6>
                    <div class="small">
                        <div class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                            <pre class="mb-0" style="font-size: 0.85rem; white-space: pre-wrap;"><?= htmlspecialchars($projectInfo) ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.table tbody tr {
    cursor: pointer;
    transition: background-color 0.2s;
}

code {
    padding: 0.2rem 0.4rem;
    background-color: #f8f9fa;
    border-radius: 0.25rem;
}
</style>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
