<?php

declare(strict_types=1);

$title     = $pageTitle  ?? 'Wizard de Clonagem por Concorrente';
$subtitle  = 'Selecione uma loja concorrente, filtre anúncios e clone em massa com segurança';
$activePage = $activePage ?? 'catalog-clone-wizard';
$headerButtons = '
    <a href="/dashboard/catalog/clone-batch" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Clone em Lote
    </a>
';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<style>
   /* ── Wizard steps bar ── */
   .wizard-steps {
      display: flex;
      align-items: center;
      gap: 0;
      margin-bottom: 2rem;
   }

   .ws-item {
      display: flex;
      align-items: center;
      flex: 1;
   }

   .ws-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 2px solid #dee2e6;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .9rem;
      background: #fff;
      color: #6c757d;
      flex-shrink: 0;
      transition: all .25s;
   }

   .ws-label {
      font-size: .75rem;
      color: #6c757d;
      margin-left: .5rem;
      white-space: nowrap;
   }

   .ws-connector {
      flex: 1;
      height: 2px;
      background: #dee2e6;
      margin: 0 .5rem;
   }

   .ws-item.done .ws-circle {
      background: #198754;
      border-color: #198754;
      color: #fff;
   }

   .ws-item.active .ws-circle {
      background: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
   }

   .ws-item.done .ws-label {
      color: #198754;
   }

   .ws-item.active .ws-label {
      color: #0d6efd;
      font-weight: 600;
   }

   /* ── Panels ── */
   .wizard-panel {
      display: none;
   }

   .wizard-panel.active {
      display: block;
   }

   /* ── Seller card ── */
   .seller-card {
      border: 1px solid #dee2e6;
      border-radius: 12px;
      padding: 1.5rem;
      background: #fff;
   }

   .reputation-bar {
      height: 8px;
      border-radius: 4px;
      background: #e9ecef;
      overflow: hidden;
   }

   .reputation-fill {
      height: 100%;
      border-radius: 4px;
      background: #198754;
   }

   /* ── Browser grid ── */
   .browser-grid {
      display: flex;
      gap: 1rem;
      min-height: 420px;
   }

   .facets-panel {
      flex: 0 0 220px;
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
   }

   .items-panel {
      flex: 1;
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
   }

   .panel-header {
      padding: .75rem 1rem;
      border-bottom: 1px solid #dee2e6;
      background: #f8f9fa;
      font-size: .8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6c757d;
   }

   .panel-body {
      flex: 1;
      overflow-y: auto;
   }

   .facet-row {
      padding: .6rem 1rem;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: .875rem;
      transition: background .15s;
   }

   .facet-row:hover {
      background: #f8f9fa;
   }

   .facet-row.active {
      background: #e7f1ff;
      border-left: 3px solid #0d6efd;
      font-weight: 600;
   }

   .facet-badge {
      font-size: .7rem;
      background: #e9ecef;
      padding: 1px 7px;
      border-radius: 10px;
   }

   .item-row {
      padding: .6rem 1rem;
      border-bottom: 1px solid #f0f0f0;
      display: flex;
      align-items: center;
      gap: .75rem;
      cursor: pointer;
      font-size: .875rem;
      transition: background .15s;
   }

   .item-row:hover {
      background: #f8f9fa;
   }

   .item-row.selected {
      background: #e7f1ff;
   }

   .item-thumb {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 4px;
      flex-shrink: 0;
   }

   .item-title {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
   }

   .item-price {
      white-space: nowrap;
      font-size: .8rem;
      color: #198754;
      font-weight: 600;
   }

   .pagination-row {
      padding: .5rem 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid #dee2e6;
      background: #f8f9fa;
      font-size: .8rem;
   }

   /* ── Selection bar ── */
   .selection-bar {
      background: #0d6efd;
      color: #fff;
      border-radius: 8px;
      padding: .6rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
   }

   /* ── Summary table ── */
   .summary-table td {
      vertical-align: middle;
   }

   /* ── Progress ── */
   .job-progress-card {
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 1.5rem;
      background: #fff;
   }
</style>

<!-- Wizard Steps -->
<div class="wizard-steps" id="wizardSteps">
   <div class="ws-item active" id="ws1">
      <div class="ws-circle">1</div>
      <span class="ws-label">Buscar Vendedor</span>
   </div>
   <div class="ws-connector"></div>
   <div class="ws-item" id="ws2">
      <div class="ws-circle">2</div>
      <span class="ws-label">Selecionar Anúncios</span>
   </div>
   <div class="ws-connector"></div>
   <div class="ws-item" id="ws3">
      <div class="ws-circle">3</div>
      <span class="ws-label">Configurar Clone</span>
   </div>
   <div class="ws-connector"></div>
   <div class="ws-item" id="ws4">
      <div class="ws-circle">4</div>
      <span class="ws-label">Confirmar</span>
   </div>
</div>

