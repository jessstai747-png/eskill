<!-- Dashboard Search View -->
<div class="mb-4">
    <h4 class="mb-3">Busca Global</h4>
    <form id="searchForm" class="mb-4">
        <div class="input-group input-group-lg">
            <input type="text" class="form-control" id="searchQuery" name="q"
                value="<?= htmlspecialchars($query ?? '') ?>"
                placeholder="Buscar pedidos, anúncios, clientes...">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-search"></i> Buscar
            </button>
        </div>
        <div class="mt-2">
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="type" id="typeAll" value="" checked>
                <label class="btn btn-outline-secondary" for="typeAll">Tudo</label>

                <input type="radio" class="btn-check" name="type" id="typeOrders" value="orders">
                <label class="btn btn-outline-secondary" for="typeOrders">Pedidos</label>

                <input type="radio" class="btn-check" name="type" id="typeItems" value="items">
                <label class="btn btn-outline-secondary" for="typeItems">Anúncios</label>

                <input type="radio" class="btn-check" name="type" id="typeCustomers" value="customers">
                <label class="btn btn-outline-secondary" for="typeCustomers">Clientes</label>
            </div>
        </div>
    </form>
</div>

<div id="searchResults">
    <?php

declare(strict_types=1);

if (empty($query)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-search fs-1 d-block mb-3"></i>
            <h5>Digite algo para buscar</h5>
            <p class="mb-0">Busque por pedidos, anúncios, clientes e mais</p>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 text-muted">Buscando...</p>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?= CSP_NONCE ?>">

    function normalizeExternalUrl(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        if (!trimmed) return '';
        if (/^(data:|blob:)/i.test(trimmed)) return trimmed;
        if (trimmed.startsWith('//')) return window.location.protocol + trimmed;
        if (/^http:\/\//i.test(trimmed)) return trimmed.replace(/^http:\/\//i, 'https://');
        return trimmed;
    }

    const searchForm = document.getElementById('searchForm');
    const searchQuery = document.getElementById('searchQuery');
    const searchResults = document.getElementById('searchResults');

    searchForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const query = searchQuery.value.trim();
        if (!query) return;

        const type = document.querySelector('input[name="type"]:checked').value;

        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('q', query);
        if (type) url.searchParams.set('type', type);
        window.history.pushState({}, '', url);

        await performSearch(query, type);
    });

    async function performSearch(query, type = '') {
        searchResults.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Buscando...</p></div>';

        try {
            const data = await requestJson(`/api/search?q=${encodeURIComponent(query)}&type=${type}`);

            renderResults(data);
        } catch (e) {
            searchResults.innerHTML = '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-circle fs-1 d-block mb-2"></i>Erro ao buscar</div>';
        }
    }

    function renderResults(data) {
        if (!data.total || data.total === 0) {
            searchResults.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-search fs-1 d-block mb-3"></i>
                <h5>Nenhum resultado encontrado</h5>
                <p class="mb-0">Tente outros termos de busca</p>
            </div>`;
            return;
        }

        let html = `<p class="text-muted mb-4">${data.total} resultado(s) encontrado(s)</p>`;

        // Orders
        if (data.orders && data.orders.length > 0) {
            html += `
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bag-check me-2"></i>Pedidos (${data.orders.length})</h6>
                </div>
                <div class="list-group list-group-flush">
                    ${data.orders.map(o => `
                        <a href="/orders/${o.id}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>#${o.pack_id || o.id}</strong>
                                    <span class="ms-2 text-muted">${o.buyer_name || 'Cliente'}</span>
                                </div>
                                <div>
                                    <span class="badge bg-${o.status === 'paid' ? 'success' : 'secondary'}">${o.status}</span>
                                    <span class="ms-2">${formatCurrency(o.total_amount)}</span>
                                </div>
                            </div>
                        </a>
                    `).join('')}
                </div>
            </div>`;
        }

        // Items
        if (data.items && data.items.length > 0) {
            html += `
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Anúncios (${data.items.length})</h6>
                </div>
                <div class="list-group list-group-flush">
                    ${data.items.map(i => `
                        <a href="/items/${i.ml_item_id}" class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-center">
                                <img src="${normalizeExternalUrl(i.thumbnail) || '/icons/icon-72x72.png'}" class="rounded me-3" width="50" height="50" style="object-fit:cover">
                                <div class="flex-grow-1">
                                    <strong>${i.title}</strong>
                                    <br><small class="text-muted">${i.ml_item_id}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-${i.status === 'active' ? 'success' : 'secondary'}">${i.status}</span>
                                    <br><span class="text-primary">${formatCurrency(i.price)}</span>
                                </div>
                            </div>
                        </a>
                    `).join('')}
                </div>
            </div>`;
        }

        // Customers
        if (data.customers && data.customers.length > 0) {
            html += `
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Clientes (${data.customers.length})</h6>
                </div>
                <div class="list-group list-group-flush">
                    ${data.customers.map(c => `
                        <a href="/customers/${c.id}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${c.name || c.nickname}</strong>
                                    <br><small class="text-muted">${c.email || ''}</small>
                                </div>
                                <div class="text-end">
                                    <span class="text-muted">${c.total_orders || 0} pedidos</span>
                                </div>
                            </div>
                        </a>
                    `).join('')}
                </div>
            </div>`;
        }

        searchResults.innerHTML = html;
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value || 0);
    }

    // Auto-search if query exists
    <?php if (!empty($query)): ?>
        performSearch(<?= json_encode($query, JSON_HEX_TAG | JSON_HEX_APOS) ?>);
    <?php endif; ?>
</script>