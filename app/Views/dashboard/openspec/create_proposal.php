<?php
/**
 * OpenSpec - Criar Novo Proposal
 * Formulário para criação de uma nova proposta de mudança
 */
$pageTitle = 'OpenSpec - Novo Proposal';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid p-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/dashboard/openspec">OpenSpec</a></li>
            <li class="breadcrumb-item active">Novo Proposal</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-4">
        <h1 class="h3 mb-1">
            <i class="bi bi-plus-circle text-primary me-2"></i>
            Novo Proposal
        </h1>
        <p class="text-muted mb-0">Crie uma nova proposta de mudança para o projeto</p>
    </div>

    <!-- Form -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="proposalForm">
                <div class="mb-3">
                    <label for="changeId" class="form-label fw-semibold">ID da Mudança</label>
                    <input type="text" class="form-control" id="changeId" name="change_id"
                           placeholder="ex: melhoria-checkout-v2" required
                           pattern="[a-zA-Z0-9_\-]+"
                           title="Apenas letras, números, hifens e underscores">
                    <div class="form-text">Identificador único (slug). Use apenas letras, números, hifens e underscores.</div>
                </div>

                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold">Título</label>
                    <input type="text" class="form-control" id="title" name="title"
                           placeholder="Título descritivo do proposal" required>
                </div>

                <div class="mb-4">
                    <label for="proposal" class="form-label fw-semibold">Conteúdo do Proposal</label>
                    <textarea class="form-control" id="proposal" name="proposal" rows="12"
                              placeholder="Descreva a mudança proposta em Markdown..." required></textarea>
                    <div class="form-text">Use formato Markdown para formatação.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>
                        Criar Proposal
                    </button>
                    <a href="/dashboard/openspec" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

document.getElementById('proposalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const data = Object.fromEntries(form.entries());

    try {
        const result = await requestJson('/api/openspec/changes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        if (result.success) {
            window.location.href = '/dashboard/openspec';
        } else {
            alert(result.error || 'Erro ao criar proposal');
        }
    } catch (err) {
        alert('Erro de conexão');
    }
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
