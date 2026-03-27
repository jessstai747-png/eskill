<?php

declare(strict_types=1);

/**
 * Componente de Seleção de Conta
 * Deve ser incluído no início de cada módulo que requer contexto de conta
 * 
 * Uso:
 * <?php 
 * $requireAccountSelection = true; // Força modal se nenhuma conta selecionada
 * $showAccountBanner = true; // Mostra banner fixo com conta ativa
 * include __DIR__ . '/../components/account-selector.php'; 
 * ?>
 */

use App\Helpers\SessionHelper;

// Obter contas do usuário
$userAccounts = SessionHelper::getUserAccounts();
$activeAccountId = SessionHelper::getActiveAccountId();
$activeAccount = null;

if ($activeAccountId) {
    foreach ($userAccounts as $account) {
        if ($account['id'] == $activeAccountId) {
            $activeAccount = $account;
            break;
        }
    }
}

// Configurações padrão
$requireAccountSelection = $requireAccountSelection ?? true;
$showAccountBanner = $showAccountBanner ?? true;
$moduleTitle = $moduleTitle ?? 'Este Módulo';
?>

<!-- Banner de Conta Ativa (Fixo no topo) -->
<?php if ($showAccountBanner && $activeAccount): ?>
<div class="account-context-banner" id="accountContextBanner">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="account-avatar-sm">
                <?= strtoupper(substr($activeAccount['nickname'], 0, 2)) ?>
            </div>
            <div class="ms-3">
                <small class="text-muted d-block" style="font-size: 0.75rem;">Conta Ativa:</small>
                <strong style="font-size: 0.95rem;"><?= htmlspecialchars($activeAccount['nickname']) ?></strong>
                <small class="text-muted ms-2">(ID: <?= htmlspecialchars($activeAccount['ml_user_id']) ?>)</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (count($userAccounts) > 1): ?>
            <button class="btn btn-sm btn-outline-light" onclick="AccountSelector.openModal()">
                <i class="bi bi-arrow-left-right me-1"></i>
                Trocar Conta
            </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-light" onclick="AccountSelector.toggleBanner()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</div>