<!-- ══════════ STEP 1 — Buscar Vendedor ══════════ -->
<div class="wizard-panel active" id="panel1">
   <div class="card shadow-sm">
      <div class="card-body">
         <h5 class="card-title mb-3"><i class="bi bi-search"></i> Localizar Vendedor no Mercado Livre</h5>
         <div class="row g-3 align-items-end">
            <div class="col-md-6">
               <label class="form-label fw-semibold">Vendedor (ID, Nickname ou URL do perfil)</label>
               <input type="text" class="form-control" id="sellerIdInput"
                  placeholder="Ex: 123456789 ou LOJA_EXEMPLO ou https://www.mercadolivre.com.br/perfil/...">
               <small class="form-text text-muted">
                  Aceita ID numérico, nickname (ex: LOJA_EXEMPLO) ou URL do perfil/loja do Mercado Livre.
               </small>
            </div>
            <div class="col-md-auto">
               <button class="btn btn-primary" id="btnSearchSeller">
                  <i class="bi bi-search"></i> Buscar Vendedor
               </button>
            </div>
         </div>

         <div id="sellerError" class="alert alert-danger mt-3 d-none"></div>

         <div id="sellerCard" class="seller-card mt-4 d-none">
            <div class="row align-items-center g-3">
               <div class="col-md-auto">
                  <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                     style="width:64px;height:64px;font-size:1.5rem;font-weight:700;" id="sellerAvatar">?</div>
               </div>
               <div class="col">
                  <h5 class="mb-1" id="sellerNickEl">—</h5>
                  <div class="d-flex gap-3 text-muted small">
                     <span><i class="bi bi-box-seam"></i> <span id="sellerTotalItems">0</span> anúncios ativos</span>
                     <span><i class="bi bi-star-fill text-warning"></i> <span id="sellerRep">—</span></span>
                  </div>
                  <div class="reputation-bar mt-2" style="max-width:200px;">
                     <div class="reputation-fill" id="sellerRepBar" style="width:0%;"></div>
                  </div>
               </div>
               <div class="col-md-auto">
                  <button class="btn btn-success" id="btnBrowseItems">
                     <i class="bi bi-grid"></i> Ver Anúncios <i class="bi bi-arrow-right"></i>
                  </button>
               </div>
            </div>
            <hr>
            <div class="row g-3">
               <div class="col-md-6">
                  <p class="mb-1 fw-semibold small text-muted text-uppercase">Top Categorias</p>
                  <ul class="list-unstyled mb-0" id="sellerTopCats"></ul>
               </div>
               <div class="col-md-6">
                  <p class="mb-1 fw-semibold small text-muted text-uppercase">Top Marcas</p>
                  <ul class="list-unstyled mb-0" id="sellerTopBrands"></ul>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<!-- ══════════ STEP 2 — Selecionar Anúncios ══════════ -->
<div class="wizard-panel" id="panel2">
   <!-- Selection bar -->
   <div class="selection-bar" id="selectionBar">
      <span><i class="bi bi-check2-square"></i> <span id="selectedCount">0</span> anúncios selecionados</span>
      <div class="d-flex gap-2">
         <button class="btn btn-sm btn-light" id="btnSelectAll">Todos desta página</button>
         <button class="btn btn-sm btn-info text-white" id="btnSelectAllSeller" title="Selecionar TODOS os anúncios do vendedor (sem navegar páginas)">
            <i class="bi bi-check2-all"></i> Todos do vendedor
         </button>
         <button class="btn btn-sm btn-outline-light" id="btnClearAll">Limpar</button>
         <button class="btn btn-sm btn-warning text-dark" id="btnConfigureClone" disabled>
            Configurar Clone <i class="bi bi-arrow-right"></i>
         </button>
      </div>
   </div>

   <!-- Search bar -->
   <div class="d-flex gap-2 mb-3">
      <input type="text" class="form-control" id="itemSearchInput" placeholder="Buscar por título...">
      <button class="btn btn-outline-secondary" id="btnItemSearch"><i class="bi bi-search"></i></button>
   </div>

   <!-- Browser grid -->
   <div class="browser-grid">
      <div class="facets-panel">
         <div class="panel-header">Marcas</div>
         <div class="panel-body" id="brandList"></div>
         <div class="panel-header border-top" style="margin-top:auto;">Categorias</div>
         <div class="panel-body" id="catList" style="max-height:180px;"></div>
      </div>
      <div class="items-panel">
         <div class="panel-header d-flex justify-content-between align-items-center">
            <span id="itemsCountLabel">Anúncios</span>
            <div class="spinner-border spinner-border-sm text-primary d-none" id="itemsSpinner" role="status"></div>
         </div>
         <div class="panel-body" id="itemList"></div>
         <div class="pagination-row" id="paginationRow">
            <button class="btn btn-sm btn-outline-secondary" id="btnPrevPage" disabled><i class="bi bi-chevron-left"></i></button>
            <span id="pageInfo">Pág. 1</span>
            <button class="btn btn-sm btn-outline-secondary" id="btnNextPage" disabled><i class="bi bi-chevron-right"></i></button>
         </div>
      </div>
   </div>
   <div class="mt-3 d-flex justify-content-between">
      <button class="btn btn-outline-secondary" id="btnBackToStep1"><i class="bi bi-arrow-left"></i> Voltar</button>
   </div>
</div>

