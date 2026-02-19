<?php
/**
 * Gerenciamento de Contas Mercado Livre
 * 
 * Dashboard para conectar, reconectar e excluir contas do ML
 */

use App\Helpers\SecurityHelper;
$userId = $_SESSION['user_id'] ?? 0;
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-person-badge text-primary me-2"></i>
                        Contas Mercado Livre
                    </h1>
                    <p class="text-muted mb-0">Gerencie suas integrações com o Mercado Livre</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" id="syncAllBtn">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        Sincronizar Todas
                    </button>
                    <a href="/auth/authorize" class="btn btn-primary" id="connectNewAccountBtn">
                        <i class="bi bi-plus-lg me-2"></i>
                        Conectar Nova Conta
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Token Status Alert -->
    <div id="tokenStatusAlert" class="alert d-none mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
            <div>
                <strong id="tokenAlertTitle">Atenção</strong>
                <p class="mb-0" id="tokenAlertMessage"></p>
            </div>
            <button class="btn btn-warning btn-sm ms-auto" onclick="refreshAllTokens()">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Renovar Tokens
            </button>
        </div>
    </div>

    <!-- Accounts Grid -->
    <div class="row" id="accountsGrid">
        <!-- Loading placeholder -->
        <div class="col-12 text-center py-5" id="loadingPlaceholder">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="text-muted mt-3">Carregando contas...</p>
        </div>
    </div>

    <!-- Empty State -->
    <div class="row d-none" id="emptyState">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-shop display-1 text-muted"></i>
                    </div>
                    <h4 class="mb-3">Nenhuma conta conectada</h4>
                    <p class="text-muted mb-4">
                        Conecte sua conta do Mercado Livre para começar a usar todas as funcionalidades do sistema.
                    </p>
                    <a href="/auth/authorize" class="btn btn-primary btn-lg" id="connectNewAccountEmptyBtn">
                        <i class="bi bi-link-45deg me-2"></i>
                        Conectar Conta do Mercado Livre
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Account Card Template -->
<template id="accountCardTemplate">
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100 border-0 shadow-sm account-card" data-account-id="">
            <div class="card-header bg-transparent border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="account-avatar me-3">
                            <img src="" alt="" class="rounded-circle" width="48" height="48" 
                                 onerror="this.src='https://http2.mlstatic.com/frontend-assets/ui-navigation/5.21.11/mercadolibre/logo__large_plus.png'">
                        </div>
                        <div>
                            <h5 class="mb-0 account-nickname"></h5>
                            <small class="text-muted account-email"></small>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical fs-5"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="reconnectAccount(this)">
                                <i class="bi bi-arrow-repeat me-2"></i>Reconectar
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="syncAccount(this)">
                                <i class="bi bi-cloud-download me-2"></i>Sincronizar Itens
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="refreshToken(this)">
                                <i class="bi bi-key me-2"></i>Renovar Token
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="disconnectAccount(this)">
                                <i class="bi bi-plug me-2"></i>Desconectar
                            </a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteAccount(this)">
                                <i class="bi bi-trash me-2"></i>Excluir Permanentemente
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded text-center">
                            <small class="text-muted d-block">Status</small>
                            <span class="badge account-status"></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded text-center">
                            <small class="text-muted d-block">Token</small>
                            <span class="badge account-token-status"></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block">Expira em</small>
                            <span class="account-expires"></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-2 bg-light rounded d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">Itens Sincronizados</small>
                                <span class="account-items-count">-</span>
                            </div>
                            <small class="text-muted account-last-sync"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm flex-fill" onclick="viewAccountDetails(this)">
                        <i class="bi bi-eye me-1"></i>Detalhes
                    </button>
                    <button class="btn btn-outline-success btn-sm flex-fill btn-sync" onclick="syncAccount(this)">
                        <i class="bi bi-cloud-download me-1"></i>Sincronizar
                    </button>
                    <button class="btn btn-primary btn-sm flex-fill btn-reconnect" onclick="reconnectAccount(this)">
                        <i class="bi bi-arrow-repeat me-1"></i>Reconectar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Confirmar Exclusão
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir permanentemente a conta <strong id="deleteAccountName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong>Atenção:</strong> Esta ação é irreversível. Todos os dados relacionados a esta conta serão perdidos.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="bi bi-trash me-1"></i>Excluir Permanentemente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Account Details Modal -->
<div class="modal fade" id="accountDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>
                    Detalhes da Conta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="accountDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<style>
