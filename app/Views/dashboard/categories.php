<?php
$pageTitle = 'Navegador de Categorias';
$activePage = 'categories';

// Account Selector
$requireAccountSelection = true;
$showAccountBanner = true;
include __DIR__ . '/../components/account-selector.php';
?>

<div class="container-fluid px-0 px-md-4 py-4">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h5 class="mb-0 flex-grow-1">
                        <i class="bi bi-diagram-3"></i> Categorias
                    </h5>
                    <div class="search-wrapper flex-shrink-0">
                        <input type="text" class="form-control form-control-sm" id="category-search" placeholder="Buscar...">
                    </div>
                </div>
                <div class="card-body category-tree" id="category-tree">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0" id="category-title">
                        <i class="bi bi-info-circle"></i> Selecione uma categoria
                    </h5>
                </div>
                <div class="card-body" id="category-details">
                    <p class="text-muted mb-0">Selecione uma categoria à esquerda para ver detalhes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .category-tree {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    @media (max-width: 991.98px) {
        .category-tree {
            max-height: 400px;
        }

        .search-wrapper {
            width: 100%;
        }
    }

    .category-item {
        cursor: pointer;
        padding: 0.35rem 0.5rem;
        border-radius: var(--bs-border-radius);
        color: var(--bs-body-color);
        transition: background-color 0.2s ease;
    }

    .category-item:hover {
        background-color: rgba(13, 110, 253, 0.08);
    }

    .category-item.active {
        background-color: var(--bs-primary);
        color: #fff;
    }

    .category-item.active .category-toggle {
        color: #fff;
    }

    .category-toggle {
        color: var(--bs-secondary-color);
    }

    .category-toggle-placeholder {
        display: inline-block;
        width: 1rem;
    }

    .category-children {
        margin-left: 1.25rem;
        padding-left: 0.75rem;
        border-left: 1px dashed rgba(0, 0, 0, 0.08);
        display: none;
    }

    .category-children.show {
        display: block;
    }

    .category-tree-empty {
        text-align: center;
        color: var(--bs-secondary-color);
    }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    async function requestJson(url, options = {}) {
        if (window.ApiClient) return window.ApiClient.request(url, options);
        const resp = await fetch(url, { credentials: 'include', ...options });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp.json();
    }

    (() => {
        const treeContainer = document.getElementById('category-tree');
        const searchInput = document.getElementById('category-search');
        const titleElement = document.getElementById('category-title');
        const detailsElement = document.getElementById('category-details');

        let allCategories = [];
        let selectedCategory = null;
        let searchTimeout = null;
        const parentMap = new Map();

        const setTreeLoading = () => {
            treeContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
        `;
        };

        const setTreeError = (message = 'Erro ao carregar categorias', details = null) => {
            let errorHtml = `<div class="alert alert-danger mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>${message}`;
            
            if (details) {
                errorHtml += `<br><small class="text-muted mt-2 d-block">${details}</small>`;
            }
            
            errorHtml += `</div>`;
            treeContainer.innerHTML = errorHtml;
        };

        const highlightSelected = () => {
            treeContainer.querySelectorAll('.category-item').forEach(item => {
                item.classList.toggle('active', item.dataset.categoryId === selectedCategory);
            });
        };

        const buildParentMap = categories => {
            parentMap.clear();
            categories.forEach(category => {
                parentMap.set(category.id, category.parent_id || null);
            });
        };

        const expandToCategory = categoryId => {
            if (!categoryId) {
                return;
            }

            let current = parentMap.get(categoryId);

            while (current) {
                const wrapper = treeContainer.querySelector(`.category-children[data-parent-id="${current}"]`);
                if (wrapper && !wrapper.classList.contains('show')) {
                    wrapper.classList.add('show');
                    const icon = treeContainer.querySelector(`.category-item[data-category-id="${current}"] .category-toggle i`);
                    if (icon) {
                        icon.classList.remove('bi-chevron-right');
                        icon.classList.add('bi-chevron-down');
                    }
                }
                current = parentMap.get(current) || null;
            }
        };

        const renderCategoryTree = (categories, parentId = null, container = treeContainer, level = 0) => {
            if (!Array.isArray(categories) || categories.length === 0) {
                if (level === 0) {
                    container.innerHTML = '<p class="category-tree-empty mb-0">Nenhuma categoria encontrada.</p>';
                }
                return;
            }

            if (container === treeContainer && level === 0) {
                container.innerHTML = '';
            }

            const currentLevel = categories.filter(category => {
                if (parentId) {
                    return category.parent_id === parentId;
                }
                return !category.parent_id || !categories.some(c => c.id === category.parent_id);
            });

            if (currentLevel.length === 0 && level === 0) {
                container.innerHTML = '<p class="category-tree-empty mb-0">Nenhuma categoria encontrada.</p>';
                return;
            }

            currentLevel.forEach(category => {
                const item = document.createElement('div');
                item.className = 'category-item d-flex align-items-center gap-2';
                item.dataset.categoryId = category.id;

                const hasChildren = categories.some(child => child.parent_id === category.id);
                let childrenWrapper = null;

                if (hasChildren) {
                    childrenWrapper = document.createElement('div');
                    childrenWrapper.className = 'category-children';
                }

                if (hasChildren) {
                    const toggleBtn = document.createElement('button');
                    toggleBtn.type = 'button';
                    toggleBtn.className = 'category-toggle btn btn-link btn-sm p-0 d-flex align-items-center';
                    toggleBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
                    toggleBtn.addEventListener('click', event => {
                        event.stopPropagation();
                        childrenWrapper.classList.toggle('show');
                        const icon = toggleBtn.querySelector('i');
                        icon.classList.toggle('bi-chevron-right');
                        icon.classList.toggle('bi-chevron-down');
                    });
                    item.appendChild(toggleBtn);
                } else {
                    const spacer = document.createElement('span');
                    spacer.className = 'category-toggle-placeholder';
                    item.appendChild(spacer);
                }

                const label = document.createElement('span');
                label.className = 'flex-grow-1';
                label.textContent = category.name;
                label.addEventListener('click', () => selectCategory(category.id, category.name));
                item.appendChild(label);

                container.appendChild(item);

                if (hasChildren && childrenWrapper) {
                    childrenWrapper.dataset.parentId = category.id;
                    container.appendChild(childrenWrapper);
                    renderCategoryTree(categories, category.id, childrenWrapper, level + 1);
                }
            });

            if (container === treeContainer && level === 0) {
                if (categories === allCategories) {
                    expandToCategory(selectedCategory);
                }
                highlightSelected();
            }
        };

        const selectCategory = (categoryId, categoryName) => {
            selectedCategory = categoryId;
            expandToCategory(categoryId);
            highlightSelected();
            titleElement.innerHTML = `<i class="bi bi-tag"></i> ${categoryName}`;
            loadCategoryDetails(categoryId);
        };

        const loadCategoryDetails = categoryId => {
            detailsElement.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

            Promise.all([
                    requestJson(`/api/categories/${categoryId}`),
                    requestJson(`/api/categories/${categoryId}/brands`),
                    requestJson(`/api/categories/${categoryId}/subcategories`)
                ])
                .then(([category, brands, subcategories]) => {
                    let html = `
                    <div class="mb-3">
                        <h6>Informações</h6>
                        <p class="mb-1"><strong>ID:</strong> ${category.id || categoryId}</p>
                        <p class="mb-1"><strong>Nome:</strong> ${category.name || 'N/A'}</p>
                        <p class="mb-1"><strong>Total de itens:</strong> ${category.total_items_in_this_category || 0}</p>
                    </div>
                `;

                    if (Array.isArray(subcategories) && subcategories.length > 0) {
                        html += '<div class="mb-3">';
                        html += `<h6>Subcategorias (${subcategories.length})</h6>`;
                        html += '<div class="list-group">';
                        subcategories.slice(0, 10).forEach(sub => {
                            html += `<button class="list-group-item list-group-item-action text-start" data-subcategory-id="${sub.id}">${sub.name}</button>`;
                        });
                        html += '</div></div>';
                    }

                    if (Array.isArray(brands) && brands.length > 0) {
                        html += '<div class="mb-3">';
                        html += `<h6>Marcas Disponíveis (${brands.length})</h6>`;
                        html += '<div class="d-flex flex-wrap gap-2">';
                        brands.slice(0, 20).forEach(brand => {
                            html += `<span class="badge bg-secondary">${brand.name}</span>`;
                        });
                        html += '</div></div>';
                    }

                    html += `
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <a href="/dashboard/analysis?category=${encodeURIComponent(categoryId)}" class="btn btn-primary">
                            <i class="bi bi-search"></i> Analisar esta categoria
                        </a>
                        <a href="https://www.mercadolivre.com.br/categorias#category=${encodeURIComponent(categoryId)}" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right"></i> Ver no Mercado Livre
                        </a>
                    </div>
                `;

                    detailsElement.innerHTML = html;

                    detailsElement.querySelectorAll('[data-subcategory-id]').forEach(button => {
                        button.addEventListener('click', () => selectCategory(button.dataset.subcategoryId, button.textContent.trim()));
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar detalhes:', error);
                    detailsElement.innerHTML = '<div class="alert alert-danger mb-0">Erro ao carregar detalhes da categoria</div>';
                });
        };

        const handleSearch = () => {
            const term = searchInput.value.trim().toLowerCase();

            if (term.length < 2) {
                renderCategoryTree(allCategories);
                return;
            }

            const filtered = allCategories.filter(category =>
                category.name?.toLowerCase().includes(term)
            );

            renderCategoryTree(filtered);
        };

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(handleSearch, 250);
        });

        setTreeLoading();

        // ApiClient adiciona retry em 429/503 e tratamento de 401
        (window.ApiClient ? window.ApiClient.fetch('/api/categories/tree') : fetch('/api/categories/tree'))
            .then(async response => {
                // Always try to parse JSON, even on error
                const data = await response.json().catch(() => null);
                
                if (!response.ok) {
                    // Handle HTTP errors with parsed data
                    if (data && data.error) {
                        let errorMsg = 'Erro ao carregar categorias';
                        let errorDetails = '';
                        
                        if (data.error === 'no_valid_token') {
                            errorMsg = 'Nenhuma conta do Mercado Livre está ativa';
                            errorDetails = 'Por favor, ative uma conta na página de Contas para visualizar as categorias.';
                        } else {
                            errorDetails = data.message || data.error;
                        }
                        
                        setTreeError(errorMsg, errorDetails);
                        return null; // Signal to skip further processing
                    }
                    
                    throw new Error('Falha na resposta da API (HTTP ' + response.status + ')');
                }
                
                return data;
            })
            .then(data => {
                // Skip if error was already handled
                if (data === null) {
                    return;
                }
                
                // Validate response is an array
                if (!Array.isArray(data)) {
                    console.error('Resposta inválida da API:', data);
                    
                    // Check for specific errors (shouldn't happen here but keep as fallback)
                    if (data && data.error) {
                        let errorMsg = 'Erro ao carregar categorias';
                        let errorDetails = '';
                        
                        if (data.error === 'no_valid_token') {
                            errorMsg = 'Nenhuma conta do Mercado Livre está ativa';
                            errorDetails = 'Por favor, ative uma conta na página de Contas para visualizar as categorias.';
                        } else {
                            errorDetails = data.message || data.error;
                        }
                        
                        setTreeError(errorMsg, errorDetails);
                        return;
                    }
                    
                    throw new Error(data?.error || 'Resposta não é um array de categorias');
                }
                
                allCategories = data;
                buildParentMap(allCategories);
                renderCategoryTree(allCategories);
            })
            .catch(error => {
                console.error('Erro ao carregar categorias:', error);
                setTreeError('Erro ao carregar categorias', error.message);
            });
    })();
</script>