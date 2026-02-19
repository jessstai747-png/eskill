/**
 * Gerador de sons usando Web Audio API
 * Fallback quando arquivos MP3 não estão disponíveis
 */

const NotificationSoundGenerator = {
    audioContext: null,
    
    sounds: {
    "order": {
        "frequencies": [
            880,
            1100,
            1320
        ],
        "duration": 0.15,
        "description": "Som de novo pedido - 3 notas ascendentes"
    },
    "question": {
        "frequencies": [
            660,
            880
        ],
        "duration": 0.2,
        "description": "Som de nova pergunta - 2 notas"
    },
    "message": {
        "frequencies": [
            523
        ],
        "duration": 0.3,
        "description": "Som de nova mensagem - 1 nota suave"
    },
    "cash_register": {
        "frequencies": [
            1046,
            1318,
            1568
        ],
        "duration": 0.1,
        "description": "Som de caixa registradora"
    },
    "cha_ching": {
        "frequencies": [
            880,
            1046,
            1318,
            1568
        ],
        "duration": 0.12,
        "description": "Som cha-ching (dinheiro)"
    },
    "bell": {
        "frequencies": [
            1047
        ],
        "duration": 0.5,
        "description": "Som de sino"
    },
    "chime": {
        "frequencies": [
            523,
            659,
            784
        ],
        "duration": 0.25,
        "description": "Som de campainha"
    },
    "pop": {
        "frequencies": [
            700
        ],
        "duration": 0.1,
        "description": "Som pop curto"
    },
    "alert": {
        "frequencies": [
            800,
            600,
            800,
            600
        ],
        "duration": 0.15,
        "description": "Som de alerta"
    },
    "success": {
        "frequencies": [
            523,
            659,
            784,
            1046
        ],
        "duration": 0.15,
        "description": "Som de sucesso"
    },
    "notification": {
        "frequencies": [
            700,
            880
        ],
        "duration": 0.2,
        "description": "Som gen\u00e9rico de notifica\u00e7\u00e3o"
    }
},
    
    init() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('Web Audio API not supported');
        }
    },
    
    async play(soundName, volume = 0.8) {
        if (!this.audioContext) {
            this.init();
        }
        
        if (!this.audioContext) return;
        
        // Retomar contexto se suspenso
        if (this.audioContext.state === 'suspended') {
            await this.audioContext.resume();
        }
        
        const soundConfig = this.sounds[soundName];
        if (!soundConfig) {
            console.warn('Sound not found:', soundName);
            return;
        }
        
        const { frequencies, duration } = soundConfig;
        
        let time = this.audioContext.currentTime;
        
        for (const freq of frequencies) {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.setValueAtTime(freq, time);
            oscillator.type = 'sine';
            
            // Envelope ADSR simples
            gainNode.gain.setValueAtTime(0, time);
            gainNode.gain.linearRampToValueAtTime(volume * 0.5, time + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.01, time + duration);
            
            oscillator.start(time);
            oscillator.stop(time + duration);
            
            time += duration;
        }
    },
    
    // Métodos de conveniência
    playOrder(volume) { return this.play('order', volume); },
    playQuestion(volume) { return this.play('question', volume); },
    playMessage(volume) { return this.play('message', volume); },
    playBell(volume) { return this.play('bell', volume); },
    playAlert(volume) { return this.play('alert', volume); },
    playSuccess(volume) { return this.play('success', volume); }
};

// Auto-inicializar
if (typeof window !== 'undefined') {
    window.NotificationSoundGenerator = NotificationSoundGenerator;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSoundGenerator;
}