.account-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.account-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
.account-avatar img {
    object-fit: cover;
    background: #f8f9fa;
}
.account-card[data-status="expired"] {
    border-left: 4px solid #dc3545 !important;
}
.account-card[data-status="expiring_soon"] {
    border-left: 4px solid #ffc107 !important;
}
.account-card[data-status="valid"] {
    border-left: 4px solid #28a745 !important;
}
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) {
        return window.ApiClient.request(url, options);
    }
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (/^(data:|blob:)/i.test(trimmed)) return trimmed;
    if (trimmed.startsWith('//')) return window.location.protocol + trimmed;
    if (/^http:\/\//i.test(trimmed)) return trimmed.replace(/^http:\/\//i, 'https://');
    return trimmed;
}

console.log('=== Script accounts.php iniciado ===');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded fired');
    
    try {
        // Bind event listeners
        const btnConnect = document.getElementById('connectNewAccountBtn');
        const btnConnectEmpty = document.getElementById('connectNewAccountEmptyBtn');
        const btnSyncAll = document.getElementById('syncAllBtn');
        
        console.log('Botões encontrados:', { btnConnect, btnConnectEmpty, btnSyncAll });
        
        if (btnConnect) {
            btnConnect.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Click em connectNewAccountBtn');
                connectNewAccount(e.currentTarget);
            });
        }
        
        if (btnConnectEmpty) {
            btnConnectEmpty.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Click em connectNewAccountEmptyBtn');
                connectNewAccount(e.currentTarget);
            });
        }
        
        if (btnSyncAll) {
            btnSyncAll.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Click em syncAllBtn');
                syncAllAccounts();
            });
        }
        
        loadAccounts();
        checkTokenStatus();
    } catch (err) {
        console.error('Erro no DOMContentLoaded:', err);
    }
});

let accountsData = [];

async function loadAccounts() {
    console.log('loadAccounts() iniciando...');
    try {
        const data = await requestJson('/api/auth/accounts');
        console.log('loadAccounts() data:', data);
        
        accountsData = data.accounts || [];
        
        const grid = document.getElementById('accountsGrid');
        const loading = document.getElementById('loadingPlaceholder');
        const empty = document.getElementById('emptyState');
        
        if (loading) loading.classList.add('d-none');
        
        if (accountsData.length === 0) {
            if (empty) empty.classList.remove('d-none');
            console.log('loadAccounts() - sem contas, mostrando empty state');
            return;
        }

        if (empty) empty.classList.add('d-none');
        if (grid) grid.innerHTML = '';
        
        const template = document.getElementById('accountCardTemplate');
        if (!template) {
            console.error('Template accountCardTemplate não encontrado');
            return;
        }
        
        for (const account of accountsData) {
            const card = template.content.cloneNode(true);
            
            card.querySelector('.account-card').dataset.accountId = account.id;
            card.querySelector('.account-nickname').textContent = account.nickname || 'Sem nome';
            card.querySelector('.account-email').textContent = account.email || '';
            card.querySelector('.account-avatar img').src = normalizeExternalUrl(account.thumbnail) || '';
            card.querySelector('.account-avatar img').alt = account.nickname || '';
            
            // Status
            const statusBadge = card.querySelector('.account-status');
            const status = account.status || 'unknown';
            statusBadge.textContent = getStatusLabel(status);
            statusBadge.className = `badge ${getStatusClass(status)}`;
            
            // Token status
            const tokenBadge = card.querySelector('.account-token-status');
            const tokenStatus = getTokenStatus(account.token_expires_at);
            tokenBadge.textContent = tokenStatus.label;
            tokenBadge.className = `badge ${tokenStatus.class}`;
            
            card.querySelector('.account-card').dataset.status = tokenStatus.status;
            
            // Expires
            const expiresEl = card.querySelector('.account-expires');
            expiresEl.textContent = formatExpiration(account.token_expires_at);
            
            // Items count e last sync
            const itemsCount = card.querySelector('.account-items-count');
            const lastSync = card.querySelector('.account-last-sync');
            itemsCount.textContent = account.items_count ?? '-';
            lastSync.textContent = account.last_synced_at 
                ? `Último sync: ${formatRelativeTime(account.last_synced_at)}`
                : 'Nunca sincronizado';
            
            // Show reconnect button only if needed
            if (status === 'active' && tokenStatus.status === 'valid') {
                card.querySelector('.btn-reconnect').classList.add('d-none');
            }
            
            // Disable sync if token invalid
            if (status === 'expired' || !tokenStatus.status || tokenStatus.status === 'expired') {
                const syncBtn = card.querySelector('.btn-sync');
                if (syncBtn) {
                    syncBtn.disabled = true;
                    syncBtn.title = 'Token expirado - reconecte a conta';
                }
            }
            
            grid.appendChild(card);
        }
        
        // Carregar status de sync para cada conta
        loadSyncStatuses();
        
    } catch (error) {
        console.error('Erro ao carregar contas:', error);
        showToast('Erro ao carregar contas', 'error');
    }
}

