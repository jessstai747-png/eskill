<!-- Image Analyzer Modal -->
<div class="modal fade" id="imageAnalyzerModal" tabindex="-1" aria-labelledby="imageAnalyzerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="imageAnalyzerModalLabel">
                    <i class="bi bi-camera-fill me-2"></i>
                    Análise de Imagens 📸
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <!-- Product Selection -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <label for="imageAnalyzerProductSelect" class="form-label fw-bold">
                            <i class="bi bi-box-seam me-2"></i>Selecione o Produto:
                        </label>
                        <select class="form-select" id="imageAnalyzerProductSelect">
                            <option value="">Carregando produtos...</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" id="analyzeImagesBtn" disabled>
                            <i class="bi bi-search me-2"></i>Analisar Imagens
                        </button>
                    </div>
                </div>

                <!-- Analysis Results (hidden by default) -->
                <div id="imageAnalysisResults" style="display: none;">

                    <!-- Overall Score Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="mb-1">Score Geral de Qualidade das Imagens</h5>
                                    <p class="text-muted mb-0">Baseado em resolução, qualidade, quantidade e otimização SEO</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="position-relative d-inline-block">
                                        <svg width="120" height="120">
                                            <circle cx="60" cy="60" r="50" fill="none" stroke="#e9ecef" stroke-width="10" />
                                            <circle id="imageScoreCircle" cx="60" cy="60" r="50" fill="none" stroke="#28a745" stroke-width="10"
                                                stroke-dasharray="314" stroke-dashoffset="314"
                                                transform="rotate(-90 60 60)"
                                                style="transition: stroke-dashoffset 1s ease;" />
                                        </svg>
                                        <div class="position-absolute top-50 start-50 translate-middle">
                                            <h2 class="mb-0" id="overallImageScore">-</h2>
                                            <small class="text-muted">/100</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <i class="bi bi-images text-info fs-3"></i>
                                    <h4 class="mt-2 mb-0" id="totalImagesCount">-</h4>
                                    <small class="text-muted">Total de Imagens</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <i class="bi bi-check-circle text-success fs-3"></i>
                                    <h4 class="mt-2 mb-0" id="goodImagesCount">-</h4>
                                    <small class="text-muted">Imagens OK</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <i class="bi bi-exclamation-triangle text-warning fs-3"></i>
                                    <h4 class="mt-2 mb-0" id="warningImagesCount">-</h4>
                                    <small class="text-muted">Atenção</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <i class="bi bi-x-circle text-danger fs-3"></i>
                                    <h4 class="mt-2 mb-0" id="criticalImagesCount">-</h4>
                                    <small class="text-muted">Crítico</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Images Grid -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-grid-3x3-gap me-2"></i>
                                Galeria de Imagens
                            </h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="uploadImageBtn">
                                    <i class="bi bi-upload me-1"></i>Adicionar Imagem
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="reorderImagesBtn">
                                    <i class="bi bi-arrow-left-right me-1"></i>Reordenar
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="imagesGrid" class="row g-3">
                                <!-- Images will be dynamically loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-warning bg-opacity-10 border-bottom">
                            <h5 class="mb-0">
                                <i class="bi bi-lightbulb text-warning me-2"></i>
                                Recomendações para Melhorar
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="imageRecommendations">
                                <!-- Recommendations will be dynamically loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- SEO Tips -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info bg-opacity-10 border-bottom">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle text-info me-2"></i>
                                Dicas de SEO para Imagens
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Mínimo 6 imagens:</strong> Anúncios com mais imagens têm 30% mais conversões
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Resolução ideal:</strong> 1200x1200 pixels ou superior
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Fundo branco:</strong> Use fundo branco nas primeiras 3 imagens
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Contexto de uso:</strong> Inclua imagens mostrando o produto em uso
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Detalhes importantes:</strong> Close-ups de características relevantes
                                </li>
                                <li class="mb-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Sem marcas d'água:</strong> Evite marcas d'água ou logos que poluem a imagem
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>

                <!-- Empty State -->
                <div id="imageAnalysisEmpty" class="text-center py-5">
                    <i class="bi bi-image text-muted" style="font-size: 5rem;"></i>
                    <h4 class="mt-4 text-muted">Analise as Imagens do Seu Produto</h4>
                    <p class="text-muted">Selecione um produto acima para começar a análise de qualidade das imagens</p>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success" id="applyImageChangesBtn" style="display: none;">
                    <i class="bi bi-check-circle me-2"></i>Aplicar Mudanças
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Upload Modal -->
<div class="modal fade" id="imageUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upload me-2"></i>Upload de Imagem
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="imageUploadInput" class="form-label">Selecione a imagem:</label>
                    <input type="file" class="form-control" id="imageUploadInput" accept="image/*" multiple>
                    <small class="text-muted">Formatos aceitos: JPG, PNG, WEBP (máx. 10MB por imagem)</small>
                </div>
                <div id="imageUploadPreview" class="row g-2" style="display: none;">
                    <!-- Preview will be shown here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmImageUploadBtn" disabled>
                    <i class="bi bi-upload me-2"></i>Fazer Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Detail Modal -->
