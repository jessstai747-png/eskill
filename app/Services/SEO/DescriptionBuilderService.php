<?php
declare(strict_types=1);

namespace App\Services\SEO;

class DescriptionBuilderService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function build(array $item, array $distribution): array
    {
        $blocks = [];
        
        $blocks['intro'] = $this->generateBlock('intro', $item, $distribution['description']['keywords'] ?? []);
        $blocks['features'] = $this->generateBlock('features', $item, $distribution['description']['keywords'] ?? []);
        $blocks['benefits'] = $this->generateBlock('benefits', $item, $distribution['description']['keywords'] ?? []);
        $blocks['faq'] = $this->generateBlock('faq', $item, $distribution['description']['keywords'] ?? []);
        
        $fullDescription = implode("\n\n", array_filter($blocks));
        
        return [
            'blocks' => $blocks,
            'full_description' => $fullDescription,
            'score' => $this->calculateDescriptionScore($fullDescription),
            'word_count' => str_word_count($fullDescription)
        ];
    }

    public function generateBlock(string $blockType, array $item, array $keywords): string
    {
        $title = $item['title'] ?? 'Produto';
        
        switch ($blockType) {
            case 'intro':
                return "Apresentamos o {$title}, ideal para quem busca qualidade e durabilidade.";
            
            case 'features':
                return "✓ Alta qualidade\n✓ Durabilidade garantida\n✓ Fácil instalação";
            
            case 'benefits':
                return "Benefícios:\n- Melhor custo-benefício\n- Garantia do fabricante\n- Entrega rápida";
            
            case 'faq':
                return "Perguntas Frequentes:\n\nP: Qual a garantia?\nR: 90 dias de garantia do fabricante.\n\nP: Como instalar?\nR: Instalação simples, acompanha manual.";
            
            default:
                return '';
        }
    }

    public function validateDescription(string $description): array
    {
        $wordCount = str_word_count($description);
        $charCount = strlen($description);
        
        return [
            'is_valid' => $wordCount >= 50 && $charCount <= 50000,
            'word_count' => $wordCount,
            'char_count' => $charCount,
            'min_words' => 50,
            'max_chars' => 50000
        ];
    }

    private function calculateDescriptionScore(string $description): int
    {
        $wordCount = str_word_count($description);
        
        if ($wordCount < 50) return 30;
        if ($wordCount < 100) return 60;
        if ($wordCount < 200) return 80;
        return 95;
    }
}
