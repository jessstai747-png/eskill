<?php

declare(strict_types=1);

$title = 'Análise de Concorrente';
ob_start();
function normalizeExternalUrl(?string $url): string
{
    $trimmed = trim((string)$url);
    if ($trimmed === '') return '';
    if (str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, 'blob:') || str_starts_with($trimmed, '#')) {
        return $trimmed;
    }
    if (str_starts_with($trimmed, '//')) {
        return 'https:' . $trimmed;
    }
    if (str_starts_with($trimmed, 'http://')) {
        return 'https://' . substr($trimmed, strlen('http://'));
    }
    return $trimmed;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <a href="/dashboard/competitors" class="text-decoration-none text-muted mb-2 d-inline-block">
            <i class="bi bi-arrow-left"></i> Voltar para Lista
        </a>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="badge bg-secondary mb-2"><?= $item['status'] ?></span>
                        <h3 class="fw-bold mb-1"><?= htmlspecialchars($item['title'] ?? '') ?></h3>
                        <p class="text-muted font-monospace mb-0"><?= $item['ml_item_id'] ?> • Vendedor ID: <?= $item['seller_id'] ?></p>
                        <a href="<?= htmlspecialchars(normalizeExternalUrl($item['permalink'] ?? '') ?: '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="small text-primary text-decoration-none mt-2 d-inline-block">
                            Ver anúncio original <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block uppercase">Preço Atual</small>
                        <h2 class="fw-bold text-success mb-0">R$ <?= number_format($item['price'], 2, ',', '.') ?></h2>
                        <small class="text-muted">Atualizado em <?= date('d/m H:i', strtotime($item['updated_at'])) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Histórico de Preços (90 Dias)</h6>
            </div>
            <div class="card-body">
                <canvas id="priceChart" height="150"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Insights</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-light border">
                    <h6 class="fw-bold text-dark"><i class="bi bi-lightbulb text-warning me-2"></i> Volatilidade</h6>
                    <p class="small text-muted mb-0">Este vendedor alterou o preço <strong><?= count($history) > 1 ? count($history)-1 : 0 ?> vezes</strong> no período analisado.</p>
                </div>
                
                <?php if(is_array($history) && count($history) >= 2): 
                    $first = $history[0]['price'];
                    $lastEntry = end($history);
                    $last = (is_array($lastEntry) && isset($lastEntry['price'])) ? (float)$lastEntry['price'] : 0.0;
                    $diff = $last - $first;
                    $percent = ($first > 0) ? ($diff / $first) * 100 : 0;
                    $color = $diff < 0 ? 'text-success' : ($diff > 0 ? 'text-danger' : 'text-muted');
                    $icon = $diff < 0 ? 'bi-arrow-down' : ($diff > 0 ? 'bi-arrow-up' : 'bi-dash');
                ?>
                <div class="alert alert-light border mt-3">
                    <h6 class="fw-bold text-dark"><i class="bi bi-graph-up-arrow me-2"></i> Tendência</h6>
                    <p class="small text-muted mb-0">
                        Variação de <strong class="<?= $color ?>"><?= number_format($percent, 1) ?>%</strong> desde o início do rastreamento.
                        <br>
                        (R$ <?= number_format($first, 2, ',', '.') ?> <i class="bi bi-arrow-right"></i> R$ <?= number_format($last, 2, ',', '.') ?>)
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script nonce="<?= CSP_NONCE ?>">
    const ctx = document.getElementById('priceChart').getContext('2d');
    const historyData = <?= json_encode($history) ?>;
    
    // Prepare Data
    const labels = historyData.map(h => {
        const d = new Date(h.recorded_at);
        return d.toLocaleDateString('pt-BR');
    });
    const prices = historyData.map(h => parseFloat(h.price));

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Preço do Concorrente (R$)',
                data: prices,
                borderColor: '#6f42c1', // Purple theme
                backgroundColor: 'rgba(111, 66, 193, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        borderDash: [2, 2]
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/modern/app.php';
?>