<!-- ══════════ STEP 3 — Configurar Clone ══════════ -->
<div class="wizard-panel" id="panel3">
   <div class="card shadow-sm">
      <div class="card-body">
         <h5 class="card-title mb-4"><i class="bi bi-gear"></i> Configurar Clonagem</h5>
         <div class="row g-4">
            <div class="col-md-6">
               <label class="form-label fw-semibold">Conta Destino</label>
               <select class="form-select" id="targetAccountSelect">
                  <option value="">— Selecione —</option>
                  <?php foreach (($accounts ?? []) as $acc): ?>
                     <option value="<?= htmlspecialchars((string)$acc['id']) ?>">
                        <?= htmlspecialchars($acc['nickname'] ?? $acc['ml_user_id'] ?? '') ?>
                     </option>
                  <?php endforeach; ?>
               </select>
            </div>
            <div class="col-md-6">
               <label class="form-label fw-semibold">Template (opcional)</label>
               <input type="text" class="form-control" id="templateSlug" placeholder="slug-do-template">
            </div>
            <div class="col-md-6">
               <label class="form-label fw-semibold">Estratégia de Preço</label>
               <select class="form-select" id="priceStrategy">
                  <option value="copy">Copiar preço original</option>
                  <option value="markup">Markup (%)</option>
                  <option value="markdown">Desconto (%)</option>
                  <option value="competitive">Mais barato que concorrente</option>
                  <option value="fixed">Preço fixo (R$)</option>
               </select>
            </div>
            <div class="col-md-6" id="priceValueWrap" style="display:none;">
               <label class="form-label fw-semibold" id="priceValueLabel">Valor (%)</label>
               <input type="number" class="form-control" id="priceValue" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-6">
               <label class="form-label fw-semibold">Status Inicial</label>
               <select class="form-select" id="initialStatus">
                  <option value="paused">Pausado (recomendado)</option>
                  <option value="active">Ativo</option>
               </select>
            </div>
            <div class="col-12">
               <label class="form-label fw-semibold">Opções de Conteúdo</label>
               <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" id="includeDescription">
                  <label class="form-check-label" for="includeDescription">
                     Copiar descrição original
                     <span class="badge bg-warning text-dark ms-1">⚠ Risco de duplicidade</span>
                  </label>
               </div>
               <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="includePictures">
                  <label class="form-check-label" for="includePictures">
                     Copiar fotos originais
                     <span class="badge bg-warning text-dark ms-1">⚠ Risco de duplicidade</span>
                  </label>
               </div>
               <div class="alert alert-warning small mt-2 d-none" id="guardrailWarning">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                  Atenção: copiar descrição/fotos pode violar as políticas do Mercado Livre e resultar em suspensão de anúncios.
               </div>
            </div>
            <div class="col-12 d-flex justify-content-between">
               <button class="btn btn-outline-secondary" id="btnBackToStep2">
                  <i class="bi bi-arrow-left"></i> Voltar
               </button>
               <button class="btn btn-primary" id="btnPreviewAndConfirm">
                  Revisar e Confirmar <i class="bi bi-arrow-right"></i>
               </button>
            </div>
         </div>
      </div>
   </div>
</div>

