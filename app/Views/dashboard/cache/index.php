<?php
use App\Helpers\SecurityHelper;

$pageTitle = 'Gerenciamento de Cache';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SecurityHelper::e($pageTitle) ?> - ML Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.primary { border-left-color: #0d6efd; }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #0dcaf0; }
        .stat-card.danger { border-left-color: #dc3545; }
        
        .cache-item {
            border-left: 3px solid #dee2e6;
            transition: all 0.2s;
        }
        .cache-item:hover {
            background-color: #f8f9fa;
            border-left-color: #0d6efd;
        }
        .cache-item.expired {
            opacity: 0.6;
            border-left-color: #dc3545;
        }
        
        .tag-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1><i class="bi bi-hdd-stack"></i> <?= SecurityHelper::e($pageTitle) ?></h1>
                <p class="text-muted">Monitore e gerencie o cache do sistema</p>
            </div>
            <div class="col-auto">
                <button class="btn btn-warning" onclick="clearExpiredCache()">
                    <i class="bi bi-clock-history"></i> Limpar Expirados
                </button>
                <button class="btn btn-danger" onclick="clearAllCache()">
                    <i class="bi bi-trash"></i> Limpar Tudo
                </button>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4" id="stats-container">
            <div class="col-md-3">
                <div class="card stat-card primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Total de Itens</p>
                                <h3 class="mb-0" id="stat-total">-</h3>
                            </div>
                            <i class="bi bi-file-earmark-fill fs-1 text-primary opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Cache Hits</p>
                                <h3 class="mb-0" id="stat-hits">-</h3>
                            </div>
                            <i class="bi bi-check-circle-fill fs-1 text-success opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Cache Misses</p>
                                <h3 class="mb-0" id="stat-misses">-</h3>
                            </div>
                            <i class="bi bi-x-circle-fill fs-1 text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Hit Rate</p>
                                <h3 class="mb-0" id="stat-hit-rate">-</h3>
                            </div>
                            <i class="bi bi-percent fs-1 text-info opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros e Busca -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="search-key" 
                               placeholder="Buscar por chave..." onkeyup="filterCacheItems()">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter-status" onchange="filterCacheItems()">
                            <option value="all">Todos</option>
                            <option value="active">Ativos</option>
                            <option value="expired">Expirados</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="loadCacheItems()">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Cache -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Itens em Cache</h5>
            </div>
            <div class="card-body">
                <div class="loading" id="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2">Carregando cache...</p>
                </div>
                
                <div id="cache-items-container"></div>
                
                <div class="empty-state" id="empty-state" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-3">Nenhum item em cache</p>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <nav class="mt-4" id="pagination-container" style="display: none;">
            <ul class="pagination justify-content-center" id="pagination"></ul>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

        let cacheItems = [];
        let filteredItems = [];
        let currentPage = 1;
        const itemsPerPage = 20;

        // Carregar estatísticas
        async function loadStatistics() {
            try {
                const data = await requestJson('/api/cache/statistics');
                
                document.getElementById('stat-total').textContent = data.total_items || 0;
                document.getElementById('stat-hits').textContent = (data.hits || 0).toLocaleString();
                document.getElementById('stat-misses').textContent = (data.misses || 0).toLocaleString();
                document.getElementById('stat-hit-rate').textContent = (data.hit_rate || 0).toFixed(1) + '%';
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
            }
        }

        // Carregar itens do cache
        async function loadCacheItems() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('cache-items-container').innerHTML = '';
            
            try {
                const data = await requestJson('/api/cache/list');
                
                cacheItems = data.items || [];
                filterCacheItems();
                loadStatistics();
            } catch (error) {
                console.error('Erro ao carregar cache:', error);
                alert('Erro ao carregar itens do cache');
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }

        // Filtrar itens
        function filterCacheItems() {
            const searchTerm = document.getElementById('search-key').value.toLowerCase();
            const statusFilter = document.getElementById('filter-status').value;
            
            filteredItems = cacheItems.filter(item => {
                const matchSearch = item.key.toLowerCase().includes(searchTerm);
                const matchStatus = statusFilter === 'all' || 
                    (statusFilter === 'expired' && item.is_expired) ||
                    (statusFilter === 'active' && !item.is_expired);
                
                return matchSearch && matchStatus;
            });
            
            currentPage = 1;
            renderCacheItems();
        }

        // Renderizar itens
        function renderCacheItems() {
            const container = document.getElementById('cache-items-container');
            const emptyState = document.getElementById('empty-state');
            
            if (filteredItems.length === 0) {
                container.innerHTML = '';
                emptyState.style.display = 'block';
                document.getElementById('pagination-container').style.display = 'none';
                return;
            }
            
            emptyState.style.display = 'none';
            
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageItems = filteredItems.slice(start, end);
            
            let html = '<div class="list-group">';
            
            pageItems.forEach(item => {
                const expiredClass = item.is_expired ? 'expired' : '';
                const expiresText = item.expires_at 
                    ? new Date(item.expires_at * 1000).toLocaleString('pt-BR')
                    : 'Sem expiração';
                
                html += `
                    <div class="list-group-item cache-item ${expiredClass}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <strong class="me-2">${escapeHtml(item.key)}</strong>
                                    ${item.is_expired ? '<span class="badge bg-danger">Expirado</span>' : ''}
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-clock"></i> Modificado: ${item.modified}
                                    ${item.expires_at ? ` | <i class="bi bi-hourglass"></i> Expira: ${expiresText}` : ''}
                                    | <i class="bi bi-file-earmark"></i> Tamanho: ${item.size_formatted}
                                </div>
                                ${item.tags && item.tags.length > 0 ? `
                                    <div class="mt-2">
                                        ${item.tags.map(tag => 
                                            `<span class="badge bg-secondary tag-badge">${escapeHtml(tag)}</span>`
                                        ).join(' ')}
                                    </div>
                                ` : ''}
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary action-btn me-1" 
                                        onclick="viewCacheItem('${escapeHtml(item.key)}')"
                                        title="Ver conteúdo">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger action-btn" 
                                        onclick="deleteCacheItem('${escapeHtml(item.key)}')"
                                        title="Remover">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
            
            renderPagination();
        }

        // Renderizar paginação
        function renderPagination() {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
            
            if (totalPages <= 1) {
                document.getElementById('pagination-container').style.display = 'none';
                return;
            }
            
            document.getElementById('pagination-container').style.display = 'block';
            
            let html = '';
            
            // Anterior
            html += `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Anterior</a>
                </li>
            `;
            
            // Páginas
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                        </li>
                    `;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Próxima
            html += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Próxima</a>
                </li>
            `;
            
            document.getElementById('pagination').innerHTML = html;
        }

        // Mudar página
        function changePage(page) {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderCacheItems();
        }

        // Ver conteúdo do cache
        async function viewCacheItem(key) {
            try {
                const data = await requestJson(`/api/cache/get?key=${encodeURIComponent(key)}`);
                
                alert(`Chave: ${key}\n\nValor:\n${JSON.stringify(data.value, null, 2)}`);
            } catch (error) {
                console.error('Erro ao buscar cache:', error);
                alert('Erro ao buscar item do cache');
            }
        }

        // Deletar item do cache
        async function deleteCacheItem(key) {
            if (!confirm(`Deseja remover o cache "${key}"?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('key', key);
                
                const data = await requestJson('/api/cache/delete', {
                    method: 'POST',
                    body: formData
                });
                
                if (data.success) {
                    alert('Cache removido com sucesso!');
                    loadCacheItems();
                } else {
                    alert('Erro ao remover cache');
                }
            } catch (error) {
                console.error('Erro ao deletar cache:', error);
                alert('Erro ao remover cache');
            }
        }

        // Limpar cache expirado
        async function clearExpiredCache() {
            if (!confirm('Deseja remover todos os caches expirados?')) return;
            
            try {
                const data = await requestJson('/api/cache/clear-expired', {
                    method: 'POST'
                });
                
                if (data.success) {
                    alert(`${data.removed} itens removidos com sucesso!`);
                    loadCacheItems();
                } else {
                    alert('Erro ao limpar cache');
                }
            } catch (error) {
                console.error('Erro ao limpar cache:', error);
                alert('Erro ao limpar cache expirado');
            }
        }

        // Limpar todo o cache
        async function clearAllCache() {
            if (!confirm('⚠️ ATENÇÃO: Deseja remover TODO o cache?\n\nEsta ação não pode ser desfeita!')) return;
            
            try {
                const data = await requestJson('/api/cache/clear', {
                    method: 'POST'
                });
                
                if (data.success) {
                    alert('Todo o cache foi removido com sucesso!');
                    loadCacheItems();
                } else {
                    alert('Erro ao limpar cache');
                }
            } catch (error) {
                console.error('Erro ao limpar cache:', error);
                alert('Erro ao limpar todo o cache');
            }
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Carregar ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadCacheItems();
            
            // Auto-refresh a cada 30 segundos
            setInterval(() => {
                loadStatistics();
            }, 30000);
        });
    </script>
</body>
</html>
