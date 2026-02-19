<!-- Componente de Configuração de Notificações com Áudio -->
<div class="card" id="notification-settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-bell-fill me-2"></i>
            Notificações em Tempo Real
        </h5>
        <span class="badge bg-success" id="notification-status">
            <i class="bi bi-check-circle me-1"></i> Ativo
        </span>
    </div>
    <div class="card-body">
        <!-- Status da Conexão -->
        <div class="alert alert-info d-flex align-items-center mb-4" id="notification-connection-status">
            <i class="bi bi-wifi me-2"></i>
            <span>Conectado e monitorando novos pedidos e perguntas</span>
        </div>

        <!-- Configurações Gerais -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="soundEnabled" checked>
                    <label class="form-check-label" for="soundEnabled">
                        <i class="bi bi-volume-up me-1"></i> Ativar Sons de Notificação
                    </label>
                </div>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="desktopEnabled" checked>
                    <label class="form-check-label" for="desktopEnabled">
                        <i class="bi bi-window me-1"></i> Ativar Notificações Desktop
                    </label>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Volume -->
                <label class="form-label">
                    <i class="bi bi-volume-up me-1"></i> Volume: <span id="volumeValue">80</span>%
                </label>
                <input type="range" class="form-range" id="soundVolume" min="0" max="100" value="80">
            </div>
        </div>

        <hr>

        <!-- Sons Personalizados -->
        <h6 class="mb-3">
            <i class="bi bi-music-note-list me-2"></i>
            Sons Personalizados
        </h6>
        
        <div class="row g-3 mb-4">
            <!-- Som de Pedido -->
            <div class="col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-cart-check text-primary me-2"></i>
                            Novo Pedido
                        </h6>
                        <select class="form-select form-select-sm mb-2" id="soundOrder">
                            <option value="order_notification">Padrão (Cha-Ching)</option>
                            <option value="cash_register">Caixa Registradora</option>
                            <option value="cha_ching">Cha-Ching</option>
                            <option value="bell">Sino</option>
                            <option value="success">Sucesso</option>
                        </select>
                        <button class="btn btn-outline-primary btn-sm w-100" onclick="testNotificationSound('order')">
                            <i class="bi bi-play-fill"></i> Testar
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Som de Pergunta -->
            <div class="col-md-4">
                <div class="card h-100 border-warning">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-question-circle text-warning me-2"></i>
                            Nova Pergunta
                        </h6>
                        <select class="form-select form-select-sm mb-2" id="soundQuestion">
                            <option value="question_notification">Padrão</option>
                            <option value="chime">Campainha</option>
                            <option value="pop">Pop</option>
                            <option value="notification">Genérico</option>
                        </select>
                        <button class="btn btn-outline-warning btn-sm w-100" onclick="testNotificationSound('question')">
                            <i class="bi bi-play-fill"></i> Testar
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Som de Mensagem -->
            <div class="col-md-4">
                <div class="card h-100 border-info">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-chat-dots text-info me-2"></i>
                            Nova Mensagem
                        </h6>
                        <select class="form-select form-select-sm mb-2" id="soundMessage">
                            <option value="message_notification">Padrão</option>
                            <option value="pop">Pop</option>
                            <option value="notification">Genérico</option>
                            <option value="chime">Campainha</option>
                        </select>
                        <button class="btn btn-outline-info btn-sm w-100" onclick="testNotificationSound('message')">
                            <i class="bi bi-play-fill"></i> Testar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <!-- Intervalo de Verificação -->
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">
                    <i class="bi bi-clock me-1"></i> Intervalo de Verificação
                </label>
                <select class="form-select" id="pollingInterval">
                    <option value="15">A cada 15 segundos</option>
                    <option value="30" selected>A cada 30 segundos (Recomendado)</option>
                    <option value="60">A cada 1 minuto</option>
                    <option value="120">A cada 2 minutos</option>
                </select>
                <small class="text-muted">Intervalos menores consomem mais recursos</small>
            </div>
            
            <div class="col-md-6">
                <!-- Horário Silencioso -->
                <label class="form-label">
                    <i class="bi bi-moon me-1"></i> Horário Silencioso (opcional)
                </label>
                <div class="row g-2">
                    <div class="col-6">
                        <input type="time" class="form-control form-control-sm" id="quietHoursStart" placeholder="Início">
                    </div>
                    <div class="col-6">
                        <input type="time" class="form-control form-control-sm" id="quietHoursEnd" placeholder="Fim">
                    </div>
                </div>
                <small class="text-muted">Sem sons durante este período</small>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4" id="notification-stats-section">
            <div class="col-12">
                <h6 class="mb-3">
                    <i class="bi bi-graph-up me-2"></i>
                    Estatísticas (últimos 7 dias)
                </h6>
                <div class="row g-2">
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-primary" id="stats-total">0</div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-danger" id="stats-unread">0</div>
                            <small class="text-muted">Não Lidas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-success" id="stats-orders">0</div>
                            <small class="text-muted">Pedidos</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-warning" id="stats-questions">0</div>
                            <small class="text-muted">Perguntas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="d-flex justify-content-between">
            <div>
                <button class="btn btn-outline-secondary btn-sm" onclick="loadNotificationSettings()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Recarregar
                </button>
                <button class="btn btn-outline-danger btn-sm ms-2" onclick="markAllNotificationsRead()">
                    <i class="bi bi-check-all me-1"></i> Marcar Todas como Lidas
                </button>
            </div>
            <button class="btn btn-primary" onclick="saveNotificationSettings()">
                <i class="bi bi-save me-1"></i> Salvar Configurações
            </button>
        </div>
    </div>
