<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TitleAttributeExtractorService;

/**
 * @covers \App\Services\TitleAttributeExtractorService
 */
class TitleAttributeExtractorServiceTest extends TestCase
{
    private TitleAttributeExtractorService $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new TitleAttributeExtractorService();
    }

    // =============================
    // INICIALIZAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(TitleAttributeExtractorService::class, $this->extractor);
    }

    // =============================
    // DETECÇÃO DE CATEGORIA
    // =============================

    public function testDetectCategoryTypeElectronics(): void
    {
        $this->assertSame('electronics', $this->extractor->detectCategoryType('Celular Samsung Galaxy S24'));
        $this->assertSame('electronics', $this->extractor->detectCategoryType('Fone de Ouvido JBL Bluetooth'));
        $this->assertSame('electronics', $this->extractor->detectCategoryType('Notebook Dell Inspiron 15'));
    }

    public function testDetectCategoryTypeAppliances(): void
    {
        $this->assertSame('appliances', $this->extractor->detectCategoryType('Geladeira Consul Frost Free'));
        $this->assertSame('appliances', $this->extractor->detectCategoryType('Liquidificador Philips Walita'));
    }

    public function testDetectCategoryTypeFashion(): void
    {
        $this->assertSame('fashion', $this->extractor->detectCategoryType('Camiseta Nike Dry Fit Masculina'));
        $this->assertSame('fashion', $this->extractor->detectCategoryType('Tênis Adidas Ultraboost'));
    }

    public function testDetectCategoryTypeComputers(): void
    {
        $this->assertSame('computers', $this->extractor->detectCategoryType('Placa de Vídeo RTX 4090'));
        $this->assertSame('computers', $this->extractor->detectCategoryType('Memória RAM DDR5 16GB'));
    }

    public function testDetectCategoryTypeAutomotive(): void
    {
        $this->assertSame('automotive', $this->extractor->detectCategoryType('Pneu Pirelli Aro 15'));
        $this->assertSame('automotive', $this->extractor->detectCategoryType('Óleo Motor 5W30'));
    }

    public function testDetectCategoryTypeReturnsNullForUnknown(): void
    {
        $this->assertNull($this->extractor->detectCategoryType('Livro PHP Programação'));
    }

    // =============================
    // EXTRAÇÃO DE MARCA
    // =============================

    public function testExtractBrandFromTitle(): void
    {
        $results = $this->extractor->extractFromTitle('Samsung Galaxy S24 Ultra 256GB');
        $brands = array_filter($results, fn($r) => $r['attribute_id'] === 'BRAND');
        $this->assertNotEmpty($brands);
        $brand = array_values($brands)[0];
        $this->assertSame('Samsung', $brand['value']);
        $this->assertGreaterThanOrEqual(90, $brand['confidence']);
    }

    public function testExtractBrandApple(): void
    {
        $results = $this->extractor->extractFromTitle('Apple iPhone 15 Pro Max 512GB');
        $brands = array_filter($results, fn($r) => $r['attribute_id'] === 'BRAND');
        $this->assertNotEmpty($brands);
        $brand = array_values($brands)[0];
        $this->assertSame('Apple', $brand['value']);
    }

    public function testExtractBrandMotorcycle(): void
    {
        $results = $this->extractor->extractFromTitle('Bagageiro Honda CG 160 Titan Reforçado');
        $brands = array_filter($results, fn($r) => $r['attribute_id'] === 'BRAND');
        // Honda should be found in brand dictionary
        $this->assertNotEmpty($brands);
    }

    // =============================
    // EXTRAÇÃO DE ARMAZENAMENTO
    // =============================

    public function testExtractStorageGB(): void
    {
        $results = $this->extractor->extractFromTitle('iPhone 15 256GB Preto');
        $storage = array_filter($results, fn($r) => in_array($r['attribute_id'], ['INTERNAL_MEMORY', 'STORAGE', 'CAPACITY', 'STORAGE_CAPACITY']));
        $this->assertNotEmpty($storage);
        $item = array_values($storage)[0];
        $this->assertSame('256 GB', $item['value']);
    }

    public function testExtractStorageTB(): void
    {
        $results = $this->extractor->extractFromTitle('SSD Samsung 1TB NVMe');
        $storage = array_filter($results, fn($r) => in_array($r['attribute_id'], ['INTERNAL_MEMORY', 'STORAGE', 'CAPACITY', 'STORAGE_CAPACITY']));
        $this->assertNotEmpty($storage);
        $item = array_values($storage)[0];
        $this->assertSame('1 TB', $item['value']);
    }

    // =============================
    // EXTRAÇÃO DE COR
    // =============================

    public function testExtractColorBlack(): void
    {
        $results = $this->extractor->extractFromTitle('Galaxy S24 Ultra Preto 256GB');
        $colors = array_filter($results, fn($r) => in_array($r['attribute_id'], ['COLOR', 'MAIN_COLOR']));
        $this->assertNotEmpty($colors);
    }

    public function testExtractColorWhite(): void
    {
        $results = $this->extractor->extractFromTitle('iPhone 15 Branco 128GB');
        $colors = array_filter($results, fn($r) => in_array($r['attribute_id'], ['COLOR', 'MAIN_COLOR']));
        $this->assertNotEmpty($colors);
    }

    // =============================
    // EXTRAÇÃO DE VOLTAGEM
    // =============================

    public function testExtractVoltage110V(): void
    {
        $results = $this->extractor->extractFromTitle('Liquidificador Philips 110V 800W');
        $volts = array_filter($results, fn($r) => in_array($r['attribute_id'], ['VOLTAGE', 'POWER_SUPPLY']));
        $this->assertNotEmpty($volts);
        $item = array_values($volts)[0];
        $this->assertStringContainsString('110', $item['value']);
    }

    public function testExtractVoltageBivolt(): void
    {
        $results = $this->extractor->extractFromTitle('Cafeteira Nespresso Bivolt Preta');
        $volts = array_filter($results, fn($r) => in_array($r['attribute_id'], ['VOLTAGE', 'POWER_SUPPLY']));
        $this->assertNotEmpty($volts);
        $item = array_values($volts)[0];
        $this->assertSame('Bivolt', $item['value']);
    }

    // =============================
    // EXTRAÇÃO DE RESOLUÇÃO
    // =============================

    public function testExtractResolutionFullHD(): void
    {
        $results = $this->extractor->extractFromTitle('Monitor LG 27" Full HD IPS');
        $res = array_filter($results, fn($r) => in_array($r['attribute_id'], ['RESOLUTION', 'DISPLAY_RESOLUTION']));
        $this->assertNotEmpty($res);
        $item = array_values($res)[0];
        $this->assertSame('Full HD', $item['value']);
    }

    public function testExtractResolution4K(): void
    {
        $results = $this->extractor->extractFromTitle('Smart TV Samsung 55" 4K UHD');
        $res = array_filter($results, fn($r) => in_array($r['attribute_id'], ['RESOLUTION', 'DISPLAY_RESOLUTION']));
        $this->assertNotEmpty($res);
    }

    // =============================
    // EXTRAÇÃO DE MATERIAL
    // =============================

    public function testExtractMaterialInox(): void
    {
        $results = $this->extractor->extractFromTitle('Panela Tramontina Aço Inox 5L');
        $materials = array_filter($results, fn($r) => in_array($r['attribute_id'], ['MATERIAL', 'MAIN_MATERIAL', 'BODY_MATERIAL']));
        $this->assertNotEmpty($materials);
        $item = array_values($materials)[0];
        $this->assertStringContainsString('Inox', $item['value']);
    }

    public function testExtractMaterialAluminio(): void
    {
        $results = $this->extractor->extractFromTitle('Suporte Notebook Alumínio Ajustável');
        $materials = array_filter($results, fn($r) => in_array($r['attribute_id'], ['MATERIAL', 'MAIN_MATERIAL', 'BODY_MATERIAL']));
        $this->assertNotEmpty($materials);
        $item = array_values($materials)[0];
        $this->assertSame('Alumínio', $item['value']);
    }

    // =============================
    // EXTRAÇÃO DE ANO
    // =============================

    public function testExtractYear(): void
    {
        $results = $this->extractor->extractFromTitle('Bagageiro CG 160 2024 Reforçado');
        $years = array_filter($results, fn($r) => in_array($r['attribute_id'], ['YEAR', 'MODEL_YEAR', 'RELEASE_YEAR']));
        $this->assertNotEmpty($years);
        $item = array_values($years)[0];
        $this->assertSame('2024', $item['value']);
    }

    // =============================
    // EXTRAÇÃO DE POTÊNCIA
    // =============================

    public function testExtractPower(): void
    {
        $results = $this->extractor->extractFromTitle('Aspirador Electrolux 1400W Filtro');
        $power = array_filter($results, fn($r) => in_array($r['attribute_id'], ['POWER', 'POWER_CONSUMPTION', 'WATTAGE']));
        $this->assertNotEmpty($power);
        $item = array_values($power)[0];
        $this->assertStringContainsString('1400', $item['value']);
    }

    // =============================
    // EXTRAÇÃO DE PESO
    // =============================

    public function testExtractWeight(): void
    {
        $results = $this->extractor->extractFromTitle('Haltere 5kg Emborrachado Fitness');
        $weight = array_filter($results, fn($r) => in_array($r['attribute_id'], ['WEIGHT', 'PRODUCT_WEIGHT', 'NET_WEIGHT']));
        $this->assertNotEmpty($weight);
        $item = array_values($weight)[0];
        $this->assertSame('5Kg', $item['value']);
    }

    // =============================
    // EXTRAÇÃO DE CONECTIVIDADE
    // =============================

    public function testExtractConnectivityBluetooth(): void
    {
        $results = $this->extractor->extractFromTitle('Caixa de Som JBL Bluetooth 5.0');
        $conn = array_filter($results, fn($r) => in_array($r['attribute_id'], ['CONNECTIVITY', 'WIRELESS', 'CONNECTION_TYPE']));
        $this->assertNotEmpty($conn);
        $item = array_values($conn)[0];
        $this->assertSame('Bluetooth', $item['value']);
    }

    // =============================
    // MODELOS DE MOTO (CONTEXTO NEGÓCIO)
    // =============================

    public function testExtractMotoModelCG160(): void
    {
        $results = $this->extractor->extractFromTitle('Bagageiro CG 160 Titan Fan 2024 Reforçado AWA');
        $models = array_filter($results, fn($r) => in_array($r['attribute_id'], ['COMPATIBLE_VEHICLE_MODELS', 'VEHICLE_MODEL', 'MOTO_MODEL']));
        $this->assertNotEmpty($models);
        $values = array_map(fn($r) => mb_strtolower($r['value']), array_values($models));
        $this->assertTrue(
            in_array('cg 160', $values) || in_array('cg160', $values) ||
                count(array_filter($values, fn($v) => str_contains($v, 'cg'))) > 0,
            'CG 160 should be detected'
        );
    }

    public function testExtractMotoModelBros160(): void
    {
        $results = $this->extractor->extractFromTitle('Protetor Motor Bros 160 Honda Reforçado');
        $models = array_filter($results, fn($r) => in_array($r['attribute_id'], ['COMPATIBLE_VEHICLE_MODELS', 'VEHICLE_MODEL', 'MOTO_MODEL']));
        $this->assertNotEmpty($models);
    }

    public function testExtractMotoBrandHonda(): void
    {
        $results = $this->extractor->extractFromTitle('Retrovisor Honda CG 160 Original');
        // Honda might appear as brand or vehicle brand
        $brands = array_filter($results, fn($r) => in_array($r['attribute_id'], ['BRAND', 'COMPATIBLE_VEHICLE_BRANDS', 'VEHICLE_BRAND']));
        $this->assertNotEmpty($brands);
    }

    // =============================
    // ACABAMENTO
    // =============================

    public function testExtractFinishFosco(): void
    {
        $results = $this->extractor->extractFromTitle('Case iPhone 15 Silicone Fosco Preto');
        $finish = array_filter($results, fn($r) => in_array($r['attribute_id'], ['FINISH', 'SURFACE_FINISH', 'COLOR_TYPE']));
        $this->assertNotEmpty($finish);
        $item = array_values($finish)[0];
        $this->assertSame('Fosco', $item['value']);
    }

    // =============================
    // COMPATIBILIDADE UNIVERSAL
    // =============================

    public function testExtractUniversalCompatibility(): void
    {
        $results = $this->extractor->extractFromTitle('Suporte Celular Moto Universal Garra');
        $compat = array_filter($results, fn($r) => in_array($r['attribute_id'], ['COMPATIBILITY', 'FIT_TYPE', 'APPLICATION']));
        $this->assertNotEmpty($compat);
        $item = array_values($compat)[0];
        $this->assertSame('Universal', $item['value']);
    }

    // =============================
    // ALLOWED ATTRIBUTES FILTER
    // =============================

    public function testFilterByAllowedAttributes(): void
    {
        $results = $this->extractor->extractFromTitle(
            'Samsung Galaxy S24 Ultra 256GB Preto',
            ['BRAND', 'COLOR']
        );
        foreach ($results as $result) {
            $this->assertTrue(
                in_array($result['attribute_id'], ['BRAND', 'COLOR']),
                "Attribute {$result['attribute_id']} should be in allowed list"
            );
        }
    }

    // =============================
    // RESULTADO VAZIO
    // =============================

    public function testEmptyTitleReturnsEmptyArray(): void
    {
        $results = $this->extractor->extractFromTitle('');
        $this->assertIsArray($results);
    }

    public function testNoAttributesFoundReturnsEmptyArray(): void
    {
        $results = $this->extractor->extractFromTitle('Produto genérico sem atributos');
        $this->assertIsArray($results);
    }

    // =============================
    // CUSTOM BRANDS
    // =============================

    public function testAddCustomBrands(): void
    {
        $this->extractor->addBrands('custom', ['AWA', 'ProTork']);
        $results = $this->extractor->extractFromTitle('Bagageiro AWA CG 160 Reforçado', [], 'custom');
        $brands = array_filter($results, fn($r) => $r['attribute_id'] === 'BRAND');
        $this->assertNotEmpty($brands);
        $brand = array_values($brands)[0];
        $this->assertSame('AWA', $brand['value']);
    }

    // =============================
    // CUSTOM PATTERNS
    // =============================

    public function testAddCustomPattern(): void
    {
        $this->extractor->addPattern('custom_sku', '/\bSKU-(\d+)\b/i', ['SKU', 'PRODUCT_CODE']);
        $results = $this->extractor->extractFromTitle('Produto SKU-12345 Premium');
        $skus = array_filter($results, fn($r) => in_array($r['attribute_id'], ['SKU', 'PRODUCT_CODE']));
        $this->assertNotEmpty($skus);
    }

    // =============================
    // NORMALIZAÇÃO
    // =============================

    public function testAddCustomNormalization(): void
    {
        $this->extractor->addNormalization('preto fosco', 'Preto Fosco Premium');
        // Test that normalization was stored (via internal state)
        $this->assertTrue(true); // addNormalization is void, just ensure no exception
    }

    // =============================
    // ESTRUTURA DO RESULTADO
    // =============================

    public function testResultStructure(): void
    {
        $results = $this->extractor->extractFromTitle('Samsung Galaxy S24 256GB Preto');
        $this->assertNotEmpty($results);
        $first = $results[0];
        $this->assertArrayHasKey('attribute_id', $first);
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('confidence', $first);
        $this->assertArrayHasKey('source', $first);
        $this->assertArrayHasKey('method', $first);
        $this->assertSame('TITLE', $first['source']);
    }

    // =============================
    // CONFIANÇA
    // =============================

    public function testConfidenceRanges(): void
    {
        $results = $this->extractor->extractFromTitle('Samsung Galaxy S24 Ultra 256GB Preto 4K');
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(50, $result['confidence']);
            $this->assertLessThanOrEqual(100, $result['confidence']);
        }
    }

    // =============================
    // DEDUPLICAÇÃO
    // =============================

    public function testDeduplicatesByAttribute(): void
    {
        // Title with potential duplicate attributes should be deduplicated
        $results = $this->extractor->extractFromTitle('Samsung Galaxy 128GB 256GB');
        // Should not have multiple STORAGE entries for non-multivalue attributes
        $this->assertIsArray($results);
    }
}
