<?php
require 'vendor/autoload.php';

use App\Services\LogService;
use App\Services\ClaudeClient;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🤖 Verificação de Status da IA...\n";

// 1. Verificar Variável de Ambiente
if (empty($_ENV['ANTHROPIC_API_KEY'])) {
    echo "❌ ANTHROPIC_API_KEY não encontrada no .env\n";
    echo "   O sistema usará o modo SIMULAÇÃO.\n";
    exit(1);
}

echo "✅ API Key encontrada no .env\n";
echo "   Chave termina em: ..." . substr($_ENV['ANTHROPIC_API_KEY'], -4) . "\n";

// 2. Testar Conexão Básica
echo "\n📡 Testando conexão com a API da Anthropic...\n";

try {
    $client = new ClaudeClient($_ENV['ANTHROPIC_API_KEY']);
    
    $messages = [
        ['role' => 'user', 'content' => 'Responda apenas com a palavra "OK".']
    ];

    $options = [
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => 10
    ];

    $start = microtime(true);
    $response = $client->complete($messages, $options);
    $duration = round((microtime(true) - $start) * 1000, 2);

    if (isset($response['content'][0]['text'])) {
        echo "✅ Conexão bem-sucedida!Resposta da IA: " . trim($response['content'][0]['text']) . "\n";
        echo "⏱️ Tempo de resposta: {$duration}ms\n";
    } else {
        echo "❌ Falha: Resposta inesperada da API.\n";
        print_r($response);
    }

} catch (Exception $e) {
    echo "❌ Erro ao conectar: " . $e->getMessage() . "\n";
}