<div class="modal fade" id="imageDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageDetailModalLabel">Detalhes da Imagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="imageDetailBody">
                <!-- Content will be dynamically loaded -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="removeImageBtn">
                    <i class="bi bi-trash me-2"></i>Remover Imagem
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Analyzer JavaScript -->
<script nonce="<?= CSP_NONCE ?>">
    const ImageAnalyzer = {
        currentProduct: null,
        currentImages: [],
        pendingChanges: [],
        sortableInstance: null,

        init() {
            console.log('Initializing Image Analyzer...');
            this.loadProductsList();
            this.setupEventListeners();
        },

        setupEventListeners() {
            // Product selection
            document.getElementById('imageAnalyzerProductSelect')?.addEventListener('change', (e) => {
                const analyzeBtn = document.getElementById('analyzeImagesBtn');
                if (analyzeBtn) {
                    analyzeBtn.disabled = !e.target.value;
                }
            });

            // Analyze button
            document.getElementById('analyzeImagesBtn')?.addEventListener('click', () => {
                this.analyzeImages();
            });

            // Upload button
            document.getElementById('uploadImageBtn')?.addEventListener('click', () => {
                this.showUploadModal();
            });

            // Reorder button
            document.getElementById('reorderImagesBtn')?.addEventListener('click', () => {
                this.toggleReorderMode();
            });

            // Apply changes button
            document.getElementById('applyImageChangesBtn')?.addEventListener('click', () => {
                this.applyChanges();
            });

            // Image upload input
            document.getElementById('imageUploadInput')?.addEventListener('change', (e) => {
                this.handleImageUpload(e);
            });

            // Confirm upload button
            document.getElementById('confirmImageUploadBtn')?.addEventListener('click', () => {
                this.confirmImageUpload();
            });

            // Remove image button
            document.getElementById('removeImageBtn')?.addEventListener('click', () => {
                this.removeCurrentImage();
            });
        },

        async loadProductsList() {
            try {
                const data = await requestJson('/api/items?limit=100');
                const select = document.getElementById('imageAnalyzerProductSelect');

                if (!select) return;

                select.innerHTML = '<option value="">Selecione um produto...</option>' +
                    data.items.map(item => `
                    <option value="${item.id}">${this.escapeHtml(item.title)} (${item.ml_item_id})</option>
                `).join('');

            } catch (error) {
                console.error('Error loading products list:', error);
            }
        },

        async analyzeImages() {
            const select = document.getElementById('imageAnalyzerProductSelect');
            if (!select?.value) return;

            this.currentProduct = select.value;

            const resultsDiv = document.getElementById('imageAnalysisResults');
            const emptyDiv = document.getElementById('imageAnalysisEmpty');

            if (resultsDiv) resultsDiv.style.display = 'block';
            if (emptyDiv) emptyDiv.style.display = 'none';

            // Show loading state
            const gridDiv = document.getElementById('imagesGrid');
            if (gridDiv) {
                gridDiv.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Analisando imagens...</p>
                </div>
            `;
            }

            try {
                const data = await requestJson(`/api/seo-killer/images/analyze/${this.currentProduct}`);

                this.currentImages = data.images || [];

                // Update overall score
                this.updateOverallScore(data.overall_score || 0);

                // Update quick stats
                this.updateQuickStats(data.stats);

                // Render images grid
                this.renderImagesGrid(data.images);

                // Show recommendations
                this.renderRecommendations(data.recommendations);

            } catch (error) {
                console.error('Error analyzing images:', error);
                this.showError('Erro ao analisar imagens');
                if (gridDiv) {
                    gridDiv.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Erro ao analisar imagens. Tente novamente.
                        </div>
                    </div>
                `;
                }
            }
        },

        updateOverallScore(score) {
            const scoreEl = document.getElementById('overallImageScore');
            const circleEl = document.getElementById('imageScoreCircle');

            if (scoreEl) scoreEl.textContent = score;

            if (circleEl) {
                // Calculate stroke-dashoffset for progress circle
                const circumference = 314; // 2 * π * radius (50)
                const offset = circumference - (score / 100) * circumference;
                circleEl.style.strokeDashoffset = offset;

                // Change color based on score
                if (score >= 80) {
                    circleEl.style.stroke = '#28a745'; // Green
                } else if (score >= 60) {
                    circleEl.style.stroke = '#ffc107'; // Yellow
                } else {
                    circleEl.style.stroke = '#dc3545'; // Red
                }
            }
        },

        updateQuickStats(stats) {
            document.getElementById('totalImagesCount').textContent = stats?.total || 0;
            document.getElementById('goodImagesCount').textContent = stats?.good || 0;
            document.getElementById('warningImagesCount').textContent = stats?.warning || 0;
            document.getElementById('criticalImagesCount').textContent = stats?.critical || 0;
        },

        renderImagesGrid(images) {
            const gridDiv = document.getElementById('imagesGrid');
            if (!gridDiv) return;

            if (!images || images.length === 0) {
                gridDiv.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">Nenhuma imagem encontrada para este produto</p>
                </div>
            `;
                return;
            }

            gridDiv.innerHTML = images.map((img, index) => `
            <div class="col-md-3" data-image-id="${img.id}">
                <div class="card h-100 image-card ${this.getImageStatusClass(img.status)}"
                     onclick="ImageAnalyzer.showImageDetail('${img.id}')">
                    <div class="position-relative">
                        <img src="${img.url}" class="card-img-top" alt="Imagem ${index + 1}"
                             style="height: 200px; object-fit: cover; cursor: pointer;">
                        <div class="position-absolute top-0 start-0 m-2">
                            <span class="badge bg-dark bg-opacity-75">#${index + 1}</span>
                        </div>
                        <div class="position-absolute top-0 end-0 m-2">
                            ${this.getStatusBadge(img.status)}
                        </div>
                        <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-50 text-white p-2">
                            <small class="d-block"><strong>Score:</strong> ${img.score}/100</small>
                            <small class="d-block">${img.resolution || 'N/A'}</small>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        ${img.problems && img.problems.length > 0 ? `
                            <div class="text-danger small">
                                <i class="bi bi-exclamation-circle me-1"></i>
                                ${img.problems.length} problema(s)
                            </div>
                        ` : `
                            <div class="text-success small">
                                <i class="bi bi-check-circle me-1"></i>
                                Tudo OK
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `).join('');
        },

        getImageStatusClass(status) {
            const classes = {
                'ok': 'border-success',
                'warning': 'border-warning',
                'critical': 'border-danger'
            };
            return classes[status] || '';
        },

        getStatusBadge(status) {
            const badges = {
                'ok': '<span class="badge bg-success">✓ OK</span>',
                'warning': '<span class="badge bg-warning">⚠ Atenção</span>',
                'critical': '<span class="badge bg-danger">✕ Crítico</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">?</span>';
        },

        renderRecommendations(recommendations) {
            const container = document.getElementById('imageRecommendations');
            if (!container || !recommendations || recommendations.length === 0) return;

            container.innerHTML = `
            <div class="list-group">
                ${recommendations.map(rec => `
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <i class="bi bi-${this.getRecommendationIcon(rec.priority)} me-2"></i>
                                    ${this.escapeHtml(rec.title)}
                                </h6>
                                <p class="mb-1">${this.escapeHtml(rec.description)}</p>
                                ${rec.impact ? `
                                    <small class="text-muted">
                                        <strong>Impacto:</strong> ${this.escapeHtml(rec.impact)}
                                    </small>
                                ` : ''}
                            </div>
                            <span class="badge bg-${rec.priority === 'high' ? 'danger' : rec.priority === 'medium' ? 'warning' : 'info'} rounded-pill">
                                ${rec.priority === 'high' ? 'Alta' : rec.priority === 'medium' ? 'Média' : 'Baixa'}
                            </span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        },

        getRecommendationIcon(priority) {
            const icons = {
                'high': 'exclamation-triangle-fill text-danger',
                'medium': 'exclamation-circle-fill text-warning',
                'low': 'info-circle-fill text-info'
            };
            return icons[priority] || 'info-circle';
        },

        showImageDetail(imageId) {
            const image = this.currentImages.find(img => img.id === imageId);
            if (!image) return;

            const modal = new bootstrap.Modal(document.getElementById('imageDetailModal'));
            const body = document.getElementById('imageDetailBody');

            if (body) {
                body.innerHTML = `
                <div class="text-center mb-4">
                    <img src="${image.url}" class="img-fluid rounded" alt="Imagem" style="max-height: 400px;">
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Score de Qualidade:</strong>
                        <div class="progress mt-2" style="height: 25px;">
                            <div class="progress-bar bg-${image.score >= 80 ? 'success' : image.score >= 60 ? 'warning' : 'danger'}"
                                 role="progressbar"
                                 style="width: ${image.score}%"
                                 aria-valuenow="${image.score}"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                                ${image.score}/100
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        ${this.getStatusBadge(image.status)}
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Informações Técnicas:</strong>
                    <ul class="list-unstyled mt-2">
                        <li><i class="bi bi-aspect-ratio me-2"></i>Resolução: ${image.resolution || 'N/A'}</li>
                        <li><i class="bi bi-file-earmark me-2"></i>Tamanho: ${image.fileSize || 'N/A'}</li>
                        <li><i class="bi bi-palette me-2"></i>Formato: ${image.format || 'N/A'}</li>
                    </ul>
                </div>

                ${image.problems && image.problems.length > 0 ? `
                    <div class="mb-3">
                        <strong class="text-danger">Problemas Detectados:</strong>
                        <ul class="list-group mt-2">
                            ${image.problems.map(problem => `
                                <li class="list-group-item list-group-item-danger">
                                    <i class="bi bi-x-circle me-2"></i>${this.escapeHtml(problem)}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                ` : `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Nenhum problema detectado nesta imagem!
                    </div>
                `}
            `;

                // Store current image ID for removal
                document.getElementById('removeImageBtn').dataset.imageId = imageId;
            }

            modal.show();
        },

        showUploadModal() {
            const modal = new bootstrap.Modal(document.getElementById('imageUploadModal'));
            document.getElementById('imageUploadInput').value = '';
            document.getElementById('imageUploadPreview').style.display = 'none';
            document.getElementById('confirmImageUploadBtn').disabled = true;
            modal.show();
        },

        handleImageUpload(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;

            const previewDiv = document.getElementById('imageUploadPreview');
            const confirmBtn = document.getElementById('confirmImageUploadBtn');

            if (!previewDiv) return;

            previewDiv.style.display = 'block';
            previewDiv.innerHTML = '';

            Array.from(files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewDiv.innerHTML += `
                    <div class="col-4">
                        <img src="${e.target.result}" class="img-thumbnail" alt="Preview ${index + 1}">
                    </div>
                `;
                };
                reader.readAsDataURL(file);
            });

            if (confirmBtn) confirmBtn.disabled = false;
        },

        async confirmImageUpload() {
            const input = document.getElementById('imageUploadInput');
            if (!input?.files || input.files.length === 0) return;

            const formData = new FormData();
            formData.append('item_id', this.currentProduct);

            Array.from(input.files).forEach((file, index) => {
                formData.append(`images[${index}]`, file);
            });

            const btn = document.getElementById('confirmImageUploadBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
            }

            try {
                const data = await requestJson('/api/seo-killer/images/upload', {
                    method: 'POST',
                    body: formData
                });

                this.showSuccess('Imagens enviadas com sucesso!');

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('imageUploadModal')).hide();

                // Reload analysis
                this.analyzeImages();

                // Mark changes as pending
                this.addPendingChange('upload', data);

            } catch (error) {
                console.error('Error uploading images:', error);
                this.showError('Erro ao enviar imagens');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-upload me-2"></i>Fazer Upload';
                }
            }
        },

        toggleReorderMode() {
            const btn = document.getElementById('reorderImagesBtn');
            const gridDiv = document.getElementById('imagesGrid');

            if (!btn || !gridDiv) return;

            if (this.sortableInstance) {
                // Disable reorder mode
                this.sortableInstance.destroy();
                this.sortableInstance = null;
                btn.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i>Reordenar';
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-outline-secondary');
            } else {
                // Enable reorder mode
                if (typeof Sortable !== 'undefined') {
                    this.sortableInstance = Sortable.create(gridDiv, {
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        onEnd: () => {
                            this.addPendingChange('reorder', this.getCurrentImageOrder());
                        }
                    });
                    btn.innerHTML = '<i class="bi bi-check me-1"></i>Concluir';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-warning');
                    this.showSuccess('Modo reordenação ativo. Arraste as imagens para reordenar.');
                } else {
                    this.showError('Biblioteca Sortable não carregada');
                }
            }
        },

        getCurrentImageOrder() {
            const gridDiv = document.getElementById('imagesGrid');
            if (!gridDiv) return [];

            const imageCards = gridDiv.querySelectorAll('[data-image-id]');
            return Array.from(imageCards).map(card => card.dataset.imageId);
        },

        removeCurrentImage() {
            const btn = document.getElementById('removeImageBtn');
            const imageId = btn?.dataset.imageId;

            if (!imageId) return;

            if (!confirm('Tem certeza que deseja remover esta imagem?')) return;

            this.addPendingChange('remove', imageId);

            // Remove from current images array
            this.currentImages = this.currentImages.filter(img => img.id !== imageId);

            // Re-render grid
            this.renderImagesGrid(this.currentImages);

            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('imageDetailModal')).hide();

            this.showSuccess('Imagem marcada para remoção');
        },

        addPendingChange(type, data) {
            this.pendingChanges.push({
                type,
                data
            });

            // Show apply button
            const applyBtn = document.getElementById('applyImageChangesBtn');
            if (applyBtn) applyBtn.style.display = 'inline-block';
        },

        async applyChanges() {
            if (this.pendingChanges.length === 0) return;

            const btn = document.getElementById('applyImageChangesBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aplicando...';
            }

            try {
                const data = await requestJson(`/api/seo-killer/images/update/${this.currentProduct}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        changes: this.pendingChanges
                    })
                });

                this.showSuccess('Mudanças aplicadas com sucesso!');

                // Clear pending changes
                this.pendingChanges = [];
                if (btn) btn.style.display = 'none';

                // Reload analysis
                setTimeout(() => this.analyzeImages(), 1000);

            } catch (error) {
                console.error('Error applying changes:', error);
                this.showError('Erro ao aplicar mudanças');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Aplicar Mudanças';
                }
            }
        },

        // Utility functions
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showSuccess(message) {
            // Use your preferred notification system
            alert(message); // Placeholder
        },

        showError(message) {
            // Use your preferred notification system
            alert(message); // Placeholder
        }
    };

    // Initialize when modal is shown
    document.getElementById('imageAnalyzerModal')?.addEventListener('shown.bs.modal', function() {
        ImageAnalyzer.init();
    });
</script>

<!-- Additional CSS for Image Analyzer -->
<style>
    .image-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .image-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .sortable-ghost {
        opacity: 0.5;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
</style>

<!-- Load SortableJS for drag & drop -->
<script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
