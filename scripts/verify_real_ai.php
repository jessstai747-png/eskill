<?php
require 'vendor/autoload.php';

use App\Services\AIContentGeneratorService;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🧠 Testando Geração de Conteúdo com IA Real...\n";

if (empty($_ENV['ANTHROPIC_API_KEY'])) {
    echo "⚠️  AVISO: ANTHROPIC_API_KEY não encontrada. O teste rodará em modo SIMULAÇÃO.\n";
} else {
    echo "✅ Modo REAL ativado.\n";
}

$generator = new AIContentGeneratorService();

// Produto simulado
$productData = [
    'id' => 99999,
    'title' => 'Notebook Gamer HighPerformance',
    'brand' => 'TechMaster',
    'price' => 3500.00,
    'category_id' => 'MLB1648', // Informática
    'attributes' => [
        ['name' => 'Processador', 'value' => 'Intel Core i7'],
        ['name' => 'RAM', 'value' => '16GB'],
        ['name' => 'SSD', 'value' => '512GB']
    ]
];

echo "\n📝 Gerando descrição para: " . $productData['title'] . "\n";

$start = microtime(true);
$result = $generator->generateProductDescription($productData, ['force_regenerate' => true]);
$duration = round((microtime(true) - $start), 2);

echo "⏱️ Tempo: {$duration}s\n";
echo "📊 Score de Qualidade: " . ($result['quality_score'] ?? 'N/A') . "\n";
echo "🤖 Modelo usado: " . ($result['model_used'] ?? 'N/A') . "\n";

echo "\n--- Conteúdo Gerado ---\n";
echo substr($result['description'], 0, 300) . "...\n";
echo "-----------------------\n";

if (($result['metrics']['word_count'] ?? 0) > 10 && !str_contains($result['description'], 'Conteúdo Simulado')) {
    echo "\n✅ SUCESSO: Conteúdo gerado parece válido e real.\n";
} else {
    echo "\n⚠️  RESULTADO: Conteúdo parece simulado ou muito curto.\n";
}
