<?php

declare(strict_types=1);

$pageTitle = 'Histórico de Atividades';
$activePage = 'activities';
?>

<div class="container-fluid px-0 px-md-4 py-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row gap-3">
                        <div class="flex-grow-1">
                            <h4 class="mb-1"><i class="bi bi-clock-history"></i> Histórico de Atividades</h4>
                            <p class="text-muted mb-0">Acompanhe logins, sincronizações e eventos críticos em tempo real.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="export-activities">
                                <i class="bi bi-download"></i> Exportar CSV
                            </button>
                            <button type="button" class="btn btn-primary" id="refresh-activities">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar agora
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card filters-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros rápidos</h5>
                </div>
                <div class="card-body">
                    <form id="filter-form" class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label for="date_from" class="form-label">Data inicial</label>
                            <input type="date" class="form-control" id="date_from" name="date_from">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="date_to" class="form-label">Data final</label>
                            <input type="date" class="form-control" id="date_to" name="date_to">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="action" class="form-label">Tipo de ação</label>
                            <select class="form-select" id="action" name="action">
                                <option value="">Todas</option>
                                <option value="user.login">Login</option>
                                <option value="user.logout">Logout</option>
                                <option value="user.registered">Registro</option>
                                <option value="account.linked">Conta vinculada</option>
                                <option value="order.synced">Pedidos sincronizados</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 align-self-end">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-search"></i> Aplicar
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clear-filters" title="Limpar filtros">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                        <small class="text-muted">Última atualização: <span id="last-update">-</span></small>
                        <div class="form-check form-switch ms-lg-auto">
                            <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
                            <label class="form-check-label" for="auto-refresh">Atualizar a cada 30s</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row gap-2 align-items-md-center">
                    <div>
                        <h5 class="mb-0">Linha do tempo</h5>
                        <small class="text-muted">Mostrando as últimas 100 atividades</small>
                    </div>
                    <div class="ms-md-auto badge bg-light text-dark" id="count-badge">0 registros</div>
                </div>
                <div class="card-body">
                    <div id="activities-list" class="activities-placeholder">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .filters-card {
        border: 1px solid var(--bs-border-color-translucent);
    }

    .activities-placeholder {
        min-height: 320px;
    }

    .activity-item {
        border-left: 3px solid var(--bs-primary);
        padding-left: 1rem;
        margin-bottom: 1.5rem;
    }

    .activity-item:last-child {
        margin-bottom: 0;
    }

    .activity-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(var(--bs-primary-rgb), 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .activity-meta {
        color: var(--bs-secondary-color);
        font-size: 0.9rem;
    }

    @media (max-width: 575.98px) {
        .activity-item {
            border-left: 0;
            border-top: 3px solid var(--bs-primary);
            padding-left: 0;
            padding-top: 1rem;
        }
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    (() => {
        const form = document.getElementById('filter-form');
        const clearBtn = document.getElementById('clear-filters');
        const autoRefreshToggle = document.getElementById('auto-refresh');
        const refreshBtn = document.getElementById('refresh-activities');
        const exportBtn = document.getElementById('export-activities');
        const listEl = document.getElementById('activities-list');
        const lastUpdateEl = document.getElementById('last-update');
        const countBadge = document.getElementById('count-badge');

        const state = {
            timer: null,
            interval: 30000,
        };

        const iconMap = {
            'user.login': 'bi-box-arrow-in-right text-success',
            'user.logout': 'bi-box-arrow-right text-warning',
            'user.registered': 'bi-person-plus text-primary',
            'account.linked': 'bi-link-45deg text-info',
            'order.synced': 'bi-arrow-clockwise text-secondary',
        };

        const formatDateTime = value => {
            try {
                return new Date(value).toLocaleString('pt-BR');
            } catch (_) {
                return value;
            }
        };

        const setPlaceholder = inner => {
            listEl.innerHTML = `<div class="text-center py-4">${inner}</div>`;
        };

        const buildEndpoint = () => {
            const params = new URLSearchParams(form ? new FormData(form) : undefined);
            params.set('limit', '100');
            return `/api/activities?${params.toString()}`;
        };

        const renderActivities = activities => {
            if (!Array.isArray(activities) || activities.length === 0) {
                setPlaceholder('<p class="text-muted">Nenhuma atividade encontrada.</p>');
                countBadge.textContent = '0 registros';
                return;
            }

            countBadge.textContent = `${activities.length} registro${activities.length > 1 ? 's' : ''}`;

            const fragments = activities.map(activity => {
                const icon = iconMap[activity.action] || 'bi-circle text-primary';
                const date = formatDateTime(activity.created_at);
                const description = activity.description || activity.action;
                const ip = activity.ip_address ? `<span class="badge bg-light text-dark">${activity.ip_address}</span>` : '';

                return `
                <div class="activity-item">
                    <div class="d-flex align-items-start gap-3">
                        <div class="activity-icon">
                            <i class="bi ${icon}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <h6 class="mb-1">${description}</h6>
                                ${ip}
                            </div>
                            <div class="activity-meta">${date}</div>
                        </div>
                    </div>
                </div>
            `;
            });

            listEl.innerHTML = fragments.join('');
        };

        const loadActivities = () => {
            setPlaceholder('<div class="spinner-border text-primary" role="status"></div>');

            fetch(buildEndpoint())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Falha ao buscar atividades');
                    }
                    return response.json();
                })
                .then(data => {
                    renderActivities(data.activities || []);
                    lastUpdateEl.textContent = formatDateTime(new Date());
                })
                .catch(error => {
                    console.error(error);
                    setPlaceholder('<div class="alert alert-danger">Erro ao carregar atividades.</div>');
                });
        };

        const scheduleAutoRefresh = () => {
            clearInterval(state.timer);
            if (!autoRefreshToggle.checked) {
                return;
            }
            state.timer = setInterval(loadActivities, state.interval);
        };

        form.addEventListener('submit', event => {
            event.preventDefault();
            loadActivities();
        });

        clearBtn.addEventListener('click', () => {
            form.reset();
            loadActivities();
        });

        refreshBtn.addEventListener('click', () => {
            loadActivities();
        });

        autoRefreshToggle.addEventListener('change', scheduleAutoRefresh);

        exportBtn.addEventListener('click', () => {
            const params = new URLSearchParams(new FormData(form));
            window.location.href = `/api/activities/export?${params.toString()}`;
        });

        loadActivities();
        scheduleAutoRefresh();
    })();
</script>