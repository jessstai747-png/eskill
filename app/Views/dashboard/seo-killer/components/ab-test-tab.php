<!-- A/B Testing Tab -->
<div class="tab-pane fade" id="ab-testing" role="tabpanel" aria-labelledby="ab-testing-tab">
    <div class="container-fluid py-4">

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="bi bi-clipboard-data text-primary me-2"></i>
                            Testes A/B
                        </h2>
                        <p class="text-muted mb-0">Teste cientificamente suas otimizações e descubra o que realmente converte</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" id="createABTestBtn">
                        <i class="bi bi-plus-circle me-2"></i>Novo Teste A/B
                    </button>
                </div>
            </div>
        </div>

        <!-- Active Tests -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="bi bi-play-circle text-success me-2"></i>
                    Testes Ativos
                </h4>
            </div>

            <div id="activeTestsContainer" class="row">
                <!-- Active tests will be dynamically loaded here -->
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted mt-2">Carregando testes ativos...</p>
                </div>
            </div>
        </div>

        <!-- Completed Tests History -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-clock-history text-secondary me-2"></i>
                            Histórico de Testes
                        </h4>
                        <select class="form-select form-select-sm" id="testHistoryFilter" style="width: auto;">
                            <option value="all">Todos</option>
                            <option value="completed">Concluídos</option>
                            <option value="stopped">Interrompidos</option>
                        </select>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="testHistoryTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produto</th>
                                        <th>Tipo de Teste</th>
                                        <th style="width: 150px;">Duração</th>
                                        <th style="width: 150px;">Vencedor</th>
                                        <th style="width: 120px;">Confiança</th>
                                        <th style="width: 120px;">Data</th>
                                        <th style="width: 100px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="testHistoryBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status"></div>
                                            <p class="text-muted mt-2">Carregando histórico...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Create A/B Test Modal -->
