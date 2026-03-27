<!-- Modal Backlink Analysis -->
<div class="modal fade" id="backlinkAnalysisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-link-45deg"></i> Análise de Backlinks (Traffic Booster)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Selecione o Produto para Analisar</label>
                    <select class="form-select" id="backlink-product-select">
                        <option value="">Carregando produtos...</option>
                    </select>
                </div>

                <div class="text-center my-4" id="backlink-loading" style="display: none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">A IA está analisando oportunidades de link building...</p>
                </div>

                <div id="backlink-results" style="display: none;">
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Análise Concluída!</strong>
                            <div id="backlink-summary"></div>
                        </div>
                    </div>

                    <h6 class="mb-3">🚀 Oportunidades Identificadas</h6>
                    <div class="list-group mb-4" id="backlink-opportunities-list">
                        <!-- Items injected here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="BacklinkAnalyzer.analyze()">
                    <i class="bi bi-magic"></i> Gerar Estratégia
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    const BacklinkAnalyzer = {
        init() {
            this.loadProducts();
        },

        async loadProducts() {
            const select = document.getElementById('backlink-product-select');
            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');
                if (data.results) {
                    select.innerHTML = data.results.map(item => `<option value="${item.id}">${item.title}</option>`).join('');
                }
            } catch (error) {
                console.error('Erro ao produtos:', error);
                select.innerHTML = '<option>Erro ao carregar</option>';
            }
        },

        async analyze() {
            const itemId = document.getElementById('backlink-product-select').value;
            if (!itemId) return;

            document.getElementById('backlink-loading').style.display = 'block';
            document.getElementById('backlink-results').style.display = 'none';

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/backlinks/analyze', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: itemId
                    })
                });

                if (data.success && data.analysis) {
                    this.renderResults(data.analysis);
                } else {
                    alert('Erro na análise: ' + (data.error || 'Desconhecido'));
                }
            } catch (error) {
                alert('Erro de conexão: ' + error.message);
            } finally {
                document.getElementById('backlink-loading').style.display = 'none';
            }
        },

        renderResults(analysis) {
            document.getElementById('backlink-results').style.display = 'block';
            document.getElementById('backlink-summary').textContent =
                `Score de Estratégia: ${analysis.strategy_score}/100 - Dificuldade: ${analysis.difficulty}`;

            const list = document.getElementById('backlink-opportunities-list');
            list.innerHTML = analysis.opportunities.map(opp => `
            <div class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1 text-primary"><i class="bi bi-lightbulb"></i> ${opp.type}</h6>
                    <small class="text-muted">${opp.niche}</small>
                </div>
                <p class="mb-1 text-sm">${opp.pitch}</p>
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('${opp.pitch.replace(/'/g, "\\'")}')">
                        <i class="bi bi-clipboard"></i> Copiar Pitch
                    </button>
                </div>
            </div>
        `).join('');
        }
    };

    window.openBacklinkAnalyzer = function() {
        const modal = new bootstrap.Modal(document.getElementById('backlinkAnalysisModal'));
        modal.show();
        BacklinkAnalyzer.init();
    };
</script>