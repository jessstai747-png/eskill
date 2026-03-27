<?php

declare(strict_types=1);

/**
 * @deprecated Use /dashboard/seo-killer instead. This view is no longer routed directly.
 * SEO e Otimização - Versão Moderna
 * Integrado com o layout moderno (sidebar, temas, etc.)
 */
?>

<!-- Custom Styles for SEO Tools -->
<style>
    /* Functional styles for SEO Score */
    .seo-score {
        font-size: 3.5rem;
        font-weight: 800;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    .feature-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        transition: transform 0.3s ease;
    }

    .tool-card:hover .feature-icon {
        transform: scale(1.2) rotate(5deg);
    }

    .badge-lg {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
        font-weight: 600;
    }
</style>

<!-- Page Header -->
<?php
$title = 'SEO e Otimização';
$subtitle = 'Ferramentas completas para análise e otimização de anúncios';
$breadcrumbs = [['label' => 'Ferramentas', 'url' => ''], ['label' => 'SEO', 'url' => '']];
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- Descrição da Seção -->
<div class="alert alert-info mb-4 d-flex align-items-center">
    <i class="bi bi-info-circle me-3" style="font-size: 1.5rem;"></i>
    <div>
        <strong>Central de Otimização SEO</strong> - Ferramentas completas para análise, otimização e construção de anúncios de alto desempenho no Mercado Livre.
        <div class="mt-2">
            <span class="badge bg-primary me-2"><i class="bi bi-lightning-fill"></i> 5 Ferramentas</span>
            <span class="badge bg-success me-2"><i class="bi bi-graph-up"></i> Score até 100</span>
            <span class="badge bg-warning text-dark"><i class="bi bi-speedometer2"></i> Análise Rápida</span>
        </div>
    </div>
</div>

<!-- Estatísticas Rápidas -->
<div class="row mb-4" id="quickStats" style="display: none;">
    <div class="col-md-3">
        <div class="card text-center border-primary shadow-sm">
            <div class="card-body">
                <i class="bi bi-file-text text-primary" style="font-size: 2rem;"></i>
                <h4 class="mt-2 mb-0" id="totalAnalyzed">0</h4>
                <small class="text-muted">Anúncios Analisados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success shadow-sm">
            <div class="card-body">
                <i class="bi bi-trophy text-success" style="font-size: 2rem;"></i>
                <h4 class="mt-2 mb-0" id="avgScore">0</h4>
                <small class="text-muted">Score Médio</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning shadow-sm">
            <div class="card-body">
                <i class="bi bi-key text-warning" style="font-size: 2rem;"></i>
                <h4 class="mt-2 mb-0" id="totalKeywords">0</h4>
                <small class="text-muted">Keywords Pesquisadas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info shadow-sm">
            <div class="card-body">
                <i class="bi bi-building text-info" style="font-size: 2rem;"></i>
                <h4 class="mt-2 mb-0" id="totalBuilt">0</h4>
                <small class="text-muted">Anúncios Criados</small>
            </div>
        </div>
    </div>
</div>

