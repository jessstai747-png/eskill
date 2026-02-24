/**
 * Real-Time Notification System with Audio
 * Sistema de notificações em tempo real com som
 * 
 * @version 1.0.0
 * @author Mercado Livre Manager
 */

// requestJson is defined globally in <head> via the layout

class RealTimeNotifications {
    constructor(options = {}) {
        // Configurações padrão
        this.config = {
            pollingInterval: options.pollingInterval || 30000, // 30 segundos
            soundEnabled: options.soundEnabled !== false,
            desktopEnabled: options.desktopEnabled !== false,
            soundVolume: options.soundVolume || 0.8,
            apiEndpoint: '/api/notifications/poll',
            soundsPath: '/sounds/',
            ...options
        };

        // Estado interno
        this.isPolling = false;
        this.pollingTimer = null;
        this.lastNotificationId = 0;
        this.audioContext = null;
        this.sounds = {};
        this.notificationQueue = [];
        this.isProcessingQueue = false;

        // Sons disponíveis
        this.soundFiles = {
            order_notification: 'order.mp3',
            question_notification: 'question.mp3',
            message_notification: 'message.mp3',
            cash_register: 'cash_register.mp3',
            cha_ching: 'cha_ching.mp3',
            bell: 'bell.mp3',
            chime: 'chime.mp3',
            pop: 'pop.mp3',
            alert: 'alert.mp3',
            success: 'success.mp3',
            notification: 'notification.mp3'
        };

        // Callbacks
        this.callbacks = {
            onNotification: options.onNotification || null,
            onOrderNotification: options.onOrderNotification || null,
            onQuestionNotification: options.onQuestionNotification || null,
            onMessageNotification: options.onMessageNotification || null,
            onError: options.onError || null,
            onCountUpdate: options.onCountUpdate || null
        };

        // Bind methods
        this.poll = this.poll.bind(this);
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);

