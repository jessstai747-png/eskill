<!-- Command Palette Modal -->
<div id="command-palette" class="command-palette-backdrop" style="display: none;">
    <div class="command-palette-modal">
        <div class="cp-header">
            <i class="bi bi-search cp-search-icon"></i>
            <input type="text" id="cp-input" placeholder="O que você procura? (Comandos, páginas, buscas...)" autocomplete="off">
            <span class="cp-shortcut-hint">ESC</span>
        </div>
        <div class="cp-body">
            <div id="cp-results" class="cp-results-list">
                <!-- Navigation Group -->
                <div class="cp-group-header">Navegação</div>
                <a href="/dashboard" class="cp-item" data-keywords="home dashboard inicio">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                    <span class="cp-item-shortcut">G D</span>
                </a>
                <a href="/dashboard/items" class="cp-item" data-keywords="anuncios produtos catalogo itens">
                    <i class="bi bi-box-seam"></i>
                    <span>Anúncios</span>
                    <span class="cp-item-shortcut">G I</span>
                </a>
                 <a href="/dashboard/seo-killer" class="cp-item" data-keywords="seo otimizacao keywords killer">
                    <i class="bi bi-fire"></i>
                    <span>SEO Killer</span>
                    <span class="cp-item-shortcut">G S</span>
                </a>
                <a href="/dashboard/orders" class="cp-item" data-keywords="pedidos vendas orders">
                    <i class="bi bi-cart"></i>
                    <span>Pedidos</span>
                    <span class="cp-item-shortcut">G O</span>
                </a>
                <a href="/dashboard/messages" class="cp-item" data-keywords="mensagens perguntas chat">
                    <i class="bi bi-chat-dots"></i>
                    <span>Mensagens</span>
                    <span class="cp-item-shortcut">G M</span>
                </a>

                <!-- Actions Group -->
                <div class="cp-group-header">Ações</div>
                <div class="cp-item" onclick="location.href='/dashboard/items/new'" data-keywords="novo criar anuncio produto">
                    <i class="bi bi-plus-circle"></i>
                    <span>Novo Anúncio</span>
                </div>
                 <div class="cp-item" onclick="toggleAutoPilot()" data-keywords="autopilot automacao ia">
                    <i class="bi bi-robot"></i>
                    <span>Ativar/Desativar AutoPilot</span>
                </div>
                <div class="cp-item" onclick="themeManager.toggle()" data-keywords="tema escuro claro dark mode">
                    <i class="bi bi-moon-stars"></i>
                    <span>Alternar Tema</span>
                </div>
            </div>
            
            <div id="cp-empty" class="cp-empty-state" style="display: none;">
                <i class="bi bi-search"></i>
                <p>Nenhum resultado encontrado.</p>
            </div>
        </div>
        <div class="cp-footer">
            <div>
                <span class="kbd">↑</span> <span class="kbd">↓</span> para navegar
            </div>
            <div>
                <span class="kbd">↵</span> para selecionar
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>" src="/js/command-palette.js"></script>
