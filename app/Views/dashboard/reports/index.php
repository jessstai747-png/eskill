<?php
$title = 'Relatórios Avançados';
$subtitle = 'Exportação de dados e balanços financeiros';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row g-4">
    <!-- Sales Report Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="bi bi-file-earmark-pdf"></i> Relatório de Vendas</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Totais de vendas, ticket médio e produtos mais vendidos no período.</p>
                <form id="sales-report-form">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small">Início</label>
                            <input type="date" class="form-control form-control-sm" name="start_date" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Fim</label>
                            <input type="date" class="form-control form-control-sm" name="end_date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary w-100 btn-sm" onclick="reportManager.generatePdf('sales')">
                        <i class="bi bi-download"></i> Baixar PDF
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Inventory Report Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-info"><i class="bi bi-box-seam"></i> Valorização de Estoque</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Custo total, valor de venda potencial e lucro projetado do estoque atual.</p>
                <div class="alert alert-light border small text-center">
                    Gera um PDF com o inventário completo (Top 100 itens).
                </div>
                <button type="button" class="btn btn-info text-white w-100 btn-sm mt-3" onclick="reportManager.generatePdf('inventory')">
                    <i class="bi bi-download"></i> Baixar Relatório
                </button>
            </div>
        </div>
    </div>

    <!-- Customer Report Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-warning"><i class="bi bi-people"></i> Melhores Clientes (LTV)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Ranking dos clientes que mais compraram (Receita e Frequência).</p>
                <div class="alert alert-light border small text-center">
                    Lista Top 50 clientes por receita total.
                </div>
                <button type="button" class="btn btn-warning text-dark w-100 btn-sm mt-3" onclick="reportManager.generatePdf('customer')">
                    <i class="bi bi-download"></i> Baixar Relatório
                </button>
            </div>
        </div>
    </div>
    
    <!-- CSV Export Card -->
    <div class="col-12 mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar Dados (CSV/Excel)</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <p class="text-muted mb-0">Exporte dados brutos para análise externa.</p>
                    </div>
                    <div class="col-md-8 text-end">
                        <button class="btn btn-outline-success btn-sm me-2" onclick="reportManager.generateCsv('orders')">
                            <i class="bi bi-cart"></i> Pedidos
                        </button>
                        <button class="btn btn-outline-success btn-sm me-2" onclick="reportManager.generateCsv('financial')">
                            <i class="bi bi-currency-dollar"></i> Financeiro
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="reportManager.generateCsv('items')">
                            <i class="bi bi-box-seam"></i> Produtos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History (Mock) -->
<div class="row mt-4">
    <div class="col-12">
        <h6 class="text-muted mb-3">Histórico de Downloads</h6>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Arquivo</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3">relatorio_vendas_20231201.pdf</td>
                            <td>PDF</td>
                            <td>01/12/2023 10:00</td>
                            <td><span class="badge bg-success">Concluído</span></td>
                            <td class="text-end pe-4"><button class="btn btn-sm btn-light"><i class="bi bi-download"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    const reportManager = {
        generatePdf: async function(type = 'sales') {
            const form = document.getElementById('sales-report-form');
            const toastId = Toast.info(`Gerando Relatório de ${type}...`, 0); // Permanent toast
            
            try {
                const formData = new FormData(form);
                formData.append('type', type);
                
                const result = await requestJson('/api/reports/generate-pdf', {
                    method: 'POST',
                    body: formData
                });
                
                if (result.success) {
                    Toast.dismiss(toastId);
                    Toast.success('Relatório gerado!');
                    // Open in new tab
                    window.open(result.url, '_blank');
                } else {
                    Toast.dismiss(toastId);
                    Toast.error(result.error);
                }
            } catch (e) {
                Toast.dismiss(toastId);
                Toast.error('Erro ao gerar relatório');
            }
        },
        
        generateCsv: async function(type) {
             const toastId = Toast.info('Exportando CSV...', 0);
             
             try {
                 const formData = new FormData();
                 formData.append('type', type);
                 
                 const result = await requestJson('/api/reports/generate-csv', {
                     method: 'POST',
                     body: formData
                 });
                 
                 if (result.success) {
                     Toast.dismiss(toastId);
                     Toast.success('Exportação concluída!');
                     window.open(result.url, '_blank');
                 } else {
                     Toast.dismiss(toastId);
                     Toast.error(result.error);
                 }
             } catch (e) {
                 Toast.dismiss(toastId);
                 Toast.error('Erro ao exportar');
             }
        }
    };
</script>
