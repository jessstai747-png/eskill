<!-- 📊 Worker Monitor Modal -->
<div class="modal fade" id="workerMonitorModal" tabindex="-1" aria-labelledby="workerMonitorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="workerMonitorModalLabel">
                    <i class="bi bi-cpu"></i> Monitor de Workers & Jobs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <!-- Loading State -->
                <div id="workerMonitorLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-3 text-muted">Carregando dados do monitor...</p>
                </div>

                <!-- Stats Overview -->
                <div id="workerMonitorContent" style="display: none;">
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-check-circle text-success fs-2"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="text-muted mb-1">Jobs Concluídos</h6>
                                            <h3 class="mb-0" id="statsCompleted">0</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-hourglass-split text-warning fs-2"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="text-muted mb-1">Pendentes</h6>
                                            <h3 class="mb-0" id="statsPending">0</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-arrow-repeat text-primary fs-2"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="text-muted mb-1">Em Execução</h6>
                                            <h3 class="mb-0" id="statsRunning">0</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-x-circle text-danger fs-2"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="text-muted mb-1">Falhados</h6>
                                            <h3 class="mb-0" id="statsFailed">0</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted">Total de Itens Processados</h6>
                                    <h4 id="statsTotalItems">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted">Taxa de Sucesso</h6>
                                    <h4 id="statsSuccessRate">0%</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted">Tempo Médio</h6>
                                    <h4 id="statsAvgDuration">0s</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Running Jobs -->
                    <div id="runningJobsSection" style="display: none;">
                        <h5 class="mb-3">
                            <i class="bi bi-arrow-repeat text-primary"></i> Jobs em Execução
                        </h5>
                        <div id="runningJobsList" class="mb-4"></div>
                    </div>

                    <!-- Recent Jobs Table -->
                    <h5 class="mb-3">
                        <i class="bi bi-clock-history"></i> Jobs Recentes
                    </h5>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Progresso</th>
                                    <th>Sucesso/Falha</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="recentJobsTableBody">
                                <!-- Jobs will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="SEOKiller.refreshWorkerMonitor()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .job-type-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .running-job-card {
        border-left: 4px solid #0d6efd;
        background: #f8f9fa;
    }

    .running-job-card .progress {
        height: 8px;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    // Worker Monitor Functions
    if (!window.SEOKiller) window.SEOKiller = {};

    SEOKiller.openWorkerMonitor = async function() {
        const modal = new bootstrap.Modal(document.getElementById('workerMonitorModal'));
        modal.show();

        await this.loadWorkerMonitorData();
    };

    SEOKiller.loadWorkerMonitorData = async function() {
        const loading = document.getElementById('workerMonitorLoading');
        const content = document.getElementById('workerMonitorContent');

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const data = await requestJson('/api/seo-killer/bulk/monitor');

            if (data.error) {
                throw new Error(data.error);
            }

            // Update stats
            document.getElementById('statsCompleted').textContent = data.stats.completed || 0;
            document.getElementById('statsPending').textContent = data.stats.pending || 0;
            document.getElementById('statsRunning').textContent = data.stats.running || 0;
            document.getElementById('statsFailed').textContent = data.stats.failed || 0;
            document.getElementById('statsTotalItems').textContent = data.stats.total_items_processed || 0;

            const successRate = data.stats.total_items_processed > 0 ?
                Math.round((data.stats.total_successful / data.stats.total_items_processed) * 100) :
                0;
            document.getElementById('statsSuccessRate').textContent = successRate + '%';

            const avgDuration = data.stats.avg_duration_seconds || 0;
            document.getElementById('statsAvgDuration').textContent = avgDuration > 0 ?
                Math.round(avgDuration) + 's' :
                'N/A';

            // Update running jobs
            if (data.running_jobs && data.running_jobs.length > 0) {
                document.getElementById('runningJobsSection').style.display = 'block';
                this.renderRunningJobs(data.running_jobs);
            } else {
                document.getElementById('runningJobsSection').style.display = 'none';
            }

            // Update recent jobs table
            this.renderRecentJobsTable(data.recent_jobs || []);

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('Erro ao carregar dados do monitor:', error);
            loading.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Erro ao carregar dados: ${error.message}
            </div>
        `;
        }
    };

    SEOKiller.renderRunningJobs = function(jobs) {
        const container = document.getElementById('runningJobsList');

        container.innerHTML = jobs.map(job => {
            const progress = job.total_items > 0 ?
                Math.round((job.processed_items / job.total_items) * 100) :
                0;

            const elapsed = job.running_seconds || 0;
            const elapsedMin = Math.floor(elapsed / 60);
            const elapsedSec = elapsed % 60;

            return `
            <div class="card running-job-card mb-2">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-1">Job #${job.id} - ${this.getJobTypeLabel(job.job_type)}</h6>
                            <small class="text-muted">
                                Executando há ${elapsedMin}m ${elapsedSec}s
                            </small>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                                             role="progressbar"
                                             style="width: ${progress}%"
                                             aria-valuenow="${progress}"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <strong>${progress}%</strong>
                                </div>
                            </div>
                            <small class="text-muted">
                                ${job.processed_items || 0} / ${job.total_items} itens
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        }).join('');
    };

    SEOKiller.renderRecentJobsTable = function(jobs) {
        const tbody = document.getElementById('recentJobsTableBody');

        if (jobs.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    Nenhum job encontrado
                </td>
            </tr>
        `;
            return;
        }

        tbody.innerHTML = jobs.map(job => {
            const progress = job.total_items > 0 ?
                Math.round((job.processed_items / job.total_items) * 100) :
                0;

            return `
            <tr>
                <td><strong>#${job.id}</strong></td>
                <td>${this.getJobTypeBadge(job.job_type)}</td>
                <td>${this.getStatusBadge(job.status)}</td>
                <td>
                    ${job.status === 'running' || job.status === 'completed' ? `
                        <div class="progress" style="height: 8px; min-width: 80px;">
                            <div class="progress-bar ${job.status === 'completed' ? 'bg-success' : ''}"
                                 role="progressbar"
                                 style="width: ${progress}%"></div>
                        </div>
                        <small class="text-muted">${job.processed_items}/${job.total_items}</small>
                    ` : `<small class="text-muted">${job.total_items} itens</small>`}
                </td>
                <td>
                    <span class="badge bg-success">${job.successful_items || 0}</span>
                    <span class="badge bg-danger">${job.failed_items || 0}</span>
                </td>
                <td><small>${this.formatDateTime(job.created_at)}</small></td>
                <td>${this.getJobActions(job)}</td>
            </tr>
        `;
        }).join('');
    };

    SEOKiller.getJobTypeLabel = function(type) {
        const labels = {
            'full': 'Otimização Completa',
            'title': 'Apenas Títulos',
            'description': 'Apenas Descrições',
            'attributes': 'Apenas Atributos'
        };
        return labels[type] || type;
    };

    SEOKiller.getJobTypeBadge = function(type) {
        const badges = {
            'full': '<span class="badge bg-primary job-type-badge">Completa</span>',
            'title': '<span class="badge bg-info job-type-badge">Títulos</span>',
            'description': '<span class="badge bg-secondary job-type-badge">Descrições</span>',
            'attributes': '<span class="badge bg-warning job-type-badge">Atributos</span>'
        };
        return badges[type] || `<span class="badge bg-secondary job-type-badge">${type}</span>`;
    };

    SEOKiller.getStatusBadge = function(status) {
        const badges = {
            'pending': '<span class="badge bg-warning">Pendente</span>',
            'running': '<span class="badge bg-primary">Executando</span>',
            'completed': '<span class="badge bg-success">Concluído</span>',
            'failed': '<span class="badge bg-danger">Falhou</span>',
            'cancelled': '<span class="badge bg-secondary">Cancelado</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
    };

    SEOKiller.getJobActions = function(job) {
        let actions = '';

        // View details
        actions += `<button class="btn btn-sm btn-outline-primary me-1" onclick="SEOKiller.viewJobDetails(${job.id})" title="Ver detalhes">
        <i class="bi bi-eye"></i>
    </button>`;

        // Cancel if pending or running
        if (job.status === 'pending' || job.status === 'running') {
            actions += `<button class="btn btn-sm btn-outline-danger me-1" onclick="SEOKiller.cancelJob(${job.id})" title="Cancelar">
            <i class="bi bi-x-circle"></i>
        </button>`;
        }

        // Retry if failed
        if (job.status === 'failed') {
            actions += `<button class="btn btn-sm btn-outline-success" onclick="SEOKiller.retryJob(${job.id})" title="Tentar novamente">
            <i class="bi bi-arrow-clockwise"></i>
        </button>`;
        }

        return actions;
    };

    SEOKiller.viewJobDetails = async function(jobId) {
        try {
            const data = await requestJson(`/api/seo-killer/bulk/status/${jobId}`);

            const detailsHtml = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalhes do Job #${jobId}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                </div>
            </div>
        `;

            // Simple alert for now - can be improved to show a proper modal
            alert('Job #' + jobId + '\n\nStatus: ' + data.status + '\nProgresso: ' + data.progress.percentage + '%');

        } catch (error) {
            alert('Erro ao carregar detalhes: ' + error.message);
        }
    };

    SEOKiller.cancelJob = async function(jobId) {
        if (!confirm('Tem certeza que deseja cancelar este job?')) {
            return;
        }

        try {
            const data = await requestJson(`/api/seo-killer/bulk/cancel/${jobId}`, {
                method: 'POST'
            });

            if (data.error) {
                throw new Error(data.error);
            }

            Toastify({
                text: "Job cancelado com sucesso!",
                duration: 3000,
                style: {
                    background: "linear-gradient(to right, #00b09b, #96c93d)"
                }
            }).showToast();

            await this.refreshWorkerMonitor();

        } catch (error) {
            console.error('Erro ao cancelar job:', error);
            Toastify({
                text: "Erro ao cancelar job: " + error.message,
                duration: 3000,
                style: {
                    background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                }
            }).showToast();
        }
    };

    SEOKiller.retryJob = async function(jobId) {
        if (!confirm('Deseja criar um novo job com os mesmos parâmetros?')) {
            return;
        }

        try {
            const data = await requestJson(`/api/seo-killer/bulk/retry/${jobId}`, {
                method: 'POST'
            });

            if (data.error) {
                throw new Error(data.error);
            }

            Toastify({
                text: `Novo job #${data.job_id} criado com sucesso!`,
                duration: 3000,
                style: {
                    background: "linear-gradient(to right, #00b09b, #96c93d)"
                }
            }).showToast();

            await this.refreshWorkerMonitor();

        } catch (error) {
            console.error('Erro ao reprocessar job:', error);
            Toastify({
                text: "Erro ao reprocessar job: " + error.message,
                duration: 3000,
                style: {
                    background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                }
            }).showToast();
        }
    };

    SEOKiller.refreshWorkerMonitor = async function() {
        await this.loadWorkerMonitorData();
    };

    SEOKiller.formatDateTime = function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };
</script>
