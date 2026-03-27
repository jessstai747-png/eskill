<!-- PDF Export Modal -->
<div class="modal fade" id="pdfExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-pdf"></i> Exportar Relatório PDF</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Selecione o Tipo de Relatório</label>
                    <select class="form-select mb-3" id="pdf-report-type">
                        <option value="performance">📈 Performance Mensal</option>
                        <option value="history">🕵️ Histórico de Monitoramento</option>
                        <option value="competitor">⚔️ Análise de Concorrente</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Selecione o Produto</label>
                    <select class="form-select" id="pdf-product-select">
                        <option value="">Carregando...</option>
                    </select>
                </div>

                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i> O relatório será gerado e o download iniciará automaticamente.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-danger" onclick="PdfManager.download()">
                    <i class="bi bi-download"></i> Baixar Relatório
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    const PdfManager = {
        init() {
            this.loadProducts();
        },

        async loadProducts() {
            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');
                const select = document.getElementById('pdf-product-select');

                if (data.results) {
                    select.innerHTML = data.results.map(item => `<option value="${item.id}">${item.title}</option>`).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar produtos PDF:', error);
            }
        },

        async download() {
            const type = document.getElementById('pdf-report-type').value;
            const itemId = document.getElementById('pdf-product-select').value;

            if (!itemId) return alert('Selecione um produto');

            const btn = document.querySelector('#pdfExportModal .btn-danger');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
            btn.disabled = true;

            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/export/pdf/${type}/${itemId}`);

                if (data.success && (data.url || data.path)) {
                    // If URL provided, open it
                    const downloadUrl = data.url || data.path;
                    // For MVP if raw path returned we might need a download endpoint proxy, 
                    // but usually exporter returns public URL if stored in public, or we handle stream.
                    // Assuming URL valid:
                    window.open(downloadUrl, '_blank');
                    SEOKiller.showSuccess('Download iniciado!');
                } else {
                    alert('Erro ao gerar PDF: ' + (data.error || 'Desconhecido'));
                }
            } catch (error) {
                alert('Erro de conexão ao gerar PDF');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    };

    function openPdfExport() {
        new bootstrap.Modal(document.getElementById('pdfExportModal')).show();
        PdfManager.init();
    }
</script>