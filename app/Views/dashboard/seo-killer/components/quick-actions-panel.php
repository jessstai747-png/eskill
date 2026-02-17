<!-- Quick Actions Panel - Floating Side Panel -->
<div id="quickActionsPanel" class="quick-actions-panel">
    <!-- Toggle Button -->
    <button id="quickActionsToggle" class="quick-actions-toggle" onclick="toggleQuickActionsPanel()">
        <i class="bi bi-lightning-charge-fill"></i>
    </button>

    <!-- Panel Content -->
    <div id="quickActionsPanelContent" class="quick-actions-content" style="display: none;">
        <div class="quick-actions-header">
            <h6 class="mb-0">
                <i class="bi bi-lightning-charge-fill me-2"></i>
                Ações Rápidas
            </h6>
            <button class="btn btn-sm btn-link text-white p-0" onclick="toggleQuickActionsPanel()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="quick-actions-body">
            <!-- Recent Items -->
            <div class="quick-section">
                <h6 class="quick-section-title">
                    <i class="bi bi-clock-history"></i>
                    Itens Recentes
                </h6>
                <div id="recentItemsList" class="recent-items-list">
                    <div class="text-center text-muted small py-2">
                        <i class="bi bi-inbox"></i> Nenhum item recente
                    </div>
                </div>
            </div>

            <!-- Quick Optimize -->
            <div class="quick-section">
                <h6 class="quick-section-title">
                    <i class="bi bi-rocket-takeoff"></i>
                    Otimização Rápida
                </h6>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" id="quickOptimizeInput" placeholder="MLB123456789">
                    <button class="btn btn-primary" onclick="quickOptimizeItem()">
                        <i class="bi bi-magic"></i>
                    </button>
                </div>
            </div>

            <!-- Quick Actions Buttons -->
            <div class="quick-section">
                <h6 class="quick-section-title">
                    <i class="bi bi-grid-3x3-gap"></i>
                    Ferramentas
                </h6>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="SEOKiller.openTitleGenerator()" title="Gerador de Títulos (Ctrl+Shift+T)">
                        <i class="bi bi-fonts"></i>
                        <span>Títulos</span>
                    </button>
                    <button class="quick-action-btn" onclick="SEOKiller.openDescriptionGenerator()" title="Gerador de Descrições (Ctrl+Shift+D)">
                        <i class="bi bi-file-text"></i>
                        <span>Descrições</span>
                    </button>
                    <button class="quick-action-btn" onclick="SEOKiller.openKeywordResearch()" title="Pesquisa de Keywords (Ctrl+Shift+K)">
                        <i class="bi bi-key"></i>
                        <span>Keywords</span>
                    </button>
                    <button class="quick-action-btn" onclick="SEOKiller.openAttributeFiller()" title="Preencher Atributos">
                        <i class="bi bi-list-check"></i>
                        <span>Atributos</span>
                    </button>
                    <button class="quick-action-btn" onclick="SEOKiller.openImageAnalyzer()" title="Análise de Imagens">
                        <i class="bi bi-images"></i>
                        <span>Imagens</span>
                    </button>
                    <button class="quick-action-btn" onclick="SEOKiller.openBulkOptimizer()" title="Otimização em Lote (Ctrl+Shift+B)">
                        <i class="bi bi-collection"></i>
                        <span>Lote</span>
                    </button>
                </div>
            </div>

            <!-- Diagnostics -->
            <div class="quick-section">
                <h6 class="quick-section-title">
                    <i class="bi bi-activity"></i>
                    Status Rápido
                </h6>
                <div id="quickDiagnostics" class="quick-diagnostics">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small">Score Médio</span>
                        <span class="badge bg-warning" id="quickAvgScore">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small">Pendentes</span>
                        <span class="badge bg-danger" id="quickPendingCount">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small">Otimizados</span>
                        <span class="badge bg-success" id="quickOptimizedCount">--</span>
                    </div>
                    <button class="btn btn-sm btn-outline-light w-100 mt-2" onclick="refreshQuickDiagnostics()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                </div>
            </div>

            <!-- Keyboard Shortcuts -->
            <div class="quick-section">
                <h6 class="quick-section-title">
                    <i class="bi bi-keyboard"></i>
                    Atalhos
                </h6>
                <div class="shortcuts-list">
                    <div class="shortcut-item">
                        <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>T</kbd>
                        <span>Títulos</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>D</kbd>
                        <span>Descrições</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>K</kbd>
                        <span>Keywords</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>B</kbd>
                        <span>Lote</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .quick-actions-panel {
        position: fixed;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1040;
    }

    .quick-actions-toggle {
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 48px;
        height: 48px;
        border: none;
        background: linear-gradient(135deg, #ff4757, #ff6b81);
        color: white;
        border-radius: 12px 0 0 12px;
        font-size: 1.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: -4px 0 20px rgba(255, 71, 87, 0.3);
    }

    .quick-actions-toggle:hover {
        width: 56px;
        background: linear-gradient(135deg, #e8404f, #ff5a6e);
    }

    .quick-actions-toggle.active {
        border-radius: 0;
        right: 320px;
    }

    .quick-actions-content {
        position: fixed;
        right: 0;
        top: 0;
        width: 320px;
        height: 100vh;
        background: linear-gradient(180deg, #2f3542 0%, #1e272e 100%);
        color: white;
        box-shadow: -4px 0 30px rgba(0, 0, 0, 0.3);
        overflow-y: auto;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .quick-actions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .quick-actions-body {
        padding: 1rem;
    }

    .quick-section {
        margin-bottom: 1.5rem;
    }

    .quick-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .recent-items-list {
        max-height: 150px;
        overflow-y: auto;
    }

    .recent-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: background 0.2s;
    }

    .recent-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .recent-item img {
        width: 32px;
        height: 32px;
        border-radius: 4px;
        object-fit: cover;
    }

    .recent-item .item-title {
        font-size: 0.8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
    }

    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.75rem 0.5rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: white;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .quick-action-btn:hover {
        background: rgba(255, 71, 87, 0.2);
        border-color: rgba(255, 71, 87, 0.5);
        transform: translateY(-2px);
    }

    .quick-action-btn i {
        font-size: 1.25rem;
    }

    .quick-diagnostics {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        padding: 0.75rem;
    }

    .shortcuts-list {
        font-size: 0.75rem;
    }

    .shortcut-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.25rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .shortcut-item:last-child {
        border-bottom: none;
    }

    .shortcut-item kbd {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        font-size: 0.65rem;
    }

    .shortcut-item span {
        color: rgba(255, 255, 255, 0.6);
    }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // Quick Actions Panel State
    let quickActionsPanelState = {
        isOpen: false,
        recentItems: []
    };

    // Toggle Panel
    function toggleQuickActionsPanel() {
        const panel = document.getElementById('quickActionsPanelContent');
        const toggle = document.getElementById('quickActionsToggle');

        quickActionsPanelState.isOpen = !quickActionsPanelState.isOpen;

        if (quickActionsPanelState.isOpen) {
            panel.style.display = 'block';
            toggle.classList.add('active');
            refreshQuickDiagnostics();
            loadRecentItems();
        } else {
            panel.style.display = 'none';
            toggle.classList.remove('active');
        }
    }

    // Quick Optimize Item
    async function quickOptimizeItem() {
        const input = document.getElementById('quickOptimizeInput');
        const itemId = input.value.trim();

        if (!itemId) {
            SEOKiller.showError('Digite o ID do item (ex: MLB123456789)');
            return;
        }

        try {
            await SEOKiller.quickActions.optimizeItem(itemId);
            input.value = '';
            addToRecentItems(itemId);
        } catch (error) {
            console.error('Quick optimize error:', error);
        }
    }

    // Refresh Quick Diagnostics
    async function refreshQuickDiagnostics() {
        try {
            const {
                data
            } = await requestJson('/api/seo-killer/diagnose');

            if (data.success && data.stats) {
                document.getElementById('quickAvgScore').textContent =
                    data.stats.avgScore ? data.stats.avgScore.toFixed(0) : '--';
                document.getElementById('quickPendingCount').textContent =
                    data.stats.pending || 0;
                document.getElementById('quickOptimizedCount').textContent =
                    data.stats.optimized || 0;

                // Update badge colors based on score
                const avgScoreBadge = document.getElementById('quickAvgScore');
                const avgScore = data.stats.avgScore || 0;
                avgScoreBadge.className = 'badge ' + (avgScore >= 80 ? 'bg-success' : avgScore >= 50 ? 'bg-warning' : 'bg-danger');
            }
        } catch (error) {
            console.error('Error refreshing diagnostics:', error);
        }
    }

    // Add to Recent Items
    function addToRecentItems(itemId, title = null, thumbnail = null) {
        const existing = quickActionsPanelState.recentItems.findIndex(item => item.id === itemId);
        if (existing !== -1) {
            quickActionsPanelState.recentItems.splice(existing, 1);
        }

        quickActionsPanelState.recentItems.unshift({
            id: itemId,
            title: title || itemId,
            thumbnail: thumbnail,
            timestamp: Date.now()
        });

        // Keep only last 5 items
        quickActionsPanelState.recentItems = quickActionsPanelState.recentItems.slice(0, 5);

        // Save to localStorage
        localStorage.setItem('seo_killer_recent_items', JSON.stringify(quickActionsPanelState.recentItems));

        renderRecentItems();
    }

    // Load Recent Items from localStorage
    function loadRecentItems() {
        const saved = localStorage.getItem('seo_killer_recent_items');
        if (saved) {
            try {
                quickActionsPanelState.recentItems = JSON.parse(saved);
                renderRecentItems();
            } catch (e) {
                console.error('Error loading recent items:', e);
            }
        }
    }

    function normalizeExternalUrl(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        if (!trimmed) return '';
        if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('#')) return trimmed;
        if (trimmed.startsWith('//')) return `${window.location.protocol}${trimmed}`;
        if (trimmed.startsWith('http://')) return `https://${trimmed.slice('http://'.length)}`;
        return trimmed;
    }

    // Render Recent Items
    function renderRecentItems() {
        const container = document.getElementById('recentItemsList');

        if (quickActionsPanelState.recentItems.length === 0) {
            container.innerHTML = `
            <div class="text-center text-muted small py-2">
                <i class="bi bi-inbox"></i> Nenhum item recente
            </div>
        `;
            return;
        }

        container.innerHTML = quickActionsPanelState.recentItems.map(item => `
        <div class="recent-item" onclick="openRecentItem('${item.id}')">
            ${item.thumbnail 
                ? `<img src="${normalizeExternalUrl(item.thumbnail)}" alt="">`
                : '<div class="bg-secondary rounded" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-box small"></i></div>'
            }
            <div class="item-title">${item.title}</div>
            <i class="bi bi-arrow-right-circle small text-muted"></i>
        </div>
    `).join('');
    }

    // Open Recent Item
    function openRecentItem(itemId) {
        SEOKiller.openTitleGenerator(itemId);
        toggleQuickActionsPanel();
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        loadRecentItems();
    });

    // Close panel with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && quickActionsPanelState.isOpen) {
            toggleQuickActionsPanel();
        }
    });
</script>