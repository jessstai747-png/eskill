<?php
/**
 * OpenSpec Change Detail View
 * Shows detailed information about a specific change
 */
$pageTitle = 'OpenSpec - ' . htmlspecialchars($change['id']);
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard/openspec">OpenSpec</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($change['id']) ?></li>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-folder-open text-primary me-2"></i>
                    <?= htmlspecialchars($change['id']) ?>
                </h1>
                <p class="text-muted mb-0">Detalhes da mudança</p>
            </div>
            <div>
                <button class="btn btn-outline-primary" onclick="validateChange('<?= htmlspecialchars($change['id']) ?>')">
                    <i class="bi bi-check-circle me-1"></i>
                    Validar
                </button>
                <a href="/dashboard/openspec" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="changeTabs" role="tablist">
        <?php if (isset($change['proposal'])): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="proposal-tab" data-bs-toggle="tab" data-bs-target="#proposal" type="button">
                    <i class="bi bi-file-text me-1"></i> Proposal
                </button>
            </li>
        <?php endif; ?>
        
        <?php if (isset($change['tasks'])): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= !isset($change['proposal']) ? 'active' : '' ?>" 
                        id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button">
                    <i class="bi bi-list-check me-1"></i> Tasks
                </button>
            </li>
        <?php endif; ?>
        
        <?php if (isset($change['design'])): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="design-tab" data-bs-toggle="tab" data-bs-target="#design" type="button">
                    <i class="bi bi-diagram-3 me-1"></i> Design
                </button>
            </li>
        <?php endif; ?>
        
        <?php if (!empty($change['specs'])): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs" type="button">
                    <i class="bi bi-code-square me-1"></i> Spec Deltas (<?= count($change['specs']) ?>)
                </button>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="changeTabsContent">
        <?php if (isset($change['proposal'])): ?>
            <div class="tab-pane fade show active" id="proposal" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Proposal</h5>
                    </div>
                    <div class="card-body">
                        <div class="markdown-content">
                            <?= nl2br(htmlspecialchars($change['proposal'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($change['tasks'])): ?>
            <div class="tab-pane fade <?= !isset($change['proposal']) ? 'show active' : '' ?>" id="tasks" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Tasks</h5>
                    </div>
                    <div class="card-body">
                        <div class="markdown-content">
                            <?= nl2br(htmlspecialchars($change['tasks'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($change['design'])): ?>
            <div class="tab-pane fade" id="design" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Design</h5>
                    </div>
                    <div class="card-body">
                        <div class="markdown-content">
                            <?= nl2br(htmlspecialchars($change['design'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($change['specs'])): ?>
            <div class="tab-pane fade" id="specs" role="tabpanel">
                <?php foreach ($change['specs'] as $spec): ?>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0">
                                <i class="bi bi-code-square me-2"></i>
                                <?= htmlspecialchars($spec['name']) ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="markdown-content">
                                <?= nl2br(htmlspecialchars($spec['content'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Validation Modal -->
<div class="modal fade" id="validationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle me-2"></i>
                    Resultado da Validação
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="validationResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<style>
.markdown-content {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.6;
}

.markdown-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    overflow-x: auto;
}
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

function validateChange(changeId) {
    const resultDiv = document.getElementById('validationResult');
    resultDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Validando...</span></div><p class="mt-3">Validando mudança...</p></div>';
    
    const modal = new bootstrap.Modal(document.getElementById('validationModal'));
    modal.show();
    
    requestJson(`/api/openspec/validate/${encodeURIComponent(changeId)}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Validação bem-sucedida!</strong>
                </div>
                <pre class="bg-light p-3 rounded">${escapeHtml(data.output)}</pre>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Validação falhou (código: ${data.return_code})</strong>
                </div>
                <pre class="bg-light p-3 rounded">${escapeHtml(data.output)}</pre>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-x-circle-fill me-2"></i>
                <strong>Erro ao validar:</strong> ${escapeHtml(error.message)}
            </div>
        `;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