async function loadSyncStatuses() {
    for (const account of accountsData) {
        try {
            const data = await requestJson(`/api/accounts/${account.id}/sync/status`);
            
            if (data.success && data.data) {
                const card = document.querySelector(`.account-card[data-account-id="${account.id}"]`);
                if (card) {
                    const itemsCount = card.querySelector('.account-items-count');
                    const lastSync = card.querySelector('.account-last-sync');
                    
                    itemsCount.textContent = data.data.items_count ?? '-';
                    lastSync.textContent = data.data.last_synced_at 
                        ? `Último sync: ${formatRelativeTime(data.data.last_synced_at)}`
                        : 'Nunca sincronizado';
                }
            }
        } catch (e) {
            console.warn(`Erro ao carregar status de sync da conta ${account.id}:`, e);
        }
    }
}

function formatRelativeTime(dateStr) {
    if (!dateStr) return 'N/A';
    
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'agora';
    if (diffMins < 60) return `${diffMins}min atrás`;
    if (diffHours < 24) return `${diffHours}h atrás`;
    if (diffDays < 7) return `${diffDays}d atrás`;
    
    return date.toLocaleDateString('pt-BR');
}

async function checkTokenStatus() {
    try {
        const data = await requestJson('/api/multi-account/tokens/status');
        
        const alert = document.getElementById('tokenStatusAlert');
        
        if (data.needs_attention) {
            alert.classList.remove('d-none');
            alert.className = 'alert alert-warning mb-4';
            
            let message = '';
            if (data.summary.expired > 0) {
                message += `${data.summary.expired} conta(s) com token expirado. `;
            }
            if (data.summary.expiring_soon > 0) {
                message += `${data.summary.expiring_soon} conta(s) com token expirando em breve.`;
            }
            
            document.getElementById('tokenAlertTitle').textContent = 'Atenção com seus tokens';
            document.getElementById('tokenAlertMessage').textContent = message;
        } else {
            alert.classList.add('d-none');
        }
    } catch (error) {
        console.error('Erro ao verificar status dos tokens:', error);
    }
}

function getStatusLabel(status) {
    const labels = {
        'active': 'Ativo',
        'inactive': 'Inativo',
        'expired': 'Expirado',
        'error': 'Erro'
    };
    return labels[status] || 'Desconhecido';
}