        // Inicializar
        this.init();
    }

    /**
     * Inicializa o sistema
     */
    async init() {
        console.log('[Notifications] Initializing real-time notifications...');

        // Carregar configurações do servidor
        await this.loadSettings();

        // Preparar sons
        await this.prepareSounds();

        // Solicitar permissão de notificação desktop
        if (this.config.desktopEnabled) {
            await this.requestDesktopPermission();
        }

        // Ouvir mudanças de visibilidade
        document.addEventListener('visibilitychange', this.handleVisibilityChange);

        // Iniciar polling
        this.startPolling();

        console.log('[Notifications] System initialized');
    }

    /**
     * Carrega configurações do servidor
     */
    async loadSettings() {
        try {
            const data = await requestJson('/api/notifications/realtime/settings');

            if (data.success && data.settings) {
                this.config.soundEnabled = data.settings.sound_enabled;
                this.config.soundVolume = (data.settings.sound_volume || 80) / 100;
                this.config.pollingInterval = (data.settings.polling_interval || 30) * 1000;
                this.config.desktopEnabled = data.settings.desktop_enabled;
                this.config.soundOrder = data.settings.sound_order || 'order_notification';
                this.config.soundQuestion = data.settings.sound_question || 'question_notification';
                this.config.soundMessage = data.settings.sound_message || 'message_notification';
            }
        } catch (error) {
            console.warn('[Notifications] Could not load settings:', error);
        }
    }

    /**
     * Prepara os arquivos de áudio
     */
    async prepareSounds() {
        // Usar AudioContext para melhor controle
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('[Notifications] AudioContext not supported');
        }

        // Pré-carregar sons principais
        const mainSounds = [
            this.config.soundOrder || 'order_notification',
            this.config.soundQuestion || 'question_notification',
            this.config.soundMessage || 'message_notification'
        ];

        for (const sound of mainSounds) {
            await this.loadSound(sound);
        }
    }

    /**
     * Carrega um arquivo de som
     */
    async loadSound(soundName) {
        if (this.sounds[soundName]) return;

        const fileName = this.soundFiles[soundName] || `${soundName}.mp3`;
        const url = this.config.soundsPath + fileName;

        try {
            const audio = new Audio(url);
            audio.preload = 'auto';
            audio.volume = this.config.soundVolume;
            
            // Aguardar carregamento
            await new Promise((resolve, reject) => {
                audio.oncanplaythrough = resolve;
                audio.onerror = () => {
                    // Se falhar, tentar gerar som sintético
                    console.warn(`[Notifications] Could not load sound: ${fileName}`);
                    resolve();
                };
                setTimeout(resolve, 2000); // Timeout de 2s
            });

            this.sounds[soundName] = audio;
        } catch (error) {
            console.warn(`[Notifications] Error loading sound ${soundName}:`, error);
        }
    }

    /**
     * Toca um som de notificação
     */
    async playSound(type = 'notification') {
        if (!this.config.soundEnabled) return;

        // Mapear tipo para som
        let soundName;
        switch (type) {
            case 'order':
                soundName = this.config.soundOrder || 'order_notification';
                break;
            case 'question':
                soundName = this.config.soundQuestion || 'question_notification';
                break;
            case 'message':
                soundName = this.config.soundMessage || 'message_notification';
                break;
            default:
                soundName = 'notification';
        }

        // Carregar se não estiver carregado
        if (!this.sounds[soundName]) {
            await this.loadSound(soundName);
        }

        // Tocar som
        const audio = this.sounds[soundName];
        if (audio) {
            try {
                audio.currentTime = 0;
                audio.volume = this.config.soundVolume;
                await audio.play();
            } catch (error) {
                // Tentar som sintético como fallback
                this.playFallbackSound(type);
            }
        } else {
            // Fallback: som sintético
            this.playFallbackSound(type);
        }
    }

    /**
     * Som sintético de fallback usando Web Audio API
     */
    playFallbackSound(type = 'notification') {
        if (!this.audioContext) return;

        try {
            // Retomar contexto se necessário
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume();
            }

            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);

            // Frequências diferentes por tipo
            const frequencies = {
                order: [800, 1000, 1200],
                question: [600, 800],
                message: [500, 700],
                notification: [700]
            };

            const freqs = frequencies[type] || frequencies.notification;
            
            gainNode.gain.setValueAtTime(this.config.soundVolume * 0.3, this.audioContext.currentTime);
            
            let time = this.audioContext.currentTime;
            for (const freq of freqs) {
                oscillator.frequency.setValueAtTime(freq, time);
                time += 0.1;
            }

            gainNode.gain.exponentialRampToValueAtTime(0.01, time + 0.3);

            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(time + 0.4);
        } catch (error) {
            console.warn('[Notifications] Fallback sound failed:', error);
        }
    }

    /**
     * Solicita permissão para notificações desktop
     */
    async requestDesktopPermission() {
        if (!('Notification' in window)) {
            console.warn('[Notifications] Desktop notifications not supported');
            return false;
        }

        if (Notification.permission === 'granted') {
            return true;
        }

        if (Notification.permission !== 'denied') {
            const permission = await Notification.requestPermission();
            return permission === 'granted';
        }

        return false;
    }

    /**
     * Mostra notificação desktop
     */
    showDesktopNotification(notification) {
        if (!this.config.desktopEnabled || Notification.permission !== 'granted') {
            return;
        }

        const options = {
            body: notification.message,
            icon: '/icons/icon-192x192.png',
            badge: '/icons/badge-72x72.png',
            tag: `notification-${notification.id}`,
            requireInteraction: notification.priority === 'high' || notification.priority === 'urgent',
            data: notification.data
        };

        const desktopNotif = new Notification(notification.title, options);

        desktopNotif.onclick = () => {
            window.focus();
            this.handleNotificationClick(notification);
            desktopNotif.close();
        };

        // Auto-fechar após 10 segundos
        setTimeout(() => desktopNotif.close(), 10000);
    }

    /**
     * Manipula clique na notificação
     */
    handleNotificationClick(notification) {
        // Marcar como lida
        this.markAsRead(notification.id);

        // Redirecionar baseado no tipo
        const data = notification.data || {};
        switch (notification.type) {
            case 'order':
                if (data.order_id) {
                    window.location.href = `/dashboard/orders?highlight=${data.order_id}`;
                }
                break;
            case 'question':
                if (data.question_id) {
                    window.location.href = `/dashboard/questions?highlight=${data.question_id}`;
                }
                break;
            case 'message':
                window.location.href = '/dashboard/messages';
                break;
        }
    }

    /**
     * Inicia o polling
     */
    startPolling() {
        if (this.isPolling) return;

        this.isPolling = true;
        this.poll();
        
        this.pollingTimer = setInterval(this.poll, this.config.pollingInterval);
        
        console.log(`[Notifications] Polling started (interval: ${this.config.pollingInterval}ms)`);
    }

    /**
     * Para o polling
     */
    stopPolling() {
        this.isPolling = false;
        
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
        
        console.log('[Notifications] Polling stopped');
    }

    /**
     * Executa uma verificação de notificações
     */
    async poll() {
        if (document.hidden) return; // Não verificar se a aba não está visível

        try {
            const response = await fetch(this.config.apiEndpoint);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Unknown error');
            }

            // Atualizar contadores
            if (data.counts && this.callbacks.onCountUpdate) {
                this.callbacks.onCountUpdate(data.counts);
            }

            // Atualizar badge
            this.updateBadge(data.counts?.total || 0);

            // Processar novas notificações
            if (data.notifications && data.notifications.length > 0) {
                for (const notification of data.notifications) {
                    this.processNotification(notification, data.settings);
                }
            }

            // Atualizar intervalo de polling se necessário
            if (data.settings?.polling_interval) {
                const newInterval = data.settings.polling_interval * 1000;
                if (newInterval !== this.config.pollingInterval) {
                    this.config.pollingInterval = newInterval;
                    this.stopPolling();
                    this.startPolling();
                }
            }

        } catch (error) {
            console.error('[Notifications] Poll error:', error);
            if (this.callbacks.onError) {
                this.callbacks.onError(error);
            }
        }
    }

    /**
     * Processa uma notificação recebida
     */
    async processNotification(notification, settings = {}) {
        console.log('[Notifications] New notification:', notification);

        // Verificar se som está habilitado
        const soundEnabled = settings?.sound_enabled !== false && this.config.soundEnabled;

        // Tocar som baseado no tipo
        if (soundEnabled) {
            await this.playSound(notification.type);
        }

        // Mostrar notificação desktop
        this.showDesktopNotification(notification);

        // Mostrar toast no app
        this.showToast(notification);

        // Chamar callbacks específicos
        if (this.callbacks.onNotification) {
            this.callbacks.onNotification(notification);
        }

        switch (notification.type) {
            case 'order':
                if (this.callbacks.onOrderNotification) {
                    this.callbacks.onOrderNotification(notification);
                }
                break;
            case 'question':
                if (this.callbacks.onQuestionNotification) {
                    this.callbacks.onQuestionNotification(notification);
                }
                break;
            case 'message':
                if (this.callbacks.onMessageNotification) {
                    this.callbacks.onMessageNotification(notification);
                }
                break;
        }
    }

    /**
     * Mostra toast de notificação no app
     */
    showToast(notification) {
        // Verificar se existe container de toasts
        let container = document.getElementById('notification-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-toast-container';
            container.className = 'position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        // Ícones por tipo
        const icons = {
            order: '🛒',
            question: '❓',
            message: '💬',
            alert: '⚠️'
        };

        // Cores por prioridade
        const colors = {
            urgent: 'danger',
            high: 'warning',
            normal: 'primary',
            low: 'secondary'
        };

        const icon = icons[notification.type] || '🔔';
        const color = colors[notification.priority] || 'primary';

        // Criar toast
        const toastEl = document.createElement('div');
        toastEl.className = `toast show border-${color}`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="toast-header bg-${color} text-white">
                <span class="me-2">${icon}</span>
                <strong class="me-auto">${this.escapeHtml(notification.title)}</strong>
                <small>Agora</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${this.escapeHtml(notification.message)}
                <div class="mt-2 pt-2 border-top">
                    <button type="button" class="btn btn-${color} btn-sm notification-action" data-id="${notification.id}">
                        Ver detalhes
                    </button>
                </div>
            </div>
        `;

        container.appendChild(toastEl);

        // Configurar evento de clique
        toastEl.querySelector('.notification-action').addEventListener('click', () => {
            this.handleNotificationClick(notification);
            toastEl.remove();
        });

        // Configurar botão fechar
        toastEl.querySelector('.btn-close').addEventListener('click', () => {
            toastEl.remove();
        });

        // Auto-remover após 10 segundos
        setTimeout(() => {
            if (toastEl.parentNode) {
                toastEl.classList.remove('show');
                setTimeout(() => toastEl.remove(), 300);
            }
        }, 10000);
    }

    /**
     * Atualiza o badge de notificações
     */
    updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        const countEl = document.getElementById('notification-count');
        
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        if (countEl) {
            countEl.textContent = count;
        }

        // Atualizar título da página
        if (count > 0) {
            document.title = `(${count}) ${this.originalTitle || 'Mercado Livre Manager'}`;
        } else {
            document.title = this.originalTitle || 'Mercado Livre Manager';
        }
    }

    /**
     * Marca notificação como lida
     */
    async markAsRead(id) {
        try {
            await requestJson(`/api/notifications/realtime/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
        } catch (error) {
            console.error('[Notifications] Error marking as read:', error);
        }
    }

    /**
     * Marca todas como lidas
     */
    async markAllAsRead(type = null) {
        try {
            await requestJson('/api/notifications/realtime/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ type })
            });

            // Atualizar badge
            this.updateBadge(0);
        } catch (error) {
            console.error('[Notifications] Error marking all as read:', error);
        }
    }

    /**
     * Manipula mudança de visibilidade da página
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Pausar polling quando a aba não está visível
            // mas manter um intervalo maior
            if (this.pollingTimer) {
                clearInterval(this.pollingTimer);
                this.pollingTimer = setInterval(this.poll, this.config.pollingInterval * 2);
            }
        } else {
            // Voltar ao intervalo normal e verificar imediatamente
            if (this.pollingTimer) {
                clearInterval(this.pollingTimer);
            }
            this.poll();
            this.pollingTimer = setInterval(this.poll, this.config.pollingInterval);
        }
    }

    /**
     * Testa o som de notificação
     */
    async testSound(type = 'order') {
        // Habilitar temporariamente
        const wasEnabled = this.config.soundEnabled;
        this.config.soundEnabled = true;

        await this.playSound(type);

        this.config.soundEnabled = wasEnabled;
    }

    /**
     * Atualiza configurações
     */
    updateConfig(newConfig) {
        Object.assign(this.config, newConfig);

        // Se mudou o intervalo de polling
        if (newConfig.pollingInterval && this.isPolling) {
            this.stopPolling();
            this.startPolling();
        }

        // Atualizar volume dos sons carregados
        if (newConfig.soundVolume !== undefined) {
            for (const audio of Object.values(this.sounds)) {
                if (audio) {
                    audio.volume = this.config.soundVolume;
                }
            }
        }
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Salva título original
     */
    saveOriginalTitle() {
        if (!this.originalTitle) {
            this.originalTitle = document.title;
        }
    }

    /**
     * Destrói a instância
     */
    destroy() {
        this.stopPolling();
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        
        // Limpar sons
        for (const audio of Object.values(this.sounds)) {
            if (audio) {
                audio.pause();
                audio.src = '';
            }
        }
        this.sounds = {};

        // Fechar AudioContext
        if (this.audioContext) {
            this.audioContext.close();
        }
    }
}

// Instância global
let realTimeNotifications = null;

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    // Verificar se está numa página autenticada
    if (document.querySelector('#notification-badge') || window.enableRealTimeNotifications) {
        realTimeNotifications = new RealTimeNotifications({
            onOrderNotification: (notification) => {
                console.log('🛒 Novo pedido:', notification);
            },
            onQuestionNotification: (notification) => {
                console.log('❓ Nova pergunta:', notification);
            },
            onCountUpdate: (counts) => {
                console.log('📊 Counts updated:', counts);
            }
        });

        // Salvar título original
        realTimeNotifications.saveOriginalTitle();

        // Expor globalmente
        window.realTimeNotifications = realTimeNotifications;
    }
});

// CSS para os toasts (injetado)
(function() {
    const style = document.createElement('style');
    style.textContent = `
        #notification-toast-container .toast {
            min-width: 300px;
            margin-bottom: 10px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        #notification-toast-container .toast.hiding {
            animation: slideOutRight 0.3s ease;
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
})();

// Exportar para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealTimeNotifications;
}
