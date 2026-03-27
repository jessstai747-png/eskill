<?php

declare(strict_types=1);

/**
 * Item Edit / Optimizer Page
 * GET /dashboard/items/{itemId}/edit
 *
 * @var string $itemId   ML item ID (sanitized by controller)
 * @var string $pageTitle
 */

$currentPage  = 'items';
$activePage   = 'items';

$breadcrumbs = [
    ['label' => 'Anúncios', 'url' => '/dashboard/items'],
    ['label' => $itemId, 'url' => ''],
];
$title    = '<i class="bi bi-pencil-square me-2"></i>' . htmlspecialchars($itemId, ENT_QUOTES, 'UTF-8');
$subtitle = 'Editar e otimizar anúncio';
$actions  = '
    <a href="/dashboard/items" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
    <a id="btn-view-ml" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Ver no Mercado Livre">
        <i class="bi bi-box-arrow-up-right"></i> Ver no ML
    </a>
    <button id="btn-save-all" class="btn btn-sm btn-success" disabled>
        <i class="bi bi-floppy"></i> Salvar
    </button>
';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<style>
    .edit-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 1.25rem;
        align-items: start;
    }

    @media (max-width: 992px) {
        .edit-grid {
            grid-template-columns: 1fr;
        }
    }

    .edit-card {
        background: var(--bg-card);
        border-radius: 0.75rem;
        padding: 1.25rem;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        margin-bottom: 1.25rem;
    }

    .edit-card h6 {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .score-ring {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }

    .score-ring.good {
        background: var(--success-color, #198754);
    }

    .score-ring.ok {
        background: var(--warning-color, #ffc107);
        color: #212529;
    }

    .score-ring.poor {
        background: var(--danger-color, #dc3545);
    }

    .bucket-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0;
        font-size: 0.875rem;
        border-bottom: 1px solid var(--border-color);
    }

    .bucket-item:last-child {
        border-bottom: none;
    }

    .bucket-icon.completed {
        color: var(--success-color, #198754);
    }

    .bucket-icon.pending {
        color: var(--warning-color, #ffc107);
    }

    .img-thumb {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 0.5rem;
        border: 1px solid var(--border-color);
    }

    .img-thumb-wrap {
        position: relative;
        display: inline-block;
    }

    .img-main-badge {
        position: absolute;
        top: 2px;
        left: 2px;
        font-size: 9px;
        padding: 1px 4px;
        background: var(--primary-color);
        color: #fff;
        border-radius: 3px;
    }

    .attr-row {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 0.4rem 0.75rem;
        align-items: center;
        padding: 0.35rem 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.875rem;
    }

    .attr-row:last-child {
        border-bottom: none;
    }

    .attr-name {
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .char-count {
        font-size: 0.75rem;
        color: var(--text-muted);
        float: right;
    }

    #loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: #fff;
        gap: 0.75rem;
        font-size: 1rem;
    }

    .ai-badge {
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        color: #fff;
        border: none;
        padding: 0.2rem 0.65rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
    }
</style>

<!-- Loading overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-light" role="status" style="width:2.5rem;height:2.5rem;"></div>
    <span>Carregando anúncio…</span>
</div>

<!-- Page skeleton (hidden until loaded) -->
<div id="item-edit-root" style="display:none;">

    <!-- ── header strip ─────────────────────────────── -->
    <div class="edit-card d-flex gap-3 align-items-center mb-0" id="item-header-strip">
        <img id="item-thumb" src="" alt="thumb"
            class="img-thumb" style="width:80px;height:80px;">
        <div class="flex-grow-1 min-w-0">
            <p class="text-muted mb-1" style="font-size:.75rem;">
                <span id="item-category-name">-</span>
                &nbsp;·&nbsp; <span id="item-status-badge" class="badge">-</span>
            </p>
            <div class="fw-bold" id="item-title-display" style="font-size:1.05rem;line-height:1.3;"></div>
            <div class="mt-1 d-flex gap-2 flex-wrap" style="font-size:.8rem;color:var(--text-muted);">
                <span><i class="bi bi-bag me-1"></i><span id="item-sold">0</span> vendidos</span>
                <span><i class="bi bi-eye me-1"></i><span id="item-visits">-</span> visitas/mês</span>
                <span><i class="bi bi-tag me-1"></i>R$ <span id="item-price">0</span></span>
                <span><i class="bi bi-box me-1"></i><span id="item-stock">0</span> em estoque</span>
            </div>
        </div>
    </div>

    <div class="edit-grid mt-3">

        <!-- ── LEFT COLUMN ───────────────────────────── -->
        <div>

            <!-- Title -->
            <div class="edit-card">
                <h6><i class="bi bi-fonts me-1"></i>Título <span class="char-count" id="title-chars">0/60</span></h6>
                <div class="input-group mb-2">
                    <input type="text" id="field-title" class="form-control"
                        maxlength="60" placeholder="Título do anúncio">
                    <button class="btn btn-outline-secondary" id="btn-ai-title" title="Otimizar com IA">
                        <span class="ai-badge">IA</span>
                    </button>
                </div>
                <div id="title-suggestions" class="d-none mt-1"></div>
            </div>

            <!-- Description -->
            <div class="edit-card">
                <h6><i class="bi bi-text-paragraph me-1"></i>Descrição</h6>
                <textarea id="field-description" class="form-control font-monospace"
                    rows="8" style="font-size:0.85rem;resize:vertical;"
                    placeholder="Descrição do anúncio…"></textarea>
                <div class="d-flex justify-content-between mt-2">
                    <span class="text-muted" style="font-size:.75rem;">
                        <span id="desc-chars">0</span> caracteres
                    </span>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-ai-description">
                        <span class="ai-badge">IA</span> Otimizar descrição
                    </button>
                </div>
            </div>

            <!-- Attributes -->
            <div class="edit-card">
                <h6><i class="bi bi-list-check me-1"></i>Atributos / Ficha Técnica</h6>
                <div id="attributes-container">
                    <span class="text-muted" style="font-size:.85rem;">Carregando atributos…</span>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="btn-ai-attributes">
                        <span class="ai-badge">IA</span> Preencher lacunas
                    </button>
                </div>
            </div>

        </div>

        <!-- ── RIGHT COLUMN ──────────────────────────── -->
        <div>

            <!-- Quality Score -->
            <div class="edit-card">
                <h6><i class="bi bi-graph-up me-1"></i>Qualidade ML</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="score-ring" id="quality-ring">-</div>
                    <div>
                        <div class="fw-bold" id="quality-level">Carregando…</div>
                        <div class="text-muted" style="font-size:.8rem;">Nível atual do anúncio</div>
                    </div>
                </div>
                <div id="quality-buckets"></div>
            </div>

            <!-- Purchase Experience -->
            <div class="edit-card" id="card-purchase-exp" style="display:none;">
                <h6><i class="bi bi-emoji-smile me-1"></i>Experiência de Compra</h6>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span id="exp-badge" class="badge fs-6 px-3 py-1">-</span>
                    <span id="exp-score" class="fw-bold">-</span>
                </div>
                <div id="exp-problems" class="text-muted" style="font-size:.8rem;"></div>
            </div>

            <!-- Images -->
            <div class="edit-card">
                <h6><i class="bi bi-images me-1"></i>Imagens</h6>
                <div id="images-container" class="d-flex flex-wrap gap-2"></div>
                <p class="text-muted mt-2 mb-0" style="font-size:.75rem;">Para adicionar/remover imagens, use o painel do Mercado Livre.</p>
            </div>

            <!-- Quick Stats -->
            <div class="edit-card">
                <h6><i class="bi bi-bar-chart me-1"></i>Métricas Rápidas</h6>
                <div id="quick-stats" class="d-flex flex-column gap-1" style="font-size:.875rem;"></div>
            </div>

        </div>
    </div>

    <!-- Bottom action bar -->
    <div class="d-flex gap-2 mt-2 pb-4">
        <button id="btn-save-bottom" class="btn btn-success" disabled>
            <i class="bi bi-floppy"></i> Salvar alterações
        </button>
        <button id="btn-discard" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i> Descartar
        </button>
        <a id="btn-open-techsheet" href="#" class="btn btn-outline-purple ms-auto">
            <i class="bi bi-file-text"></i> Ficha Técnica completa
        </a>
    </div>

</div><!-- /#item-edit-root -->

<script nonce="<?= CSP_NONCE ?>">
    (function() {
        'use strict';

        const ITEM_ID = <?= json_encode($itemId) ?>;

        // ── helpers ──────────────────────────────────────
        const $ = id => document.getElementById(id);

        const FALLBACK_IMG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='64'%3E%3Crect width='64' height='64' fill='%23f0f0f0'/%3E%3Ctext x='32' y='36' text-anchor='middle' font-size='10' fill='%23aaa'%3ESem img%3C/text%3E%3C/svg%3E";

        function bindFallbackImages(root) {
            (root || document).querySelectorAll('img.img-fallback').forEach(img => {
                img.addEventListener('error', function onErr() {
                    this.removeEventListener('error', onErr);
                    this.src = FALLBACK_IMG;
                });
            });
        }

        const requestJson = async (url, options = {}) => {
            if (window.ApiClient && typeof window.ApiClient.request === 'function') {
                return window.ApiClient.request(url, options);
            }
            const res = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                ...options,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        };

        const toast = (msg, type = 'success') => {
            if (window.Toastify) {
                Toastify({
                    text: msg,
                    duration: 3500,
                    gravity: 'top',
                    position: 'right',
                    style: {
                        background: type === 'success' ? '#198754' : type === 'warning' ? '#ffc107' : '#dc3545'
                    },
                }).showToast();
            }
        };

        function updateCharCount(inputId, counterId, max) {
            const input = $(inputId);
            const counter = $(counterId);
            if (!input || !counter) return;
            const len = input.value.length;
            counter.textContent = `${len}/${max}`;
            counter.style.color = len > max * 0.9 ? 'var(--danger-color,#dc3545)' : 'var(--text-muted)';
        }

        window.updateCharCount = updateCharCount;

        // ── state ────────────────────────────────────────
        let originalTitle = '';
        let originalDescription = '';
        let originalAttributes = {};
        let isDirty = false;

        function markDirty() {
            if (isDirty) return;
            isDirty = true;
            $('btn-save-all').disabled = false;
            $('btn-save-bottom').disabled = false;
        }

        function markClean() {
            isDirty = false;
            $('btn-save-all').disabled = true;
            $('btn-save-bottom').disabled = true;
        }

        // ── render helpers ───────────────────────────────
        function renderImages(pictures) {
            const container = $('images-container');
            if (!pictures || pictures.length === 0) {
                container.innerHTML = '<span class="text-muted" style="font-size:.85rem;">Nenhuma imagem</span>';
                return;
            }
            container.innerHTML = pictures.map((p, i) => `
            <div class="img-thumb-wrap">
                ${i === 0 ? '<span class="img-main-badge">Principal</span>' : ''}
                <img src="${escHtml(p.url || '')}" alt="img ${i+1}" class="img-thumb img-fallback">
            </div>
        `).join('');
            bindFallbackImages(container);
        }

        function renderAttributes(attributes) {
            const container = $('attributes-container');
            if (!attributes || attributes.length === 0) {
                container.innerHTML = '<span class="text-muted" style="font-size:.85rem;">Nenhum atributo disponível</span>';
                return;
            }
            const rows = attributes.map(attr => {
                const id = `attr-${escHtml(attr.id)}`;
                const val = attr.value_name || attr.values?.[0]?.name || '';
                originalAttributes[attr.id] = val;
                return `
                <div class="attr-row">
                    <span class="attr-name" title="${escHtml(attr.name)}">${escHtml(attr.name)}</span>
                    <input type="text" class="form-control form-control-sm attr-input"
                           id="${id}" data-attr-id="${escHtml(attr.id)}"
                           value="${escHtml(val)}"
                           placeholder="— vazio —">
                </div>
            `;
            }).join('');
            container.innerHTML = rows;

            container.querySelectorAll('.attr-input').forEach(el => {
                el.addEventListener('input', markDirty);
            });
        }

        function renderQuality(perf) {
            if (!perf || perf.error) return;

            const score = perf.health_score ?? perf.score ?? 0;
            const ring = $('quality-ring');
            ring.textContent = score;
            ring.className = 'score-ring ' + (score >= 80 ? 'good' : score >= 50 ? 'ok' : 'poor');

            const levelWording = perf.level_wording || perf.level || '—';
            $('quality-level').textContent = levelWording;

            const bucketsEl = $('quality-buckets');
            const buckets = perf.buckets ?? [];
            if (buckets.length === 0) {
                bucketsEl.innerHTML = '';
                return;
            }

            bucketsEl.innerHTML = buckets.map(b => {
                const st = (b.status || '').toLowerCase();
                const icon = st === 'completed' ?
                    '<i class="bi bi-check-circle-fill bucket-icon completed"></i>' :
                    '<i class="bi bi-exclamation-circle-fill bucket-icon pending"></i>';
                const scoreVal = b.score != null ? Math.round(b.score) + '%' : '';
                return `
                <div class="bucket-item">
                    ${icon}
                    <span class="flex-grow-1">${escHtml(b.title || b.key || '')}</span>
                    <small class="text-muted">${escHtml(scoreVal)}</small>
                </div>
            `;
            }).join('');
        }

        function renderPurchaseExp(exp) {
            if (!exp || exp.error) return;

            const card = $('card-purchase-exp');
            card.style.display = '';

            const rep = exp.reputation || {};
            const color = rep.color || 'secondary';
            const score = rep.value ?? '—';
            const text = rep.text || '—';

            const colorMap = {
                green: 'success',
                orange: 'warning',
                red: 'danger',
                yellow: 'warning'
            };
            const bsColor = colorMap[color] || 'secondary';

            $('exp-badge').className = `badge bg-${bsColor} fs-6 px-3 py-1`;
            $('exp-badge').textContent = text;
            $('exp-score').textContent = typeof score === 'number' ? `${score}%` : score;

            const problems = exp.metrics_details?.problems ?? [];
            if (problems.length > 0) {
                $('exp-problems').innerHTML = problems.map(p =>
                    `<div>⚠ ${escHtml(p.level_three?.title?.text || p.level_two?.title?.text || p.key || '')}</div>`
                ).join('');
            }
        }

        function renderQuickStats(item, visits) {
            const el = $('quick-stats');
            const rows = [{
                    label: 'Categoria',
                    value: item.category_id || '—'
                },
                {
                    label: 'Tipo de listagem',
                    value: item.listing_type_id || '—'
                },
                {
                    label: 'Condição',
                    value: item.condition === 'new' ? 'Novo' : item.condition === 'used' ? 'Usado' : (item.condition || '—')
                },
                {
                    label: 'Garantia',
                    value: item.warranty || '—'
                },
                {
                    label: 'Frete grátis',
                    value: item.shipping?.free_shipping ? 'Sim ✓' : 'Não'
                },
            ];
            el.innerHTML = rows.map(r => `
            <div class="d-flex justify-content-between">
                <span class="text-muted">${escHtml(r.label)}</span>
                <span>${escHtml(String(r.value))}</span>
            </div>
        `).join('');
        }

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // ── load item data ───────────────────────────────
        async function loadItem() {
            try {
                const [itemData, perfData, expData] = await Promise.allSettled([
                    requestJson(`/api/seo/technical-sheet/items/${ITEM_ID}`),
                    requestJson(`/api/quality/health/${ITEM_ID}`),
                    requestJson(`/api/quality/purchase-experience/${ITEM_ID}`),
                ]);

                const item = itemData.status === 'fulfilled' ? (itemData.value?.data ?? itemData.value) : null;

                if (!item || item.error) {
                    $('loading-overlay').innerHTML = `
                    <div class="text-center">
                        <div class="mb-2" style="font-size:2rem;">⚠️</div>
                        <div>Anúncio não encontrado ou sem acesso.</div>
                        <a href="/dashboard/items" class="btn btn-outline-light btn-sm mt-3">Voltar</a>
                    </div>`;
                    return;
                }

                // ── header strip ──
                const thumb = item.pictures?.[0]?.url || item.thumbnail || '';
                $('item-thumb').src = thumb;
                $('item-title-display').textContent = item.title || ITEM_ID;
                $('item-category-name').textContent = item.category_id || '';
                $('item-sold').textContent = item.sold_quantity ?? 0;
                $('item-price').textContent = (+(item.price ?? 0)).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2
                });
                $('item-stock').textContent = item.available_quantity ?? 0;

                const statusBadge = $('item-status-badge');
                const statusMap = {
                    active: ['Ativo', 'success'],
                    paused: ['Pausado', 'warning'],
                    closed: ['Encerrado', 'danger']
                };
                const [statusLabel, statusColor] = statusMap[item.status] ?? [item.status || '—', 'secondary'];
                statusBadge.textContent = statusLabel;
                statusBadge.className = `badge bg-${statusColor}`;

                // permalink
                const permalink = item.permalink || '';
                if (permalink) {
                    $('btn-view-ml').href = permalink;
                } else {
                    $('btn-view-ml').style.display = 'none';
                }

                // tech-sheet link
                $('btn-open-techsheet').href = `/dashboard/tech-sheet?item=${ITEM_ID}`;

                // ── title field ──
                originalTitle = item.title || '';
                $('field-title').value = originalTitle;
                updateCharCount('field-title', 'title-chars', 60);

                // ── description ──
                let desc = '';
                if (typeof item.description === 'string') {
                    desc = item.description;
                } else if (typeof item.description === 'object' && item.description?.plain_text) {
                    desc = item.description.plain_text;
                }
                originalDescription = desc;
                $('field-description').value = desc;
                updateDescChars();

                // ── images ──
                renderImages(item.pictures || []);

                // ── attributes ──
                renderAttributes(item.attributes || []);

                // ── quick stats ──
                renderQuickStats(item, null);

                // ── quality ──
                if (perfData.status === 'fulfilled') {
                    renderQuality(perfData.value);
                }

                // ── purchase experience ──
                if (expData.status === 'fulfilled') {
                    renderPurchaseExp(expData.value);
                }

                // show page
                $('loading-overlay').style.display = 'none';
                $('item-edit-root').style.display = '';

                // bind thumb fallback
                const thumbEl = $('item-thumb');
                if (thumbEl) {
                    thumbEl.classList.add('img-fallback');
                    bindFallbackImages(thumbEl.parentElement);
                }

                // bind title char counter
                $('field-title').addEventListener('input', () => updateCharCount('field-title', 'title-chars', 60));

                // bind change detection after DOM is populated
                $('field-title').addEventListener('input', markDirty);
                $('field-description').addEventListener('input', () => {
                    markDirty();
                    updateDescChars();
                });

            } catch (err) {
                console.error('loadItem error', err);
                $('loading-overlay').innerHTML = `
                <div class="text-center">
                    <div class="mb-2" style="font-size:2rem;">💥</div>
                    <div>Erro ao carregar o anúncio.</div>
                    <small class="d-block text-muted mt-1">${escHtml(err.message)}</small>
                    <a href="/dashboard/items" class="btn btn-outline-light btn-sm mt-3">Voltar</a>
                </div>`;
            }
        }

        function updateDescChars() {
            const el = $('field-description');
            const counter = $('desc-chars');
            if (!el || !counter) return;
            counter.textContent = el.value.length;
        }

        // ── save ─────────────────────────────────────────
        async function saveChanges() {
            const title = $('field-title').value.trim();
            const description = $('field-description').value.trim();

            if (!title) {
                toast('O título não pode estar vazio.', 'error');
                return;
            }

            // Collect changed attributes
            const attrs = [];
            document.querySelectorAll('.attr-input').forEach(el => {
                const attrId = el.dataset.attrId;
                const newVal = el.value.trim();
                if (newVal !== (originalAttributes[attrId] ?? '')) {
                    attrs.push({
                        id: attrId,
                        value_name: newVal
                    });
                }
            });

            const payload = {};
            if (title !== originalTitle) payload.title = title;
            if (description !== originalDescription) payload.description = description;
            if (attrs.length > 0) payload.attributes = attrs;

            if (Object.keys(payload).length === 0) {
                toast('Nenhuma alteração detectada.', 'warning');
                markClean();
                return;
            }

            const btn = $('btn-save-bottom');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando…';

            try {
                const result = await requestJson(`/api/seo/technical-sheet/items/${ITEM_ID}/apply`, {
                    method: 'POST',
                    body: JSON.stringify({
                        approved_fields: payload
                    }),
                });

                if (result?.success) {
                    toast('Anúncio salvo com sucesso!');
                    originalTitle = title;
                    originalDescription = description;
                    attrs.forEach(a => {
                        originalAttributes[a.id] = a.value_name;
                    });
                    markClean();
                } else {
                    const msg = result?.message || result?.error || 'Erro desconhecido';
                    toast('Erro ao salvar: ' + msg, 'error');
                }
            } catch (err) {
                toast('Falha na requisição: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-floppy"></i> Salvar alterações';
                $('btn-save-all').disabled = isDirty ? false : true;
            }
        }

        // ── AI actions ───────────────────────────────────
        async function aiOptimizeTitle() {
            const btn = $('btn-ai-title');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const data = await requestJson(`/api/seo/technical-sheet/items/${ITEM_ID}/optimize-title`, {
                    method: 'POST',
                });
                const suggestions = data?.suggestions ?? data?.optimized_titles ?? [];
                if (suggestions.length > 0) {
                    const container = $('title-suggestions');
                    container.classList.remove('d-none');
                    container.innerHTML = `<p class="text-muted mb-1" style="font-size:.8rem;">Sugestões da IA — clique para aplicar:</p>` +
                        suggestions.slice(0, 3).map(s => `
                        <div class="alert alert-secondary py-1 px-2 mb-1 cursor-pointer suggestion-pick"
                             style="font-size:.85rem;cursor:pointer;" data-value="${escHtml(s)}">
                            ${escHtml(s)}
                        </div>
                    `).join('');
                    container.querySelectorAll('.suggestion-pick').forEach(el => {
                        el.addEventListener('click', () => {
                            $('field-title').value = el.dataset.value;
                            updateCharCount('field-title', 'title-chars', 60);
                            markDirty();
                            container.classList.add('d-none');
                        });
                    });
                } else {
                    toast('Nenhuma sugestão de título disponível.', 'warning');
                }
            } catch (err) {
                toast('Erro ao otimizar título: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="ai-badge">IA</span>';
            }
        }

        async function aiOptimizeDescription() {
            const btn = $('btn-ai-description');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Gerando…';
            try {
                const data = await requestJson(`/api/seo/technical-sheet/items/${ITEM_ID}/optimize-description`, {
                    method: 'POST',
                });
                const desc = data?.optimized_description ?? data?.description ?? '';
                if (desc) {
                    $('field-description').value = desc;
                    updateDescChars();
                    markDirty();
                    toast('Descrição otimizada pela IA.');
                } else {
                    toast('Nenhuma sugestão de descrição.', 'warning');
                }
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="ai-badge">IA</span> Otimizar descrição';
            }
        }

        async function aiSmartFill() {
            const btn = $('btn-ai-attributes');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preenchendo…';
            try {
                const data = await requestJson(`/api/seo/technical-sheet/items/${ITEM_ID}/smart-fill`, {
                    method: 'POST',
                });
                const filled = data?.filled ?? data?.attributes ?? [];
                let count = 0;
                filled.forEach(attr => {
                    const el = document.getElementById(`attr-${attr.id}`);
                    if (el && el.value !== attr.value_name) {
                        el.value = attr.value_name || '';
                        el.classList.add('border-success');
                        count++;
                    }
                });
                if (count > 0) {
                    markDirty();
                    toast(`${count} atributo(s) preenchido(s) pela IA.`);
                } else {
                    toast('Nenhum atributo novo para preencher.', 'warning');
                }
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="ai-badge">IA</span> Preencher lacunas';
            }
        }

        // ── discard ──────────────────────────────────────
        function discardChanges() {
            $('field-title').value = originalTitle;
            updateCharCount('field-title', 'title-chars', 60);
            $('field-description').value = originalDescription;
            updateDescChars();
            document.querySelectorAll('.attr-input').forEach(el => {
                el.value = originalAttributes[el.dataset.attrId] ?? '';
                el.classList.remove('border-success');
            });
            const container = $('title-suggestions');
            if (container) container.classList.add('d-none');
            markClean();
            toast('Alterações descartadas.', 'warning');
        }

        // ── bind events ──────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            loadItem();

            ['btn-save-all', 'btn-save-bottom'].forEach(id => {
                $(id)?.addEventListener('click', saveChanges);
            });
            $('btn-discard')?.addEventListener('click', discardChanges);
            $('btn-ai-title')?.addEventListener('click', aiOptimizeTitle);
            $('btn-ai-description')?.addEventListener('click', aiOptimizeDescription);
            $('btn-ai-attributes')?.addEventListener('click', aiSmartFill);

            window.addEventListener('beforeunload', e => {
                if (isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        });
    })();
</script>
