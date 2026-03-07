<?php
declare(strict_types=1);

namespace App\Services\SEO;

class ContextInjectorService
{
    private const CONTEXTS = [
        'profissional' => [
            'keywords' => ['delivery', 'motoboy', 'entrega', 'trabalho', 'profissional'],
            'phrases' => [
                'Perfeito para uso profissional com {produto}',
                'Resistente para rotina de entrega e trabalho diário'
            ]
        ],
        'lazer' => [
            'keywords' => ['viagem', 'passeio', 'turismo', 'lazer'],
            'phrases' => [
                'Ideal para viagens e passeios com {produto}',
                'Perfeito para turismo e aventuras do dia a dia'
            ]
        ],
        'urbano' => [
            'keywords' => ['cidade', 'dia a dia'],
            'phrases' => [
                'Ideal para uso urbano no dia a dia',
                'Prático para locomoção na cidade'
            ]
        ],
        'carga' => [
            'keywords' => ['capacete', 'transporte', 'bagagem', 'carga'],
            'phrases' => [
                'Comporta capacete e outros itens de transporte',
                'Espaço ideal para cargas leves e objetos pessoais'
            ]
        ],
        'seguranca' => [
            'keywords' => ['segurança', 'seguro', 'proteção', 'protecao'],
            'phrases' => [
                'Mais segurança e proteção durante o uso',
                'Estrutura reforçada para maior proteção'
            ]
        ],
        'conforto' => [
            'keywords' => ['conforto', 'ergonômico', 'ergonomico'],
            'phrases' => [
                'Design pensado para conforto e praticidade',
                'Uso mais confortável em trajetos longos'
            ]
        ],
        'esporte' => [
            'keywords' => ['esporte', 'trilha', 'off road', 'aventura'],
            'phrases' => [
                'Indicado para uso esportivo e trilhas',
                'Apoio ideal para aventuras off-road'
            ]
        ]
    ];

    public function __construct(?int $accountId = null)
    {
        // Initialization code if needed
    }

    /**
     * Injeta contextos no texto
     */
    public function inject(string $text, array $contexts, array $item = []): string
    {
        $resultText = trim($text);

        foreach ($contexts as $contextType) {
            if (!isset(self::CONTEXTS[$contextType])) {
                continue;
            }

            $phrases = $this->generateContextPhrases($contextType, $item);
            foreach ($phrases as $phrase) {
                if (!$this->containsRelatedConcept($resultText, $phrase)) {
                    $resultText = $this->appendSentence($resultText, $phrase);
                }
            }
        }

        return $resultText;
    }

    /**
     * Detecta contextos aplicáveis
     */
    public function detectApplicableContexts(array $item): array
    {
        $contexts = [];
        $title = $item['title'] ?? '';
        if (is_array($title)) {
            $title = $title['title'] ?? '';
        }
        $description = $item['description'] ?? '';
        if (is_array($description)) {
            $description = $description['plain_text'] ?? ($description['text'] ?? '');
        }
        $title = is_string($title) ? $title : (string) $title;
        $description = is_string($description) ? $description : (string) $description;
        $categoryId = $item['category_id'] ?? '';
        
        $combinedText = mb_strtolower($title . ' ' . $description);
        
        foreach (self::CONTEXTS as $contextType => $contextData) {
            foreach ($contextData['keywords'] as $keyword) {
                if (strpos($combinedText, mb_strtolower($keyword)) !== false) {
                    if (!in_array($contextType, $contexts)) {
                        $contexts[] = $contextType;
                    }
                }
            }
        }
        
        if (empty($contexts)) {
            $contexts = $this->getDefaultContextsForCategory($categoryId);
        }

        $contexts = array_values(array_unique($contexts));
        $contexts = array_values(array_intersect($contexts, array_keys(self::CONTEXTS)));

        return $contexts;
    }

    /**
     * Gera frases de contexto
     */
    public function generateContextPhrases(string $context, array $item): array
    {
        if (!isset(self::CONTEXTS[$context])) {
            return [];
        }
        
        $contextData = self::CONTEXTS[$context];
        $title = $item['title'] ?? 'produto';
        if (is_array($title)) {
            $title = $title['title'] ?? 'produto';
        }
        $title = is_string($title) ? $title : (string) $title;
        
        $generatedPhrases = [];
        
        foreach ($contextData['phrases'] as $phraseTemplate) {
            // Replace placeholder with actual product name
            $phrase = str_replace('{produto}', $title, $phraseTemplate);
            $generatedPhrases[] = $phrase;
        }
        
        return $generatedPhrases;
    }

    /**
     * Checks if text contains related concepts to avoid repetition
     */
    private function containsRelatedConcept(string $text, string $phrase): bool
    {
        $textLower = mb_strtolower($text);
        $phraseLower = mb_strtolower($phrase);
        
        // Simple check for overlapping words
        $phraseWords = explode(' ', $phraseLower);
        $overlapCount = 0;
        $totalPhraseWords = count($phraseWords);
        
        foreach ($phraseWords as $word) {
            if (mb_strlen($word) > 3 && strpos($textLower, $word) !== false) { // Only check words longer than 3 chars
                $overlapCount++;
            }
        }
        
        // If more than half the words overlap, consider it a related concept
        return ($overlapCount / $totalPhraseWords) > 0.5;
    }

    /**
     * Gets default contexts for a category
     */
    private function getDefaultContextsForCategory(string $categoryId): array
    {
        // Default contexts based on common categories
        // This would normally come from a configuration or database
        $defaultMappings = [
            'MLB3530' => ['profissional', 'carga'],
            'MLB1071' => ['seguranca'],
            'MLB1234' => ['lazer', 'urbano'],
        ];

        return $defaultMappings[$categoryId] ?? ['lazer', 'urbano'];
    }

    private function appendSentence(string $text, string $sentence): string
    {
        $text = rtrim($text);
        if ($text !== '' && !preg_match('/[.!?]$/', $text)) {
            $text .= '.';
        }
        if ($text !== '') {
            $text .= ' ';
        }

        return $text . rtrim($sentence, '.!?') . '.';
    }
}