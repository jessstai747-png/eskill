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
        $blocks = [
            'beneficios' => $this->generateBlock('beneficios', $item, $distribution),
            'especificacoes' => $this->generateBlock('especificacoes', $item, $distribution),
            'compatibilidade' => $this->generateBlock('compatibilidade', $item, $distribution),
            'faq' => $this->generateBlock('faq', $item, $distribution),
        ];

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
        $normalizedType = $this->normalizeBlockType($blockType);
        $keywordTerms = $this->extractKeywordTerms($keywords);
        $topKeywords = array_slice($keywordTerms, 0, 4);
        $keywordSentence = empty($topKeywords)
            ? 'qualidade, durabilidade e desempenho no uso diário'
            : implode(', ', $topKeywords);
        $attributes = $this->formatAttributes($item['attributes'] ?? []);

        switch ($normalizedType) {
            case 'beneficios':
                return "Benefícios do {$title}\n\n" .
                    "Projetado para uso intenso em cidade e estrada, este produto entrega praticidade real para rotinas de trabalho e deslocamento diário. " .
                    "Sua proposta combina segurança, organização e resistência em um único conjunto, ajudando a manter itens protegidos mesmo sob chuva, poeira e vibração constante. " .
                    "Com foco em {$keywordSentence}, o resultado é mais confiança durante o trajeto e menos tempo perdido com ajustes improvisados. " .
                    "Na prática, isso significa melhor aproveitamento da moto, conforto operacional para quem roda todos os dias e uma experiência consistente em entregas, viagens curtas e uso profissional contínuo.";

            case 'especificacoes':
                return "Especificações técnicas\n\n" .
                    "O {$title} foi estruturado para oferecer encaixe estável, manutenção simples e desempenho previsível em diferentes cenários de uso. " .
                    "A composição prioriza robustez mecânica, acabamento funcional e durabilidade para operação recorrente em ambientes urbanos. " .
                    "Características relevantes: {$attributes}. " .
                    "O conjunto técnico foi pensado para reduzir desgaste prematuro, preservar a organização da carga e facilitar inspeções rápidas. " .
                    "Esse equilíbrio entre construção e funcionalidade torna o produto adequado para quem precisa de padrão estável ao longo do tempo, sem comprometer eficiência no dia a dia.";

            case 'compatibilidade':
                return "Compatibilidade e aplicação\n\n" .
                    "Esta solução atende motociclistas que precisam de versatilidade e padronização no uso cotidiano. " .
                    "A aplicação foi planejada para contextos de delivery, deslocamento urbano e trabalho profissional, com instalação intuitiva e comportamento consistente. " .
                    "Ao considerar {$keywordSentence}, o produto se adapta a diferentes perfis operacionais sem perder foco em segurança e praticidade. " .
                    "Antes da compra, recomenda-se conferir medidas e pontos de fixação da sua motocicleta para garantir ajuste ideal. " .
                    "Com a verificação correta, a integração no veículo tende a ser rápida e previsível, mantendo o desempenho esperado em jornadas prolongadas.";

            case 'faq':
                return "Perguntas frequentes\n\n" .
                    "P: O produto suporta uso diário intenso?\n" .
                    "R: Sim. A construção foi pensada para rotina contínua, com foco em resistência e estabilidade no transporte.\n\n" .
                    "P: É indicado para trabalho com entregas?\n" .
                    "R: Sim. O projeto prioriza organização, praticidade e confiabilidade para operações frequentes em área urbana.\n\n" .
                    "P: Como validar a compatibilidade antes da instalação?\n" .
                    "R: Verifique medidas, pontos de fixação e padrão da base da motocicleta para assegurar encaixe correto.\n\n" .
                    "P: Quais cuidados aumentam a vida útil?\n" .
                    "R: Limpeza periódica, inspeção dos fixadores e uso dentro dos limites recomendados mantêm o melhor desempenho.";

            default:
                return '';
        }
    }

    public function validateDescription(string $description): array
    {
        $wordCount = str_word_count($description);
        $charCount = mb_strlen($description);
        
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

    private function normalizeBlockType(string $blockType): string
    {
        return match (mb_strtolower($blockType)) {
            'intro', 'features', 'benefits', 'benefits_block', 'beneficios' => 'beneficios',
            'especificacoes', 'specs', 'specifications' => 'especificacoes',
            'compatibilidade', 'compatibility' => 'compatibilidade',
            'faq', 'perguntas', 'perguntas_frequentes' => 'faq',
            default => mb_strtolower($blockType),
        };
    }

    private function extractKeywordTerms(array $keywords): array
    {
        if (isset($keywords['keywords']) && is_array($keywords['keywords'])) {
            return array_values(array_filter($keywords['keywords'], 'is_string'));
        }

        if (isset($keywords['description']['keywords']) && is_array($keywords['description']['keywords'])) {
            $descriptionTerms = array_values(array_filter($keywords['description']['keywords'], 'is_string'));
            $titleTerms = isset($keywords['title']['keywords']) && is_array($keywords['title']['keywords'])
                ? array_values(array_filter($keywords['title']['keywords'], 'is_string'))
                : [];
            $modelTerms = isset($keywords['model']['keywords']) && is_array($keywords['model']['keywords'])
                ? array_values(array_filter($keywords['model']['keywords'], 'is_string'))
                : [];

            return array_values(array_unique(array_merge($descriptionTerms, $titleTerms, $modelTerms)));
        }

        $terms = [];
        foreach ($keywords as $term) {
            if (is_string($term)) {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

    private function formatAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return 'material resistente, fixação segura e acabamento para uso prolongado';
        }

        $parts = [];
        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }
            $name = isset($attribute['name']) ? trim((string)$attribute['name']) : '';
            $value = isset($attribute['value_name']) ? trim((string)$attribute['value_name']) : '';
            if ($name === '' || $value === '') {
                continue;
            }
            $parts[] = mb_strtolower($name) . ': ' . mb_strtolower($value);
        }

        if ($parts === []) {
            return 'material resistente, fixação segura e acabamento para uso prolongado';
        }

        return implode(', ', $parts);
    }
}
