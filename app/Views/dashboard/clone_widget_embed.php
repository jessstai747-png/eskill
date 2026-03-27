<?php

declare(strict_types=1);

/**
 * Widget de Progresso Embeddable - Gerador de Código
 * 
 * Permite gerar código de embed para incorporar o widget de progresso
 * em qualquer página HTML externa ou interna.
 */

// Widget embed generator view
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1">
                        <i class="fas fa-code me-2 text-primary"></i>Widget de Progresso Embeddable
                    </h3>
                    <p class="text-muted mb-0">
                        Gere código para incorporar o widget de progresso em qualquer página
                    </p>
                </div>
                <a href="/dashboard/catalog/clone" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Configurador -->
        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cog me-2"></i>Configuração do Widget
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Job Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-briefcase me-2"></i>Job ID
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="widgetJobId" 
                                   placeholder="Digite o ID do job" min="1">
                            <button class="btn btn-outline-secondary" type="button" id="btnSelectJob">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            ID do job de clonagem que será monitorado
                        </div>
                    </div>

                    <!-- Theme -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-palette me-2"></i>Tema
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="widgetTheme" id="themeLight" value="light" checked>
                            <label class="btn btn-outline-primary" for="themeLight">
                                <i class="fas fa-sun me-1"></i>Claro
                            </label>
                            
                            <input type="radio" class="btn-check" name="widgetTheme" id="themeDark" value="dark">
                            <label class="btn btn-outline-primary" for="themeDark">
                                <i class="fas fa-moon me-1"></i>Escuro
                            </label>
                            
                            <input type="radio" class="btn-check" name="widgetTheme" id="themeAuto" value="auto">
                            <label class="btn btn-outline-primary" for="themeAuto">
                                <i class="fas fa-adjust me-1"></i>Auto
                            </label>
                        </div>
                    </div>

                    <!-- Display Options -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-eye me-2"></i>Opções de Exibição
                        </label>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="optShowDetails" checked>
                                    <label class="form-check-label" for="optShowDetails">
                                        Mostrar estatísticas
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="optShowItems" checked>
                                    <label class="form-check-label" for="optShowItems">
                                        Mostrar itens recentes
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="optShowLogs">
                                    <label class="form-check-label" for="optShowLogs">
                                        Mostrar logs
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="optCompact">
                                    <label class="form-check-label" for="optCompact">
                                        Modo compacto
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refresh Interval -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sync-alt me-2"></i>Intervalo de Atualização
                        </label>
                        <div class="row align-items-center">
                            <div class="col-8">
                                <input type="range" class="form-range" id="widgetRefreshInterval" 
                                       min="1000" max="10000" step="500" value="2000">
                            </div>
                            <div class="col-4">
                                <span id="refreshIntervalValue">2s</span>
                            </div>
                        </div>
                    </div>

                    <!-- Container Size -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-expand me-2"></i>Largura do Container
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="widgetWidth" 
                                   value="350" min="280" max="600">
                            <span class="input-group-text">px</span>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100" id="btnGenerateCode">
                        <i class="fas fa-code me-2"></i>Gerar Código de Embed
                    </button>
                </div>
            </div>
        </div>

        <!-- Preview e Código -->
        <div class="col-lg-6">
            <!-- Preview -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-desktop me-2"></i>Preview
                    </h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center" 
                     style="min-height: 300px; background: #f8f9fa;">
                    <div id="widgetPreviewContainer"></div>
                </div>
            </div>

            <!-- Código Gerado -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-code me-2"></i>Código para Embed
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" id="btnCopyCode">
                        <i class="fas fa-copy me-2"></i>Copiar
                    </button>
                </div>
                <div class="card-body p-0">
                    <pre class="m-0 p-3" style="background: #1e1e1e; color: #d4d4d4; max-height: 400px; overflow: auto;"><code id="embedCodeOutput">&lt;!-- Selecione um Job ID e clique em "Gerar Código" --&gt;</code></pre>
                </div>
            </div>

            <!-- Documentação -->
            <div class="card shadow-sm">
                <div class="card-header" data-bs-toggle="collapse" data-bs-target="#docsCollapse" 
                     style="cursor: pointer;">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-book me-2"></i>Documentação
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                </div>
                <div class="collapse" id="docsCollapse">
                    <div class="card-body">
                        <h6>Opções Disponíveis</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Opção</th>
                                    <th>Tipo</th>
                                    <th>Padrão</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>jobId</code></td>
                                    <td>number</td>
                                    <td>null</td>
                                    <td>ID do job (obrigatório)</td>
                                </tr>
                                <tr>
                                    <td><code>theme</code></td>
                                    <td>string</td>
                                    <td>'light'</td>
                                    <td>'light', 'dark' ou 'auto'</td>
                                </tr>
                                <tr>
                                    <td><code>showDetails</code></td>
                                    <td>boolean</td>
                                    <td>true</td>
                                    <td>Exibir estatísticas</td>
                                </tr>
                                <tr>
                                    <td><code>showItems</code></td>
                                    <td>boolean</td>
                                    <td>true</td>
                                    <td>Exibir lista de itens</td>
                                </tr>
                                <tr>
                                    <td><code>showLogs</code></td>
                                    <td>boolean</td>
                                    <td>false</td>
                                    <td>Exibir log de atividades</td>
                                </tr>
                                <tr>
                                    <td><code>compact</code></td>
                                    <td>boolean</td>
                                    <td>false</td>
                                    <td>Modo compacto</td>
                                </tr>
                                <tr>
                                    <td><code>refreshInterval</code></td>
                                    <td>number</td>
                                    <td>2000</td>
                                    <td>Intervalo em ms</td>
                                </tr>
                            </tbody>
                        </table>

                        <h6 class="mt-4">Eventos (Callbacks)</h6>
                        <pre class="bg-light p-2 rounded"><code>CloneProgressWidget.init('#container', {
    jobId: 123,
    onProgress: (data) => console.log('Progresso:', data),
    onComplete: (data) => console.log('Concluído!'),
    onError: (error) => console.error('Erro:', error)
});</code></pre>

                        <h6 class="mt-4">Métodos da Instância</h6>
                        <pre class="bg-light p-2 rounded"><code>const widget = CloneProgressWidget.init('#container', {...});

