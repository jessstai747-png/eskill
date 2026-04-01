<?php

declare(strict_types=1);

$title    = 'Clonar Anúncios';
$subtitle = 'Pesquise, filtre e clone anúncios entre contas do Mercado Livre';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- ================================================================== -->
<!-- Tabs navigation                                                       -->
<!-- ================================================================== -->
<ul class="nav nav-tabs border-bottom mb-4" id="cloneTabNav" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active px-4 py-2" id="tab-anuncio-btn"
            data-bs-toggle="tab" data-bs-target="#tab-anuncio"
            type="button" role="tab">
            <i class="bi bi-search me-1"></i> Clonar Anúncio
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4 py-2" id="tab-conta-btn"
            data-bs-toggle="tab" data-bs-target="#tab-conta"
            type="button" role="tab">
            <i class="bi bi-shop me-1"></i> Clonar Conta
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4 py-2" id="tab-lista-btn"
            data-bs-toggle="tab" data-bs-target="#tab-lista"
            type="button" role="tab">
            <i class="bi bi-list-ul me-1"></i> Clonar Por Lista
        </button>
    </li>
</ul>

<div class="tab-content" id="cloneTabContent">

    <!-- ============================================================ -->
    <!-- TAB 1 — Clonar Anúncio                                        -->
    <!-- ============================================================ -->
    <div class="tab-pane fade show active" id="tab-anuncio" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form id="cloneSearchForm" class="row g-2 align-items-end" role="search" novalidate>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small text-muted" for="searchType">Pesquisar Por</label>
                        <select class="form-select" id="searchType" name="type">
                            <option value="item_id">Id ou URL do Anúncio</option>
                            <option value="seller_nickname">Apelido vendedor</option>
                            <option value="seller_id">ID do vendedor</option>
                            <option value="keyword" selected>Palavra-chave</option>
                            <option value="catalog_id">Id do Catálogo ou Número da Peça</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-semibold small text-muted" for="searchQuery">Busca</label>
                        <input type="search" class="form-control" id="searchQuery" name="q"
                            placeholder="Digite aqui..." autocomplete="off" enterkeyhint="search" spellcheck="false"
                            aria-describedby="searchQueryHint searchQueryFeedback" required>
                        <div class="form-text text-muted" id="searchQueryHint">Use ID, URL, apelido, seller ID ou palavra-chave.</div>
                        <div class="invalid-feedback" id="searchQueryFeedback"></div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" id="btnSearch">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results -->
        <div id="searchResultsWrapper" class="d-none" aria-live="polite">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small" id="searchResultsCount" role="status"></span>
                <div id="searchPagination" class="d-flex gap-1"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px">Foto</th>
                            <th>ID</th>
                            <th>Vendedor</th>
                            <th>Título</th>
                            <th style="width:90px">Anúncio</th>
                            <th style="width:120px">Preço</th>
                            <th style="width:90px"></th>
                        </tr>
                    </thead>
                    <tbody id="searchResultsBody"></tbody>
                </table>
            </div>
        </div>

        <div id="searchEmpty" class="text-center py-5 text-muted d-none" role="status" aria-live="polite">
            <i class="bi bi-search fs-1 opacity-25"></i>
            <p class="mt-2">Nenhum resultado encontrado.</p>
        </div>

        <div id="searchLoading" class="text-center py-4 d-none" role="status" aria-live="polite">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">Buscando anúncios…</p>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 2 — Clonar Conta                                          -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="tab-conta" role="tabpanel">
        <div class="row g-4 mb-4">
            <!-- Passo 1 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary rounded-pill me-2">1</span>
                            <span class="fw-semibold">Conta de origem</span>
                        </div>
                        <select class="form-select" id="sourceAccountType">
                            <option value="own">Minha conta</option>
                            <option value="nickname">Apelido de outra conta</option>
                            <option value="id">ID de outra conta</option>
                        </select>
                        <div id="sourceAccountInputWrapper" class="mt-2 d-none">
                            <input type="text" class="form-control" id="sourceAccountInput"
                                placeholder="Apelido ou ID">
                        </div>
                        <div id="sourceAccountSelect" class="mt-2">
                            <select class="form-select" id="sourceAccountId">
                                <option value="">Selecione uma conta…</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= htmlspecialchars((string)$acc['id'], ENT_QUOTES) ?>"
                                        data-nickname="<?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Passo 2 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary rounded-pill me-2">2</span>
                            <span class="fw-semibold">Status dos anúncios</span>
                        </div>
                        <select class="form-select" id="sourceItemStatus">
                            <option value="active">Ativos</option>
                            <option value="paused">Pausados</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Passo 3 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary rounded-pill me-2">3</span>
                            <span class="fw-semibold">Conta de destino</span>
                        </div>
                        <p class="text-muted small mb-2">Qual conta receberá os anúncios clonados?</p>
                        <select class="form-select" id="targetAccountId">
                            <option value="">Selecione uma conta…</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= htmlspecialchars((string)$acc['id'], ENT_QUOTES) ?>"
                                    data-nickname="<?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mb-5">
            <button class="btn btn-lg px-5 py-2" id="btnStartCloneAccount"
                style="background:#6f42c1;color:#fff;">
                <i class="bi bi-play-fill me-1"></i> Iniciar Procedimento
            </button>
        </div>

        <!-- History table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold">Histórico de clonagens de conta</span>
                <button class="btn btn-sm btn-outline-secondary" id="btnRefreshHistory">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Criada</th>
                                <th>Conta origem</th>
                                <th>Conta destino</th>
                                <th class="text-center">Anúncios</th>
                                <th class="text-center">Com erro</th>
                                <th class="text-center">Sucesso</th>
                                <th>Status</th>
                                <th style="width:100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Carregando histórico…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 3 — Clonar Por Lista                                      -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="tab-lista" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">IDs dos Anúncios</label>
                        <textarea class="form-control font-monospace" id="listItemIds" rows="8"
                            placeholder="Cole os IDs aqui, um por linha ou separados por vírgula.