</div>

<!-- Toast Container para Notificações -->
<div id="notification-toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}
// Funções de configuração de notificações
async function loadNotificationSettings() {
    try {
        const data = await requestJson('/api/notifications/realtime/settings');
        
        if (data.success && data.settings) {
            const s = data.settings;
            
            document.getElementById('soundEnabled').checked = s.sound_enabled;
            document.getElementById('desktopEnabled').checked = s.desktop_enabled;
            document.getElementById('soundVolume').value = s.sound_volume || 80;
            document.getElementById('volumeValue').textContent = s.sound_volume || 80;
            document.getElementById('soundOrder').value = s.sound_order || 'order_notification';
            document.getElementById('soundQuestion').value = s.sound_question || 'question_notification';
            document.getElementById('soundMessage').value = s.sound_message || 'message_notification';
            document.getElementById('pollingInterval').value = s.polling_interval || 30;
            
            if (s.quiet_hours_start) {
                document.getElementById('quietHoursStart').value = s.quiet_hours_start;
            }
            if (s.quiet_hours_end) {
                document.getElementById('quietHoursEnd').value = s.quiet_hours_end;
            }
        }
        
        // Carregar estatísticas
        await loadNotificationStats();
        
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
    }
}

async function saveNotificationSettings() {
    const settings = {
        sound_enabled: document.getElementById('soundEnabled').checked,
        desktop_enabled: document.getElementById('desktopEnabled').checked,
        sound_volume: parseInt(document.getElementById('soundVolume').value),
        sound_order: document.getElementById('soundOrder').value,
        sound_question: document.getElementById('soundQuestion').value,
        sound_message: document.getElementById('soundMessage').value,
        polling_interval: parseInt(document.getElementById('pollingInterval').value),
        quiet_hours_start: document.getElementById('quietHoursStart').value || null,
        quiet_hours_end: document.getElementById('quietHoursEnd').value || null
    };
    
    try {
        const data = await requestJson('/api/notifications/realtime/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(settings)
        });
        
        if (data.success) {
            showAlert('Configurações salvas com sucesso!', 'success');
            
            // Atualizar instância global
            if (window.realTimeNotifications) {
                window.realTimeNotifications.updateConfig({
                    soundEnabled: settings.sound_enabled,
                    desktopEnabled: settings.desktop_enabled,
                    soundVolume: settings.sound_volume / 100,
                    pollingInterval: settings.polling_interval * 1000
                });
            }
        } else {
            showAlert('Erro ao salvar configurações', 'danger');
        }
    } catch (error) {
        console.error('Erro ao salvar:', error);
        showAlert('Erro ao salvar configurações', 'danger');
    }
}

async function loadNotificationStats() {
    try {
        const data = await requestJson('/api/notifications/realtime/stats');
        
        if (data.success && data.stats) {
            document.getElementById('stats-total').textContent = data.stats.total || 0;
            document.getElementById('stats-unread').textContent = data.stats.unread || 0;
            document.getElementById('stats-orders').textContent = data.stats.orders || 0;
            document.getElementById('stats-questions').textContent = data.stats.questions || 0;
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

function testNotificationSound(type) {
    if (window.realTimeNotifications) {
        // Obter som selecionado
        const soundMap = {
            'order': document.getElementById('soundOrder').value,
            'question': document.getElementById('soundQuestion').value,
            'message': document.getElementById('soundMessage').value
        };
        
        // Atualizar configuração temporariamente
        const config = window.realTimeNotifications.config;
        config.soundOrder = soundMap.order;
        config.soundQuestion = soundMap.question;
        config.soundMessage = soundMap.message;
        config.soundVolume = parseInt(document.getElementById('soundVolume').value) / 100;
        
        window.realTimeNotifications.testSound(type);
    } else {
        // Fallback se o sistema não estiver inicializado
        playFallbackSound(type);
    }
}

function playFallbackSound(type) {
    // Tentar usar Web Audio API
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        const frequencies = {
            order: [880, 1100, 1320],
            question: [660, 880],
            message: [523]
        };
        
        const freqs = frequencies[type] || [700];
        const volume = parseInt(document.getElementById('soundVolume').value) / 100;
        
        gainNode.gain.setValueAtTime(volume * 0.3, audioContext.currentTime);
        
        let time = audioContext.currentTime;
        for (const freq of freqs) {
            oscillator.frequency.setValueAtTime(freq, time);
            time += 0.15;
        }
        
        gainNode.gain.exponentialRampToValueAtTime(0.01, time + 0.2);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(time + 0.3);
    } catch (error) {
        console.warn('Não foi possível tocar o som:', error);
    }
}

async function markAllNotificationsRead() {
    try {
        const data = await requestJson('/api/notifications/realtime/read-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (data.success) {
            showAlert(`${data.marked} notificações marcadas como lidas`, 'success');
            loadNotificationStats();
            
            // Atualizar badge
            if (window.realTimeNotifications) {
                window.realTimeNotifications.updateBadge(0);
            }
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '10000';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => alertDiv.remove(), 3000);
}

// Event listeners
document.getElementById('soundVolume')?.addEventListener('input', function() {
    document.getElementById('volumeValue').textContent = this.value;
});

// Carregar configurações ao iniciar
document.addEventListener('DOMContentLoaded', loadNotificationSettings);
</script>

<style>
#notification-settings-card .card-title {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

#notification-settings-card .form-select-sm {
    font-size: 0.8rem;
}

#notification-toast-container .toast {
    min-width: 320px;
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
</style>