function getStatusClass(status) {
    const classes = {
        'active': 'bg-success',
        'inactive': 'bg-secondary',
        'expired': 'bg-danger',
        'error': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function getTokenStatus(expiresAt) {
    if (!expiresAt) {
        return { status: 'unknown', label: 'Desconhecido', class: 'bg-secondary' };
    }
    
    const now = new Date();
    const expires = new Date(expiresAt);
    const hoursLeft = (expires - now) / (1000 * 60 * 60);
    
    if (hoursLeft < 0) {
        return { status: 'expired', label: 'Expirado', class: 'bg-danger' };
    } else if (hoursLeft < 2) {
        return { status: 'expiring_soon', label: 'Expirando', class: 'bg-warning text-dark' };
    }
    return { status: 'valid', label: 'Válido', class: 'bg-success' };
}

function formatExpiration(expiresAt) {
    if (!expiresAt) return 'N/A';
    
    const now = new Date();
    const expires = new Date(expiresAt);
    const diff = expires - now;
    
    if (diff < 0) {
        return 'Expirado';
    }
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 24) {
        const days = Math.floor(hours / 24);
        return `${days} dia(s)`;
    }
    
    return `${hours}h ${minutes}min`;
}

function connectNewAccount(el) {
    console.log('=== connectNewAccount() chamada ===');
    const url = el?.getAttribute('href') || '/auth/authorize';
    window.location.href = url;
}

function reconnectAccount(el) {
    const card = el.closest('.account-card');
    const accountId = card.dataset.accountId;
    window.location.href = `/auth/authorize?reconnect=${accountId}`;
}

async function syncAccount(el) {
    const card = el.closest('.account-card');
    const accountId = card.dataset.accountId;
    const syncBtn = card.querySelector('.btn-sync');
    const originalHtml = syncBtn.innerHTML;
    
    try {
        // Mostrar loading
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sincronizando...';
        
        const data = await requestJson(`/api/accounts/${accountId}/sync`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        if (data.success) {
            const stats = data.stats || {};
            showToast(`Sincronização concluída! ${stats.total_synced || 0} itens sincronizados.`, 'success');
            loadAccounts(); // Recarregar para mostrar dados atualizados
        } else {
            if (data.needs_reconnect && data.reconnect_url) {
                showToast('Token inválido/expirado. Redirecionando para reconectar...', 'warning');
                window.location.href = data.reconnect_url;
                return;
            }
            showToast(data.error || 'Falha na sincronização', 'error');
        }
    } catch (error) {
        console.error('Erro ao sincronizar:', error);
        showToast('Erro ao sincronizar conta', 'error');
    } finally {
        syncBtn.disabled = false;
        syncBtn.innerHTML = originalHtml;
    }
}

async function syncAllAccounts() {
    const syncBtn = document.getElementById('syncAllBtn');
    const originalHtml = syncBtn.innerHTML;
    
    try {
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sincronizando...';
        
        const data = await requestJson('/api/accounts/sync-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        if (data.success) {
            const result = data.data || {};
            showToast(
                `Sincronização concluída! ${result.success || 0} de ${result.total || 0} contas sincronizadas.`, 
                result.failed > 0 ? 'warning' : 'success'
            );
            loadAccounts();
        } else {
            showToast(data.error || 'Falha na sincronização', 'error');
        }
    } catch (error) {
        console.error('Erro ao sincronizar todas as contas:', error);
        showToast('Erro ao sincronizar contas', 'error');
    } finally {
        syncBtn.disabled = false;
        syncBtn.innerHTML = originalHtml;
    }
}

async function refreshToken(el) {
    const card = el.closest('.account-card');
    const accountId = card.dataset.accountId;
    
    try {
        const data = await requestJson('/api/multi-account/tokens/refresh', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ account_id: parseInt(accountId) })
        });
        
        if (data.success) {
            showToast('Token renovado com sucesso!', 'success');
            loadAccounts();
            checkTokenStatus();
        } else {
            if (data.needs_reconnect && data.reconnect_url) {
                showToast('Não foi possível renovar o token. Redirecionando para reconectar...', 'warning');
                window.location.href = data.reconnect_url;
                return;
            }
            showToast(data.message || 'Falha ao renovar token. Reconecte a conta.', 'warning');
        }
    } catch (error) {
        console.error('Erro ao renovar token:', error);
        showToast('Erro ao renovar token', 'error');
    }
}

