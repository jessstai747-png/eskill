<?php

declare(strict_types=1);

$title = 'Relatórios Financeiros';
$subtitle = 'Demonstrativo de Resultados e Análise de Lucratividade';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<div class="row mb-4">
    <!-- Filters -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3">
                <form id="financial-filter" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" name="start" id="date-start" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Final</label>
                        <input type="date" class="form-control" name="end" id="date-end" value="<?= date('Y-m-t') ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary w-100" onclick="financialManager.loadData()">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                    </div>
                     <div class="col-md-3">
                        <button type="button" class="btn btn-outline-dark w-100" onclick="financialManager.exportPdf()">
                            <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- P&L Table -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Demonstrativo de Resultados (DRE)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="pnl-table">
                        <tbody>
                            <tr>
                                <td colspan="2" class="text-center py-5">
                                    <div class="spinner-border text-primary"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Evolução do Resultado</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script nonce="<?= CSP_NONCE ?>">

    const financialManager = {
        chart: null,
        
        init: function() {
            this.loadData();
        },

        loadData: async function() {
            const start = document.getElementById('date-start').value;
            const end = document.getElementById('date-end').value;

            try {
                const data = await requestJson(`/api/financials/pnl?start=${start}&end=${end}`);

                if (data.success) {
                    this.renderPnL(data.pnl);
                    this.renderChart(data.chart);
                } else {
                    alert('Erro ao carregar dados: ' + data.error);
                }
            } catch (e) {
                console.error(e);
            }
        },
        
        renderPnL: function(pnl) {
            const formatMoney = (val) => {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
            };
            
            const row = (label, value, cssClass = '', isHeader = false) => {
                const fw = isHeader ? 'fw-bold' : '';
                return `<tr>
                    <td class="${fw}">${label}</td>
                    <td class="text-end ${fw} ${cssClass}">${formatMoney(value)}</td>
                </tr>`;
            };
            
            // Re-calc logic visually if needed, but using backend data directly
            let html = '';
            html += row('Receita Bruta', pnl.gross_revenue, '', true);
            html += row('(-) Impostos', pnl.taxes, 'text-danger');
            html += row('Receita Líquida', pnl.net_revenue, '', true);
            html += '<tr><td colspan="2"><hr class="my-1"></td></tr>';
            html += row('(-) Custo Produtos (CMV)', pnl.cogs, 'text-secondary');
            html += row('(-) Comissões ML', pnl.commissions, 'text-secondary');
            html += row('(-) Taxas Pagamento', pnl.payment_fees, 'text-secondary');
            html += row('(-) Fretes', pnl.shipping_cost, 'text-secondary');
            html += row('(-) Taxas Fixas', pnl.fixed_fees, 'text-secondary');
            html += row('(-) Descontos', pnl.discounts, 'text-secondary');
            html += '<tr><td colspan="2"><hr class="my-1"></td></tr>';
            
            const profitClass = pnl.net_profit >= 0 ? 'text-success' : 'text-danger';
            html += row('Resultado Operacional', pnl.net_profit, profitClass, true);

            html += `<tr><td colspan="2"><small class="text-muted d-block text-end mt-2">Margem Líquida: ${pnl.avg_margin.toFixed(1)}%</small></td></tr>`;
            
            document.querySelector('#pnl-table tbody').innerHTML = html;
        },
        
        renderChart: function(dailyData) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            if (this.chart) this.chart.destroy();
            
            const labels = dailyData.map(d => new Date(d.date).toLocaleDateString('pt-BR'));
            const revenue = dailyData.map(d => d.revenue);
            const profit = dailyData.map(d => d.profit);
            
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Receita',
                            data: revenue,
                            borderColor: '#0d6efd',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Lucro',
                            data: profit,
                            borderColor: '#198754',
                            tension: 0.3,
                            fill: true,
                            backgroundColor: 'rgba(25, 135, 84, 0.1)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        },

        exportPdf: function() {
            const start = document.getElementById('date-start').value;
            const end = document.getElementById('date-end').value;
            window.open(`/api/financials/export?start=${start}&end=${end}`, '_blank');
        }
    };

    document.addEventListener('DOMContentLoaded', () => financialManager.init());
</script>