<!-- Ferramentas Principais -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card card-hover tool-card h-100">
            <div class="card-body text-center">
                <i class="bi bi-search feature-icon text-primary"></i>
                <h5 class="card-title">Análise SEO</h5>
                <p class="card-text text-muted">Analise anúncios existentes e receba um score detalhado com recomendações de melhorias.</p>
                <div class="mt-auto">
                    <span class="badge bg-primary badge-lg">Título • Descrição • Imagens</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <button class="btn btn-primary w-100" data-action="open-analyzer">
                    <i class="bi bi-search"></i> Analisar Anúncio
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card card-hover tool-card h-100">
            <div class="card-body text-center">
                <i class="bi bi-key feature-icon text-success"></i>
                <h5 class="card-title">Pesquisa de Keywords</h5>
                <p class="card-text text-muted">Descubra as melhores palavras-chave, tendências e long-tail keywords para sua categoria.</p>
                <div class="mt-auto">
                    <span class="badge bg-success badge-lg">Trends • Autocomplete • Concorrência</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <button class="btn btn-success w-100" data-action="open-keyword-research">
                    <i class="bi bi-key"></i> Pesquisar Keywords
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card card-hover tool-card h-100">
            <div class="card-body text-center">
                <i class="bi bi-pencil-square feature-icon text-warning"></i>
                <h5 class="card-title">Otimizar Título</h5>
                <p class="card-text text-muted">Otimize títulos automaticamente com base em keywords de alto impacto e melhores práticas.</p>
                <div class="mt-auto">
                    <span class="badge bg-warning text-dark badge-lg">60 chars • Sem termos proibidos</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <button class="btn btn-warning text-dark w-100" data-action="open-title-optimizer">
                    <i class="bi bi-pencil-square"></i> Otimizar Título
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ferramentas Avançadas -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card card-hover tool-card h-100">
            <div class="card-body text-center">
                <i class="bi bi-building feature-icon text-info"></i>
                <h5 class="card-title">Construtor de Anúncios</h5>
                <p class="card-text text-muted">Construa anúncios completos e otimizados com templates prontos e auto-preenchimento.</p>
                <div class="mt-auto">
                    <span class="badge bg-info badge-lg">Templates • Auto-fill • Preview</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <button class="btn btn-info w-100" data-action="open-listing-builder">
                    <i class="bi bi-building"></i> Construir Anúncio
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card card-hover tool-card h-100">
            <div class="card-body text-center">
                <i class="bi bi-currency-dollar feature-icon text-danger"></i>
                <h5 class="card-title">Estratégia de Preços</h5>
                <p class="card-text text-muted">Analise preços da concorrência, calcule margens ideais e receba sugestões de precificação.</p>
                <div class="mt-auto">
                    <span class="badge bg-danger badge-lg">Concorrência • Margem • ROI</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <button class="btn btn-danger w-100" data-action="open-pricing">
                    <i class="bi bi-currency-dollar"></i> Analisar Preços
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card card-hover tool-card h-100">
            <div class="card-body text-center">
                <i class="bi bi-file-earmark-bar-graph feature-icon text-secondary"></i>
                <h5 class="card-title">Análise em Lote</h5>
                <p class="card-text text-muted">Analise múltiplos anúncios simultaneamente e identifique oportunidades de melhoria.</p>
                <div class="mt-auto">
                    <span class="badge bg-secondary badge-lg">Batch Analysis • Relatórios</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <button class="btn btn-secondary w-100" data-action="open-batch-analysis">
                    <i class="bi bi-file-earmark-bar-graph"></i> Análise em Lote
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Guia Rápido de SEO -->
<div class="row mt-5">
    <div class="col-12">
        <h3 class="mb-3">
            <i class="bi bi-book"></i> Guia Rápido de SEO para Mercado Livre
        </h3>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-check-circle"></i> Melhores Práticas - Títulos
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="bi bi-check-lg text-success"></i> <strong>45-58 caracteres:</strong> Comprimento ideal para visibilidade</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Keywords no início:</strong> Palavras mais importantes primeiro</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Inclua marca e modelo:</strong> Essencial para busca</li>
                    <li><i class="bi bi-x-lg text-danger"></i> <strong>Evite:</strong> "Promoção", "Oferta", "Barato", emojis</li>
                    <li><i class="bi bi-x-lg text-danger"></i> <strong>Evite:</strong> MAIÚSCULAS EXCESSIVAS</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <i class="bi bi-check-circle"></i> Melhores Práticas - Descrição
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Mínimo 500 caracteres:</strong> Descrição completa</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Use bullets:</strong> • Lista de características</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Especificações técnicas:</strong> Dimensões, peso, materiais</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Informações de garantia:</strong> Sempre mencione</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Instruções de uso:</strong> Como usar o produto</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-check-circle"></i> Melhores Práticas - Imagens
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Mínimo 6 fotos:</strong> Mostre todos os ângulos</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>1200x1200px+:</strong> Alta resolução</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Fundo branco/neutro:</strong> Primeira foto</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Detalhes importantes:</strong> Close-ups de características</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Produto em uso:</strong> Contexto de utilização</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-info text-white">
                <i class="bi bi-check-circle"></i> Melhores Práticas - Atributos & Frete
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Todos obrigatórios:</strong> Preencha 100%</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>BRAND, MODEL, GTIN:</strong> Essenciais para SEO</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Frete grátis:</strong> Aumenta ranqueamento em 30%+</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Mercado Envios Full:</strong> Máxima prioridade</li>
                    <li><i class="bi bi-check-lg text-success"></i> <strong>Estoque disponível:</strong> Sempre mantenha atualizado</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas de Score -->