<div class="modal fade" id="createABTestModal" tabindex="-1" aria-labelledby="createABTestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createABTestModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>
                    Criar Novo Teste A/B
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createABTestForm">

                    <!-- Step 1: Product Selection -->
                    <div class="mb-4">
                        <label for="abTestProductSelect" class="form-label fw-bold">
                            1. Selecione o Produto:
                        </label>
                        <select class="form-select" id="abTestProductSelect" required>
                            <option value="">Carregando produtos...</option>
                        </select>
                    </div>

                    <!-- Step 2: Test Type -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            2. Tipo de Teste:
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="testType" id="testTypeTitle" value="title" checked>
                                <label class="btn btn-outline-primary w-100" for="testTypeTitle">
                                    <i class="bi bi-fonts d-block fs-3 mb-2"></i>
                                    Título
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="testType" id="testTypeDescription" value="description">
                                <label class="btn btn-outline-primary w-100" for="testTypeDescription">
                                    <i class="bi bi-file-text d-block fs-3 mb-2"></i>
                                    Descrição
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="testType" id="testTypePrice" value="price">
                                <label class="btn btn-outline-primary w-100" for="testTypePrice">
                                    <i class="bi bi-currency-dollar d-block fs-3 mb-2"></i>
                                    Preço
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="testType" id="testTypeImages" value="images">
                                <label class="btn btn-outline-primary w-100" for="testTypeImages">
                                    <i class="bi bi-images d-block fs-3 mb-2"></i>
                                    Imagens
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Versions -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            3. Configure as Versões:
                        </label>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <strong>Versão A (Atual)</strong>
                                    </div>
                                    <div class="card-body">
                                        <div id="versionAContent">
                                            <p class="text-muted">Carregue um produto para ver a versão atual</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <strong>Versão B (Nova)</strong>
                                    </div>
                                    <div class="card-body">
                                        <div id="versionBInput">
                                            <!-- Dynamic content based on test type -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Test Configuration -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            4. Configurações do Teste:
                        </label>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="testDuration" class="form-label">Duração:</label>
                                <select class="form-select" id="testDuration" required>
                                    <option value="7">7 dias</option>
                                    <option value="14" selected>14 dias</option>
                                    <option value="30">30 dias</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="successMetric" class="form-label">Métrica de Sucesso:</label>
                                <select class="form-select" id="successMetric" required>
                                    <option value="views">Visualizações</option>
                                    <option value="clicks">Cliques</option>
                                    <option value="conversions" selected>Conversões</option>
                                    <option value="revenue">Receita</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Traffic Split -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            5. Divisão de Tráfego:
                        </label>
                        <div class="row align-items-center">
                            <div class="col-5 text-center">
                                <h3 id="trafficSplitA">50%</h3>
                                <small class="text-muted">Versão A</small>
                            </div>
                            <div class="col-2 text-center">
                                <i class="bi bi-arrows-expand fs-4"></i>
                            </div>
                            <div class="col-5 text-center">
                                <h3 id="trafficSplitB">50%</h3>
                                <small class="text-muted">Versão B</small>
                            </div>
                        </div>
                        <input type="range" class="form-range mt-3" id="trafficSplitSlider"
                            min="20" max="80" value="50" step="10">
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="startABTestBtn">
                    <i class="bi bi-play-circle me-2"></i>Iniciar Teste
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Test Details Modal -->
<div class="modal fade" id="testDetailsModal" tabindex="-1" aria-labelledby="testDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testDetailsModalLabel">Detalhes do Teste A/B</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="testDetailsBody">
                <!-- Content will be dynamically loaded -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="stopTestBtn" style="display: none;">
                    <i class="bi bi-stop-circle me-2"></i>Parar Teste
                </button>
                <button type="button" class="btn btn-success" id="applyWinnerBtn" style="display: none;">
                    <i class="bi bi-check-circle me-2"></i>Aplicar Vencedor
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- A/B Testing JavaScript -->
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    const ABTesting = {
        currentTest: null,

        init() {
            console.log('Initializing A/B Testing...');
            this.loadActiveTests();
            this.loadTestHistory();
            this.loadProductsList();
            this.setupEventListeners();
        },

        setupEventListeners() {
            // Create test button
            document.getElementById('createABTestBtn')?.addEventListener('click', () => {
                this.showCreateTestModal();
            });

            // Product selection
            document.getElementById('abTestProductSelect')?.addEventListener('change', (e) => {
                if (e.target.value) {
                    this.loadProductCurrentVersion(e.target.value);
                }
            });

            // Test type selection
            document.querySelectorAll('input[name="testType"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    this.updateVersionBInput(e.target.value);
                });
            });

            // Traffic split slider
            document.getElementById('trafficSplitSlider')?.addEventListener('input', (e) => {
                const value = e.target.value;
                document.getElementById('trafficSplitA').textContent = value + '%';
                document.getElementById('trafficSplitB').textContent = (100 - value) + '%';
            });

            // Start test button
            document.getElementById('startABTestBtn')?.addEventListener('click', () => {
                this.startTest();
            });

            // Stop test button
            document.getElementById('stopTestBtn')?.addEventListener('click', () => {
                this.stopTest();
            });

            // Apply winner button
            document.getElementById('applyWinnerBtn')?.addEventListener('click', () => {
                this.applyWinner();
            });

            // History filter
            document.getElementById('testHistoryFilter')?.addEventListener('change', (e) => {
                this.loadTestHistory(e.target.value);
            });
        },

        async loadProductsList() {
            try {
                const {
                    response,
                    data
                } = await requestJson('/api/items?limit=100');
                if (!response.ok) throw new Error('Failed to load products');
                const select = document.getElementById('abTestProductSelect');

                if (!select) return;

                select.innerHTML = '<option value="">Selecione um produto...</option>' +
                    data.items.map(item => `
                    <option value="${item.id}">${this.escapeHtml(item.title)} (${item.ml_id})</option>
                `).join('');

            } catch (error) {
                console.error('Error loading products list:', error);
            }
        },

        async loadActiveTests() {
            try {
                const {
                    response,
                    data
                } = await requestJson('/api/seo-killer/ab-test?status=active');
                if (!response.ok) throw new Error('Failed to load active tests');
                const container = document.getElementById('activeTestsContainer');

                if (!container) return;

                if (!data.tests || data.tests.length === 0) {
                    container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-clipboard-x text-muted" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 text-muted">Nenhum teste ativo no momento</h5>
                        <p class="text-muted">Crie seu primeiro teste A/B para começar!</p>
                    </div>
                `;
                    return;
                }

                container.innerHTML = data.tests.map(test => this.renderTestCard(test)).join('');

            } catch (error) {
                console.error('Error loading active tests:', error);
                this.showError('Erro ao carregar testes ativos');
            }
        },

        renderTestCard(test) {
            const progress = ((new Date() - new Date(test.started_at)) / (test.duration * 24 * 60 * 60 * 1000)) * 100;
            const daysRemaining = Math.ceil(test.duration - ((new Date() - new Date(test.started_at)) / (24 * 60 * 60 * 1000)));

            return `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-primary bg-opacity-10 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${this.escapeHtml(test.product_title)}</h6>
                                <small class="text-muted">Teste de ${this.getTestTypeLabel(test.type)}</small>
                            </div>
                            <span class="badge bg-success">Ativo</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Progresso</small>
                                <small class="text-muted">${daysRemaining} dias restantes</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: ${Math.min(progress, 100)}%" 
                                     aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <small class="text-muted d-block">Versão A</small>
                                    <strong class="${test.results.version_a_leading ? 'text-success' : ''}">${test.results.version_a_metric}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <small class="text-muted d-block">Versão B</small>
                                    <strong class="${test.results.version_b_leading ? 'text-success' : ''}">${test.results.version_b_metric}</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-${test.results.confidence >= 95 ? 'success' : 'info'} alert-sm mb-3">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                ${test.results.confidence}% de confiança estatística
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-primary" onclick="ABTesting.showTestDetails('${test.id}')">
                                <i class="bi bi-eye me-1"></i>Ver Detalhes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        },

        async loadTestHistory(filter = 'all') {
            try {
                const {
                    response,
                    data
                } = await requestJson(`/api/seo-killer/ab-test?status=completed&filter=${filter}`);
                if (!response.ok) throw new Error('Failed to load test history');
                const tbody = document.getElementById('testHistoryBody');

                if (!tbody) return;

                if (!data.tests || data.tests.length === 0) {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Nenhum teste concluído ainda
                        </td>
                    </tr>
                `;
                    return;
                }

                tbody.innerHTML = data.tests.map(test => `
                <tr>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="${this.escapeHtml(test.product_title)}">
                            ${this.escapeHtml(test.product_title)}
                        </div>
                        <small class="text-muted">ID: ${test.product_id}</small>
                    </td>
                    <td>
                        <span class="badge bg-secondary">${this.getTestTypeLabel(test.type)}</span>
                    </td>
                    <td>${test.duration} dias</td>
                    <td>
                        <strong class="text-${test.winner === 'A' ? 'primary' : 'success'}">
                            Versão ${test.winner}
                        </strong>
                        <br>
                        <small class="text-muted">+${test.improvement}% melhor</small>
                    </td>
                    <td>
                        <span class="badge bg-${test.confidence >= 95 ? 'success' : 'warning'}">
                            ${test.confidence}%
                        </span>
                    </td>
                    <td>
                        <small>${this.formatDate(test.completed_at)}</small>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="ABTesting.showTestDetails('${test.id}')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');

            } catch (error) {
                console.error('Error loading test history:', error);
                this.showError('Erro ao carregar histórico de testes');
            }
        },

        showCreateTestModal() {
            const modal = new bootstrap.Modal(document.getElementById('createABTestModal'));
            this.updateVersionBInput('title'); // Default to title test
            modal.show();
        },

        async loadProductCurrentVersion(productId) {
            try {
                const {
                    response,
                    data
                } = await requestJson(`/api/items/${productId}`);
                if (!response.ok) throw new Error('Failed to load product');
                const testType = document.querySelector('input[name="testType"]:checked')?.value || 'title';

                const versionAContent = document.getElementById('versionAContent');
                if (versionAContent) {
                    versionAContent.innerHTML = this.getVersionAContent(data.item, testType);
                }

                this.updateVersionBInput(testType);

            } catch (error) {
                console.error('Error loading product current version:', error);
            }
        },

        getVersionAContent(product, testType) {
            switch (testType) {
                case 'title':
                    return `<strong>Título Atual:</strong><br>${this.escapeHtml(product.title)}`;
                case 'description':
                    return `<strong>Descrição Atual:</strong><br>${this.truncate(product.description, 150)}`;
                case 'price':
                    return `<strong>Preço Atual:</strong><br>R$ ${product.price}`;
                case 'images':
                    return `<strong>Imagens Atuais:</strong><br><small>${product.pictures?.length || 0} imagens</small>`;
                default:
                    return '';
            }
        },

        updateVersionBInput(testType) {
            const versionBInput = document.getElementById('versionBInput');
            if (!versionBInput) return;

            switch (testType) {
                case 'title':
                    versionBInput.innerHTML = `
                    <label class="form-label">Novo Título:</label>
                    <textarea class="form-control" id="versionBTitle" rows="3" 
                              placeholder="Digite o novo título para testar..." required></textarea>
                    <small class="text-muted">Máximo 60 caracteres recomendado</small>
                `;
                    break;
                case 'description':
                    versionBInput.innerHTML = `
                    <label class="form-label">Nova Descrição:</label>
                    <textarea class="form-control" id="versionBDescription" rows="5" 
                              placeholder="Digite a nova descrição para testar..." required></textarea>
                `;
                    break;
                case 'price':
                    versionBInput.innerHTML = `
                    <label class="form-label">Novo Preço:</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="number" class="form-control" id="versionBPrice" 
                               placeholder="0.00" step="0.01" min="0" required>
                    </div>
                `;
                    break;
                case 'images':
                    versionBInput.innerHTML = `
                    <label class="form-label">Novas Imagens:</label>
                    <input type="file" class="form-control" id="versionBImages" 
                           accept="image/*" multiple>
                    <small class="text-muted">Selecione novas imagens para testar</small>
                `;
                    break;
            }
        },

        async startTest() {
            const productId = document.getElementById('abTestProductSelect')?.value;
            const testType = document.querySelector('input[name="testType"]:checked')?.value;
            const duration = document.getElementById('testDuration')?.value;
            const metric = document.getElementById('successMetric')?.value;
            const trafficSplit = document.getElementById('trafficSplitSlider')?.value;

            if (!productId || !testType) {
                this.showError('Preencha todos os campos obrigatórios');
                return;
            }

            // Get version B content based on test type
            let versionBContent;
            switch (testType) {
                case 'title':
                    versionBContent = document.getElementById('versionBTitle')?.value;
                    break;
                case 'description':
                    versionBContent = document.getElementById('versionBDescription')?.value;
                    break;
                case 'price':
                    versionBContent = document.getElementById('versionBPrice')?.value;
                    break;
                case 'images':
                    versionBContent = document.getElementById('versionBImages')?.files;
                    break;
            }

            if (!versionBContent) {
                this.showError('Defina o conteúdo da Versão B');
                return;
            }

            const btn = document.getElementById('startABTestBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando...';
            }

            try {
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('test_type', testType);
                formData.append('duration', duration);
                formData.append('success_metric', metric);
                formData.append('traffic_split', trafficSplit);

                if (testType === 'images') {
                    Array.from(versionBContent).forEach((file, index) => {
                        formData.append(`images[${index}]`, file);
                    });
                } else {
                    formData.append('version_b_content', versionBContent);
                }

                const {
                    response
                } = await requestJson('/api/seo-killer/ab-test', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Failed to start test');

                const data = await response.json();

                this.showSuccess('Teste A/B iniciado com sucesso!');

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('createABTestModal')).hide();

                // Reload active tests
                this.loadActiveTests();

            } catch (error) {
                console.error('Error starting test:', error);
                this.showError('Erro ao iniciar teste');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-play-circle me-2"></i>Iniciar Teste';
                }
            }
        },

        async showTestDetails(testId) {
            this.currentTest = testId;

            const modal = new bootstrap.Modal(document.getElementById('testDetailsModal'));
            const body = document.getElementById('testDetailsBody');

            modal.show();

            if (!body) return;

            body.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 text-muted">Carregando detalhes...</p>
            </div>
        `;

            try {
                const {
                    response,
                    data: test
                } = await requestJson(`/api/seo-killer/ab-test/${testId}`);
                if (!response.ok) throw new Error('Failed to load test details');

                body.innerHTML = this.renderTestDetails(test);

                // Show action buttons based on test status
                if (test.status === 'active') {
                    document.getElementById('stopTestBtn').style.display = 'inline-block';
                    if (test.results.confidence >= 95) {
                        document.getElementById('applyWinnerBtn').style.display = 'inline-block';
                    }
                }

            } catch (error) {
                console.error('Error loading test details:', error);
                body.innerHTML = `
                <div class="alert alert-danger">
                    Erro ao carregar detalhes do teste
                </div>
            `;
            }
        },

        renderTestDetails(test) {
            return `
            <!-- Test Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5>${this.escapeHtml(test.product_title)}</h5>
                    <p class="text-muted mb-3">Teste de ${this.getTestTypeLabel(test.type)} • Iniciado em ${this.formatDate(test.started_at)}</p>
                    
                    <div class="alert alert-${test.results.confidence >= 95 ? 'success' : 'info'}">
                        <strong>Confiança Estatística: ${test.results.confidence}%</strong>
                        <br>
                        <small>
                            ${test.results.confidence >= 95 ? 
                                '✓ Resultados são estatisticamente significativos!' : 
                                'Aguardando mais dados para conclusões definitivas...'}
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Side by Side Comparison -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card ${test.winner === 'A' ? 'border-success border-2' : ''}">
                        <div class="card-header bg-light">
                            <strong>Versão A (Controle)</strong>
                            ${test.winner === 'A' ? '<span class="badge bg-success float-end">Vencedora</span>' : ''}
                        </div>
                        <div class="card-body">
                            ${this.renderVersionContent(test.type, test.version_a_content)}
                            
                            <hr>
                            
                            <h6 class="mb-3">Métricas:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-eye me-2"></i>
                                    <strong>Visualizações:</strong> ${test.metrics.version_a.views}
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-cursor me-2"></i>
                                    <strong>Cliques:</strong> ${test.metrics.version_a.clicks}
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-cart-check me-2"></i>
                                    <strong>Conversões:</strong> ${test.metrics.version_a.conversions}
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-currency-dollar me-2"></i>
                                    <strong>Receita:</strong> R$ ${test.metrics.version_a.revenue}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card ${test.winner === 'B' ? 'border-success border-2' : ''}">
                        <div class="card-header bg-primary text-white">
                            <strong>Versão B (Variação)</strong>
                            ${test.winner === 'B' ? '<span class="badge bg-success float-end">Vencedora</span>' : ''}
                        </div>
                        <div class="card-body">
                            ${this.renderVersionContent(test.type, test.version_b_content)}
                            
                            <hr>
                            
                            <h6 class="mb-3">Métricas:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-eye me-2"></i>
                                    <strong>Visualizações:</strong> ${test.metrics.version_b.views}
                                    ${this.getImprovementBadge(test.metrics.version_a.views, test.metrics.version_b.views)}
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-cursor me-2"></i>
                                    <strong>Cliques:</strong> ${test.metrics.version_b.clicks}
                                    ${this.getImprovementBadge(test.metrics.version_a.clicks, test.metrics.version_b.clicks)}
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-cart-check me-2"></i>
                                    <strong>Conversões:</strong> ${test.metrics.version_b.conversions}
                                    ${this.getImprovementBadge(test.metrics.version_a.conversions, test.metrics.version_b.conversions)}
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-currency-dollar me-2"></i>
                                    <strong>Receita:</strong> R$ ${test.metrics.version_b.revenue}
                                    ${this.getImprovementBadge(test.metrics.version_a.revenue, test.metrics.version_b.revenue)}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recommendation -->
            ${test.results.confidence >= 95 ? `
                <div class="alert alert-${test.winner === 'B' ? 'success' : 'warning'}">
                    <h5 class="alert-heading">
                        <i class="bi bi-lightbulb me-2"></i>Recomendação
                    </h5>
                    <p class="mb-0">
                        ${test.winner === 'B' ? 
                            `A Versão B está performando ${test.improvement}% melhor que a versão atual. 
                             Recomendamos aplicar esta mudança permanentemente.` :
                            `A Versão A (atual) continua sendo a melhor opção. 
                             A mudança proposta não trouxe melhorias significativas.`}
                    </p>
                </div>
            ` : ''}
        `;
        },

        renderVersionContent(type, content) {
            switch (type) {
                case 'title':
                    return `<p class="mb-0">${this.escapeHtml(content)}</p>`;
                case 'description':
                    return `<div style="max-height: 200px; overflow-y: auto;">${this.escapeHtml(content)}</div>`;
                case 'price':
                    return `<h4 class="mb-0">R$ ${content}</h4>`;
                case 'images':
                    return `<div class="row g-2">${content.map(img => `
                    <div class="col-4">
                        <img src="${img}" class="img-thumbnail" alt="Image">
                    </div>
                `).join('')}</div>`;
                default:
                    return '';
            }
        },

        getImprovementBadge(oldValue, newValue) {
            const improvement = ((newValue - oldValue) / oldValue * 100).toFixed(1);
            if (improvement > 0) {
                return `<span class="badge bg-success ms-2">+${improvement}%</span>`;
            } else if (improvement < 0) {
                return `<span class="badge bg-danger ms-2">${improvement}%</span>`;
            }
            return '';
        },

        async stopTest() {
            if (!this.currentTest) return;

            if (!confirm('Deseja realmente parar este teste? Os dados serão preservados no histórico.')) {
                return;
            }

            const btn = document.getElementById('stopTestBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Parando...';
            }

            try {
                const {
                    response
                } = await requestJson(`/api/seo-killer/ab-test/stop/${this.currentTest}`, {
                    method: 'POST'
                });

                if (!response.ok) throw new Error('Failed to stop test');

                this.showSuccess('Teste parado com sucesso!');

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('testDetailsModal')).hide();

                // Reload lists
                this.loadActiveTests();
                this.loadTestHistory();

            } catch (error) {
                console.error('Error stopping test:', error);
                this.showError('Erro ao parar teste');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-stop-circle me-2"></i>Parar Teste';
                }
            }
        },

        async applyWinner() {
            if (!this.currentTest) return;

            if (!confirm('Deseja aplicar a versão vencedora permanentemente ao produto?')) {
                return;
            }

            const btn = document.getElementById('applyWinnerBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aplicando...';
            }

            try {
                const {
                    response
                } = await requestJson(`/api/seo-killer/ab-test/apply/${this.currentTest}`, {
                    method: 'POST'
                });

                if (!response.ok) throw new Error('Failed to apply winner');

                this.showSuccess('Versão vencedora aplicada com sucesso!');

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('testDetailsModal')).hide();

                // Reload lists
                this.loadActiveTests();
                this.loadTestHistory();

            } catch (error) {
                console.error('Error applying winner:', error);
                this.showError('Erro ao aplicar vencedor');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Aplicar Vencedor';
                }
            }
        },

        // Utility functions
        getTestTypeLabel(type) {
            const labels = {
                'title': 'Título',
                'description': 'Descrição',
                'price': 'Preço',
                'images': 'Imagens'
            };
            return labels[type] || type;
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('pt-BR');
        },

        truncate(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showSuccess(message) {
            alert(message); // Placeholder
        },

        showError(message) {
            alert(message); // Placeholder
        }
    };

    // Initialize when tab is shown
    document.addEventListener('shown.bs.tab', function(e) {
        if (e.target.id === 'ab-testing-tab') {
            ABTesting.init();
        }
    });
</script>