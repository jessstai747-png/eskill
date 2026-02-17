<!-- AI Image Analyzer Component -->
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-images text-primary"></i>
                        AI Image Analyzer
                    </h2>
                    <p class="text-muted mb-0">Análise inteligente de imagens dos anúncios com GPT-4 Vision</p>
                </div>
                <button class="btn btn-outline-primary" onclick="refreshImageAnalysis()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Product Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Selecione o Produto</label>
                    <select id="imageProductSelect" class="form-select" onchange="loadProductImages()">
                        <option value="">Carregando produtos...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" onclick="analyzeProductImages()">
                        <i class="bi bi-search"></i> Analisar Imagens
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis Results -->
    <div id="imageAnalysisResults" style="display: none;">

        <!-- Overall Score Card -->
        <div class="card mb-4 border-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <canvas id="overallImageScoreGauge" width="150" height="150"></canvas>
                        <h4 class="mt-2 mb-0" id="overallImageScore">0</h4>
                        <small class="text-muted">Score Geral</small>
                    </div>
                    <div class="col-md-9">
                        <h5 class="mb-3">Resumo da Análise</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-images fs-3 text-primary me-2"></i>
                                    <div>
                                        <div class="fw-bold" id="totalImagesCount">0</div>
                                        <small class="text-muted">Total de Imagens</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle fs-3 text-success me-2"></i>
                                    <div>
                                        <div class="fw-bold" id="goodImagesCount">0</div>
                                        <small class="text-muted">Imagens Boas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle fs-3 text-warning me-2"></i>
                                    <div>
                                        <div class="fw-bold" id="warningImagesCount">0</div>
                                        <small class="text-muted">Com Avisos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-x-circle fs-3 text-danger me-2"></i>
                                    <div>
                                        <div class="fw-bold" id="criticalImagesCount">0</div>
                                        <small class="text-muted">Críticas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Images Grid -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Análise Individual das Imagens</h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="imageFilter" id="filterAll" checked>
                                <label class="btn btn-outline-primary" for="filterAll" onclick="filterImages('all')">Todas</label>

                                <input type="radio" class="btn-check" name="imageFilter" id="filterGood">
                                <label class="btn btn-outline-success" for="filterGood" onclick="filterImages('good')">Boas</label>

                                <input type="radio" class="btn-check" name="imageFilter" id="filterWarning">
                                <label class="btn btn-outline-warning" for="filterWarning" onclick="filterImages('warning')">Avisos</label>

                                <input type="radio" class="btn-check" name="imageFilter" id="filterCritical">
                                <label class="btn btn-outline-danger" for="filterCritical" onclick="filterImages('critical')">Críticas</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="imagesGrid" class="row g-3">
                            <!-- Images will be rendered here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issues & Recommendations -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-octagon"></i>
                            Problemas Detectados
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="detectedIssues">
                            <!-- Issues will be listed here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-lightbulb"></i>
                            Recomendações
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="imageRecommendations">
                            <!-- Recommendations will be listed here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Best Practices Comparison -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-star"></i>
                    Comparação com Melhores Práticas ML
                </h5>
            </div>
            <div class="card-body">
                <div id="bestPracticesComparison">
                    <!-- Best practices comparison table -->
                </div>
            </div>
        </div>

        <!-- Optimal Order Suggestions -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-arrows-expand"></i>
                    Ordem Otimizada Sugerida
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Por que a ordem importa?</strong> A primeira imagem é a mais importante para cliques. Imagens de alta qualidade e com fundo branco devem vir primeiro.
                </div>
                <div id="optimalOrder" class="row g-2">
                    <!-- Optimal order will be shown here -->
                </div>
                <div class="mt-3 text-end">
                    <button class="btn btn-success" onclick="applyOptimalOrder()">
                        <i class="bi bi-check-circle"></i> Aplicar Ordem Sugerida
                    </button>
                </div>
            </div>
        </div>

        <!-- Duplicate Detection -->
        <div class="card mb-4" id="duplicateDetectionCard" style="display: none;">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-files"></i>
                    Imagens Duplicadas Detectadas
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Imagens muito similares podem prejudicar a experiência do usuário e não agregam valor ao anúncio.</p>
                <div id="duplicatesList">
                    <!-- Duplicates will be listed here -->
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Image Detail Modal -->
<div class="modal fade" id="imageDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da Imagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <img id="modalImagePreview" src="" alt="Preview" class="img-fluid rounded">
                    </div>
                    <div class="col-md-6">
                        <h6>Análise Técnica</h6>
                        <ul class="list-unstyled" id="modalImageDetails">
                            <!-- Details will be populated -->
                        </ul>
                        <hr>
                        <h6>Problemas Detectados</h6>
                        <div id="modalImageIssues">
                            <!-- Issues will be populated -->
                        </div>
                        <hr>
                        <h6>Sugestões de Melhoria</h6>
                        <div id="modalImageSuggestions">
                            <!-- Suggestions will be populated -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="file" id="imageUploadInput" style="display: none;" accept="image/*" onchange="handleImageUpload(this)">
                <button type="button" class="btn btn-danger" onclick="removeImage()">
                    <i class="bi bi-trash"></i> Remover Imagem
                </button>
                <button type="button" class="btn btn-primary" onclick="replaceImage()">
                    <i class="bi bi-upload"></i> Substituir Imagem
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* AI Image Analyzer Styles */
    .image-card {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .image-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .image-card.status-good {
        border-color: #28a745;
    }

    .image-card.status-warning {
        border-color: #ffc107;
    }

    .image-card.status-critical {
        border-color: #dc3545;
    }

    .image-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .image-card-body {
        padding: 0;
        position: relative;
    }

    .image-card-img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .image-score-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .image-card-footer {
        padding: 10px;
        background: #f8f9fa;
    }

    .issue-badge {
        font-size: 11px;
        padding: 4px 8px;
        margin: 2px;
        border-radius: 12px;
    }

    .best-practice-row {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }

    .best-practice-row:last-child {
        border-bottom: none;
    }

    .practice-icon {
        font-size: 24px;
        width: 40px;
        text-align: center;
    }

    .practice-content {
        flex: 1;
        margin-left: 16px;
    }

    .practice-status {
        width: 100px;
        text-align: right;
    }

    .optimal-order-item {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 8px;
        background: white;
        transition: all 0.2s ease;
    }

    .optimal-order-item:hover {
        border-color: #667eea;
    }

    .optimal-order-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        background: #667eea;
        color: white;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }

    .duplicate-group {
        border: 2px dashed #ffc107;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        background: rgba(255, 193, 7, 0.05);
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .analyzing {
        animation: pulse 1.5s ease-in-out infinite;
    }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // AI Image Analyzer State
    const imageAnalyzerState = {
        currentItemId: null,
        images: [],
        analysis: null,
        selectedImage: null
    };

    // Load products for image analysis
    async function loadProductsForImageAnalysis() {
        try {
            const {
                data
            } = await requestJson('/api/items?status=active&limit=50');

            const select = document.getElementById('imageProductSelect');
            select.innerHTML = '<option value="">Selecione um produto...</option>';

            // Support both {data: {items: []}} and {items: []} format
            const items = data.data?.items || data.items || [];

            if (items.length === 0) {
                select.innerHTML = '<option value="">Nenhum produto ativo encontrado</option>';
                return;
            }

            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `${item.title} (${item.id})`;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading products:', error);
            Toastify({
                text: 'Erro ao carregar produtos',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }).showToast();
        }
    }

    // Load product images preview
    async function loadProductImages() {
        const itemId = document.getElementById('imageProductSelect').value;
        if (!itemId) return;

        imageAnalyzerState.currentItemId = itemId;
    }

    // Analyze product images
    async function analyzeProductImages() {
        const itemId = imageAnalyzerState.currentItemId;
        if (!itemId) {
            Toastify({
                text: 'Selecione um produto primeiro',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }).showToast();
            return;
        }

        // Show analyzing state
        Toastify({
            text: 'Analisando imagens com IA...',
            duration: 2000,
            backgroundColor: 'linear-gradient(to right, #667eea, #764ba2)'
        }).showToast();

        try {
            const {
                data: payload
            } = await requestJson(`/api/ai/images/analyze/${itemId}`);

            imageAnalyzerState.analysis = payload;
            imageAnalyzerState.images = payload.images;

            // Render results
            renderImageAnalysisResults(payload);

            Toastify({
                text: 'Análise concluída!',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
            }).showToast();

        } catch (error) {
            console.error('Error analyzing images:', error);
            Toastify({
                text: 'Erro ao analisar imagens',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }).showToast();
        }
    }

    // Render analysis results
    function renderImageAnalysisResults(data) {
        // Show results section
        document.getElementById('imageAnalysisResults').style.display = 'block';

        // Update overall score
        document.getElementById('overallImageScore').textContent = data.overall_score + '/100';
        drawImageScoreGauge(data.overall_score);

        // Update summary counts
        document.getElementById('totalImagesCount').textContent = data.images.length;
        document.getElementById('goodImagesCount').textContent = data.images.filter(img => img.score >= 80).length;
        document.getElementById('warningImagesCount').textContent = data.images.filter(img => img.score >= 50 && img.score < 80).length;
        document.getElementById('criticalImagesCount').textContent = data.images.filter(img => img.score < 50).length;

        // Render images grid
        renderImagesGrid(data.images);

        // Render issues
        renderDetectedIssues(data.issues);

        // Render recommendations
        renderImageRecommendations(data.recommendations);

        // Render best practices comparison
        renderBestPracticesComparison(data.best_practices);

        // Render optimal order
        renderOptimalOrder(data.optimal_order);

        // Render duplicates if found
        if (data.duplicates && data.duplicates.length > 0) {
            renderDuplicates(data.duplicates);
        }
    }

    // Draw overall score gauge
    function drawImageScoreGauge(score) {
        const canvas = document.getElementById('overallImageScoreGauge');
        const ctx = canvas.getContext('2d');
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 60;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Background arc
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, 0.25 * Math.PI);
        ctx.lineWidth = 12;
        ctx.strokeStyle = '#e9ecef';
        ctx.stroke();

        // Score arc
        const scoreAngle = 0.75 * Math.PI + (score / 100) * 1.5 * Math.PI;
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, scoreAngle);
        ctx.lineWidth = 12;
        ctx.strokeStyle = getScoreColor(score);
        ctx.stroke();
    }

    // Render images grid
    function renderImagesGrid(images) {
        const grid = document.getElementById('imagesGrid');
        grid.innerHTML = '';

        images.forEach((image, index) => {
            const status = image.score >= 80 ? 'good' : image.score >= 50 ? 'warning' : 'critical';

            const col = document.createElement('div');
            col.className = 'col-md-3 image-item';
            col.setAttribute('data-status', status);

            col.innerHTML = `
            <div class="image-card status-${status}" onclick="showImageDetail(${index})">
                <div class="image-card-header">
                    <span>Imagem ${index + 1}</span>
                    <span class="badge bg-${status === 'good' ? 'success' : status === 'warning' ? 'warning' : 'danger'}">
                        ${status === 'good' ? 'Boa' : status === 'warning' ? 'Aviso' : 'Crítica'}
                    </span>
                </div>
                <div class="image-card-body">
                    <img src="${image.url}" alt="Image ${index + 1}" class="image-card-img">
                    <div class="image-score-badge" style="color: ${getScoreColor(image.score)}">
                        ${image.score}
                    </div>
                </div>
                <div class="image-card-footer">
                    ${image.issues.map(issue => `
                        <span class="badge issue-badge bg-${issue.severity === 'critical' ? 'danger' : 'warning'}">
                            ${issue.type}
                        </span>
                    `).join('')}
                    ${image.issues.length === 0 ? '<span class="text-success"><i class="bi bi-check-circle"></i> Sem problemas</span>' : ''}
                </div>
            </div>
        `;

            grid.appendChild(col);
        });
    }

    // Filter images
    function filterImages(filter) {
        const items = document.querySelectorAll('.image-item');

        items.forEach(item => {
            if (filter === 'all') {
                item.style.display = 'block';
            } else {
                item.style.display = item.getAttribute('data-status') === filter ? 'block' : 'none';
            }
        });
    }

    // Render detected issues
    function renderDetectedIssues(issues) {
        const container = document.getElementById('detectedIssues');

        if (issues.length === 0) {
            container.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Nenhum problema detectado!</div>';
            return;
        }

        container.innerHTML = `
        <ul class="list-group list-group-flush">
            ${issues.map(issue => `
                <li class="list-group-item">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-${issue.severity === 'critical' ? 'exclamation-circle text-danger' : 'exclamation-triangle text-warning'} fs-4 me-3"></i>
                        <div class="flex-grow-1">
                            <strong>${issue.title}</strong>
                            <p class="mb-0 text-muted small">${issue.description}</p>
                            <span class="badge bg-${issue.severity === 'critical' ? 'danger' : 'warning'} mt-2">${issue.affected_images} imagens afetadas</span>
                        </div>
                    </div>
                </li>
            `).join('')}
        </ul>
    `;
    }

    // Render recommendations
    function renderImageRecommendations(recommendations) {
        const container = document.getElementById('imageRecommendations');

        container.innerHTML = `
        <ul class="list-group list-group-flush">
            ${recommendations.map((rec, index) => `
                <li class="list-group-item">
                    <div class="d-flex align-items-start">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 30px; height: 30px; flex-shrink: 0;">
                            ${index + 1}
                        </div>
                        <div class="flex-grow-1">
                            <strong>${rec.title}</strong>
                            <p class="mb-0 text-muted small">${rec.description}</p>
                            <span class="badge bg-info mt-2">Impacto: ${rec.impact}</span>
                        </div>
                    </div>
                </li>
            `).join('')}
        </ul>
    `;
    }

    // Render best practices comparison
    function renderBestPracticesComparison(practices) {
        const container = document.getElementById('bestPracticesComparison');

        container.innerHTML = practices.map(practice => `
        <div class="best-practice-row">
            <div class="practice-icon">
                <i class="bi bi-${practice.passed ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'} fs-4"></i>
            </div>
            <div class="practice-content">
                <strong>${practice.name}</strong>
                <p class="mb-0 text-muted small">${practice.description}</p>
            </div>
            <div class="practice-status">
                ${practice.passed ? 
                    '<span class="badge bg-success">Cumprido</span>' : 
                    '<span class="badge bg-danger">Não cumprido</span>'}
            </div>
        </div>
    `).join('');
    }

    // Render optimal order
    function renderOptimalOrder(order) {
        const container = document.getElementById('optimalOrder');

        container.innerHTML = order.map((imageIndex, position) => {
            const image = imageAnalyzerState.images[imageIndex];
            return `
            <div class="col-md-2">
                <div class="optimal-order-item position-relative">
                    <div class="optimal-order-badge">${position + 1}</div>
                    <img src="${image.url}" alt="Position ${position + 1}" class="img-fluid rounded" style="height: 100px; object-fit: cover; width: 100%;">
                    <div class="text-center mt-2 small">
                        ${image.score >= 80 ? '<i class="bi bi-star-fill text-warning"></i>' : ''}
                        Score: ${image.score}
                    </div>
                </div>
            </div>
        `;
        }).join('');
    }

    // Render duplicates
    function renderDuplicates(duplicates) {
        const card = document.getElementById('duplicateDetectionCard');
        const container = document.getElementById('duplicatesList');

        card.style.display = 'block';

        container.innerHTML = duplicates.map((group, groupIndex) => `
        <div class="duplicate-group">
            <h6 class="mb-3">Grupo ${groupIndex + 1} - Similaridade: ${group.similarity}%</h6>
            <div class="row g-2">
                ${group.images.map(imageIndex => {
                    const image = imageAnalyzerState.images[imageIndex];
                    return `
                        <div class="col-md-3">
                            <img src="${image.url}" alt="Duplicate" class="img-fluid rounded">
                            <div class="text-center mt-2">
                                <button class="btn btn-sm btn-danger" onclick="removeDuplicateImage(${imageIndex})">
                                    <i class="bi bi-trash"></i> Remover
                                </button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `).join('');
    }

    // Show image detail modal
    function showImageDetail(index) {
        const image = imageAnalyzerState.images[index];
        imageAnalyzerState.selectedImage = index;

        // Populate modal
        document.getElementById('modalImagePreview').src = image.url;

        // Technical details
        document.getElementById('modalImageDetails').innerHTML = `
        <li><strong>Resolução:</strong> ${image.width}x${image.height}px</li>
        <li><strong>Formato:</strong> ${image.format}</li>
        <li><strong>Tamanho:</strong> ${(image.size / 1024).toFixed(2)} KB</li>
        <li><strong>Score de Qualidade:</strong> <span style="color: ${getScoreColor(image.score)}">${image.score}/100</span></li>
    `;

        // Issues
        if (image.issues.length > 0) {
            document.getElementById('modalImageIssues').innerHTML = `
            <ul>
                ${image.issues.map(issue => `<li class="text-${issue.severity === 'critical' ? 'danger' : 'warning'}">${issue.description}</li>`).join('')}
            </ul>
        `;
        } else {
            document.getElementById('modalImageIssues').innerHTML = '<p class="text-success"><i class="bi bi-check-circle"></i> Nenhum problema detectado</p>';
        }

        // Suggestions
        if (image.suggestions && image.suggestions.length > 0) {
            document.getElementById('modalImageSuggestions').innerHTML = `
            <ul>
                ${image.suggestions.map(suggestion => `<li>${suggestion}</li>`).join('')}
            </ul>
        `;
        } else {
            document.getElementById('modalImageSuggestions').innerHTML = '<p class="text-muted">Nenhuma sugestão adicional</p>';
        }

        // Show modal
        new bootstrap.Modal(document.getElementById('imageDetailModal')).show();
    }

    // Apply optimal order
    async function applyOptimalOrder() {
        if (!confirm('Deseja aplicar a ordem otimizada sugerida? Esta ação irá reordenar as imagens do produto no Mercado Livre.')) {
            return;
        }

        try {
            const {
                data
            } = await requestJson(`/api/ai/images/reorder/${imageAnalyzerState.currentItemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order: imageAnalyzerState.analysis.optimal_order
                })
            });

            const {
                success
            } = data;

            if (success) {
                Toastify({
                    text: 'Ordem aplicada com sucesso!',
                    duration: 3000,
                    backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
                }).showToast();
            }
        } catch (error) {
            console.error('Error applying order:', error);
            Toastify({
                text: 'Erro ao aplicar ordem',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }).showToast();
        }
    }

    // Remove image
    async function removeImage() {
        const index = imageAnalyzerState.selectedImage;
        if (index === null) return;

        if (!confirm('Deseja remover esta imagem do produto?')) {
            return;
        }

        try {
            const image = imageAnalyzerState.images[index];
            await requestJson(`/api/ai/images/remove`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: imageAnalyzerState.currentItemId,
                    image_url: image.url
                })
            });

            Toastify({
                text: 'Imagem removida com sucesso!',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
            }).showToast();

            bootstrap.Modal.getInstance(document.getElementById('imageDetailModal')).hide();
            analyzeProductImages(); // Refresh
        } catch (error) {
            console.error('Error removing image:', error);
            Toastify({
                text: 'Erro ao remover imagem',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }).showToast();
        }
    }

    // Replace image
    // Replace image
    function replaceImage() {
        document.getElementById('imageUploadInput').click();
    }

    // Handle image upload
    async function handleImageUpload(input) {
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('image', file);
        formData.append('item_id', imageAnalyzerState.currentItemId);

        if (imageAnalyzerState.selectedImage !== null) {
            const img = imageAnalyzerState.images[imageAnalyzerState.selectedImage];
            formData.append('replace_url', img.url);
        }

        try {
            Toastify({
                text: 'Enviando imagem...',
                duration: 2000,
                backgroundColor: 'linear-gradient(to right, #667eea, #764ba2)'
            }).showToast();

            const {
                data: result
            } = await requestJson('/api/ai/images/upload', {
                method: 'POST',
                body: formData
            });

            if (result.success || result.url) { // Handle both formats
                Toastify({
                    text: 'Imagem substituída com sucesso!',
                    duration: 3000,
                    backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
                }).showToast();
                bootstrap.Modal.getInstance(document.getElementById('imageDetailModal')).hide();
                analyzeProductImages();
            } else {
                throw new Error(result.error || 'Erro no upload');
            }
        } catch (error) {
            console.error('Error uploading:', error);
            Toastify({
                text: error.message,
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }).showToast();
        }
    }

    // Remove duplicate image
    async function removeDuplicateImage(index) {
        imageAnalyzerState.selectedImage = index;
        await removeImage();
    }

    // Refresh analysis
    function refreshImageAnalysis() {
        if (imageAnalyzerState.currentItemId) {
            analyzeProductImages();
        }
    }

    // Helper: Get score color
    function getScoreColor(score) {
        if (score >= 80) return '#28a745';
        if (score >= 50) return '#ffc107';
        return '#dc3545';
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        loadProductsForImageAnalysis();
    });
</script>