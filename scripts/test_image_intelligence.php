<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\SEO\ImageKiller;
use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Iniciando Teste de Inteligência de Imagem (OpenAI Vision) ---\n";

// Instanciar ImageKiller
// Passamos null no accountId pois vamos testar apenas o método privado via Reflection,
// não o fluxo completo que exige autenticação ML
$corrector = new ImageKiller(null);

// URL de teste (Imagem de um produto real do ML ou similar)
// Exemplo: Um tênis (fundo branco geralmente)
$testImageUrl = 'https://http2.mlstatic.com/D_NQ_NP_966605-MLB48858348636_012022-O.webp'; 

echo "Imagem de teste: $testImageUrl\n";

try {
    // Usar Reflection para acessar o método privado analyzeMainImageAI
    $reflection = new ReflectionClass(ImageKiller::class);
    $method = $reflection->getMethod('analyzeMainImageAI');
    $method->setAccessible(true);
    
    echo "Enviando para OpenAI Vision...\n";
    $startTime = microtime(true);
    
    // Invocar método
    $result = $method->invoke($corrector, $testImageUrl);
    
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "Concluído em {$duration}s\n\n";
    echo "=== Resultado da Análise ===\n";
    print_r($result);
    echo "============================\n";

    // Verificações básicas
    if (isset($result['error']) && $result['error']) {
        echo "[FALHA] Erro retornado pela API: " . ($result['message'] ?? 'Unknown') . "\n";
        exit(1);
    }
    
    if (isset($result['background_score'])) {
        echo "[SUCESSO] Score de fundo recebido: " . $result['background_score'] . "\n";
    }
    
    if (isset($result['suggestion'])) {
        echo "[SUCESSO] Sugestão recebida: " . $result['suggestion'] . "\n";
    }

} catch (Exception $e) {
    echo "[ERRO CRÍTICO] " . $e->getMessage() . "\n";
    exit(1);
}