// Atualizar manualmente
widget.refresh();

// Obter status atual
const status = widget.getStatus();

// Destruir widget
widget.destroy();</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Seleção de Job -->
<div class="modal fade" id="selectJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-briefcase me-2"></i>Selecionar Job
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchJobInput" 
                           placeholder="Buscar por nome ou ID...">
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover table-sm" id="jobsTable">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Progresso</th>
                                <th>Data</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="jobsTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Carregando...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?= rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], '/') ?>';
    
    // Elementos
    const jobIdInput = document.getElementById('widgetJobId');
    const btnSelectJob = document.getElementById('btnSelectJob');
    const btnGenerateCode = document.getElementById('btnGenerateCode');
    const btnCopyCode = document.getElementById('btnCopyCode');
    const embedCodeOutput = document.getElementById('embedCodeOutput');
    const previewContainer = document.getElementById('widgetPreviewContainer');
    const refreshIntervalSlider = document.getElementById('widgetRefreshInterval');
    const refreshIntervalValue = document.getElementById('refreshIntervalValue');
    
    // Atualizar label do intervalo
    refreshIntervalSlider.addEventListener('input', function() {
        const seconds = (this.value / 1000).toFixed(1);
        refreshIntervalValue.textContent = seconds + 's';
    });
    
    // Modal de seleção de job
    btnSelectJob.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('selectJobModal'));
        modal.show();
        loadJobs();
    });
    
    // Carregar jobs
    async function loadJobs() {
        const tbody = document.getElementById('jobsTableBody');
        
        try {
            const result = await requestJson('/api/clone/jobs?limit=50');
            
            if (!result.success || !result.data?.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox me-2"></i>Nenhum job encontrado
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = result.data.map(job => `
                <tr>
                    <td><code>${job.id}</code></td>
                    <td>${escapeHtml(job.name || 'Sem nome')}</td>
                    <td>
                        <span class="badge bg-${getStatusColor(job.status)}">
                            ${job.status}
                        </span>
                    </td>
                    <td>
                        <div class="progress" style="height: 6px; width: 80px;">
                            <div class="progress-bar" style="width: ${job.progress || 0}%"></div>
                        </div>
                    </td>
                    <td><small>${formatDate(job.created_at)}</small></td>
                    <td>
                        <button class="btn btn-sm btn-primary btn-select-job" data-job-id="${job.id}">
                            <i class="fas fa-check"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            
            // Adicionar listeners
            tbody.querySelectorAll('.btn-select-job').forEach(btn => {
                btn.addEventListener('click', function() {
                    jobIdInput.value = this.dataset.jobId;
                    bootstrap.Modal.getInstance(document.getElementById('selectJobModal')).hide();
                    generateCode();
                });
            });
            
        } catch (error) {
            console.error('Error loading jobs:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Erro ao carregar jobs
                    </td>
                </tr>
            `;
        }
    }
    
    // Busca de jobs
    document.getElementById('searchJobInput').addEventListener('input', debounce(function() {
        const search = this.value.toLowerCase();
        const rows = document.querySelectorAll('#jobsTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    }, 300));
    
    // Gerar código
    btnGenerateCode.addEventListener('click', generateCode);
    
    function generateCode() {
        const jobId = parseInt(jobIdInput.value);
        
        if (!jobId) {
            alert('Por favor, informe o ID do job');
            jobIdInput.focus();
            return;
        }
        
        const config = {
            jobId: jobId,
            theme: document.querySelector('input[name="widgetTheme"]:checked')?.value || 'light',
            showDetails: document.getElementById('optShowDetails').checked,
            showItems: document.getElementById('optShowItems').checked,
            showLogs: document.getElementById('optShowLogs').checked,
            compact: document.getElementById('optCompact').checked,
            refreshInterval: parseInt(refreshIntervalSlider.value)
        };
        
        const width = parseInt(document.getElementById('widgetWidth').value) || 350;
        
        // Gerar código HTML
        const code = `<!-- Clone Progress Widget - Job #${config.jobId} -->
<div id="clone-progress-${config.jobId}" style="width: ${width}px;"></div>

<script src="${baseUrl}/js/clone-progress-widget.js"><\/script>
<script nonce="<?= CSP_NONCE ?>">
(function() {
    CloneProgressWidget.init('#clone-progress-${config.jobId}', ${JSON.stringify(config, null, 4)});
})();
<\/script>`;
        
        embedCodeOutput.textContent = code;
        
        // Atualizar preview
        updatePreview(config, width);
    }
    
    // Atualizar preview
    function updatePreview(config, width) {
        previewContainer.style.width = width + 'px';
        previewContainer.innerHTML = '';
        
        // Preview estático simulado
        const theme = config.theme === 'dark' ? 'cpw-dark' : '';
        const compact = config.compact ? 'cpw-compact' : '';
        
        previewContainer.innerHTML = `
            <div class="clone-progress-widget ${theme} ${compact}" style="width: 100%;">
                <style>
                    .clone-progress-widget {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        background: ${config.theme === 'dark' ? '#1e1e1e' : '#fff'};
                        border: 1px solid ${config.theme === 'dark' ? '#333' : '#e0e0e0'};
                        border-radius: 12px;
                        padding: 16px;
                        color: ${config.theme === 'dark' ? '#e0e0e0' : '#333'};
                    }
                    .cpw-compact { padding: 12px; }
                    .cpw-header { display: flex; justify-content: space-between; margin-bottom: 12px; }
                    .cpw-title { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; }
                    .cpw-status { display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 4px 10px; border-radius: 20px; background: ${config.theme === 'dark' ? '#333' : '#f0f0f0'}; }
                    .cpw-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #17a2b8; animation: pulse 0.5s infinite; }
                    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
                    .cpw-progress-bar { height: 8px; background: ${config.theme === 'dark' ? '#333' : '#e9ecef'}; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
                    .cpw-progress-fill { height: 100%; background: linear-gradient(90deg, #007bff, #00d4ff); border-radius: 4px; width: 67%; }
                    .cpw-progress-text { display: flex; justify-content: space-between; font-size: 12px; color: ${config.theme === 'dark' ? '#aaa' : '#666'}; }
                    .cpw-stats { display: grid; grid-template-columns: repeat(${config.compact ? 2 : 4}, 1fr); gap: 8px; margin: 12px 0; padding: 10px; background: ${config.theme === 'dark' ? '#2a2a2a' : '#f8f9fa'}; border-radius: 8px; }
                    .cpw-stat { text-align: center; }
                    .cpw-stat-value { display: block; font-size: ${config.compact ? '14px' : '18px'}; font-weight: 700; }
                    .cpw-stat-label { font-size: 10px; color: ${config.theme === 'dark' ? '#888' : '#666'}; text-transform: uppercase; }
                    .cpw-footer { display: flex; justify-content: space-between; font-size: 10px; color: #999; padding-top: 8px; border-top: 1px solid ${config.theme === 'dark' ? '#333' : '#e9ecef'}; }
                    .cpw-view-full { color: #007bff; text-decoration: none; }
                </style>
                
                <div class="cpw-header">
                    <div class="cpw-title">
                        <span>📦</span>
                        <span>Job #${config.jobId}</span>
                    </div>
                    <div class="cpw-status">
                        <span class="cpw-status-dot"></span>
                        <span>Processando</span>
                    </div>
                </div>

                <div class="cpw-progress-container">
                    <div class="cpw-progress-bar">
                        <div class="cpw-progress-fill"></div>
                    </div>
                    <div class="cpw-progress-text">
                        <span style="color: #007bff; font-weight: 600;">67%</span>
                        <span>67 / 100 itens</span>
                    </div>
                </div>

                ${config.showDetails ? `
                <div class="cpw-stats">
                    <div class="cpw-stat">
                        <span class="cpw-stat-value" style="color: #28a745;">62</span>
                        <span class="cpw-stat-label">Sucesso</span>
                    </div>
                    <div class="cpw-stat">
                        <span class="cpw-stat-value" style="color: #dc3545;">5</span>
                        <span class="cpw-stat-label">Erros</span>
                    </div>
                    <div class="cpw-stat">
                        <span class="cpw-stat-value" style="color: #ffc107;">33</span>
                        <span class="cpw-stat-label">Pendente</span>
                    </div>
                    <div class="cpw-stat">
                        <span class="cpw-stat-value" style="color: #17a2b8;">3:24</span>
                        <span class="cpw-stat-label">Tempo</span>
                    </div>
                </div>
                ` : ''}

                ${config.showItems ? `
                <div style="border-top: 1px solid ${config.theme === 'dark' ? '#333' : '#e9ecef'}; padding-top: 12px; margin-bottom: 12px;">
                    <div style="font-size: 12px; font-weight: 600; color: ${config.theme === 'dark' ? '#aaa' : '#666'}; margin-bottom: 8px;">Itens Recentes</div>
                    <div style="font-size: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0; border-bottom: 1px solid ${config.theme === 'dark' ? '#333' : '#f0f0f0'};">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #28a745;"></span>
                            <span style="font-family: monospace; color: ${config.theme === 'dark' ? '#888' : '#666'};">MLB1...234</span>
                            <span>Smartphone Samsung Galaxy A54</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #17a2b8; animation: pulse 0.5s infinite;"></span>
                            <span style="font-family: monospace; color: ${config.theme === 'dark' ? '#888' : '#666'};">MLB9...567</span>
                            <span>Fone de Ouvido Bluetooth</span>
                        </div>
                    </div>
                </div>
                ` : ''}

                <div class="cpw-footer">
                    <span>Última atualização: ${new Date().toLocaleTimeString('pt-BR')}</span>
                    <a href="#" class="cpw-view-full">Ver Dashboard Completo →</a>
                </div>
            </div>
        `;
    }
    
    // Copiar código
    btnCopyCode.addEventListener('click', async function() {
        const code = embedCodeOutput.textContent;
        
        try {
            await navigator.clipboard.writeText(code);
            
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check me-2"></i>Copiado!';
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-success');
            
            setTimeout(() => {
                this.innerHTML = originalHtml;
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-primary');
            }, 2000);
            
        } catch (err) {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = code;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            alert('Código copiado!');
        }
    });
    
    // Atualizar preview quando opções mudam
    document.querySelectorAll('input[name="widgetTheme"], .form-check-input, #widgetWidth, #widgetRefreshInterval').forEach(el => {
        el.addEventListener('change', function() {
            if (jobIdInput.value) {
                generateCode();
            }
        });
    });
    
    // Helpers
    function getStatusColor(status) {
        const colors = {
            'pending': 'secondary',
            'processing': 'primary',
            'completed': 'success',
            'failed': 'danger',
            'paused': 'warning'
        };
        return colors[status] || 'secondary';
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('pt-BR');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
});
</script>