<div class="row mt-4 mb-5">
    <div class="col-12">
        <h4 class="mb-3">
            <i class="bi bi-graph-up"></i> Entendendo o Score SEO
        </h4>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm h-100">
            <div class="card-body">
                <div class="seo-score score-excellent">90-100</div>
                <h5 class="card-title">Excelente</h5>
                <p class="card-text text-muted">Anúncio otimizado perfeitamente</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm h-100">
            <div class="card-body">
                <div class="seo-score score-good">75-89</div>
                <h5 class="card-title">Muito Bom</h5>
                <p class="card-text text-muted">Pequenos ajustes necessários</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm h-100">
            <div class="card-body">
                <div class="seo-score score-warning">60-74</div>
                <h5 class="card-title">Bom</h5>
                <p class="card-text text-muted">Melhorias recomendadas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm h-100">
            <div class="card-body">
                <div class="seo-score score-danger">0-59</div>
                <h5 class="card-title">Precisa Melhorar</h5>
                <p class="card-text text-muted">Otimização urgente necessária</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Análise SEO -->
<div class="modal fade" id="analyzerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Análise SEO de Anúncio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">ID do Anúncio (MLB)</label>
                    <input type="text" class="form-control" id="analyzeItemId" placeholder="MLB1234567890">
                </div>
                <div id="analyzeResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" data-action="run-analysis">
                    <i class="bi bi-search"></i> Analisar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pesquisa de Keywords -->
