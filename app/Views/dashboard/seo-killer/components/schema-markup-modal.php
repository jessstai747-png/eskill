<!-- Modal Schema Markup -->
<div class="modal fade" id="schemaMarkupModal" tabindex="-1" aria-labelledby="schemaMarkupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #16A085 0%, #1ABC9C 100%); color: white;">
                <h5 class="modal-title" id="schemaMarkupModalLabel">
                    <i class="bi bi-code-slash"></i> Schema Markup Generator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> O Schema Markup melhora a aparência do seu produto nos resultados de busca do Google (Rich Snippets).
                </div>

                <div class="mb-3">
                    <label class="form-label">Selecione o Produto</label>
                    <select class="form-select" id="schema-product-select" onchange="SchemaMarkup.generate()">
                        <option value="">Selecione...</option>
                    </select>
                </div>

                <div id="schema-result-area" style="display: none;">
                    <h6 class="mb-2">JSON-LD Gerado</h6>
                    <div class="position-relative">
                        <textarea class="form-control" id="schema-code" rows="15" readonly style="font-family: monospace; font-size: 13px; background: #f8f9fa;"></textarea>
                        <button class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2" onclick="SchemaMarkup.copy()">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>

                    <div class="mt-3 text-end">
                        <a href="https://search.google.com/test/rich-results" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-google"></i> Testar no Google Rich Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    const SchemaMarkup = {
        init() {
            this.loadProducts();
        },

        async loadProducts() {
            const select = document.getElementById('schema-product-select');
            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');
                if (data.results) {
                    select.innerHTML = '<option value="">Selecione...</option>' +
                        data.results.map(item => `<option value="${item.id}">${item.title}</option>`).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar produtos:', error);
            }
        },

        async generate() {
            const itemId = document.getElementById('schema-product-select').value;
            if (!itemId) {
                document.getElementById('schema-result-area').style.display = 'none';
                return;
            }

            const textarea = document.getElementById('schema-code');
            textarea.value = 'Gerando...';
            document.getElementById('schema-result-area').style.display = 'block';

            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/schema/${itemId}`);

                if (data.success) {
                    textarea.value = data.json_ld;
                } else {
                    textarea.value = 'Erro: ' + (data.error || 'Falha ao gerar schema');
                }
            } catch (error) {
                textarea.value = 'Erro de conexão: ' + error.message;
            }
        },

        copy() {
            const textarea = document.getElementById('schema-code');
            textarea.select();
            document.execCommand('copy');
            SEOKiller.showSuccess('Código copiado!');
        }
    };

    window.openSchemaMarkup = function() {
        const modal = new bootstrap.Modal(document.getElementById('schemaMarkupModal'));
        modal.show();
        SchemaMarkup.init();
    };
</script>