<?php elseif ($showAccountBanner && !$activeAccount && count($userAccounts) > 0): ?>
<div class="account-context-banner warning" id="accountContextBanner">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle fs-4 text-warning me-3"></i>
            <div>
                <strong>Nenhuma conta selecionada</strong>
                <small class="text-muted d-block">Selecione uma conta para usar este módulo</small>
            </div>
        </div>
        <button class="btn btn-sm btn-warning" onclick="AccountSelector.openModal()">
            <i class="bi bi-shop-window me-1"></i>
            Selecionar Conta
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Modal de Seleção de Conta -->
<div class="modal fade" id="accountSelectorModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">
                    <i class="bi bi-shop-window text-primary me-2"></i>
                    Selecionar Conta do Mercado Livre
                </h5>
                <?php if (!$requireAccountSelection): ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                <?php endif; ?>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    <?= $moduleTitle ?> requer que você selecione uma conta do Mercado Livre.
                    <?php if ($activeAccount): ?>
                    <br><small class="text-success">✓ Conta atual: <strong><?= htmlspecialchars($activeAccount['nickname']) ?></strong></small>
                    <?php endif; ?>
                </p>
                
                <?php if (count($userAccounts) > 0): ?>
                <div class="list-group account-selection-list">
                    <?php foreach ($userAccounts as $account): ?>
                    <label class="list-group-item list-group-item-action account-selection-item <?= $account['id'] == $activeAccountId ? 'active' : '' ?>">
                        <div class="d-flex align-items-center">
                            <input type="radio" 
                                   name="account_selection" 
                                   value="<?= $account['id'] ?>" 
                                   <?= $account['id'] == $activeAccountId ? 'checked' : '' ?>
                                   onchange="AccountSelector.selectAccount(<?= $account['id'] ?>)">
                            <div class="account-avatar ms-3">
                                <?= strtoupper(substr($account['nickname'], 0, 2)) ?>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <div class="fw-semibold"><?= htmlspecialchars($account['nickname']) ?></div>
                                <small class="text-muted">
                                    ID: <?= htmlspecialchars($account['ml_user_id']) ?>
                                    <?php if (!empty($account['email'])): ?>
                                    • <?= htmlspecialchars($account['email']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php if ($account['id'] == $activeAccountId): ?>
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-shop-window fs-1 text-muted"></i>
                    <p class="mt-3 mb-2">Nenhuma conta vinculada</p>
                    <a href="/auth/authorize" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>
                        Vincular Primeira Conta
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php if (count($userAccounts) > 0): ?>
            <div class="modal-footer border-0 pt-0">
                <div class="w-100 d-flex justify-content-between align-items-center">
                    <a href="/auth/authorize" class="btn btn-link text-decoration-none">
                        <i class="bi bi-plus-circle me-1"></i>
                        Adicionar Nova Conta
                    </a>
                    <button type="button" class="btn btn-primary" onclick="AccountSelector.confirmSelection()">
                        <i class="bi bi-check-lg me-1"></i>
                        Confirmar Seleção
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Estilos -->
<style>
.account-context-banner {
    position: sticky;
    top: 0;
    z-index: 1020;
    background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
    color: white;
    padding: 0.75rem 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    animation: slideDown 0.3s ease-out;
}

.account-context-banner.warning {
    background: linear-gradient(135deg, #ff9800, #f44336);
}

.account-context-banner.hidden {
    display: none;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.account-avatar-sm {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.account-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.account-selection-item {
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent !important;
}

.account-selection-item:hover {
    background-color: #f8f9fa;
    border-color: var(--bs-primary) !important;
}

.account-selection-item.active {
    background-color: #e7f3ff;
    border-color: var(--bs-primary) !important;
}

.account-selection-item input[type="radio"] {
    cursor: pointer;
    width: 20px;
    height: 20px;
}

.account-selection-list {
    max-height: 400px;
    overflow-y: auto;
}

#accountSelectorModal .modal-content {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

#accountSelectorModal .modal-header {
    padding: 1.5rem 1.5rem 0.5rem;
}

#accountSelectorModal .modal-body {
    padding: 1rem 1.5rem;
}

#accountSelectorModal .modal-footer {
    padding: 0.5rem 1.5rem 1.5rem;
}
</style>

<!-- JavaScript -->
<script nonce="<?= CSP_NONCE ?>">
/**
 * Account Selector Manager
 * Gerencia seleção e troca de contas
 */
const AccountSelector = {
    currentAccountId: <?= json_encode($activeAccountId) ?>,
    selectedAccountId: <?= json_encode($activeAccountId) ?>,
    requireSelection: <?= json_encode($requireAccountSelection) ?>,
    modal: null,
    
    init() {
        this.modal = new bootstrap.Modal(document.getElementById('accountSelectorModal'));
        
        // Se não há conta selecionada e é obrigatório, abrir modal
        if (!this.currentAccountId && this.requireSelection && <?= count($userAccounts) ?> > 0) {
            setTimeout(() => this.openModal(), 500);
        }
        
        // Salvar preferência de banner no localStorage
        const bannerHidden = localStorage.getItem('accountBannerHidden') === 'true';
        if (bannerHidden) {
            const banner = document.getElementById('accountContextBanner');
            if (banner) banner.classList.add('hidden');
        }
    },

    showToast(message, type = 'info', duration = 4000) {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${String(message ?? '')}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
                </div>
            </div>
        `;

        let container = document.getElementById('account-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'account-toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }

        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = container.lastElementChild;
        const toast = new bootstrap.Toast(toastElement, { delay: duration });
        toast.show();
        toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
    },
    
    openModal() {
        this.selectedAccountId = this.currentAccountId;
        this.modal.show();
    },
    
    selectAccount(accountId) {
        this.selectedAccountId = accountId;
    },
    
    async confirmSelection() {
        if (!this.selectedAccountId) {
            this.showToast('Por favor, selecione uma conta.', 'warning');
            return;
        }
        
        // Se já é a conta atual, apenas fechar
        if (this.selectedAccountId === this.currentAccountId) {
            this.modal.hide();
            return;
        }
        
        // Trocar conta
        try {
            const result = await requestJson('/api/dashboard/switch-account', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ account_id: this.selectedAccountId })
            });
            
            if (result.success) {
                // Recarregar página para aplicar novo contexto
                window.location.reload();
            } else {
                this.showToast('Erro ao trocar conta: ' + (result.error || 'Desconhecido'), 'danger');
            }
        } catch (error) {
            console.error('Erro ao trocar conta:', error);
            this.showToast('Erro ao trocar conta. Tente novamente.', 'danger');
        }
    },
    
    toggleBanner() {
        const banner = document.getElementById('accountContextBanner');
        if (banner) {
            banner.classList.toggle('hidden');
            const isHidden = banner.classList.contains('hidden');
            localStorage.setItem('accountBannerHidden', isHidden);
        }
    },
    
    showBanner() {
        const banner = document.getElementById('accountContextBanner');
        if (banner) {
            banner.classList.remove('hidden');
            localStorage.setItem('accountBannerHidden', 'false');
        }
    }
};

// Inicializar ao carregar
document.addEventListener('DOMContentLoaded', () => AccountSelector.init());

// Exportar para uso global
window.AccountSelector = AccountSelector;
</script>
