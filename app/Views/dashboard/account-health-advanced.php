<!-- ================================================================
     ADVANCED DIAGNOSTICS PANEL - Account Health
     ================================================================ -->

<div class="card border-0 shadow-sm mb-4" id="advancedDiagnosticsPanel">
    <div class="card-header bg-gradient-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-stars me-2"></i>
                Diagnósticos Avançados
            </h5>
            <button class="btn btn-sm btn-light" data-action="load-advanced-diagnostics">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Atualizar
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Loading State -->
        <div id="advancedLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3 text-muted">Analisando diagnósticos avançados...</p>
        </div>

        <!-- Content -->
        <div id="advancedContent" style="display: none;">
            <!-- Overall Advanced Score -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle fs-3 me-3"></i>
                        <div>
                            <h6 class="mb-1">Score Geral Avançado</h6>
                            <div class="d-flex align-items-center gap-3">
                                <span class="fs-2 fw-bold" id="advancedOverallScore">-</span>
                                <span class="text-muted">/ 100</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Diagnostic Cards -->
            <div class="row g-3">
                <!-- Account Status Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="text-muted mb-1">Status da Conta</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary" id="accountStatusScore">-</span>
                                        <i class="bi bi-check-circle text-success" id="accountStatusIcon"></i>
                                    </div>
                                </div>
                                <i class="bi bi-person-badge fs-3 text-primary"></i>
                            </div>

                            <div id="accountStatusDetails" class="small">
                                <div class="mb-2">
                                    <span class="text-muted">Verificação:</span>
                                    <span id="accountVerified" class="fw-bold">-</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Tipo:</span>
                                    <span id="accountType" class="fw-bold">-</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Loja Oficial:</span>
                                    <span id="officialStore" class="fw-bold">-</span>
                                </div>
                            </div>

                            <div id="accountStatusRecommendations" class="mt-3"></div>

                            <a href="#" class="btn btn-sm btn-outline-primary w-100 mt-3" data-action="show-account-status-details">
                                <i class="bi bi-eye me-1"></i>Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Customer Service Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="text-muted mb-1">Atendimento</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success" id="customerServiceScore">-</span>
                                        <i class="bi bi-chat-dots text-info" id="customerServiceIcon"></i>
                                    </div>
                                </div>
                                <i class="bi bi-headset fs-3 text-success"></i>
                            </div>

                            <div id="customerServiceDetails" class="small">
                                <div class="mb-2">
                                    <span class="text-muted">Perguntas pendentes:</span>
                                    <span id="unansweredCount" class="fw-bold text-danger">-</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Tempo resposta:</span>
                                    <span id="avgResponseTime" class="fw-bold">-</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Taxa 24h:</span>
                                    <span id="responseRate24h" class="fw-bold">-</span>
                                </div>
                            </div>

                            <div id="customerServiceRecommendations" class="mt-3"></div>

                            <a href="/dashboard/questions" class="btn btn-sm btn-outline-success w-100 mt-3">
                                <i class="bi bi-chat-left-text me-1"></i>Ver Perguntas
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Catalog Health Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="text-muted mb-1">Saúde do Catálogo</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-info" id="catalogHealthScore">-</span>
                                        <i class="bi bi-book text-warning" id="catalogHealthIcon"></i>
                                    </div>
                                </div>
                                <i class="bi bi-archive fs-3 text-info"></i>
                            </div>

                            <div id="catalogHealthDetails" class="small">
                                <div class="mb-2">
                                    <span class="text-muted">Taxa catálogo:</span>
                                    <span id="catalogRatio" class="fw-bold">-</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Duplicados:</span>
                                    <span id="duplicatesCount" class="fw-bold">-</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Total items:</span>
                                    <span id="catalogTotalItems" class="fw-bold">-</span>
                                </div>
                            </div>

                            <div id="catalogHealthRecommendations" class="mt-3"></div>

                            <a href="/dashboard/catalog" class="btn btn-sm btn-outline-info w-100 mt-3">
                                <i class="bi bi-box-seam me-1"></i>Ver Catálogo
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Modals Trigger -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-light border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-lightbulb text-warning me-2"></i>
                                <strong>Dica:</strong> Clique em "Ver Detalhes" para análise completa de cada diagnóstico.
                            </div>
                            <span class="badge bg-secondary" id="advancedGeneratedAt">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div id="advancedError" style="display: none;" class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span id="advancedErrorMessage">Erro ao carregar diagnósticos avançados.</span>
            <button class="btn btn-sm btn-outline-danger ms-3" data-action="load-advanced-diagnostics">
                Tentar Novamente
            </button>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    #advancedDiagnosticsPanel .card {
        transition: transform 0.2s ease-in-out;
    }

    #advancedDiagnosticsPanel .card:hover {
        transform: translateY(-5px);
    }

    .recommendation-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    // ================================================================
    // ADVANCED DIAGNOSTICS JAVASCRIPT
    // ================================================================

    let advancedDiagnosticsData = null;

    async function loadAdvancedDiagnostics() {
        const loading = document.getElementById('advancedLoading');
        const content = document.getElementById('advancedContent');
        const error = document.getElementById('advancedError');

        // Show loading
        loading.style.display = 'block';
        content.style.display = 'none';
        error.style.display = 'none';

        try {
            const result = await requestJson('/api/account-health/advanced/complete');

            if (!result.success) {
                throw new Error(result.error || 'Erro ao carregar diagnósticos');
            }

            advancedDiagnosticsData = result.data;
            renderAdvancedDiagnostics(result.data);

            // Hide loading, show content
            loading.style.display = 'none';
            content.style.display = 'block';
        } catch (err) {
            console.error('Advanced diagnostics error:', err);
            document.getElementById('advancedErrorMessage').textContent = err.message;
            loading.style.display = 'none';
            error.style.display = 'block';
        }
    }

    function renderAdvancedDiagnostics(data) {
        // Overall Score
        document.getElementById('advancedOverallScore').textContent = data.overall_score || 0;

        // Account Status
        const accountStatus = data.diagnostics.account_status || {};
        document.getElementById('accountStatusScore').textContent = accountStatus.score || 0;
        document.getElementById('accountVerified').textContent = accountStatus.verification?.is_verified ? 'Sim ✓' : 'Não ✗';
        document.getElementById('accountType').textContent = accountStatus.account_type || 'N/A';
        document.getElementById('officialStore').textContent = accountStatus.official_store ? 'Sim ⭐' : 'Não';

        renderRecommendations('accountStatusRecommendations', accountStatus.recommendations || []);

        // Customer Service
        const customerService = data.diagnostics.customer_service || {};
        document.getElementById('customerServiceScore').textContent = customerService.score || 0;
        document.getElementById('unansweredCount').textContent = customerService.unanswered_count || 0;
        document.getElementById('avgResponseTime').textContent = (customerService.average_response_time_hours || 0) + 'h';
        document.getElementById('responseRate24h').textContent = (customerService.response_rate_24h || 0) + '%';

        renderRecommendations('customerServiceRecommendations', customerService.recommendations || []);

        // Catalog Health
        const catalogHealth = data.diagnostics.catalog_health || {};
        document.getElementById('catalogHealthScore').textContent = catalogHealth.score || 0;
        document.getElementById('catalogRatio').textContent = (catalogHealth.catalog_ratio || 0) + '%';
        document.getElementById('duplicatesCount').textContent = catalogHealth.duplicates_detected || 0;
        document.getElementById('catalogTotalItems').textContent = catalogHealth.total_items || 0;

        renderRecommendations('catalogHealthRecommendations', catalogHealth.recommendations || []);

        // Generated at
        document.getElementById('advancedGeneratedAt').textContent =
            'Gerado em: ' + (data.generated_at ? new Date(data.generated_at).toLocaleString('pt-BR') : '-');
    }

    function renderRecommendations(containerId, recommendations) {
        const container = document.getElementById(containerId);
        if (!recommendations || recommendations.length === 0) {
            container.innerHTML = '<small class="text-success"><i class="bi bi-check-circle me-1"></i>Tudo OK!</small>';
            return;
        }

        const topRec = recommendations[0]; // Show only first
        const priorityClass = {
            'critical': 'danger',
            'high': 'warning',
            'medium': 'info',
            'low': 'secondary'
        } [topRec.priority] || 'secondary';

        container.innerHTML = `
        <div class="alert alert-${priorityClass} recommendation-badge mb-0" role="alert">
            <small><strong>${topRec.action}</strong></small>
            ${recommendations.length > 1 ? `<br><small class="text-muted">+${recommendations.length - 1} mais</small>` : ''}
        </div>
    `;
    }

    function showAccountStatusDetails() {
        if (!advancedDiagnosticsData) return;

        const data = advancedDiagnosticsData.diagnostics.account_status;

        alert(`Detalhes do Status da Conta:\n\n` +
            `Score: ${data.score}/100\n` +
            `Verificado: ${data.verification?.is_verified ? 'Sim' : 'Não'}\n` +
            `Email confirmado: ${data.verification?.confirmed_email ? 'Sim' : 'Não'}\n` +
            `Tipo: ${data.account_type}\n` +
            `Loja Oficial: ${data.official_store ? 'Sim' : 'Não'}\n` +
            `Site: ${data.site}\n` +
            `\nRecursos disponíveis:\n` +
            `- Mercado Envios: ${data.features?.mercado_envios ? 'Sim' : 'Não'}\n` +
            `- Mercado Pago: ${data.features?.mercado_pago ? 'Sim' : 'Não'}\n` +
            `- Catálogo: ${data.features?.catalog_enabled ? 'Sim' : 'Não'}\n` +
            `- FULL: ${data.features?.full_eligible ? 'Sim' : 'Não'}`
        );
    }

    // Auto-load on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Delay to not interfere with main diagnostic
        setTimeout(() => {
            loadAdvancedDiagnostics();
        }, 2000);
    });

    // Event delegation (CSP-safe)
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        if (!['load-advanced-diagnostics', 'show-account-status-details'].includes(action)) {
            return;
        }

        e.preventDefault();

        if (action === 'load-advanced-diagnostics') {
            loadAdvancedDiagnostics();
            return;
        }

        if (action === 'show-account-status-details') {
            showAccountStatusDetails();
        }
    });
</script>
