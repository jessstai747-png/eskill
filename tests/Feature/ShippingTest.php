<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\ShippingService;

/**
 * Testes de logistica e envio — Fase 5.
 *
 * ShippingService aceita skipDbAutoConnect=true para instanciar sem DB.
 * validateDimensions() eh puramente local (sem DB/ML); testavel em qualquer ambiente.
 *
 * @covers \App\Services\ShippingService
 */
class ShippingTest extends TestCase
{
    private ShippingService $shipping;
    private bool $dbAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->shipping    = new ShippingService(null, null, null, false);
            $this->dbAvailable = true;
        } catch (\Throwable) {
            // Fallback: inicializar sem DB
            $this->shipping = new ShippingService(null, null, null, true);
        }
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testShippingServiceClassExists(): void
    {
        $this->assertTrue(class_exists(ShippingService::class));
    }

    public function testShippingServiceCanBeInstantiated(): void
    {
        $service = new ShippingService(null, null, null, true);
        $this->assertInstanceOf(ShippingService::class, $service);
    }

    /** @dataProvider shippingMethodsProvider */
    public function testShippingServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(ShippingService::class, $method),
            "ShippingService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function shippingMethodsProvider(): array
    {
        return [
            'getShippingPreferences'      => ['getShippingPreferences'],
            'updateShippingPreferences'   => ['updateShippingPreferences'],
            'configureFreeShipping'       => ['configureFreeShipping'],
            'simulateShippingCost'        => ['simulateShippingCost'],
            'getCategoryDimensions'       => ['getCategoryDimensions'],
            'validateDimensions'          => ['validateDimensions'],
            'getShippingLabels'           => ['getShippingLabels'],
            'setHandlingTime'             => ['setHandlingTime'],
            'analyzeShippingPerformance'  => ['analyzeShippingPerformance'],
            'generatePickList'            => ['generatePickList'],
        ];
    }

    // ------------------------------------------------------------------
    // validateDimensions — metodo puro, sem DB/ML
    // ------------------------------------------------------------------

    public function testValidateDimensionsWithValidPackage(): void
    {
        $service = new ShippingService(null, null, null, true);

        $result = $service->validateDimensions([
            'width'  => 30,
            'height' => 20,
            'length' => 40,
            'weight' => 1500,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertTrue($result['valid'], 'Pacote valido deve passar em validateDimensions');
        $this->assertEmpty($result['errors']);
    }

    public function testValidateDimensionsRejectsOverweightPackage(): void
    {
        $service = new ShippingService(null, null, null, true);

        $result = $service->validateDimensions([
            'width'  => 30,
            'height' => 20,
            'length' => 40,
            'weight' => 35000, // 35kg — acima do limite de 30kg
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertFalse($result['weight_ok']);
    }

    public function testValidateDimensionsRejectsOversizeDimensions(): void
    {
        $service = new ShippingService(null, null, null, true);

        $result = $service->validateDimensions([
            'width'  => 110, // excede 105cm
            'height' => 20,
            'length' => 20,
            'weight' => 500,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateDimensionsRejectsZeroWeight(): void
    {
        $service = new ShippingService(null, null, null, true);

        $result = $service->validateDimensions([
            'width'  => 20,
            'height' => 15,
            'length' => 10,
            'weight' => 0,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertFalse($result['weight_ok']);
    }

    public function testValidateDimensionsRejectsTooSmallPackage(): void
    {
        $service = new ShippingService(null, null, null, true);

        $result = $service->validateDimensions([
            'width'  => 5,
            'height' => 5,
            'length' => 5, // soma = 15cm, abaixo do minimo 26cm
            'weight' => 200,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertFalse($result['dimensions_ok']);
    }

    public function testValidateDimensionsStructureHasRequiredKeys(): void
    {
        $service = new ShippingService(null, null, null, true);
        $result  = $service->validateDimensions(['width' => 30, 'height' => 20, 'length' => 40, 'weight' => 1000]);

        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('dimensions_ok', $result);
        $this->assertArrayHasKey('weight_ok', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsArray($result['errors']);
    }

    // ------------------------------------------------------------------
    // Testes funcionais (requerem DB/ML)
    // ------------------------------------------------------------------

    public function testGetShippingPreferencesViaApi(): void
    {
        $this->markTestSkipped('Requer conta ML ativa e DB conectado');
    }
}