<div class="modal fade" id="keywordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pesquisa de Keywords</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">ID da Categoria</label>
                    <input type="text" class="form-control" id="keywordCategoryId" placeholder="MLB1234">
                </div>
                <div class="mb-3">
                    <label class="form-label">Palavra-chave Base (opcional)</label>
                    <input type="text" class="form-control" id="keywordBase" placeholder="notebook">
                </div>
                <div id="keywordResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success" data-action="run-keyword-research">
                    <i class="bi bi-key"></i> Pesquisar
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    function openAnalyzer() {
        new bootstrap.Modal(document.getElementById('analyzerModal')).show();
    }

    function openKeywordResearch() {
        new bootstrap.Modal(document.getElementById('keywordModal')).show();
    }

    function openTitleOptimizer() {
        const html = `
            <div class="modal fade" id="titleOptimizerModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Otimizar Título</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Título Atual</label>
                                <textarea class="form-control" id="titleInput" rows="2" placeholder="Digite o título do anúncio..." maxlength="60"></textarea>
                                <small class="text-muted">Caracteres: <span id="titleLength">0</span>/60</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ID da Categoria (opcional)</label>
                                <input type="text" class="form-control" id="titleCategoryId" placeholder="MLB1234">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Marca (opcional)</label>
                                <input type="text" class="form-control" id="titleBrand" placeholder="Samsung, Dell, etc.">
                            </div>
                            <div id="titleResult"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-warning text-dark" data-action="run-title-optimization">
                                <i class="bi bi-magic"></i> Otimizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('titleOptimizerModal'));
        modal.show();

        document.getElementById('titleInput').addEventListener('input', (e) => {
            document.getElementById('titleLength').textContent = e.target.value.length;
        });

        document.getElementById('titleOptimizerModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async function runTitleOptimization() {
        const title = document.getElementById('titleInput').value;
        const categoryId = document.getElementById('titleCategoryId').value;
        const brand = document.getElementById('titleBrand').value;
        const resultDiv = document.getElementById('titleResult');

        if (!title) {
            showToast('Digite um título para otimizar', 'warning');
            return;
        }

        resultDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-warning" role="status"></div><p class="mt-2">Otimizando título...</p></div>';

        try {
            const data = await requestJson('/api/seo/title/optimize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    title,
                    category_id: categoryId,
                    brand
                })
            });

            if (data.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            let html = '<div class="mt-3"><h6>Sugestões de Títulos Otimizados:</h6>';
            data.suggestions.forEach((sug, i) => {
                html += `
                    <div class="card mb-2">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${sug.title}</strong>
                                    <br><small class="text-muted">${sug.title.length} caracteres • Score: ${sug.score}/100</small>
                                </div>
                                <button class="btn btn-sm btn-primary" data-action="copy-to-clipboard" data-text="${sug.title}">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            resultDiv.innerHTML = html;
            showToast('Título otimizado com sucesso!', 'success');
        } catch (error) {
            resultDiv.innerHTML = `<div class="alert alert-danger">Erro: ${error.message}</div>`;
        }
    }

    function openListingBuilder() {
        const html = `
            <div class="modal fade" id="listingBuilderModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title"><i class="bi bi-building"></i> Construtor de Anúncios</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Informações Básicas</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Título *</label>
                                        <input type="text" class="form-control" id="builderTitle" maxlength="60">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">ID da Categoria *</label>
                                        <input type="text" class="form-control" id="builderCategoryId" placeholder="MLB1234">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço *</label>
                                        <input type="number" class="form-control" id="builderPrice" step="0.01" min="0">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Marca</label>
                                        <input type="text" class="form-control" id="builderBrand">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Quantidade Disponível</label>
                                        <input type="number" class="form-control" id="builderStock" value="1" min="1">
                                    </div>
                                    <!-- Campo EAN com Widget -->
                                    <div class="mb-3">
                                        <label class="form-label d-flex align-items-center justify-content-between">
                                            <span>Código EAN/GTIN</span>
                                            <span id="ean-balance-badge" class="badge bg-secondary">Carregando...</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="builderEan" placeholder="7898123456789" maxlength="13">
                                            <button class="btn btn-outline-primary" type="button" data-action="auto-fill-ean" title="Usar EAN do seu saldo">
                                                <i class="bi bi-upc"></i> Auto
                                            </button>
                                        </div>
                                        <div id="ean-widget-status" class="small text-muted mt-1"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Configurações Avançadas</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Condição</label>
                                        <select class="form-select" id="builderCondition">
                                            <option value="new">Novo</option>
                                            <option value="used">Usado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="builderFreeShipping" checked>
                                            <label class="form-check-label" for="builderFreeShipping">
                                                Frete Grátis
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição</label>
                                        <textarea class="form-control" id="builderDescription" rows="5" placeholder="Descrição detalhada do produto..."></textarea>
                                    </div>
                                    <button class="btn btn-sm btn-secondary w-100" data-action="generate-description">
                                        <i class="bi bi-magic"></i> Gerar Descrição Automática
                                    </button>
                                </div>
                            </div>
                            <div id="builderResult"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-info" data-action="build-listing">
                                <i class="bi bi-building"></i> Criar Anúncio
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('listingBuilderModal'));
        modal.show();

        document.getElementById('listingBuilderModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async function generateDescription() {
        const title = document.getElementById('builderTitle').value;
        const brand = document.getElementById('builderBrand').value;

        if (!title) {
            showToast('Digite um título primeiro', 'warning');
            return;
        }

        try {
            const data = await requestJson('/api/seo/listing/description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    title,
                    brand
                })
            });

            if (data.description) {
                document.getElementById('builderDescription').value = data.description;
                showToast('Descrição gerada com sucesso!', 'success');
            }
        } catch (error) {
            showToast('Erro ao gerar descrição', 'error');
        }
    }

    async function buildListing() {
        const data = {
            title: document.getElementById('builderTitle').value,
            category_id: document.getElementById('builderCategoryId').value,
            price: parseFloat(document.getElementById('builderPrice').value),
            brand: document.getElementById('builderBrand').value,
            available_quantity: parseInt(document.getElementById('builderStock').value),
            condition: document.getElementById('builderCondition').value,
            description: document.getElementById('builderDescription').value,
            free_shipping: document.getElementById('builderFreeShipping').checked,
            ean: document.getElementById('builderEan').value.trim()
        };

        if (!data.title || !data.category_id || !data.price) {
            showToast('Preencha todos os campos obrigatórios', 'warning');
            return;
        }

        const resultDiv = document.getElementById('builderResult');
        resultDiv.innerHTML = '<div class="text-center mt-3"><div class="spinner-border text-info" role="status"></div><p class="mt-2">Criando anúncio...</p></div>';

        try {
            const result = await requestJson('/api/seo/listing/build', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(data)
            });

            if (result.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger mt-3">${result.error}</div>`;
                return;
            }

            resultDiv.innerHTML = `
                <div class="alert alert-success mt-3">
                    <h6><i class="bi bi-check-circle"></i> Anúncio Criado com Sucesso!</h6>
                    <p>Score SEO: <strong>${result.seo_score}/100</strong></p>
                    ${data.ean ? `<p>EAN: <code>${data.ean}</code></p>` : ''}
                    <small>O anúncio foi otimizado automaticamente</small>
                </div>
            `;
            showToast('Anúncio criado com sucesso!', 'success');
            updateStats();
            // Atualizar widget de EAN se foi usado
            if (data.ean) loadEanWidget();
        } catch (error) {
            resultDiv.innerHTML = `<div class="alert alert-danger mt-3">Erro: ${error.message}</div>`;
        }
    }

    // Funções do Widget de EAN
    async function loadEanWidget() {
        try {
            const data = await requestJson('/api/ean/widget');

            if (data.success) {
                const widget = data.widget;
                const badge = document.getElementById('ean-balance-badge');
                const status = document.getElementById('ean-widget-status');

                if (badge) {
                    badge.textContent = `${widget.available} disponíveis`;
                    badge.className = `badge ${widget.alert_level === 'danger' ? 'bg-danger' : widget.alert_level === 'warning' ? 'bg-warning text-dark' : 'bg-success'}`;
                }

                if (status) {
                    if (widget.available === 0) {
                        status.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Sem EANs. <a href="/dashboard/ean#packages">Comprar pacote</a></span>';
                    } else if (widget.available <= 5) {
                        status.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Estoque baixo</span>';
                    } else {
                        status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Clique em "Auto" para usar um EAN do seu saldo</span>';
                    }
                }
            }
        } catch (error) {
            console.error('Erro ao carregar widget EAN:', error);
        }
    }

    async function autoFillEan() {
        const eanInput = document.getElementById('builderEan');
        const status = document.getElementById('ean-widget-status');

        if (eanInput.value.trim()) {
            if (!confirm('Já existe um EAN preenchido. Deseja substituir?')) {
                return;
            }
        }

        status.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Buscando EAN...</span>';

        try {
            const data = await requestJson('/api/ean/preview');

            if (data.success) {
                eanInput.value = data.preview.ean;
                status.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> EAN preenchido. Restam ${data.preview.available_after_use} após usar este.</span>`;
                showToast('EAN preenchido automaticamente!', 'success');
            } else {
                status.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> ${data.error}. <a href="/dashboard/ean#packages">Comprar EANs</a></span>`;
                showToast(data.error || 'Sem EANs disponíveis', 'warning');
            }
        } catch (error) {
            status.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Erro ao buscar EAN</span>';
            showToast('Erro ao buscar EAN', 'error');
        }
    }

    // Carregar widget ao abrir modal
    document.addEventListener('shown.bs.modal', function(event) {
        if (event.target.id === 'listingBuilderModal') {
            loadEanWidget();
        }
    });

    function openPricing() {
        const html = `
            <div class="modal fade" id="pricingModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title"><i class="bi bi-currency-dollar"></i> Estratégia de Preços</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">ID da Categoria</label>
                                <input type="text" class="form-control" id="pricingCategoryId" placeholder="MLB1234">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preço de Custo</label>
                                <input type="number" class="form-control" id="pricingCost" step="0.01" placeholder="0.00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Margem Desejada (%)</label>
                                <input type="number" class="form-control" id="pricingMargin" value="30" min="0" max="100">
                            </div>
                            <div id="pricingResult"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-danger" data-action="analyze-pricing">
                                <i class="bi bi-graph-up"></i> Analisar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('pricingModal'));
        modal.show();

        document.getElementById('pricingModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async function analyzePricing() {
        const categoryId = document.getElementById('pricingCategoryId').value;
        const cost = parseFloat(document.getElementById('pricingCost').value) || 0;
        const margin = parseFloat(document.getElementById('pricingMargin').value) || 30;
        const resultDiv = document.getElementById('pricingResult');

        if (!categoryId) {
            showToast('Digite o ID da categoria', 'warning');
            return;
        }

        resultDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-danger" role="status"></div><p class="mt-2">Analisando preços...</p></div>';

        try {
            const data = await requestJson(`/api/seo/pricing/${categoryId}`);

            if (data.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            const suggested = cost > 0 ? cost * (1 + margin / 100) : data.average_price;

            let html = `
                <div class="mt-3">
                    <h6>Análise de Mercado</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <small class="text-muted">Preço Médio</small>
                                    <h5 class="text-primary">R$ ${data.average_price?.toFixed(2) || '0.00'}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <small class="text-muted">Preço Sugerido</small>
                                    <h5 class="text-success">R$ ${suggested.toFixed(2)}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <small class="text-muted">Margem</small>
                                    <h5 class="text-info">${margin}%</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            resultDiv.innerHTML = html;
            showToast('Análise de preços concluída!', 'success');
        } catch (error) {
            resultDiv.innerHTML = `<div class="alert alert-danger">Erro: ${error.message}</div>`;
        }
    }

    function openBatchAnalysis() {
        const html = `
            <div class="modal fade" id="batchModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-secondary text-white">
                            <h5 class="modal-title"><i class="bi bi-file-earmark-bar-graph"></i> Análise em Lote</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">IDs dos Anúncios (um por linha)</label>
                                <textarea class="form-control" id="batchItemIds" rows="6" placeholder="MLB1234567890&#10;MLB0987654321&#10;MLB1122334455"></textarea>
                                <small class="text-muted">Digite os IDs dos anúncios, um por linha</small>
                            </div>
                            <div id="batchResult"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" data-action="run-batch-analysis">
                                <i class="bi bi-play-fill"></i> Analisar Todos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('batchModal'));
        modal.show();

        document.getElementById('batchModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async function runBatchAnalysis() {
        const idsText = document.getElementById('batchItemIds').value;
        const itemIds = idsText.split('\n').map(id => id.trim()).filter(id => id.length > 0);
        const resultDiv = document.getElementById('batchResult');

        if (itemIds.length === 0) {
            showToast('Digite pelo menos um ID de anúncio', 'warning');
            return;
        }

        resultDiv.innerHTML = `<div class="text-center"><div class="spinner-border text-secondary" role="status"></div><p class="mt-2">Analisando ${itemIds.length} anúncios...</p></div>`;

        try {
            const data = await requestJson('/api/seo/analyze/batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    item_ids: itemIds
                })
            });

            if (data.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            let html = '<div class="mt-3"><h6>Resultados da Análise</h6><div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Score</th><th>Rating</th><th>Ações</th></tr></thead><tbody>';

            Object.entries(data.items || {}).forEach(([itemId, result]) => {
                if (result.error) {
                    html += `<tr><td>${itemId}</td><td colspan="3" class="text-danger">Erro: ${result.message}</td></tr>`;
                } else {
                    const scoreClass = result.overall_score >= 90 ? 'text-success' :
                        result.overall_score >= 75 ? 'text-info' :
                        result.overall_score >= 60 ? 'text-warning' : 'text-danger';
                    html += `
                        <tr>
                            <td><small>${itemId}</small></td>
                            <td><strong class="${scoreClass}">${result.overall_score}</strong></td>
                            <td><span class="badge bg-secondary">${result.rating}</span></td>
                            <td><button class="btn btn-sm btn-primary" data-action="view-details" data-item-id="${itemId}"><i class="bi bi-eye"></i></button></td>
                        </tr>
                    `;
                }
            });

            html += '</tbody></table></div>';

            if (data.summary) {
                html += `
                    <div class="alert alert-info mt-3">
                        <strong>Resumo:</strong> ${data.summary.total_analyzed} anúncios analisados
                        • Score Médio: ${data.summary.average_score}
                        • Excelentes: ${data.summary.distribution.excellent}
                        • Bons: ${data.summary.distribution.good}
                        • Precisam Melhorar: ${data.summary.distribution.needs_improvement}
                    </div>
                `;
            }

            html += '</div>';
            resultDiv.innerHTML = html;
            showToast('Análise em lote concluída!', 'success');
            updateStats();
        } catch (error) {
            resultDiv.innerHTML = `<div class="alert alert-danger">Erro: ${error.message}</div>`;
        }
    }

    async function runAnalysis() {
        const itemId = document.getElementById('analyzeItemId').value;
        const resultDiv = document.getElementById('analyzeResult');

        if (!itemId) {
            alert('Digite o ID do anúncio');
            return;
        }

        resultDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Analisando...</p></div>';

        try {
            const data = await requestJson(`/api/seo/analyze/${itemId}`);

            if (data.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            displayAnalysisResult(data, resultDiv);
        } catch (error) {
            resultDiv.innerHTML = `<div class="alert alert-danger">Erro ao analisar: ${error.message}</div>`;
        }
    }

    function displayAnalysisResult(data, container) {
        const scoreClass = data.overall_score >= 90 ? 'score-excellent' :
            data.overall_score >= 75 ? 'score-good' :
            data.overall_score >= 60 ? 'score-warning' : 'score-danger';

        let html = `
            <div class="text-center mb-4">
                <div class="seo-score ${scoreClass}">${data.overall_score}</div>
                <h5>${data.rating}</h5>
            </div>

            <h6>Análise Detalhada</h6>
            <div class="row">
                <div class="col-md-6">
                    <strong>Título:</strong> ${data.title.score}/100
                    ${data.title.issues.length > 0 ? '<div class="text-danger">' + data.title.issues.join('<br>') + '</div>' : ''}
                </div>
                <div class="col-md-6">
                    <strong>Descrição:</strong> ${data.description.score}/100
                    ${data.description.issues.length > 0 ? '<div class="text-danger">' + data.description.issues.join('<br>') + '</div>' : ''}
                </div>
                <div class="col-md-6 mt-2">
                    <strong>Imagens:</strong> ${data.images.score}/100
                    ${data.images.issues.length > 0 ? '<div class="text-danger">' + data.images.issues.join('<br>') + '</div>' : ''}
                </div>
                <div class="col-md-6 mt-2">
                    <strong>Atributos:</strong> ${data.attributes.score}/100
                    ${data.attributes.issues.length > 0 ? '<div class="text-danger">' + data.attributes.issues.join('<br>') + '</div>' : ''}
                </div>
            </div>

            <h6 class="mt-4">Recomendações</h6>
            <ul class="list-group">
        `;

        data.recommendations.forEach(rec => {
            const badgeClass = rec.type === 'critical' ? 'bg-danger' : 'bg-warning';
            html += `<li class="list-group-item"><span class="badge ${badgeClass}">${rec.type}</span> ${rec.message}</li>`;
        });

        html += '</ul>';
        container.innerHTML = html;
    }

    async function runKeywordResearch() {
        const categoryId = document.getElementById('keywordCategoryId').value;
        const baseKeyword = document.getElementById('keywordBase').value;
        const resultDiv = document.getElementById('keywordResult');

        if (!categoryId) {
            alert('Digite o ID da categoria');
            return;
        }

        resultDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-success" role="status"></div><p class="mt-2">Pesquisando keywords...</p></div>';

        try {
            const url = baseKeyword ?
                `/api/seo/keywords/${categoryId}?keyword=${encodeURIComponent(baseKeyword)}` :
                `/api/seo/keywords/${categoryId}`;

            const data = await requestJson(url);

            if (data.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            displayKeywordResult(data, resultDiv);
        } catch (error) {
            resultDiv.innerHTML = `<div class="alert alert-danger">Erro na pesquisa: ${error.message}</div>`;
        }
    }

    function displayKeywordResult(data, container) {
        let html = '<div class="row">';

        if (data.primary_keywords && data.primary_keywords.length > 0) {
            html += `
                <div class="col-12 mb-3">
                    <h6>Keywords Principais</h6>
                    <div class="d-flex flex-wrap gap-2">
                        ${data.primary_keywords.map(kw => `<span class="badge bg-primary">${kw.keyword} (${kw.score})</span>`).join('')}
                    </div>
                </div>
            `;
        }

        if (data.trending_keywords && data.trending_keywords.length > 0) {
            html += `
                <div class="col-12 mb-3">
                    <h6>Tendências</h6>
                    <div class="d-flex flex-wrap gap-2">
                        ${data.trending_keywords.map(kw => `<span class="badge bg-success">${kw.keyword}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        if (data.long_tail_keywords && data.long_tail_keywords.length > 0) {
            html += `
                <div class="col-12 mb-3">
                    <h6>Long-tail Keywords</h6>
                    <div class="d-flex flex-wrap gap-2">
                        ${data.long_tail_keywords.map(kw => `<span class="badge bg-info">${kw.keyword}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    // Utilidades
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copiado para a área de transferência!', 'success');
        });
    }

    function showToast(message, type = 'info') {
        const bgClass = type === 'success' ? 'bg-success' :
            type === 'error' ? 'bg-danger' :
            type === 'warning' ? 'bg-warning' : 'bg-info';

        const toastHtml = `
            <div class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'}"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toastElement = document.createElement('div');
        toastElement.innerHTML = toastHtml;
        container.appendChild(toastElement.firstElementChild);

        const toast = new bootstrap.Toast(toastElement.firstElementChild, {
            delay: 3000
        });
        toast.show();

        toastElement.firstElementChild.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }

    function viewDetails(itemId) {
        document.getElementById('analyzeItemId').value = itemId;
        bootstrap.Modal.getInstance(document.getElementById('batchModal')).hide();
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('analyzerModal')).show();
            runAnalysis();
        }, 300);
    }

    // Estatísticas
    function updateStats() {
        const stats = JSON.parse(localStorage.getItem('seoStats') || '{}');

        if (stats.totalAnalyzed) {
            document.getElementById('quickStats').style.display = 'flex';
            document.getElementById('totalAnalyzed').textContent = stats.totalAnalyzed || 0;
            document.getElementById('avgScore').textContent = stats.avgScore || 0;
            document.getElementById('totalKeywords').textContent = stats.totalKeywords || 0;
            document.getElementById('totalBuilt').textContent = stats.totalBuilt || 0;
        }
    }

    function incrementStat(key, value = 1) {
        const stats = JSON.parse(localStorage.getItem('seoStats') || '{}');
        stats[key] = (stats[key] || 0) + value;

        if (key === 'scores') {
            const scores = stats.scores || [];
            scores.push(value);
            stats.scores = scores;
            stats.avgScore = Math.round(scores.reduce((a, b) => a + b, 0) / scores.length);
        }

        localStorage.setItem('seoStats', JSON.stringify(stats));
        updateStats();
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        updateStats();

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case '1':
                        e.preventDefault();
                        openAnalyzer();
                        break;
                    case '2':
                        e.preventDefault();
                        openKeywordResearch();
                        break;
                    case '3':
                        e.preventDefault();
                        openTitleOptimizer();
                        break;
                }
            }
        });

        // Atualizar contadores quando análises são feitas
        const originalRunAnalysis = runAnalysis;
        runAnalysis = async function() {
            await originalRunAnalysis();
            incrementStat('totalAnalyzed');
        };

        const originalRunKeywordResearch = runKeywordResearch;
        runKeywordResearch = async function() {
            await originalRunKeywordResearch();
            incrementStat('totalKeywords');
        };
    });

    // Botão de ação rápida
    const quickActionBtn = document.createElement('button');
    quickActionBtn.className = 'btn btn-primary quick-action-btn';
    quickActionBtn.innerHTML = '<i class="bi bi-plus-lg"></i>';
    quickActionBtn.title = 'Ações Rápidas';
    quickActionBtn.onclick = function() {
        const menu = `
            <div class="position-fixed bottom-0 end-0 mb-5 me-5" style="z-index: 999;">
                <div class="btn-group-vertical" role="group">
                    <button class="btn btn-primary" data-action="open-analyzer-and-dismiss">
                        <i class="bi bi-search"></i> Analisar
                    </button>
                    <button class="btn btn-success" data-action="open-keyword-research-and-dismiss">
                        <i class="bi bi-key"></i> Keywords
                    </button>
                    <button class="btn btn-warning" data-action="open-title-optimizer-and-dismiss">
                        <i class="bi bi-pencil"></i> Título
                    </button>
                    <button class="btn btn-info" data-action="open-listing-builder-and-dismiss">
                        <i class="bi bi-building"></i> Criar
                    </button>
                </div>
            </div>
        `;

        const existing = document.querySelector('.quick-menu');
        if (existing) {
            existing.remove();
        } else {
            const menuEl = document.createElement('div');
            menuEl.className = 'quick-menu';
            menuEl.innerHTML = menu;
            document.body.appendChild(menuEl);
            setTimeout(() => menuEl.remove(), 5000);
        }
    };
    document.body.appendChild(quickActionBtn);

    // ========================================
    // EVENT DELEGATION FOR CSP COMPLIANCE
    // ========================================
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        e.preventDefault();
        const action = target.dataset.action;

        switch (action) {
            case 'open-analyzer':
                openAnalyzer();
                break;
            case 'open-keyword-research':
                openKeywordResearch();
                break;
            case 'open-title-optimizer':
                openTitleOptimizer();
                break;
            case 'open-listing-builder':
                openListingBuilder();
                break;
            case 'open-pricing':
                openPricing();
                break;
            case 'open-batch-analysis':
                openBatchAnalysis();
                break;
            case 'run-analysis':
                runAnalysis();
                break;
            case 'run-keyword-research':
                runKeywordResearch();
                break;
            case 'run-title-optimization':
                runTitleOptimization();
                break;
            case 'auto-fill-ean':
                autoFillEan();
                break;
            case 'generate-description':
                generateDescription();
                break;
            case 'build-listing':
                buildListing();
                break;
            case 'analyze-pricing':
                analyzePricing();
                break;
            case 'run-batch-analysis':
                runBatchAnalysis();
                break;
            case 'copy-to-clipboard':
                copyToClipboard(target.dataset.text);
                break;
            case 'view-details':
                viewDetails(target.dataset.itemId);
                break;
            case 'open-analyzer-and-dismiss':
                openAnalyzer();
                target.closest('div').parentElement.remove();
                break;
            case 'open-keyword-research-and-dismiss':
                openKeywordResearch();
                target.closest('div').parentElement.remove();
                break;
            case 'open-title-optimizer-and-dismiss':
                openTitleOptimizer();
                target.closest('div').parentElement.remove();
                break;
            case 'open-listing-builder-and-dismiss':
                openListingBuilder();
                target.closest('div').parentElement.remove();
                break;
        }
    });
</script>
