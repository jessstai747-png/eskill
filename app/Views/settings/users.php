<?php
$title = 'Gerenciar Equipe';
$subtitle = 'Controle de acesso e permissões';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Convidar Membro</h5>
            </div>
            <div class="card-body">
                <form id="invite-form">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Função (Cargo)</label>
                        <select class="form-select" name="role">
                            <option value="support">Suporte (Ver Pedidos/Mensagens)</option>
                            <option value="manager">Gerente (Acesso Total exceto Configs)</option>
                            <option value="admin">Administrador (Acesso Irrestrito)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha Inicial</label>
                        <input type="password" class="form-control" name="password" minlength="8" required placeholder="Mínimo 8 caracteres">
                        <small class="text-muted">Informe esta senha ao usuário. Ele deverá alterá-la no primeiro acesso.</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus"></i> Adicionar Usuário
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Membros da Equipe</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="userManager.loadUsers()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Usuário</th>
                                <th>Função</th>
                                <th>Data Cadastro</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="users-list">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    const userManager = {
        init: function() {
            this.loadUsers();
            document.getElementById('invite-form').addEventListener('submit', (e) => this.invite(e));
        },

        loadUsers: async function() {
            const list = document.getElementById('users-list');
            list.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';

            try {
                const data = await requestJson('/api/users');
                
                if (data.success) {
                    this.render(data.users);
                }
            } catch (e) {
                console.error(e);
            }
        },
        
        render: function(users) {
            const list = document.getElementById('users-list');
            if (users.length === 0) {
                list.innerHTML = '<tr><td colspan="4" class="text-center">Nenhum usuário encontrado.</td></tr>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                const date = new Date(user.created_at).toLocaleDateString('pt-BR');
                const badgeClass = user.role === 'admin' ? 'bg-danger' : (user.role === 'manager' ? 'bg-primary' : 'bg-secondary');
                
                html += `
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;font-weight:bold;">
                                    ${user.name.charAt(0)}
                                </div>
                                <div>
                                    <h6 class="mb-0">${user.name}</h6>
                                    <small class="text-muted">${user.email}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge ${badgeClass}">${user.role.toUpperCase()}</span>
                        </td>
                        <td>${date}</td>
                        <td class="text-end pe-4">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><h6 class="dropdown-header">Alterar Função</h6></li>
                                    <li><a class="dropdown-item" href="#" onclick="userManager.changeRole(${user.id}, 'admin')">Tornar Admin</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="userManager.changeRole(${user.id}, 'manager')">Tornar Manager</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="userManager.changeRole(${user.id}, 'support')">Tornar Support</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            list.innerHTML = html;
        },
        
        invite: async function(e) {
            e.preventDefault();
            const form = e.target;
            const data = Object.fromEntries(new FormData(form));
            
            try {
                const result = await requestJson('/api/users/invite', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                if (result.success) {
                    Toast.success('Usuário adicionado com sucesso!');
                    form.reset();
                    this.loadUsers();
                } else {
                    Toast.error(result.message);
                }
            } catch (err) {
                console.error(err);
                Toast.error('Erro de conexão');
            }
        },
        
        changeRole: async function(id, role) {
            if (!confirm(`Confirmar alteração de permissão para ${role}?`)) return;
            
            try {
                const result = await requestJson('/api/users/role', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id, role})
                });
                
                if (result.success) {
                    Toast.success('Permissão atualizada!');
                    this.loadUsers();
                } else {
                    Toast.error(result.message);
                }
            } catch (err) {
                console.error(err);
            }
        }
    };
    
    document.addEventListener('DOMContentLoaded', () => userManager.init());
</script>