<!-- ══════════ STEP 4 — Confirmar / Executar ══════════ -->
<div class="wizard-panel" id="panel4">
   <!-- Summary (before job start) -->
   <div id="summaryView">
      <div class="card shadow-sm mb-4">
         <div class="card-body">
            <h5 class="card-title"><i class="bi bi-list-check"></i> Resumo da Clonagem</h5>
            <table class="table summary-table">
               <tbody>
                  <tr>
                     <th style="width:200px;">Vendedor origem</th>
                     <td id="summNick">—</td>
                  </tr>
                  <tr>
                     <th>Anúncios selecionados</th>
                     <td id="summCount">0</td>
                  </tr>
                  <tr>
                     <th>Conta destino</th>
                     <td id="summAccount">—</td>
                  </tr>
                  <tr>
                     <th>Estratégia de preço</th>
                     <td id="summPrice">—</td>
                  </tr>
                  <tr>
                     <th>Status inicial</th>
                     <td id="summStatus">—</td>
                  </tr>
                  <tr>
                     <th>Incluir descrição</th>
                     <td id="summDesc">Não</td>
                  </tr>
                  <tr>
                     <th>Incluir fotos</th>
                     <td id="summPics">Não</td>
                  </tr>
               </tbody>
            </table>
            <div class="d-flex justify-content-between mt-3">
               <button class="btn btn-outline-secondary" id="btnBackToStep3">
                  <i class="bi bi-arrow-left"></i> Voltar
               </button>
               <button class="btn btn-success btn-lg" id="btnExecuteClone">
                  <i class="bi bi-play-circle"></i> Iniciar Clonagem
               </button>
            </div>

            <!-- Validation results (shown before executing) -->
            <div id="validationResult" class="mt-3 d-none">
               <div id="validationErrors" class="d-none"></div>
               <div id="validationWarnings" class="d-none"></div>
            </div>
         </div>
      </div>
   </div>

   <!-- Progress (after job start) -->
   <div id="progressView" class="d-none">
      <div class="job-progress-card">
         <h5><i class="bi bi-hourglass-split text-primary"></i> Processando Clonagem…</h5>
         <p class="text-muted small">Job ID: <code id="jobIdEl">—</code></p>
         <div class="progress mb-3" style="height:20px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
               id="jobProgressBar" style="width:0%;">0%</div>
         </div>
         <div class="d-flex gap-4 mb-3 small">
            <span><i class="bi bi-check2 text-success"></i> Clonados: <strong id="jobDone">0</strong></span>
            <span><i class="bi bi-x text-danger"></i> Erros: <strong id="jobErrors">0</strong></span>
            <span><i class="bi bi-hourglass text-warning"></i> Total: <strong id="jobTotal">0</strong></span>
         </div>
         <div id="jobStatusMsg" class="alert alert-info small">Aguardando início…</div>
         <div id="jobDoneActions" class="d-none mt-3">
            <div class="d-flex gap-2 mb-3">
               <a href="/dashboard/catalog/clone-monitoring" class="btn btn-outline-primary">
                  <i class="bi bi-graph-up"></i> Ver Monitoramento
               </a>
               <button class="btn btn-outline-info" id="btnShowResults">
                  <i class="bi bi-list-ul"></i> Ver Detalhes
               </button>
               <button class="btn btn-outline-secondary" id="btnNewWizard">
                  <i class="bi bi-plus-circle"></i> Novo Wizard
               </button>
            </div>
            <!-- Item results table (toggled by btnShowResults) -->
            <div id="jobResultsPanel" class="d-none">
               <h6 class="mb-2"><i class="bi bi-list-check"></i> Resultado por Item</h6>
               <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                  <table class="table table-sm table-hover mb-0" id="jobResultsTable">
                     <thead class="table-light sticky-top">
                        <tr>
                           <th style="width: 100px;">Status</th>
                           <th>Item Origem</th>
                           <th>Item Destino</th>
                           <th>Detalhes</th>
                        </tr>
                     </thead>
                     <tbody></tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<script>
   (function() {
      'use strict';

      var state = {
         step: 1,
         sellerId: '',
         sellerNick: '',
         sellerTotalItems: 0,
         selectedItems: new Map(), // mlbId -> {title, price}
         allItems: [],
         brandFacets: [],
         catFacets: [],
         activeBrand: '',
         activeCat: '',
         searchQ: '',
         currentOffset: 0,
         pageSize: 50,
         totalItems: 0,
         jobId: null,
         pollTimer: null
      };

      // ── helpers ──────────────────────────────────────────────
      function fmt(n) {
         return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
         }).format(n);
      }

      function api(url, opts) {
         opts = opts || {};
         opts.headers = Object.assign({
            'Content-Type': 'application/json'
         }, opts.headers || {});
         return fetch(url, opts).then(function(r) {
            return r.json();
         });
      }

      function qs(id) {
         return document.getElementById(id);
      }

      function show(id) {
         var el = qs(id);
         if (el) el.classList.remove('d-none');
      }

      function hide(id) {
         var el = qs(id);
         if (el) el.classList.add('d-none');
      }

      // ── Step navigation ───────────────────────────────────────
      function goStep(n) {
         state.step = n;
         for (var i = 1; i <= 4; i++) {
            var panel = qs('panel' + i);
            var ws = qs('ws' + i);
            if (!panel || !ws) continue;
            panel.classList.toggle('active', i === n);
            ws.classList.remove('done', 'active');
            if (i < n) ws.classList.add('done');
            if (i === n) ws.classList.add('active');
            // update circle to checkmark for done steps
            var circle = ws.querySelector('.ws-circle');
            if (circle) circle.innerHTML = (i < n) ? '<i class="bi bi-check2"></i>' : String(i);
         }
      }

      // ── STEP 1 — Search seller ────────────────────────────────

      /**
       * Detecta se o input é um ID numérico puro (>= 4 dígitos).
       * Se sim, pode ir direto para /summary sem passar pelo search.
       */
      function isNumericSellerId(input) {
         return /^\d{4,}$/.test(input);
      }

      /**
       * Popula o card do seller a partir dos dados retornados.
       * Aceita tanto resposta de /search quanto de /summary.
       */
      function populateSellerCard(info) {
         state.sellerId = String(info.seller_id || '');
         state.sellerNick = String(info.nickname || info.seller_nickname || state.sellerId);
         state.sellerTotalItems = parseInt(info.total_items || 0, 10);

         qs('sellerAvatar').textContent = (state.sellerNick[0] || '?').toUpperCase();
         qs('sellerNickEl').textContent = state.sellerNick;
         qs('sellerTotalItems').textContent = state.sellerTotalItems.toLocaleString('pt-BR');

         var repLevel = String(info.seller_reputation && info.seller_reputation.level_id || '');
         var repMap = {
            '1_red': 5,
            '2_orange': 25,
            '3_yellow': 50,
            '4_light_green': 75,
            '5_green': 100
         };
         var repPct = repMap[repLevel] || 0;
         qs('sellerRep').textContent = repLevel.replace(/_/g, ' ') || 'N/D';
         qs('sellerRepBar').style.width = repPct + '%';

         // Top categories e brands (vêm do /summary, podem estar ausentes no /search)
         var cats = (info.top_categories || []).slice(0, 5);
         var brands = (info.top_brands || []).slice(0, 5);
         qs('sellerTopCats').innerHTML = cats.map(function(c) {
            return '<li class="small text-muted"><i class="bi bi-tag"></i> ' + c.name + ' (' + c.count + ')</li>';
         }).join('') || '<li class="small text-muted">—</li>';
         qs('sellerTopBrands').innerHTML = brands.map(function(b) {
            return '<li class="small text-muted"><i class="bi bi-bookmark"></i> ' + b.name + ' (' + b.count + ')</li>';
         }).join('') || '<li class="small text-muted">—</li>';

         show('sellerCard');
      }

      qs('btnSearchSeller').addEventListener('click', function() {
         var input = String(qs('sellerIdInput').value).trim();
         if (!input) return;
         hide('sellerError');
         hide('sellerCard');
         this.disabled = true;
         var self = this;

         // Se for ID numérico puro, ir direto para /summary (mais completo)
         if (isNumericSellerId(input)) {
            api('/api/catalog/clone/source/seller/' + encodeURIComponent(input) + '/summary')
               .then(function(data) {
                  if (data.error) throw new Error(data.message || data.error);
                  var info = data.data || data;
                  populateSellerCard(info);
               })
               .catch(function(err) {
                  qs('sellerError').textContent = 'Erro: ' + (err.message || 'Vendedor não encontrado');
                  show('sellerError');
               })
               .finally(function() {
                  self.disabled = false;
               });
            return;
         }

         // Para nickname ou URL, usar o endpoint de busca primeiro
         api('/api/catalog/clone/source/seller/search?q=' + encodeURIComponent(input))
            .then(function(data) {
               if (data.error) throw new Error(data.message || data.error);
               var info = data.data || data;
               // O /search retorna dados básicos — popular o card imediatamente
               populateSellerCard(info);

               // Em background, buscar o summary completo para top_categories e top_brands
               if (info.seller_id) {
                  api('/api/catalog/clone/source/seller/' + encodeURIComponent(info.seller_id) + '/summary')
                     .then(function(summaryData) {
                        var summary = summaryData.data || summaryData;
                        if (!summary.error) {
                           // Atualizar card com dados mais completos do summary
                           populateSellerCard(summary);
                        }
                     })
                     .catch(function() {
                        /* summary falhou — card básico já exibido */
                     });
               }
            })
            .catch(function(err) {
               qs('sellerError').textContent = 'Erro: ' + (err.message || 'Vendedor não encontrado');
               show('sellerError');
            })
            .finally(function() {
               self.disabled = false;
            });
      });

      // Habilitar Enter no campo de busca
      qs('sellerIdInput').addEventListener('keypress', function(e) {
         if (e.key === 'Enter') {
            e.preventDefault();
            qs('btnSearchSeller').click();
         }
      });

      qs('btnBrowseItems').addEventListener('click', function() {
         state.currentOffset = 0;
         state.activeBrand = '';
         state.activeCat = '';
         state.searchQ = '';
         goStep(2);
         loadItems();
      });

      // ── STEP 2 — Browse & select items ───────────────────────
      function loadItems() {
         show('itemsSpinner');
         var url = '/api/catalog/clone/source/seller/' + encodeURIComponent(state.sellerId) + '/items';
         var params = new URLSearchParams({
            offset: String(state.currentOffset),
            limit: String(state.pageSize)
         });
         if (state.activeBrand) params.set('brand', state.activeBrand);
         if (state.activeCat) params.set('category', state.activeCat);
         if (state.searchQ) params.set('q', state.searchQ);

         api(url + '?' + params.toString())
            .then(function(data) {
               var res = data.data || data;
               state.allItems = res.items || [];
               state.totalItems = parseInt(res.total || 0, 10);
               state.brandFacets = res.facets && res.facets.brands ? res.facets.brands : [];
               state.catFacets = res.facets && res.facets.categories ? res.facets.categories : [];

               renderFacets();
               renderItems();
               renderPagination();
            })
            .catch(function(err) {
               console.error('[Wizard] loadItems error', err);
            })
            .finally(function() {
               hide('itemsSpinner');
            });
      }

      function renderFacets() {
         var brandHtml = state.brandFacets.map(function(f) {
            var active = (state.activeBrand === f.id) ? ' active' : '';
            return '<div class="facet-row' + active + '" data-brand="' + escHtml(f.id) + '">' +
               escHtml(f.name) +
               '<span class="facet-badge">' + f.count + '</span></div>';
         }).join('');
         qs('brandList').innerHTML = brandHtml || '<div class="p-3 text-muted small">Sem marcas</div>';

         var catHtml = state.catFacets.map(function(f) {
            var active = (state.activeCat === f.id) ? ' active' : '';
            return '<div class="facet-row' + active + '" data-cat="' + escHtml(f.id) + '">' +
               escHtml(f.name) +
               '<span class="facet-badge">' + f.count + '</span></div>';
         }).join('');
         qs('catList').innerHTML = catHtml || '<div class="p-3 text-muted small">Sem categorias</div>';

         // click events
         qs('brandList').querySelectorAll('.facet-row').forEach(function(el) {
            el.addEventListener('click', function() {
               state.activeBrand = (state.activeBrand === this.dataset.brand) ? '' : String(this.dataset.brand);
               state.currentOffset = 0;
               loadItems();
            });
         });
         qs('catList').querySelectorAll('.facet-row').forEach(function(el) {
            el.addEventListener('click', function() {
               state.activeCat = (state.activeCat === this.dataset.cat) ? '' : String(this.dataset.cat);
               state.currentOffset = 0;
               loadItems();
            });
         });
      }

      function escHtml(str) {
         return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      }

      /**
       * Formata ML item ID para URL: MLB1234567890 → MLB-1234567890
       * Insere dash entre prefixo de site (3 letras) e parte numérica.
       */
      function formatMlItemId(id) {
         var s = String(id);
         var m = s.match(/^([A-Z]{3})(\d+)$/);
         return m ? m[1] + '-' + m[2] : s;
      }

      function renderItems() {
         if (!state.allItems.length) {
            qs('itemList').innerHTML = '<div class="p-4 text-center text-muted">Nenhum anúncio encontrado</div>';
            return;
         }
         var html = state.allItems.map(function(item) {
            var mlbId = String(item.id || '');
            var title = String(item.title || '');
            var price = parseFloat(item.price || 0);
            var thumb = String(item.thumbnail || '');
            var checked = state.selectedItems.has(mlbId);
            return '<div class="item-row' + (checked ? ' selected' : '') + '" data-id="' + escHtml(mlbId) + '">' +
               '<input type="checkbox" class="form-check-input flex-shrink-0"' + (checked ? ' checked' : '') + '>' +
               (thumb ? '<img src="' + escHtml(thumb) + '" class="item-thumb" loading="lazy">' : '') +
               '<span class="item-title" title="' + escHtml(title) + '">' + escHtml(title) + '</span>' +
               '<span class="item-price">' + fmt(price) + '</span>' +
               '</div>';
         }).join('');
         qs('itemList').innerHTML = html;

         qs('itemList').querySelectorAll('.item-row').forEach(function(row) {
            row.addEventListener('click', function() {
               var mlbId = String(this.dataset.id);
               var item = state.allItems.find(function(i) {
                  return String(i.id) === mlbId;
               });
               if (!item) return;
               if (state.selectedItems.has(mlbId)) {
                  state.selectedItems.delete(mlbId);
               } else {
                  state.selectedItems.set(mlbId, {
                     title: item.title,
                     price: item.price
                  });
               }
               this.classList.toggle('selected', state.selectedItems.has(mlbId));
               var cb = this.querySelector('input[type=checkbox]');
               if (cb) cb.checked = state.selectedItems.has(mlbId);
               updateSelectionBar();
            });
         });
      }

      function renderPagination() {
         var page = Math.floor(state.currentOffset / state.pageSize) + 1;
         var total = Math.ceil(state.totalItems / state.pageSize) || 1;
         qs('pageInfo').textContent = 'Pág. ' + page + ' / ' + total;
         qs('btnPrevPage').disabled = (state.currentOffset === 0);
         qs('btnNextPage').disabled = (state.currentOffset + state.pageSize >= state.totalItems);
         qs('itemsCountLabel').textContent = state.totalItems.toLocaleString('pt-BR') + ' anúncios';
      }

      function updateSelectionBar() {
         var count = state.selectedItems.size;
         qs('selectedCount').textContent = count;
         qs('btnConfigureClone').disabled = (count === 0);
      }

      qs('btnPrevPage').addEventListener('click', function() {
         state.currentOffset = Math.max(0, state.currentOffset - state.pageSize);
         loadItems();
      });
      qs('btnNextPage').addEventListener('click', function() {
         state.currentOffset += state.pageSize;
         loadItems();
      });
      qs('btnSelectAll').addEventListener('click', function() {
         state.allItems.forEach(function(item) {
            state.selectedItems.set(String(item.id), {
               title: item.title,
               price: item.price
            });
         });
         renderItems();
         updateSelectionBar();
      });

      // ── Select ALL seller items (across all pages) ─────────────
      qs('btnSelectAllSeller').addEventListener('click', function() {
         var self = this;
         if (!state.sellerId) return;

         var proceed = confirm(
            'Selecionar TODOS os ' + state.sellerTotalItems.toLocaleString('pt-BR') +
            ' anúncios do vendedor?\n\nIsso pode levar alguns segundos para vendedores com muitos itens.'
         );
         if (!proceed) return;

         self.disabled = true;
         self.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Carregando…';
         show('itemsSpinner');

         var collected = [];
         var offset = 0;
         var pageLimit = 50;

         function fetchPage() {
            var url = '/api/catalog/clone/source/seller/' + encodeURIComponent(state.sellerId) + '/items';
            var params = new URLSearchParams({
               offset: String(offset),
               limit: String(pageLimit)
            });
            if (state.activeBrand) params.set('brand', state.activeBrand);
            if (state.activeCat) params.set('category', state.activeCat);

            api(url + '?' + params.toString())
               .then(function(data) {
                  var res = data.data || data;
                  var items = res.items || [];
                  collected = collected.concat(items);
                  offset += items.length;

                  // Update progress
                  self.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' +
                     collected.length + '/' + (res.total || '?');

                  if (items.length < pageLimit || collected.length >= (res.total || Infinity)) {
                     // Done — add all to selection
                     collected.forEach(function(item) {
                        state.selectedItems.set(String(item.id), {
                           title: item.title,
                           price: item.price
                        });
                     });
                     renderItems();
                     updateSelectionBar();
                     self.disabled = false;
                     self.innerHTML = '<i class="bi bi-check2-all"></i> Todos do vendedor';
                     hide('itemsSpinner');
                  } else {
                     // Rate-limit: wait 300ms before next page
                     setTimeout(fetchPage, 300);
                  }
               })
               .catch(function(err) {
                  console.error('[Wizard] selectAll error', err);
                  alert('Erro ao carregar todos os itens: ' + (err.message || 'Tente novamente'));
                  self.disabled = false;
                  self.innerHTML = '<i class="bi bi-check2-all"></i> Todos do vendedor';
                  hide('itemsSpinner');
               });
         }

         fetchPage();
      });
      qs('btnClearAll').addEventListener('click', function() {
         state.selectedItems.clear();
         renderItems();
         updateSelectionBar();
      });
      qs('btnItemSearch').addEventListener('click', function() {
         state.searchQ = String(qs('itemSearchInput').value).trim();
         state.currentOffset = 0;
         loadItems();
      });
      qs('itemSearchInput').addEventListener('keypress', function(e) {
         if (e.key === 'Enter') qs('btnItemSearch').click();
      });
      qs('btnConfigureClone').addEventListener('click', function() {
         goStep(3);
      });
      qs('btnBackToStep1').addEventListener('click', function() {
         goStep(1);
      });

      // ── STEP 3 — Configure ────────────────────────────────────
      qs('priceStrategy').addEventListener('change', function() {
         var needsValue = ['markup', 'markdown', 'competitive', 'fixed'].indexOf(this.value) !== -1;
         qs('priceValueWrap').style.display = needsValue ? '' : 'none';
         var labelMap = {
            markup: 'Markup (%)',
            markdown: 'Desconto (%)',
            competitive: 'Diferença (%)',
            fixed: 'Preço fixo (R$)'
         };
         qs('priceValueLabel').textContent = labelMap[this.value] || 'Valor';
      });

      function checkGuardrail() {
         var warn = qs('includeDescription').checked || qs('includePictures').checked;
         qs('guardrailWarning').classList.toggle('d-none', !warn);
      }
      qs('includeDescription').addEventListener('change', checkGuardrail);
      qs('includePictures').addEventListener('change', checkGuardrail);

      qs('btnBackToStep2').addEventListener('click', function() {
         goStep(2);
      });
      qs('btnPreviewAndConfirm').addEventListener('click', function() {
         var targetId = String(qs('targetAccountSelect').value);
         if (!targetId) {
            alert('Selecione uma conta destino');
            return;
         }
         populateSummary();
         goStep(4);
         runPreValidation();
      });

      /**
       * Roda validação pré-execução ao entrar no step 4.
       * Mostra erros (bloqueantes) e warnings (informativos).
       */
      function runPreValidation() {
         var errorsEl = qs('validationErrors');
         var warningsEl = qs('validationWarnings');
         var resultEl = qs('validationResult');
         var execBtn = qs('btnExecuteClone');

         errorsEl.className = 'd-none';
         warningsEl.className = 'd-none';
         resultEl.className = 'mt-3';

         execBtn.disabled = true;
         execBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validando…';

         var itemIds = Array.from(state.selectedItems.keys());
         var payload = {
            target_account_id: parseInt(qs('targetAccountSelect').value, 10),
            source_seller_id: state.sellerId,
            item_ids: itemIds,
            options: {
               include_description: qs('includeDescription').checked,
               include_pictures: qs('includePictures').checked,
            }
         };

         api('/api/catalog/clone/validate', {
               method: 'POST',
               body: JSON.stringify(payload)
            })
            .then(function(data) {
               var errors = data.errors || [];
               var warnings = data.warnings || [];

               if (errors.length > 0) {
                  errorsEl.className = 'alert alert-danger small';
                  errorsEl.innerHTML = '<strong><i class="bi bi-x-circle"></i> Erros (impeditivos):</strong><ul class="mb-0 mt-1">' +
                     errors.map(function(e) {
                        return '<li>' + escHtml(e) + '</li>';
                     }).join('') +
                     '</ul>';
                  execBtn.disabled = true;
                  execBtn.innerHTML = '<i class="bi bi-x-circle"></i> Corrija os erros acima';
               } else {
                  execBtn.disabled = false;
                  execBtn.innerHTML = '<i class="bi bi-play-circle"></i> Iniciar Clonagem';
               }

               if (warnings.length > 0) {
                  warningsEl.className = 'alert alert-warning small';
                  warningsEl.innerHTML = '<strong><i class="bi bi-exclamation-triangle"></i> Avisos:</strong><ul class="mb-0 mt-1">' +
                     warnings.map(function(w) {
                        return '<li>' + escHtml(w) + '</li>';
                     }).join('') +
                     '</ul>';
               }

               // Show account info if available (usar = em vez de += para evitar acúmulo em race conditions)
               if (data.account && data.account.nickname) {
                  var baseAcctName = qs('targetAccountSelect').options[qs('targetAccountSelect').selectedIndex]
                     ? qs('targetAccountSelect').options[qs('targetAccountSelect').selectedIndex].text
                     : '—';
                  qs('summAccount').textContent = baseAcctName + ' (' + data.account.nickname + ')';
               }

               show('validationResult');
            })
            .catch(function(err) {
               // Validation endpoint failed — allow execution anyway
               console.warn('[Wizard] validation failed, allowing execution:', err);
               execBtn.disabled = false;
               execBtn.innerHTML = '<i class="bi bi-play-circle"></i> Iniciar Clonagem';
               warningsEl.className = 'alert alert-warning small';
               warningsEl.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Não foi possível validar pré-condições. Prossiga com cautela.';
               show('validationResult');
            });
      }

      function populateSummary() {
         var sel = qs('targetAccountSelect');
         var acctName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '—';
         var strat = String(qs('priceStrategy').value);
         var stratMap = {
            copy: 'Copiar preço',
            markup: 'Markup',
            markdown: 'Desconto',
            competitive: 'Mais barato',
            fixed: 'Preço fixo'
         };
         var pv = qs('priceValue').value;
         var stratStr = (stratMap[strat] || strat) + (pv ? ' (' + pv + ')' : '');

         qs('summNick').textContent = state.sellerNick;
         qs('summCount').textContent = state.selectedItems.size.toLocaleString('pt-BR');
         qs('summAccount').textContent = acctName;
         qs('summPrice').textContent = stratStr;
         qs('summStatus').textContent = qs('initialStatus').value === 'active' ? 'Ativo' : 'Pausado';
         qs('summDesc').textContent = qs('includeDescription').checked ? 'Sim ⚠' : 'Não';
         qs('summPics').textContent = qs('includePictures').checked ? 'Sim ⚠' : 'Não';
      }

      qs('btnBackToStep3').addEventListener('click', function() {
         goStep(3);
      });

      // ── STEP 4 — Execute ──────────────────────────────────────
      qs('btnExecuteClone').addEventListener('click', function() {
         var self = this;
         self.disabled = true;

         var itemIds = Array.from(state.selectedItems.keys());
         var targetId = parseInt(qs('targetAccountSelect').value, 10);
         var strat = String(qs('priceStrategy').value);
         var pv = parseFloat(qs('priceValue').value) || 0;
         var initialSt = String(qs('initialStatus').value);
         var inclDesc = qs('includeDescription').checked;
         var inclPics = qs('includePictures').checked;
         var templateSlug = String(qs('templateSlug').value).trim() || null;

         var options = {
            pricing_strategy: strat,
            initial_status: initialSt,
            include_description: inclDesc,
            include_pictures: inclPics
         };
         if (pv) options.price_value = pv;

         var payload = {
            target_account_id: targetId,
            source_seller_id: state.sellerId,
            item_ids: itemIds,
            options: options
         };
         if (templateSlug) payload.template_slug = templateSlug;

         api('/api/catalog/clone/jobs/seller', {
               method: 'POST',
               body: JSON.stringify(payload)
            })
            .then(function(data) {
               if (data.error) throw new Error(data.error);
               var res = data.data || data;
               state.jobId = String(res.job_id || res.id || '');
               qs('jobIdEl').textContent = state.jobId;
               hide('summaryView');
               show('progressView');
               startPolling();
            })
            .catch(function(err) {
               alert('Erro ao criar job: ' + (err.message || 'Tente novamente'));
               self.disabled = false;
            });
      });

      function startPolling() {
         if (state.pollTimer) clearInterval(state.pollTimer);
         state.pollTimer = setInterval(pollJobStatus, 3000);
         pollJobStatus();
      }

      function pollJobStatus() {
         if (!state.jobId) return;
         api('/api/catalog/clone/jobs/' + encodeURIComponent(state.jobId) + '/status')
            .then(function(data) {
               var res = data.data || data;
               // API retorna {status:"success", job:{...}, items:[...]}
               // Os dados reais do job estão aninhados em res.job
               var job = res.job || res;
               var done = parseInt(job.processed_items || job.done || 0, 10);
               var errors = parseInt(job.failed_items || job.error_count || job.errors || 0, 10);
               var total = parseInt(job.total_items || job.total || 0, 10);
               var status = String(job.status || 'pending');
               var pct = total > 0 ? Math.round((done / total) * 100) : 0;

               qs('jobProgressBar').style.width = pct + '%';
               qs('jobProgressBar').textContent = pct + '%';
               qs('jobDone').textContent = done;
               qs('jobErrors').textContent = errors;
               qs('jobTotal').textContent = total;

               var msgEl = qs('jobStatusMsg');
               var statusMsg = {
                  pending: 'Aguardando processamento…',
                  processing: 'Processando anúncios…',
                  completed: 'Clonagem concluída com sucesso!',
                  failed: 'Job falhou. Verifique os logs.'
               };
               msgEl.textContent = statusMsg[status] || 'Status: ' + status;
               msgEl.className = 'alert small ' + (status === 'completed' ? 'alert-success' : status === 'failed' ? 'alert-danger' : 'alert-info');

               if (status === 'completed' || status === 'failed') {
                  clearInterval(state.pollTimer);
                  show('jobDoneActions');
                  qs('jobProgressBar').classList.remove('progress-bar-animated');

                  // Carregar detalhes dos itens processados
                  populateJobResults(res.items || []);
               }
            })
            .catch(function(err) {
               console.error('[Wizard] poll error', err);
            });
      }

      /**
       * Popula a tabela de resultados por item quando o job finaliza.
       */
      function populateJobResults(items) {
         var tbody = qs('jobResultsTable').querySelector('tbody');
         if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sem detalhes disponíveis</td></tr>';
            return;
         }

         var statusBadge = {
            completed: '<span class="badge bg-success">OK</span>',
            failed: '<span class="badge bg-danger">Erro</span>',
            skipped: '<span class="badge bg-secondary">Pulado</span>',
            pending: '<span class="badge bg-warning text-dark">Pendente</span>',
            processing: '<span class="badge bg-info text-white">Processando</span>'
         };

         tbody.innerHTML = items.map(function(item) {
            var st = String(item.status || 'pending');
            var badge = statusBadge[st] || '<span class="badge bg-light text-dark">' + escHtml(st) + '</span>';
            var sourceId = escHtml(String(item.source_item_id || ''));
            // ML item IDs (ex: MLB1234567890) → URL: produto.mercadolivre.com.br/MLB-1234567890
            var targetId = item.target_item_id ?
               '<a href="https://produto.mercadolivre.com.br/' + escHtml(formatMlItemId(item.target_item_id)) + '" target="_blank" rel="noopener">' +
               escHtml(item.target_item_id) + ' <i class="bi bi-box-arrow-up-right"></i></a>' :
               '—';
            var detail = escHtml(String(item.error_message || ''));

            return '<tr>' +
               '<td>' + badge + '</td>' +
               '<td><code>' + sourceId + '</code></td>' +
               '<td>' + targetId + '</td>' +
               '<td class="small text-muted">' + (detail || '—') + '</td>' +
               '</tr>';
         }).join('');
      }

      // Toggle job results panel
      qs('btnShowResults').addEventListener('click', function() {
         var panel = qs('jobResultsPanel');
         var visible = !panel.classList.contains('d-none');
         panel.classList.toggle('d-none', visible);
         this.innerHTML = visible ?
            '<i class="bi bi-list-ul"></i> Ver Detalhes' :
            '<i class="bi bi-eye-slash"></i> Ocultar Detalhes';
      });

      qs('btnNewWizard').addEventListener('click', function() {
         if (state.pollTimer) clearInterval(state.pollTimer);
         // Reset state
         state.step = 1;
         state.sellerId = '';
         state.sellerNick = '';
         state.selectedItems = new Map();
         state.allItems = [];
         state.jobId = null;
         hide('sellerCard');
         hide('sellerError');
         qs('sellerIdInput').value = '';
         hide('progressView');
         show('summaryView');
         qs('jobDoneActions').classList.add('d-none');
         hide('jobResultsPanel');
         hide('validationResult');
         goStep(1);
      });

   })();
</script>
