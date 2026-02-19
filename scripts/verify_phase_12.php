<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\SentimentService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=============================================\n";
echo "   🧠 TESTE DE INTELIGÊNCIA COGNITIVA (PHASE 12) \n";
echo "=============================================\n";

$service = new SentimentService();

// Caso de Teste 1: Cliente Bravo (Envio)
$q1_id = rand(1000000, 9999999);
$q1_text = "Cadê meu produto?? Comprei faz 10 dias e nada! Vou cancelar essa porcaria!";
echo "\n[1] Analisando Pergunta Negativa (ID: $q1_id):\n    \"$q1_text\"\n";

$result1 = $service->analyzeQuestion($q1_id, $q1_text, "Produto: Câmera Digital");

echo "    > Sentimento: " . $result1['sentiment'] . "\n";
echo "    > Intenção:   " . $result1['intent'] . "\n";
echo "    > Urgência:   " . $result1['urgency'] . "\n";

if (in_array($result1['sentiment'], ['negative', 'angry']) && $result1['urgency'] > 50) {
    echo "✅ SUCESSO (Detectou Problema)\n";
} else {
    echo "⚠️  ALERTA (Análise fraca - Verifique Prompt ou Simulador)\n";
}

// Caso de Teste 2: Cliente Feliz (Técnico)
$q2_id = rand(1000000, 9999999);
$q2_text = "Bom dia! Esse modelo serve na Nikon D3200? O preço está ótimo!";
echo "\n[2] Analisando Pergunta Positiva (ID: $q2_id):\n    \"$q2_text\"\n";

$result2 = $service->analyzeQuestion($q2_id, $q2_text, "Produto: Lente 50mm");

echo "    > Sentimento: " . $result2['sentiment'] . "\n"; // Esperado: positive/neutral
echo "    > Intenção:   " . $result2['intent'] . "\n";    // Esperado: technical
echo "    > Urgência:   " . $result2['urgency'] . "\n";   // Esperado: < 50

if ($result2['urgency'] < 50) {
    echo "✅ SUCESSO (Detectou Baixa Urgência)\n";
} else {
    echo "⚠️  ALERTA (Classificou como urgente incorretamente)\n";
}

echo "\n=============================================\n";
