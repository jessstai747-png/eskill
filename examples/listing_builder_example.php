<?php
/**
 * Listing Builder Wizard - Exemplo Completo de Uso
 * 
 * Demonstra o fluxo completo de criação de um anúncio usando o wizard
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ListingBuilder\ListingBuilderService;
use App\Services\ListingBuilder\TemplateManagerService;

// Account ID (simulado para exemplo)
$accountId = 1;

// Instanciar serviços
$builder = new ListingBuilderService($accountId);
$templateManager = new TemplateManagerService();

echo "=== LISTING BUILDER WIZARD - EXEMPLO COMPLETO ===\n\n";

// ========================================
// STEP 1: INICIAR WIZARD
// ========================================
echo "STEP 1: Iniciando wizard...\n";
echo str_repeat('-', 60) . "\n";

$wizardStart = $builder->startListing([
    'category_id' => 'MLB1234',
    'product_name' => 'iPhone 15 Pro Max'
]);

echo "Categoria: {$wizardStart['category_info']['name']}\n";
echo "Step Inicial: {$wizardStart['current_step']}\n";
echo "Market Insights:\n";
echo "  - Preço Médio: R\$ " . number_format($wizardStart['market_insights']['avg_price'], 2, ',', '.') . "\n";
echo "  - Top Keywords: " . implode(', ', $wizardStart['market_insights']['top_keywords']) . "\n";
echo "  - Free Shipping: {$wizardStart['market_insights']['free_shipping_percent']}%\n";
echo "\n";

// ========================================
// STEP 2: BASIC INFO
// ========================================
echo "STEP 2: Validando informações básicas...\n";
echo str_repeat('-', 60) . "\n";

$basicInfo = $builder->validateStep([
    'category_id' => 'MLB1234',
    'product_name' => 'iPhone 15 Pro Max',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro Max',
    'condition' => 'new'
], 'basic_info');

echo "Score: {$basicInfo['score']}/100\n";
echo "Válido: " . ($basicInfo['valid'] ? 'SIM' : 'NÃO') . "\n";
if (!empty($basicInfo['suggestions'])) {
    echo "Sugestões:\n";
    foreach ($basicInfo['suggestions'] as $suggestion) {
        echo "  - $suggestion\n";
    }
}
echo "\n";

// ========================================
// STEP 3: TÍTULO
// ========================================
echo "STEP 3: Validando título otimizado...\n";
echo str_repeat('-', 60) . "\n";

$title = $builder->validateStep([
    'title' => 'iPhone 15 Pro Max 256GB Titanio Natural Apple'
], 'title');

echo "Título: iPhone 15 Pro Max 256GB Titanio Natural Apple\n";
echo "Score: {$title['score']}/100\n";
echo "Comprimento: " . strlen('iPhone 15 Pro Max 256GB Titanio Natural Apple') . " caracteres\n";
echo "Válido: " . ($title['valid'] ? 'SIM' : 'NÃO') . "\n";
if (!empty($title['suggestions'])) {
    echo "Sugestões:\n";
    foreach ($title['suggestions'] as $suggestion) {
        echo "  - $suggestion\n";
    }
}
echo "\n";

// ========================================
// STEP 4: DESCRIÇÃO COM TEMPLATE
// ========================================
echo "STEP 4: Gerando descrição com template...\n";
echo str_repeat('-', 60) . "\n";

// Listar templates disponíveis
$templates = $templateManager->getAllTemplates();
echo "Templates disponíveis: " . count($templates) . "\n";
foreach ($templates as $template) {
    echo "  - {$template['id']}: {$template['name']} ({$template['description']})\n";
}
echo "\n";

// Renderizar template Modern
$descriptionHtml = $templateManager->renderTemplate('modern', [
    'product_name' => 'iPhone 15 Pro Max',
    'description' => 'O mais avançado iPhone já criado, com chip A17 Pro revolucionário.',
    'features' => [
        'Chip A17 Pro - Performance sem precedentes',
        'Câmera Pro de 48MP com zoom óptico 5x',
        'Design em Titânio com USB-C',
        'Tela Super Retina XDR de 6.7"',
        'Botão de Ação personalizável',
        'Bateria com duração de até 29h de vídeo'
    ],
    'specs' => [
        'Marca' => 'Apple',
        'Modelo' => 'iPhone 15 Pro Max',
        'Memória Interna' => '256 GB',
        'Cor' => 'Titanio Natural',
        'Tela' => '6.7" Super Retina XDR',
        'Processador' => 'A17 Pro',
        'Câmera Principal' => '48MP',
        'Sistema Operacional' => 'iOS 17'
    ],
    'includes' => [
        '1x iPhone 15 Pro Max',
        '1x Cabo USB-C para Lightning',
        '1x Documentação',
        '1x Ferramenta de ejeção SIM'
    ],
    'warranty' => '12 meses de garantia oficial Apple',
    'shipping' => 'Frete grátis para todo o Brasil via Mercado Envios Full'
]);

echo "Template renderizado com sucesso!\n";
echo "Tamanho: " . strlen($descriptionHtml) . " caracteres\n\n";

// Validar descrição
$description = $builder->validateStep([
    'description' => $descriptionHtml
], 'description');

echo "Score da Descrição: {$description['score']}/100\n";
echo "Válida: " . ($description['valid'] ? 'SIM' : 'NÃO') . "\n";
echo "\n";

// ========================================
// STEP 5: ATRIBUTOS
// ========================================
echo "STEP 5: Validando atributos...\n";
echo str_repeat('-', 60) . "\n";

$attributes = $builder->validateStep([
    'attributes' => [
        ['id' => 'BRAND', 'value_name' => 'Apple'],
        ['id' => 'MODEL', 'value_name' => 'iPhone 15 Pro Max'],
        ['id' => 'GTIN', 'value_name' => '0195949038266'],
        ['id' => 'INTERNAL_MEMORY', 'value_name' => '256 GB'],
        ['id' => 'COLOR', 'value_name' => 'Titanio Natural']
    ]
], 'attributes');

echo "Score: {$attributes['score']}/100\n";
echo "Válido: " . ($attributes['valid'] ? 'SIM' : 'NÃO') . "\n";
echo "Atributos obrigatórios preenchidos\n";
echo "\n";

// ========================================
// STEP 6: IMAGENS
// ========================================
echo "STEP 6: Validando imagens...\n";
echo str_repeat('-', 60) . "\n";

$images = $builder->validateStep([
    'pictures' => [
        ['source' => 'https://exemplo.com/iphone-frente.jpg'],
        ['source' => 'https://exemplo.com/iphone-verso.jpg'],
        ['source' => 'https://exemplo.com/iphone-lateral.jpg'],
        ['source' => 'https://exemplo.com/iphone-camera.jpg'],
        ['source' => 'https://exemplo.com/iphone-tela.jpg'],
        ['source' => 'https://exemplo.com/iphone-embalagem.jpg']
    ]
], 'images');

echo "Score: {$images['score']}/100\n";
echo "Imagens: 6 (mínimo atendido)\n";
echo "Válido: " . ($images['valid'] ? 'SIM' : 'NÃO') . "\n";
echo "\n";

// ========================================
// STEP 7: PRICING
// ========================================
echo "STEP 7: Validando preço...\n";
echo str_repeat('-', 60) . "\n";

$pricing = $builder->validateStep([
    'price' => 7299.90,
    'available_quantity' => 10
], 'pricing');

echo "Preço: R\$ 7.299,90\n";
echo "Quantidade: 10 unidades\n";
echo "Score: {$pricing['score']}/100\n";
echo "Válido: " . ($pricing['valid'] ? 'SIM' : 'NÃO') . "\n";
echo "\n";

// ========================================
// STEP 8: SHIPPING
// ========================================
echo "STEP 8: Validando estratégia de frete...\n";
echo str_repeat('-', 60) . "\n";

$shipping = $builder->validateStep([
    'shipping' => [
        'dimensions' => '15x7x1',
        'weight' => 221,
        'zip_code' => '01310-100'
    ]
], 'shipping');

echo "Dimensões: 15x7x1 cm\n";
echo "Peso: 221g\n";
echo "Score: {$shipping['score']}/100\n";
echo "Válido: " . ($shipping['valid'] ? 'SIM' : 'NÃO') . "\n";
if (!empty($shipping['suggestions'])) {
    echo "Sugestões:\n";
    foreach ($shipping['suggestions'] as $suggestion) {
        echo "  - $suggestion\n";
    }
}
echo "\n";

// ========================================
// STEP 9: BUILD LISTING
// ========================================
echo "STEP 9: Construindo anúncio completo...\n";
echo str_repeat('-', 60) . "\n";

$listing = $builder->buildListing([
    'title' => 'iPhone 15 Pro Max 256GB Titanio Natural Apple',
    'category_id' => 'MLB1234',
    'price' => 7299.90,
    'condition' => 'new',
    'available_quantity' => 10,
    'description' => $descriptionHtml,
    'pictures' => [
        ['source' => 'https://exemplo.com/iphone-frente.jpg'],
        ['source' => 'https://exemplo.com/iphone-verso.jpg'],
        ['source' => 'https://exemplo.com/iphone-lateral.jpg'],
        ['source' => 'https://exemplo.com/iphone-camera.jpg'],
        ['source' => 'https://exemplo.com/iphone-tela.jpg'],
        ['source' => 'https://exemplo.com/iphone-embalagem.jpg']
    ],
    'attributes' => [
        ['id' => 'BRAND', 'value_name' => 'Apple'],
        ['id' => 'MODEL', 'value_name' => 'iPhone 15 Pro Max'],
        ['id' => 'GTIN', 'value_name' => '0195949038266'],
        ['id' => 'INTERNAL_MEMORY', 'value_name' => '256 GB'],
        ['id' => 'COLOR', 'value_name' => 'Titanio Natural']
    ],
    'shipping' => [
        'dimensions' => '15x7x1',
        'weight' => 221,
        'zip_code' => '01310-100'
    ]
]);

echo "Anúncio construído com sucesso!\n";
echo "Quality Score Previsto: {$listing['quality_prediction']['score']}/100\n";
echo "Pronto para Publicar: " . ($listing['ready_to_publish'] ? 'SIM' : 'NÃO') . "\n";
echo "\nOtimizações Aplicadas:\n";
foreach ($listing['optimizations_applied'] as $opt) {
    echo "  ✓ $opt\n";
}
echo "\n";

// ========================================
// EXEMPLO: CLONE COM MELHORIAS
// ========================================
echo "=== EXEMPLO: CLONE COM MELHORIAS ===\n";
echo str_repeat('-', 60) . "\n";

$cloned = $builder->cloneListing('MLB1234567890', [
    'optimize_title',
    'optimize_shipping',
    'apply_template',
    'enhance_seo'
]);

if ($cloned['success']) {
    echo "Anúncio clonado com sucesso!\n";
    echo "Item Original: {$cloned['original_item']['id']}\n";
    echo "Quality Score Original: {$cloned['original_item']['quality_score']}/100\n";
    echo "Quality Score Novo: {$cloned['quality_prediction']}/100\n";
    echo "Melhoria: {$cloned['improvement']}\n";
    echo "\nMelhorias Aplicadas:\n";
    foreach ($cloned['improvements_applied'] as $improvement) {
        echo "  ✓ $improvement\n";
    }
}
echo "\n";

// ========================================
// EXEMPLO: SALVAR RASCUNHO
// ========================================
echo "=== EXEMPLO: SALVAR RASCUNHO ===\n";
echo str_repeat('-', 60) . "\n";

$draft = $builder->saveDraft([
    'title' => 'iPhone 15 Pro Max 256GB Titanio Natural Apple',
    'category_id' => 'MLB1234',
    'price' => 7299.90,
    'last_step' => 'shipping'
], 'iPhone 15 Pro Rascunho 1');

echo "Rascunho salvo!\n";
echo "Draft ID: {$draft['draft_id']}\n";
echo "Salvo em: {$draft['saved_at']}\n";
echo "\n";

// ========================================
// RESUMO FINAL
// ========================================
echo "=== RESUMO DO WIZARD ===\n";
echo str_repeat('=', 60) . "\n";
echo "✓ STEP 1: Basic Info      - Score: {$basicInfo['score']}/100\n";
echo "✓ STEP 2: Título          - Score: {$title['score']}/100\n";
echo "✓ STEP 3: Descrição       - Score: {$description['score']}/100\n";
echo "✓ STEP 4: Atributos       - Score: {$attributes['score']}/100\n";
echo "✓ STEP 5: Imagens         - Score: {$images['score']}/100\n";
echo "✓ STEP 6: Pricing         - Score: {$pricing['score']}/100\n";
echo "✓ STEP 7: Shipping        - Score: {$shipping['score']}/100\n";
echo str_repeat('=', 60) . "\n";
echo "QUALITY SCORE FINAL: {$listing['quality_prediction']['score']}/100\n";
echo str_repeat('=', 60) . "\n";

echo "\n✅ Exemplo concluído com sucesso!\n";
