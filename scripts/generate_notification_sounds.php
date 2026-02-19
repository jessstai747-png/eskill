<?php
/**
 * Gerador de arquivos de áudio para notificações
 * Execute este script para gerar os arquivos de som MP3
 * 
 * php scripts/generate_notification_sounds.php
 */

// Verificar se FFmpeg está disponível
$ffmpegAvailable = shell_exec('which ffmpeg') !== null;

$soundsDir = __DIR__ . '/../public/sounds';

// Criar diretório se não existir
if (!is_dir($soundsDir)) {
    mkdir($soundsDir, 0755, true);
}

// Definir sons com frequências e durações
$sounds = [
    'order' => [
        'frequencies' => [880, 1100, 1320],
        'duration' => 0.15,
        'description' => 'Som de novo pedido - 3 notas ascendentes'
    ],
    'question' => [
        'frequencies' => [660, 880],
        'duration' => 0.2,
        'description' => 'Som de nova pergunta - 2 notas'
    ],
    'message' => [
        'frequencies' => [523],
        'duration' => 0.3,
        'description' => 'Som de nova mensagem - 1 nota suave'
    ],
    'cash_register' => [
        'frequencies' => [1046, 1318, 1568],
        'duration' => 0.1,
        'description' => 'Som de caixa registradora'
    ],
    'cha_ching' => [
        'frequencies' => [880, 1046, 1318, 1568],
        'duration' => 0.12,
        'description' => 'Som cha-ching (dinheiro)'
    ],
    'bell' => [
        'frequencies' => [1047],
        'duration' => 0.5,
        'description' => 'Som de sino'
    ],
    'chime' => [
        'frequencies' => [523, 659, 784],
        'duration' => 0.25,
        'description' => 'Som de campainha'
    ],
    'pop' => [
        'frequencies' => [700],
        'duration' => 0.1,
        'description' => 'Som pop curto'
    ],
    'alert' => [
        'frequencies' => [800, 600, 800, 600],
        'duration' => 0.15,
        'description' => 'Som de alerta'
    ],
    'success' => [
        'frequencies' => [523, 659, 784, 1046],
        'duration' => 0.15,
        'description' => 'Som de sucesso'
    ],
    'notification' => [
        'frequencies' => [700, 880],
        'duration' => 0.2,
        'description' => 'Som genérico de notificação'
    ]
];

echo "🔊 Gerador de Sons de Notificação\n";
echo str_repeat("=", 50) . "\n\n";

if (!$ffmpegAvailable) {
    echo "⚠️  FFmpeg não encontrado. Gerando arquivos HTML de fallback.\n";
    echo "   Instale FFmpeg para gerar arquivos MP3 reais.\n\n";
    
    // Criar arquivo JavaScript com sons em Base64 (Web Audio API)
    $jsContent = generateWebAudioJs($sounds);
    file_put_contents($soundsDir . '/sound-generator.js', $jsContent);
    echo "✅ Criado: sound-generator.js (fallback Web Audio)\n";
    
} else {
    echo "✅ FFmpeg encontrado. Gerando arquivos MP3...\n\n";
    
    foreach ($sounds as $name => $config) {
        $outputFile = $soundsDir . '/' . $name . '.mp3';
        $success = generateMp3Sound($name, $config, $outputFile);
        
        if ($success) {
            echo "  ✅ {$name}.mp3 - {$config['description']}\n";
        } else {
            echo "  ❌ {$name}.mp3 - Erro ao gerar\n";
        }
    }
}

// Criar README
$readme = <<<README
# Sons de Notificação

Este diretório contém os arquivos de áudio para notificações em tempo real.

## Arquivos

| Arquivo | Descrição |
|---------|-----------|
README;

foreach ($sounds as $name => $config) {
    $readme .= "| {$name}.mp3 | {$config['description']} |\n";
}

$readme .= <<<README

## Personalizando Sons

Você pode substituir qualquer arquivo MP3 por um som personalizado.
Recomendações:
- Duração: 0.5 a 2 segundos
- Formato: MP3
- Taxa de bits: 128kbps ou superior
- Volume: Normalizado

## Configurações

As configurações de som podem ser alteradas em:
- Dashboard > Configurações > Notificações
- Ou via API: POST /api/notifications/realtime/settings

README;

file_put_contents($soundsDir . '/README.md', $readme);

echo "\n✅ Arquivos de som gerados com sucesso!\n";
echo "📁 Diretório: {$soundsDir}\n";

/**
 * Gera arquivo MP3 usando FFmpeg
 */
function generateMp3Sound(string $name, array $config, string $outputFile): bool
{
    $frequencies = $config['frequencies'];
    $duration = $config['duration'];
    $totalDuration = $duration * count($frequencies);
    
    // Construir comando FFmpeg para gerar tom
    $filters = [];
    $inputs = [];
    
    foreach ($frequencies as $i => $freq) {
        $startTime = $i * $duration;
        // Criar cada tom como input separado
        $inputs[] = "-f lavfi -t {$duration} -i \"sine=frequency={$freq}:duration={$duration}\"";
    }
    
    // Concatenar e adicionar fade
    $inputCount = count($frequencies);
    $concatFilter = "";
    for ($i = 0; $i < $inputCount; $i++) {
        $concatFilter .= "[{$i}:a]";
    }
    $concatFilter .= "concat=n={$inputCount}:v=0:a=1[concat];";
    $concatFilter .= "[concat]afade=t=in:d=0.01,afade=t=out:d=0.1:st=" . ($totalDuration - 0.1) . "[out]";
    
    $inputStr = implode(' ', $inputs);
    $cmd = "ffmpeg -y {$inputStr} -filter_complex \"{$concatFilter}\" -map \"[out]\" -codec:a libmp3lame -b:a 128k \"{$outputFile}\" 2>/dev/null";
    
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0 && file_exists($outputFile);
}

/**
 * Gera JavaScript com Web Audio API como fallback
 */
function generateWebAudioJs(array $sounds): string
{
    $soundsJson = json_encode($sounds, JSON_PRETTY_PRINT);
    
    return <<<JS
/**
 * Gerador de sons usando Web Audio API
 * Fallback quando arquivos MP3 não estão disponíveis
 */

const NotificationSoundGenerator = {
    audioContext: null,
    
    sounds: {$soundsJson},
    
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
JS;
}
