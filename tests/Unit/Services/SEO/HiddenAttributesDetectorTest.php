<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\HiddenAttributesDetector;

class HiddenAttributesDetectorTest extends TestCase
{
    public function testGenerateMPNValueReturnsEmptyWhenNoCandidateData(): void
    {
        $detector = new HiddenAttributesDetector(null);
        $value = $detector->generateMPNValue(['title' => 'Produto Teste']);

        $this->assertSame('', $value);
    }

    public function testGenerateMPNValueUsesAttributeValue(): void
    {
        $detector = new HiddenAttributesDetector(null);
        $value = $detector->generateMPNValue([
            'attributes' => [
                ['id' => 'MPN', 'value_name' => 'ABC123'],
            ],
        ]);

        $this->assertSame('ABC123', $value);
    }

    public function testGenerateLineValuePrefersLineAttribute(): void
    {
        $detector = new HiddenAttributesDetector(null);
        $value = $detector->generateLineValue([
            'attributes' => [
                ['id' => 'LINE', 'value_name' => 'Premium X'],
                ['id' => 'BRAND', 'value_name' => 'Marca'],
                ['id' => 'MODEL', 'value_name' => 'Modelo'],
            ],
        ]);

        $this->assertSame('Premium X', $value);
    }

    public function testGenerateLineValueUsesBrandAndModel(): void
    {
        $detector = new HiddenAttributesDetector(null);
        $value = $detector->generateLineValue([
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Marca'],
                ['id' => 'MODEL', 'value_name' => 'Modelo'],
            ],
        ]);

        $this->assertSame('Marca Modelo', $value);
    }
}