async function refreshAllTokens() {
    try {
        const data = await requestJson('/api/multi-account/tokens/refresh-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        if (data.success) {
            showToast('Todos os tokens foram renovados!', 'success');
        } else {
            showToast(data.message, data.failed.length > 0 ? 'warning' : 'success');
        }
        
        loadAccounts();
        checkTokenStatus();
    } catch (error) {
        console.error('Erro ao renovar tokens:', error);
        showToast('Erro ao renovar tokens', 'error');
    }
}

async function disconnectAccount(el) {
    const card = el.closest('.account-card');
    const accountId = card.dataset.accountId;
    const nickname = card.querySelector('.account-nickname').textContent;
    
    if (!confirm(`Deseja desconectar a conta "${nickname}"? Os dados serão mantidos.`)) {
        return;
    }
    
    try {
        const data = await requestJson(`/auth/disconnect/${accountId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        if (data.success) {
            showToast('Conta desconectada com sucesso', 'success');
            loadAccounts();
        } else {
            showToast(data.error || 'Erro ao desconectar conta', 'error');
        }
    } catch (error) {
        console.error('Erro ao desconectar conta:', error);
        showToast('Erro ao desconectar conta', 'error');
    }
}

let deleteAccountId = null;

function deleteAccount(el) {
    const card = el.closest('.account-card');
    deleteAccountId = card.dataset.accountId;
    const nickname = card.querySelector('.account-nickname').textContent;
    
    document.getElementById('deleteAccountName').textContent = nickname;
    
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    if (!deleteAccountId) return;
    
    try {
        const data = await requestJson(`/auth/account/${deleteAccountId}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        });
        
        bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
        
        if (data.success) {
            showToast('Conta excluída permanentemente', 'success');
            loadAccounts();
        } else {
            showToast(data.error || 'Erro ao excluir conta', 'error');
        }
    } catch (error) {
        console.error('Erro ao excluir conta:', error);
        showToast('Erro ao excluir conta', 'error');
    }
    
    deleteAccountId = null;
});

function viewAccountDetails(el) {
    const card = el.closest('.account-card');
    const accountId = card.dataset.accountId;
    const account = accountsData.find(a => a.id == accountId);
    
    if (!account) return;
    
    const content = document.getElementById('accountDetailsContent');
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Informações Gerais</h6>
                <table class="table table-sm">
                    <tr><td class="text-muted">ID:</td><td>${account.id}</td></tr>
                    <tr><td class="text-muted">ML User ID:</td><td>${account.ml_user_id || 'N/A'}</td></tr>
                    <tr><td class="text-muted">Nickname:</td><td>${account.nickname || 'N/A'}</td></tr>
                    <tr><td class="text-muted">Email:</td><td>${account.email || 'N/A'}</td></tr>
                    <tr><td class="text-muted">Site:</td><td>${account.site_id || 'MLB'}</td></tr>
                    <tr><td class="text-muted">Status:</td><td><span class="badge ${getStatusClass(account.status)}">${getStatusLabel(account.status)}</span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Token de Acesso</h6>
                <table class="table table-sm">
                    <tr><td class="text-muted">Expira em:</td><td>${account.token_expires_at || 'N/A'}</td></tr>
                    <tr><td class="text-muted">Criptografado:</td><td>${account.tokens_encrypted ? 'Sim' : 'Não'}</td></tr>
                    <tr><td class="text-muted">Criado:</td><td>${account.created_at || 'N/A'}</td></tr>
                    <tr><td class="text-muted">Atualizado:</td><td>${account.updated_at || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('accountDetailsModal'));
    modal.show();
}

function showToast(message, type = 'info') {
    // Simple toast implementation
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'primary'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}
</script>
