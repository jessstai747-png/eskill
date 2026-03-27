<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Optimizers\DescriptionOptimizer;

class DescriptionOptimizerTest extends TestCase
{
    private DescriptionOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new DescriptionOptimizer();
    }

    public function testAnalyzeReturnProperStructure()
    {
        $description = $this->getSampleDescription();
        
        $result = $this->optimizer->analyze($description);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('char_count', $result);
        $this->assertArrayHasKey('word_count', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('strengths', $result);
        $this->assertArrayHasKey('structure', $result);
    }

    public function testAnalyzeDetectsTooShortDescription()
    {
        $shortDescription = 'Produto de boa qualidade.';
        
        $result = $this->optimizer->analyze($shortDescription);

        $this->assertLessThan(50, $result['score']);
        $this->assertContains('Descrição muito curta', $result['issues']);
    }

    public function testAnalyzeDetectsMissingStructure()
    {
        $plainDescription = str_repeat('Texto simples sem formatação. ', 50);
        
        $result = $this->optimizer->analyze($plainDescription);

        $this->assertFalse($result['structure']['has_bullets']);
        $this->assertFalse($result['structure']['has_sections']);
    }

    public function testAnalyzeRecognizesWellStructuredDescription()
    {
        $description = "
# Características Principais

- Bluetooth 5.3 para conexão estável
- Resistente à água IPX7 certificado
- Bateria de 40 horas de duração
- Cancelamento de ruído ativo

## Qualidade de Áudio

🎧 Som de alta qualidade Hi-Fi
📱 Compatível com iOS e Android
🔋 Carregamento rápido USB-C

Este é um fone de ouvido premium com tecnologia avançada de áudio, 
ideal para quem busca qualidade e conforto. Design ergonômico que 
se adapta perfeitamente aos seus ouvidos.
        ";

        $result = $this->optimizer->analyze($description);

        $this->assertGreaterThan(60, $result['score']);
        $this->assertTrue($result['structure']['has_bullets']);
        $this->assertTrue($result['structure']['has_emojis']);
    }

    public function testAnalyzeDetectsKeywordDensity()
    {
        $description = str_repeat('Fone bluetooth wireless tws sem fio. ', 30);
        
        $result = $this->optimizer->analyze($description, ['bluetooth', 'wireless', 'tws']);

        $this->assertGreaterThan(0, $result['keyword_density']);
    }

    public function testEnhanceAddsStructure()
    {
        $plainDescription = "Fone bluetooth com bateria de 40 horas e resistência à água.
- Alta qualidade de áudio
- Conexão estável
- Design moderno";
        
        $result = $this->optimizer->enhance($plainDescription, [
            'add_emojis' => true,
            'add_bullets' => true,
            'add_sections' => true
        ]);

        // Should convert - to • for bullet points
        $this->assertStringContainsString('•', $result['enhanced']);
    }

    public function testGetTemplateByCategory()
    {
        $categories = ['electronics', 'fashion', 'home', 'sports'];

        foreach ($categories as $category) {
            $template = $this->optimizer->getTemplateByCategory($category);
            
            $this->assertIsString($template);
            $this->assertNotEmpty($template);
        }
    }

    public function testValidateDescriptionLength()
    {
        $tooShort = 'Curta';
        $tooLong = str_repeat('A', 5000);
        $justRight = str_repeat('Texto adequado. ', 50);

        $this->assertFalse($this->optimizer->validateLength($tooShort));
        $this->assertFalse($this->optimizer->validateLength($tooLong));
        $this->assertTrue($this->optimizer->validateLength($justRight));
    }

    private function getSampleDescription(): string
    {
        return "
Fone de Ouvido Bluetooth TWS Sony

Características principais:
- Bluetooth 5.3 com conexão estável
- Resistência à água IPX7
- Bateria de 40 horas
- Cancelamento ativo de ruído
- Microfone integrado

🎧 Qualidade de áudio premium
📱 Compatível com todos os dispositivos
🔋 Carregamento rápido USB-C
💪 Resistente para esportes

Ideal para quem busca qualidade e durabilidade!
        ";
    }
}
