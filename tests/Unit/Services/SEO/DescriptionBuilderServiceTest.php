<?php

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\DescriptionBuilderService;
use App\Services\SEO\LongTailGeneratorService;

class DescriptionBuilderServiceTest extends TestCase
{
    private DescriptionBuilderService $service;

    protected function setUp(): void
    {
        $this->service = new DescriptionBuilderService();
    }

    public function testBuild(): void
    {
        $item = [
            'title' => 'Bauleto 41L',
            'category_id' => 'MLB3530',
            'description' => 'Ideal para delivery e uso urbano.',
            'attributes' => [
                ['name' => 'Material', 'value_name' => 'ABS']
            ]
        ];
        $distribution = [
            'title' => ['keywords' => ['bauleto', '41L', 'moto']],
            'attributes' => ['keywords' => ['abs', 'resistente']],
            'description' => ['keywords' => ['capacete', 'delivery', 'motoboy']],
            'hidden_keywords' => ['keywords' => ['compatível', 'honda']]
        ];
        $result = $this->service->build($item, $distribution);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('full_description', $result);
        $this->assertArrayHasKey('word_count', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('beneficios', $result['blocks']);
        $this->assertArrayHasKey('especificacoes', $result['blocks']);
        $this->assertArrayHasKey('compatibilidade', $result['blocks']);
        $this->assertArrayHasKey('faq', $result['blocks']);
        $this->assertGreaterThan(0, $result['word_count']);
    }

    public function testGenerateBlock(): void
    {
        $item = [
            'title' => 'Bauleto 41L',
            'category_id' => 'MLB3530'
        ];
        $distribution = [
            'description' => ['keywords' => ['bauleto', 'capacete', 'moto']]
        ];

        $result = $this->service->generateBlock('beneficios', $item, $distribution);

        $this->assertIsString($result);
        $this->assertStringContainsString('Benefícios', $result);
    }

    public function testGenerateFaqBlock(): void
    {
        $item = [
            'title' => 'Bauleto 41L',
            'category_id' => 'MLB3530'
        ];
        $distribution = [
            'description' => ['keywords' => ['delivery']]
        ];

        $result = $this->service->generateBlock('faq', $item, $distribution);

        $this->assertStringContainsString('Perguntas frequentes', $result);
        $this->assertStringContainsString('P:', $result);
        $this->assertStringContainsString('R:', $result);
    }

    public function testGenerateLongTailKeywords(): void
    {
        $generator = new LongTailGeneratorService();
        $result = $generator->generate('Bauleto 41L', 'MLB3530');

        $this->assertNotEmpty($result);
        $normalized = array_map('strtolower', $result);
        $this->assertContains('bauleto com capacidade de 41l', $normalized);
    }
}