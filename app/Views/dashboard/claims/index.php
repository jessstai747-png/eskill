<?php
$title = 'Gestão de Reclamações';
$subtitle = 'Resolução de disputas e mediações';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Reclamações em Aberto</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="claimsManager.loadClaims()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Reclamação</th>
                                <th>Pedido Relacionado</th>
                                <th>Motivo</th>
                                <th>Fase</th>
                                <th>Risco</th>
                                <th>Data</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="claims-list">
                            <tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Responder -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Responder Reclamação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-claim-id">
                <div class="mb-3">
                    <label class="form-label">Mensagem para o comprador</label>
                    <textarea class="form-control" id="modal-message" rows="4" placeholder="Escreva sua mensagem..."></textarea>
                </div>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i> Mantenha a cordialidade para aumentar as chances de mediação positiva.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="claimsManager.sendReply()">Enviar Resposta</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    const claimsManager = {
        init: function() {
            this.loadClaims();
        },

        loadClaims: async function() {
            try {
                const data = await requestJson('/api/claims/list');
                
                if (data.success) {
                    const claims = Array.isArray(data.claims)
                        ? data.claims
                        : (data.claims && typeof data.claims === 'object' ? Object.values(data.claims) : []);
                    this.render(claims);
                }
            } catch (e) {
                console.error(e);
            }
        },

        render: function(claims) {
            const container = document.getElementById('claims-list');
            if (claims.length === 0) {
                container.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma reclamação em aberto.</td></tr>';
                return;
            }

            let html = '';
            claims.forEach(c => {
                const stageBadge = c.stage === 'mediation' 
                    ? '<span class="badge bg-danger">Mediação</span>' 
                    : '<span class="badge bg-warning text-dark">Disputa</span>';
                    
                const riskColor = c.risk_level === 'high' ? 'text-danger' : (c.risk_level === 'medium' ? 'text-warning' : 'text-success');

                html += `
                    <tr>
                        <td class="ps-4 fw-bold">#${c.id}</td>
                        <td>${c.resource_id}</td>
                        <td>${c.reason} <br> <small class="text-muted">${c.reason_id}</small></td>
                        <td>${stageBadge}</td>
                        <td class="${riskColor} fw-bold text-uppercase small">${c.risk_level}</td>
                        <td>${new Date(c.created_date).toLocaleDateString('pt-BR')}</td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-primary" onclick="claimsManager.openReply('${c.id}')">
                                <i class="bi bi-chat-left-text"></i> Responder
                            </button>
                        </td>
                    </tr>
                `;
            });
            container.innerHTML = html;
        },
        
        openReply: function(id) {
            document.getElementById('modal-claim-id').value = id;
            document.getElementById('modal-message').value = '';
            new bootstrap.Modal(document.getElementById('replyModal')).show();
        },
        
        sendReply: async function() {
            const id = document.getElementById('modal-claim-id').value;
            const message = document.getElementById('modal-message').value;
            
            if (!message) return alert('Digite uma mensagem');
            
            try {
                const result = await requestJson('/api/claims/send-message', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ claim_id: id, message: message })
                });
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('replyModal')).hide();
                    Toast.success('Mensagem enviada com sucesso!');
                } else {
                    Toast.error('Erro ao enviar mensagem.');
                }
            } catch (error) {
                Toast.error('Erro de conexão.');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => claimsManager.init());
</script>
