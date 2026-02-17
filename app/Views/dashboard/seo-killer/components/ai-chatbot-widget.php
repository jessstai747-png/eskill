<!-- AI Chatbot Widget - Floating Assistant -->
<div id="aiChatbotWidget" class="ai-chatbot-widget">
    <!-- Chat Button (Collapsed State) -->
    <button id="chatToggleBtn" class="chat-toggle-btn" onclick="toggleChatWidget()">
        <i class="bi bi-robot"></i>
        <span class="notification-badge" id="chatNotificationBadge" style="display: none;"></span>
    </button>

    <!-- Chat Window (Expanded State) -->
    <div id="chatWindow" class="chat-window" style="display: none;">
        <!-- Header -->
        <div class="chat-header">
            <div class="d-flex align-items-center">
                <div class="chat-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="ms-2">
                    <h6 class="mb-0">Assistente IA</h6>
                    <small class="text-white-50">Powered by GPT-4</small>
                </div>
            </div>
            <div class="chat-actions">
                <button class="btn btn-sm btn-link text-white" onclick="clearChatHistory()" title="Limpar conversa">
                    <i class="bi bi-trash"></i>
                </button>
                <button class="btn btn-sm btn-link text-white" onclick="toggleChatWidget()" title="Minimizar">
                    <i class="bi bi-dash-lg"></i>
                </button>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions-bar">
            <button class="quick-action-btn" onclick="sendQuickMessage('Como melhorar meu SEO?')">
                <i class="bi bi-search"></i> SEO
            </button>
            <button class="quick-action-btn" onclick="sendQuickMessage('Analisar meus preços')">
                <i class="bi bi-tag"></i> Preços
            </button>
            <button class="quick-action-btn" onclick="sendQuickMessage('Próximas ações')">
                <i class="bi bi-list-check"></i> Ações
            </button>
        </div>

        <!-- Messages Container -->
        <div id="chatMessages" class="chat-messages">
            <!-- Welcome Message -->
            <div class="message bot-message">
                <div class="message-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="message-content">
                    <p>👋 Olá! Sou seu assistente de IA. Posso ajudar com:</p>
                    <ul class="mb-0">
                        <li>Análise de métricas e KPIs</li>
                        <li>Recomendações de otimização</li>
                        <li>Ajuda com funcionalidades</li>
                        <li>Estratégias de crescimento</li>
                    </ul>
                    <p class="mt-2 mb-0">Como posso ajudar você hoje?</p>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div id="typingIndicator" class="typing-indicator" style="display: none;">
            <div class="message-avatar">
                <i class="bi bi-robot"></i>
            </div>
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <!-- Suggested Actions -->
        <div id="suggestedActions" class="suggested-actions" style="display: none;"></div>

        <!-- Input Area -->
        <div class="chat-input-container">
            <div class="input-group">
                <input 
                    type="text" 
                    id="chatInput" 
                    class="form-control chat-input" 
                    placeholder="Digite sua mensagem..."
                    onkeypress="handleChatKeyPress(event)"
                    autocomplete="off"
                >
                <button class="btn btn-primary" onclick="sendChatMessage()" id="sendChatBtn">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
            <div class="input-hint">
                <small class="text-muted">
                    <i class="bi bi-lightbulb"></i>
                    Dica: Pergunte sobre métricas, peça recomendações ou ajuda com features
                </small>
            </div>
        </div>
    </div>
</div>