Ex:
MLB123456789
MLB987654321
MLB111222333"></textarea>
                        <div class="form-text text-muted">
                            Cole IDs ou URLs. O sistema extrai o código automaticamente.
                        </div>
                    </div>
                    <div class="col-md-4 d-flex flex-column">
                        <label class="form-label fw-semibold">Conta de destino</label>
                        <select class="form-select mb-3" id="listTargetAccount">
                            <option value="">Selecione…</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= htmlspecialchars((string)$acc['id'], ENT_QUOTES) ?>"
                                    data-nickname="<?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary mt-auto" id="btnCloneList">
                            <i class="bi bi-copy me-1"></i> Clonar Lista
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clone modal — destination selector -->
<div class="modal fade" id="cloneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title"><i class="bi bi-copy me-2 text-primary"></i>Clonar Anúncio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="cloneModalItemInfo"></p>
                <label class="form-label fw-semibold">Conta de destino</label>
                <select class="form-select" id="cloneModalTargetAccount">
                    <option value="">Selecione uma conta…</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= htmlspecialchars((string)$acc['id'], ENT_QUOTES) ?>"
                            data-nickname="<?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="alert alert-secondary small d-none mt-3 mb-0" id="cloneModalValidation"></div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmClone" disabled>
                    <i class="bi bi-check2 me-1"></i> Confirmar Clone
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="cloneToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="cloneToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    (function() {
        'use strict';

        // -----------------------------------------------------------------------
        // State
        // -----------------------------------------------------------------------
        let searchOffset = 0;
        const searchLimit = 20;
        let searchTotal = 0;
        let currentType = 'keyword';
        let currentQuery = '';
        let pendingItemId = null;
        let activeSearchController = null;
        let activeSearchRequestId = 0;
        let clonePrecheckState = {
            valid: false,
            targetAccountId: 0,
            pending: false
        };

        // -----------------------------------------------------------------------
        // Utilities
        // -----------------------------------------------------------------------
        function toast(msg, type = 'success') {
            const el = document.getElementById('cloneToast');
            const txt = document.getElementById('cloneToastMsg');
            el.className = 'toast align-items-center border-0 text-bg-' + type;
            txt.textContent = msg;
            bootstrap.Toast.getOrCreateInstance(el, {
                delay: 4000
            }).show();
        }

        function formatBRL(price) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(price || 0);
        }

        function listingTypeBadge(listingType) {
            if (!listingType) return '';
            if (listingType === 'gold_special' || listingType === 'gold_pro') {
                return '<span class="badge bg-warning text-dark" title="Gold">◆</span>';
            }
            if (listingType === 'catalog') {
                return '<span class="badge bg-info text-white" title="Catálogo">◈</span>';
            }
            return '<span class="badge bg-secondary" title="' + listingType + '">●</span>';
        }

        function statusBadge(status) {
            const map = {
                'Finalizada com pendências': 'warning',
                'Finalizada': 'success',
                'Processando': 'primary',
                'Na fila': 'info',
                'Aguardando': 'secondary',
                'Falhou': 'danger',
                'Cancelada': 'dark',
            };
            const variant = map[status] || 'secondary';
            return `<span class="badge bg-${variant}">${status}</span>`;
        }

        function escHtml(s) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(s || ''));
            return d.innerHTML;
        }

        function escAttr(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[char]);
        }

        const cloneSearchForm = document.getElementById('cloneSearchForm');
        const searchTypeField = document.getElementById('searchType');
        const searchQueryField = document.getElementById('searchQuery');
        const searchQueryFeedback = document.getElementById('searchQueryFeedback');
        const btnSearch = document.getElementById('btnSearch');
        const searchLoading = document.getElementById('searchLoading');
        const searchResultsWrapper = document.getElementById('searchResultsWrapper');
        const searchEmpty = document.getElementById('searchEmpty');
        const searchResultsBody = document.getElementById('searchResultsBody');
        const searchResultsCount = document.getElementById('searchResultsCount');
        const searchPagination = document.getElementById('searchPagination');
        const cloneModalTargetAccount = document.getElementById('cloneModalTargetAccount');
        const cloneModalValidation = document.getElementById('cloneModalValidation');
        const btnConfirmClone = document.getElementById('btnConfirmClone');

        function setSearchBusy(isBusy) {
            btnSearch.disabled = isBusy;
            btnSearch.classList.toggle('loading', isBusy);
            btnSearch.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            cloneSearchForm.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        }

        function resetSearchState() {
            searchResultsWrapper.classList.add('d-none');
            searchEmpty.classList.add('d-none');
            searchResultsBody.innerHTML = '';
            searchResultsCount.textContent = '';
            searchPagination.innerHTML = '';
        }

        function setSearchFieldError(message = '') {
            const hasError = message !== '';
            searchQueryField.classList.toggle('is-invalid', hasError);
            searchQueryField.setAttribute('aria-invalid', hasError ? 'true' : 'false');
            searchQueryFeedback.textContent = message;
        }

        function syncSearchFieldMode() {
            const type = searchTypeField.value;
            const placeholderByType = {
                item_id: 'Digite o ID MLB ou cole a URL do anúncio',
                seller_nickname: 'Digite o apelido do vendedor',
                seller_id: 'Digite apenas números do seller ID',
                keyword: 'Digite uma palavra-chave',
                catalog_id: 'Digite o ID do catálogo ou número da peça'
            };

            searchQueryField.placeholder = placeholderByType[type] || 'Digite aqui...';
            searchQueryField.inputMode = type === 'seller_id' ? 'numeric' : 'search';
            searchQueryField.pattern = type === 'seller_id' ? '\\d*' : '';
        }

        function validateSearchQuery(type, query) {
            if (query === '') {
                return 'Digite um termo para buscar.';
            }

            if (type === 'seller_id' && !/^\d+$/.test(query)) {
                return 'Para ID do vendedor, digite apenas números.';
            }

            return '';
        }

        function parseJsonResponse(response, fallbackMessage = 'Erro ao processar a resposta do servidor.') {
            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                return response.text().then(() => {
                    throw new Error(fallbackMessage);
                });
            }

            return response.json()
                .catch(() => {
                    throw new Error(fallbackMessage);
                })
                .then((payload) => {
                    if (!response.ok) {
                        throw payload;
                    }

                    return payload;
                });
        }

        function renderClonePrecheck(state, messages = []) {
            const variants = {
                idle: 'alert-secondary',
                loading: 'alert-secondary',
                success: 'alert-success',
                warning: 'alert-warning',
                danger: 'alert-danger',
            };

            cloneModalValidation.className = `alert small mt-3 mb-0 ${variants[state] || variants.idle}`;
            cloneModalValidation.classList.remove('d-none');
            cloneModalValidation.innerHTML = messages.join('<br>');
        }

        function updateCloneConfirmState() {
            btnConfirmClone.disabled = clonePrecheckState.pending || !clonePrecheckState.valid;
        }

        function resetClonePrecheck() {
            clonePrecheckState = {
                valid: false,
                targetAccountId: 0,
                pending: false
            };
            renderClonePrecheck('idle', ['Selecione uma conta de destino para validar token e permissões antes de clonar.']);
            updateCloneConfirmState();
        }

        function runClonePrecheck() {
            const targetAccountId = parseInt(cloneModalTargetAccount.value, 10);

            if (!pendingItemId || !targetAccountId) {
                resetClonePrecheck();
                return Promise.resolve(null);
            }

            clonePrecheckState = {
                valid: false,
                targetAccountId,
                pending: true
            };
            updateCloneConfirmState();
            renderClonePrecheck('loading', ['Validando conta de destino e acessibilidade do anúncio...']);

            return fetch('/api/catalog/clone/validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        target_account_id: targetAccountId,
                        item_ids: [pendingItemId]
                    }),
                })
                .then((response) => parseJsonResponse(response, 'Não foi possível interpretar a validação da clonagem.'))
                .then((data) => {
                    const warnings = Array.isArray(data.warnings) ? data.warnings : [];
                    const errors = Array.isArray(data.errors) ? data.errors : [];
                    const accountLabel = data.account?.nickname || data.account?.ml_user_id || `Conta ${targetAccountId}`;

                    clonePrecheckState = {
                        valid: data.valid === true,
                        targetAccountId,
                        pending: false
                    };

                    if (clonePrecheckState.valid) {
                        const messages = [`Conta validada: ${escHtml(accountLabel)}.`];
                        if (warnings.length) {
                            messages.push(...warnings.map((warning) => escHtml(warning)));
                            renderClonePrecheck('warning', messages);
                        } else {
                            renderClonePrecheck('success', messages);
                        }
                    } else {
                        renderClonePrecheck('danger', errors.length ? errors.map((error) => escHtml(error)) : ['Não foi possível validar a clonagem.']);
                    }

                    updateCloneConfirmState();
                    return data;
                })
                .catch((error) => {
                    clonePrecheckState = {
                        valid: false,
                        targetAccountId,
                        pending: false
                    };
                    updateCloneConfirmState();
                    renderClonePrecheck('danger', [escHtml(error?.message || error?.error || 'Falha ao validar a conta de destino.')]);
                    console.error('Erro ao validar pré-condições da clonagem.', error);
                    return null;
                });
        }

        // -----------------------------------------------------------------------
        // TAB 1 — Search
        // -----------------------------------------------------------------------
        function fetchResults(type, query, offset) {
            currentType = type;
            currentQuery = query;
            searchOffset = offset;
            activeSearchRequestId += 1;
            const requestId = activeSearchRequestId;

            if (activeSearchController) {
                activeSearchController.abort();
            }

            activeSearchController = new AbortController();
            setSearchBusy(true);
            resetSearchState();
            searchLoading.classList.remove('d-none');

            const params = new URLSearchParams({
                type,
                q: query,
                offset,
                limit: searchLimit
            });
            return fetch('/api/catalog/clone/search?' + params, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    signal: activeSearchController.signal
                })
                .then((response) => parseJsonResponse(response, 'Não foi possível interpretar a resposta da busca.'))
                .then(data => {
                    if (data.error) {
                        throw new Error(data.message || data.error);
                    }

                    if (requestId !== activeSearchRequestId) {
                        return;
                    }

                    searchTotal = data.total || 0;
                    renderResultsTable(data.items || []);
                    renderPagination();
                })
                .catch((error) => {
                    if (error?.name === 'AbortError') {
                        return;
                    }

                    if (requestId !== activeSearchRequestId) {
                        return;
                    }

                    resetSearchState();
                    const errorMessage = error?.message || error?.error || 'Erro ao conectar ao servidor.';
                    toast(errorMessage, 'danger');
                    console.error('Erro ao buscar anúncios para clonagem.', error);
                })
                .finally(() => {
                    if (requestId !== activeSearchRequestId) {
                        return;
                    }

                    activeSearchController = null;
                    searchLoading.classList.add('d-none');
                    setSearchBusy(false);
                });
        }

        function renderResultsTable(items) {
            if (!items.length) {
                searchTotal = 0;
                searchResultsCount.textContent = '0 resultado(s)';
                searchEmpty.classList.remove('d-none');
                searchResultsWrapper.classList.add('d-none');
                return;
            }

            searchResultsCount.textContent = `${searchTotal.toLocaleString('pt-BR')} resultado(s) — exibindo ${searchOffset + 1}–${Math.min(searchOffset + items.length, searchTotal)}`;

            searchResultsBody.innerHTML = items.map(item => {
                const thumb = item.thumbnail ?
                    `<img src="${escAttr(item.thumbnail)}" width="48" height="48" class="rounded object-fit-cover" loading="lazy" alt="${escAttr(item.title || 'Anúncio do Mercado Livre')}">` :
                    `<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="bi bi-image text-muted"></i></div>`;

                const link = item.permalink ?
                    `<a href="${escAttr(item.permalink)}" target="_blank" rel="noopener noreferrer" class="font-monospace small text-decoration-none">${escHtml(item.id)}</a>` :
                    `<span class="font-monospace small">${escHtml(item.id)}</span>`;

                const badge = item.is_catalog ?
                    '<span class="badge bg-info text-white">Catálogo</span>' :
                    listingTypeBadge(item.listing_type_id || '');

                return `<tr>
                <td>${thumb}</td>
                <td>${link}</td>
                <td class="small text-muted">${escHtml(item.seller_nickname || item.seller_id || '')}</td>
                <td class="small">${escHtml(item.title)}</td>
                <td class="text-center">${badge}</td>
                <td class="fw-semibold small">${formatBRL(item.price)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-primary clone-result-btn" data-item-id="${escAttr(item.id)}" data-item-title="${escAttr(item.title)}">
                        <i class="bi bi-copy"></i> Clonar
                    </button>
                </td>
            </tr>`;
            }).join('');

            searchResultsWrapper.classList.remove('d-none');
        }

        function renderPagination() {
            const pages = Math.ceil(searchTotal / searchLimit);
            const current = Math.floor(searchOffset / searchLimit);

            if (pages <= 1) {
                searchPagination.innerHTML = '';
                return;
            }

            let html = '';
            const prev = current > 0;
            const next = current < pages - 1;

            html += `<button type="button" class="btn btn-sm btn-outline-secondary" aria-label="Página anterior" ${prev ? '' : 'disabled'} onclick="window.cloneGoPage(${current - 1})">‹</button>`;

            const start = Math.max(0, current - 2);
            const end = Math.min(pages - 1, current + 2);
            for (let p = start; p <= end; p++) {
                html += `<button type="button" class="btn btn-sm ${p === current ? 'btn-primary' : 'btn-outline-secondary'}" aria-label="Ir para a página ${p + 1}" onclick="window.cloneGoPage(${p})">${p + 1}</button>`;
            }
            html += `<button type="button" class="btn btn-sm btn-outline-secondary" aria-label="Próxima página" ${next ? '' : 'disabled'} onclick="window.cloneGoPage(${current + 1})">›</button>`;

            searchPagination.innerHTML = html;
        }

        window.cloneGoPage = function(page) {
            if (page < 0) {
                return;
            }

            fetchResults(currentType, currentQuery, page * searchLimit);
        };

        cloneSearchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const type = searchTypeField.value;
            const query = searchQueryField.value.trim();
            const validationMessage = validateSearchQuery(type, query);

            if (validationMessage !== '') {
                setSearchFieldError(validationMessage);
                toast(validationMessage, 'warning');
                searchQueryField.focus();
                return;
            }

            setSearchFieldError();
            fetchResults(type, query, 0);
        });

        searchTypeField.addEventListener('change', () => {
            setSearchFieldError();
            syncSearchFieldMode();
        });

        searchQueryField.addEventListener('input', () => {
            if (searchQueryField.classList.contains('is-invalid')) {
                setSearchFieldError();
            }
        });

        searchResultsBody.addEventListener('click', (event) => {
            const button = event.target.closest('.clone-result-btn');
            if (!button) {
                return;
            }

            window.openCloneModal(button.dataset.itemId || '', button.dataset.itemTitle || '');
        });

        syncSearchFieldMode();

        // -----------------------------------------------------------------------
        // Clone modal
        // -----------------------------------------------------------------------
        window.openCloneModal = function(itemId, title) {
            pendingItemId = itemId;
            document.getElementById('cloneModalItemInfo').textContent = `ID: ${itemId} — ${title}`;
            cloneModalTargetAccount.value = '';
            resetClonePrecheck();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('cloneModal')).show();
        };

        cloneModalTargetAccount.addEventListener('change', () => {
            runClonePrecheck();
        });

        btnConfirmClone.addEventListener('click', () => {
            const targetId = cloneModalTargetAccount.value;
            if (!targetId) {
                toast('Selecione uma conta de destino.', 'warning');
                return;
            }
            if (!pendingItemId) return;
            if (!clonePrecheckState.valid || clonePrecheckState.targetAccountId !== parseInt(targetId, 10)) {
                toast('Valide a conta de destino antes de confirmar a clonagem.', 'warning');
                runClonePrecheck();
                return;
            }

            const btn = btnConfirmClone;
            btn.disabled = true;
            btn.classList.add('loading');
            renderClonePrecheck('loading', ['Iniciando clonagem do anúncio...']);

            fetch('/api/catalog/clone/item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        source_item_id: pendingItemId,
                        target_account_id: parseInt(targetId)
                    }),
                })
                .then((response) => parseJsonResponse(response, 'Não foi possível interpretar a resposta da clonagem.'))
                .then(data => {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    if (data.status === 'success' || data.success) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('cloneModal')).hide();
                        resetClonePrecheck();
                        toast('Anúncio enviado para clonagem com sucesso!', 'success');
                        if (Array.isArray(data.warnings) && data.warnings.length) {
                            toast(data.warnings[0], 'warning');
                        }
                    } else {
                        const details = Array.isArray(data.details?.errors) ? data.details.errors.join(' ') : '';
                        renderClonePrecheck('danger', [escHtml(details || data.message || data.error || 'Erro ao clonar.')]);
                        toast(details || data.message || data.error || 'Erro ao clonar.', 'danger');
                    }
                })
                .catch((error) => {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    const details = Array.isArray(error?.details?.errors) ? error.details.errors.join(' ') : '';
                    renderClonePrecheck('danger', [escHtml(details || error?.message || error?.error || 'Erro de conexão.')]);
                    toast(details || error?.message || error?.error || 'Erro de conexão.', 'danger');
                    console.error('Erro ao iniciar clonagem de anúncio.', error);
                });
        });

        // -----------------------------------------------------------------------
        // TAB 2 — Clonar Conta
        // -----------------------------------------------------------------------
        function loadHistory() {
            fetch('/api/catalog/clone/batch-jobs?limit=50')
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        renderHistoryTable([]);
                        return;
                    }
                    renderHistoryTable(data.jobs || []);
                })
                .catch(() => renderHistoryTable([]));
        }

        function renderHistoryTable(jobs) {
            const tbody = document.getElementById('historyBody');
            if (!jobs.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Nenhuma clonagem de conta encontrada.</td></tr>';
                return;
            }

            tbody.innerHTML = jobs.map(job => {
                const created = job.created_at ? new Date(job.created_at).toLocaleString('pt-BR') : '-';
                const retryBtn = job.has_failures ?
                    `<button class="btn btn-sm btn-outline-warning ms-1" onclick="retryJob('${escHtml(job.job_id)}')" title="Reprocessar pendentes"><i class="bi bi-arrow-repeat"></i></button>` :
                    '';

                return `<tr>
                <td class="small text-muted">${created}</td>
                <td class="small">${escHtml(job.source_account)}</td>
                <td class="small">${escHtml(job.target_account)}</td>
                <td class="text-center small">${job.total_items}</td>
                <td class="text-center small ${job.failed_items > 0 ? 'text-danger fw-semibold' : ''}">${job.failed_items}</td>
                <td class="text-center small ${job.successful_items > 0 ? 'text-success' : ''}">${job.successful_items}</td>
                <td>${statusBadge(job.display_status)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary" onclick="alert('Log do job: ${escHtml(job.job_id)}')" title="Ver log"><i class="bi bi-eye"></i></button>
                    ${retryBtn}
                </td>
            </tr>`;
            }).join('');
        }

        window.retryJob = function(jobId) {
            if (!confirm('Reprocessar todos os itens com falha deste job?')) return;

            fetch('/api/catalog/clone/jobs/' + encodeURIComponent(jobId) + '/retry-failed', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                })
                .then(r => r.json())
                .then(data => {
                    toast(data.message || 'Reprocessamento iniciado.', data.status === 'success' ? 'success' : 'warning');
                    loadHistory();
                })
                .catch(() => toast('Erro ao reprocessar.', 'danger'));
        };

        document.getElementById('btnRefreshHistory').addEventListener('click', loadHistory);

        // Source account type toggle
        document.getElementById('sourceAccountType').addEventListener('change', function() {
            const wrapper = document.getElementById('sourceAccountInputWrapper');
            const select = document.getElementById('sourceAccountSelect');
            if (this.value === 'own') {
                wrapper.classList.add('d-none');
                select.classList.remove('d-none');
            } else {
                wrapper.classList.remove('d-none');
                select.classList.add('d-none');
                const ph = this.value === 'nickname' ? 'Apelido do vendedor' : 'ID numérico do vendedor';
                document.getElementById('sourceAccountInput').placeholder = ph;
            }
        });

        document.getElementById('btnStartCloneAccount').addEventListener('click', () => {
            const sourceType = document.getElementById('sourceAccountType').value;
            const targetId = document.getElementById('targetAccountId').value;
            const itemStatus = document.getElementById('sourceItemStatus').value;

            let sourceId = null;
            let sourceNick = null;

            if (sourceType === 'own') {
                sourceId = document.getElementById('sourceAccountId').value;
                if (!sourceId) {
                    toast('Selecione a conta de origem.', 'warning');
                    return;
                }
            } else {
                const val = document.getElementById('sourceAccountInput').value.trim();
                if (!val) {
                    toast('Informe ' + (sourceType === 'nickname' ? 'o apelido' : 'o ID') + ' da conta de origem.', 'warning');
                    return;
                }
                if (sourceType === 'nickname') {
                    sourceNick = val;
                } else {
                    sourceId = val;
                }
            }

            if (!targetId) {
                toast('Selecione a conta de destino.', 'warning');
                return;
            }

            const btn = document.getElementById('btnStartCloneAccount');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando…';

            const payload = {
                target_account_id: parseInt(targetId),
                filters: {
                    status: itemStatus || null
                },
            };
            if (sourceId) payload.source_account_id = parseInt(sourceId);
            if (sourceNick) payload.source_seller_nickname = sourceNick;

            fetch('/api/catalog/clone/seller-job', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Iniciar Procedimento';
                    if (data.status === 'success' || data.job_id) {
                        toast('Clonagem iniciada! Acompanhe o histórico abaixo.', 'success');
                        loadHistory();
                    } else {
                        toast(data.message || data.error || 'Erro ao iniciar clonagem.', 'danger');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Iniciar Procedimento';
                    toast('Erro de conexão.', 'danger');
                });
        });

        // Load history on tab switch
        document.getElementById('tab-conta-btn').addEventListener('shown.bs.tab', loadHistory);

        // -----------------------------------------------------------------------
        // TAB 3 — Clonar Por Lista
        // -----------------------------------------------------------------------
        document.getElementById('btnCloneList').addEventListener('click', () => {
            const raw = document.getElementById('listItemIds').value.trim();
            const targetId = document.getElementById('listTargetAccount').value;

            if (!raw) {
                toast('Cole ao menos um ID de anúncio.', 'warning');
                return;
            }
            if (!targetId) {
                toast('Selecione uma conta de destino.', 'warning');
                return;
            }

            // Parse IDs: accept comma, newline, or MLB\d+ pattern
            const ids = [...new Set(
                raw.split(/[\s,\n]+/)
                .map(s => {
                    const m = s.match(/MLB-?\d+/i);
                    return m ? m[0].replace('-', '').toUpperCase() : s.trim().toUpperCase();
                })
                .filter(s => /^MLB\d+$/.test(s))
            )];

            if (!ids.length) {
                toast('Nenhum ID válido (formato MLB...) encontrado.', 'warning');
                return;
            }

            const btn = document.getElementById('btnCloneList');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando…';

            fetch('/api/catalog/clone/batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_ids: ids,
                        target_account_id: parseInt(targetId)
                    }),
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-copy me-1"></i> Clonar Lista';
                    if (data.status === 'success' || data.job_id) {
                        toast(`${ids.length} anúncio(s) enviados para clonagem. Job: ${data.job_id || ''}`, 'success');
                        document.getElementById('listItemIds').value = '';
                    } else {
                        toast(data.message || data.error || 'Erro ao iniciar clonagem.', 'danger');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-copy me-1"></i> Clonar Lista';
                    toast('Erro de conexão.', 'danger');
                });
        });

        // -----------------------------------------------------------------------
        // Auto-poll active jobs every 10s when tab 2 is active
        // -----------------------------------------------------------------------
        let pollInterval = null;

        document.getElementById('tab-conta-btn').addEventListener('shown.bs.tab', () => {
            loadHistory();
            pollInterval = setInterval(loadHistory, 10000);
        });

        document.getElementById('tab-conta-btn').addEventListener('hidden.bs.tab', () => {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        });

    })();
</script>
