<style>
    .profile-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: #667eea;
    }
</style>

<div class="row g-4">
    <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="card profile-card text-center p-4 mb-4">
            <div class="profile-avatar mx-auto mb-3">
                <i class="bi bi-person-circle"></i>
            </div>
            <h5 class="mb-1"><?= htmlspecialchars($currentUser['name'] ?? 'Usuário') ?></h5>
            <p class="opacity-75 mb-3"><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
            <span class="badge bg-light text-dark">
                <?= $currentUser['role'] === 'admin' ? 'Administrador' : 'Usuário' ?>
            </span>
        </div>

        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Estatísticas</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Contas ML</span>
                    <strong id="accountsCount">0</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Último login</span>
                    <strong><?= date('d/m/Y H:i') ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Membro desde</span>
                    <strong><?= isset($currentUser['created_at']) ? date('d/m/Y', strtotime($currentUser['created_at'])) : '-' ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Informações Pessoais -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i>Informações Pessoais</h6>
            </div>
            <div class="card-body">
                <form id="profileForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Nome</label>
                            <input type="text" class="form-control" id="userName" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input type="email" class="form-control" id="userEmail" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Telefone</label>
                            <input type="tel" class="form-control" id="userPhone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="(11) 99999-9999">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Empresa</label>
                            <input type="text" class="form-control" id="userCompany" value="<?= htmlspecialchars($currentUser['company'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="bi bi-save me-1"></i>Salvar Alterações
                    </button>
                </form>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-key me-2"></i>Alterar Senha</h6>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Senha Atual</label>
                            <input type="password" class="form-control" id="currentPassword" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Nova Senha</label>
                            <input type="password" class="form-control" id="newPassword" required minlength="8">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Confirmar</label>
                            <input type="password" class="form-control" id="confirmPassword" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning mt-3">
                        <i class="bi bi-key me-1"></i>Alterar Senha
                    </button>
                </form>
            </div>
        </div>

        <!-- Segurança -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Segurança</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong>Autenticação em Dois Fatores (2FA)</strong>
                        <p class="text-muted small mb-0">Adicione uma camada extra de segurança</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enable2FA" <?= ($currentUser['two_factor_enabled'] ?? false) ? 'checked' : '' ?>>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Sessões Ativas</strong>
                        <p class="text-muted small mb-0">Gerencie seus dispositivos conectados</p>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" onclick="logoutAllSessions()">
                        Encerrar Todas
                    </button>
                </div>
            </div>
        </div>

        <!-- Contas ML Conectadas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Contas Mercado Livre</h6>
                <a href="/auth/authorize" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Adicionar
                </a>
            </div>
            <div class="list-group list-group-flush" id="mlAccountsList">
                <div class="text-center py-4 text-muted">Carregando...</div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    document.addEventListener('DOMContentLoaded', function() {
        loadAccounts();
    });

    async function loadAccounts() {
        try {
            const data = await requestJson('/api/auth/accounts');

            // API retorna { accounts: [...], total: N }
            const accounts = Array.isArray(data) ? data : (data.accounts || []);

            document.getElementById('accountsCount').textContent = accounts.length;

            const container = document.getElementById('mlAccountsList');
            if (accounts.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-link-slash fs-1 d-block mb-2"></i>Nenhuma conta conectada</div>';
                return;
            }

            container.innerHTML = accounts.map(acc => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${acc.nickname || 'Conta ' + acc.id}</strong>
                    <br><small class="text-muted">ID: ${acc.ml_user_id || '-'}</small>
                </div>
                <div>
                    <span class="badge bg-${acc.status === 'active' ? 'success' : 'warning'} me-2">${acc.status === 'active' ? 'Ativa' : 'Inativa'}</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-danger" onclick="disconnectAccount(${acc.id})" title="Desconectar (mantém histórico)">
                            <i class="bi bi-plug"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteAccount(${acc.id})" title="Excluir permanentemente">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        } catch (e) {
            document.getElementById('mlAccountsList').innerHTML = '<div class="text-center py-4 text-danger">Erro ao carregar</div>';
        }
    }

    document.getElementById('profileForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        try {
            const data = await requestJson('/api/user/profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: document.getElementById('userName').value,
                    phone: document.getElementById('userPhone').value,
                    company: document.getElementById('userCompany').value
                })
            });

            alert(data.success ? 'Perfil atualizado!' : 'Erro ao atualizar');
        } catch (e) {
            alert('Erro ao salvar');
        }
    });

    document.getElementById('passwordForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const newPass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;

        if (newPass !== confirmPass) {
            alert('As senhas não conferem');
            return;
        }

        try {
            const data = await requestJson('/api/user/password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    current_password: document.getElementById('currentPassword').value,
                    new_password: newPass
                })
            });

            if (data.success) {
                alert('Senha alterada!');
                this.reset();
            } else {
                alert(data.error || 'Erro ao alterar senha');
            }
        } catch (e) {
            alert('Erro ao alterar senha');
        }
    });

    document.getElementById('enable2FA').addEventListener('change', async function() {
        const enabled = this.checked;

        if (enabled) {
            // Show 2FA setup modal or redirect
            window.location.href = '/auth/2fa/setup';
        } else {
            if (!confirm('Desativar a autenticação em dois fatores?')) {
                this.checked = true;
                return;
            }

            try {
                await requestJson('/api/user/2fa/disable', {
                    method: 'POST'
                });
            } catch (e) {
                this.checked = true;
                alert('Erro ao desativar 2FA');
            }
        }
    });

    async function disconnectAccount(accountId) {
        if (!confirm('Desconectar esta conta? (O histórico será mantido)')) return;

        try {
            const result = await requestJson(`/auth/disconnect/${accountId}`, {
                method: 'POST'
            });

            if (result.success) {
                alert('Conta desconectada com sucesso!');
                loadAccounts();
            } else {
                alert('Erro: ' + result.error);
            }
        } catch (e) {
            alert('Erro ao desconectar');
        }
    }

    async function deleteAccount(accountId) {
        if (!confirm('⚠️ ATENÇÃO: Esta ação é IRREVERSÍVEL!\n\nDeseja realmente EXCLUIR PERMANENTEMENTE esta conta?\n\nTodos os dados relacionados serão perdidos!')) {
            return;
        }

        // Segunda confirmação
        if (!confirm('Confirme novamente: EXCLUIR PERMANENTEMENTE esta conta?')) {
            return;
        }

        try {
            const result = await requestJson(`/auth/account/${accountId}`, {
                method: 'DELETE'
            });

            if (result.success) {
                alert('Conta excluída permanentemente!');
                loadAccounts();
            } else {
                alert('Erro: ' + result.error);
            }
        } catch (e) {
            alert('Erro ao excluir conta');
        }
    }

    async function logoutAllSessions() {
        if (!confirm('Encerrar todas as outras sessões?')) return;

        try {
            await requestJson('/api/user/sessions/logout-all', {
                method: 'POST'
            });
            alert('Sessões encerradas');
        } catch (e) {
            alert('Erro');
        }
    }
</script>
