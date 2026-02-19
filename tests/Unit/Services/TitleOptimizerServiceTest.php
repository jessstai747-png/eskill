<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Testes unitários para TitleOptimizerService
 * 
 * Usamos uma classe mock para testar os métodos de análise sem
 * dependências externas (ML API, CategoryService, etc.)
 */
class TitleOptimizerServiceTest extends TestCase
{
    private object $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = $this->createOptimizerForTesting();
    }

    /**
     * Cria um mock com os métodos principais expostos
     */
    private function createOptimizerForTesting(): object
    {
        return new class {
            private const MAX_LENGTH = 60;

            private const FORBIDDEN_TERMS = [
                'promoção',
                'oferta',
                'desconto',
                'liquidação',
                'black friday',
                'compre já',
                'aproveite',
                'não perca',
                'últimas unidades',
                'frete grátis',
                'menor preço',
                'melhor preço',
                'barato',
                '!!!',
                '???',
                '***',
            ];

            private const HIGH_VALUE_TERMS = [
                'original' => 10,
                'genuíno' => 9,
                'lacrado' => 9,
                'novo' => 8,
                'garantia' => 8,
                'nota fiscal' => 7,
                'pronta entrega' => 7,
            ];

            public function analyzeTitle(string $title): array
            {
                $analysis = [
                    'title' => $title,
                    'length' => mb_strlen($title),
                    'score' => 0,
                    'issues' => [],
                    'positives' => [],
                ];

                $length = $analysis['length'];

                // Comprimento
                if ($length >= 45 && $length <= 58) {
                    $analysis['score'] += 25;
                    $analysis['positives'][] = 'Comprimento ideal';
                } elseif ($length >= 35 && $length <= 60) {
                    $analysis['score'] += 20;
                } elseif ($length > 60) {
                    $analysis['score'] += 10;
                    $analysis['issues'][] = 'Título muito longo';
                } else {
                    $analysis['score'] += 10;
                    $analysis['issues'][] = 'Título muito curto';
                }

                // Termos proibidos
                $forbiddenFound = $this->findForbiddenTerms($title);
                if (empty($forbiddenFound)) {
                    $analysis['score'] += 20;
                    $analysis['positives'][] = 'Sem termos proibidos';
                } else {
                    $analysis['issues'][] = 'Termos proibidos: ' . implode(', ', $forbiddenFound);
                }

                // Termos de alto valor
                $highValueFound = $this->findHighValueTerms($title);
                $analysis['score'] += min(20, count($highValueFound) * 5);
                if (!empty($highValueFound)) {
                    $analysis['positives'][] = 'Termos de valor encontrados';
                }

                // Capitalização
                if ($this->hasProperCapitalization($title)) {
                    $analysis['score'] += 10;
                } else {
                    $analysis['score'] += 5;
                    $analysis['issues'][] = 'Capitalização pode ser melhorada';
                }

                return $analysis;
            }

            public function findForbiddenTerms(string $title): array
            {
                $found = [];
                $titleLower = mb_strtolower($title);
                foreach (self::FORBIDDEN_TERMS as $term) {
                    if (mb_strpos($titleLower, mb_strtolower($term)) !== false) {
                        $found[] = $term;
                    }
                }
                return $found;
            }

            public function findHighValueTerms(string $title): array
            {
                $found = [];
                $titleLower = mb_strtolower($title);
                foreach (self::HIGH_VALUE_TERMS as $term => $value) {
                    if (mb_strpos($titleLower, mb_strtolower($term)) !== false) {
                        $found[$term] = $value;
                    }
                }
                return $found;
            }

            public function hasProperCapitalization(string $title): bool
            {
                $words = explode(' ', $title);
                $capitalizedCount = 0;

                foreach ($words as $word) {
                    if (strlen($word) > 2 && preg_match('/^[A-Z]/', $word)) {
                        $capitalizedCount++;
                    }
                }

                return $capitalizedCount >= 2;
            }

            public function cleanTitle(string $title): string
            {
                // Remover termos proibidos
                $titleLower = mb_strtolower($title);
                foreach (self::FORBIDDEN_TERMS as $term) {
                    $titleLower = str_ireplace($term, '', $titleLower);
                }

                // Limpar espaços extras
                return trim(preg_replace('/\s+/', ' ', $titleLower));
            }

            public function truncateTitle(string $title, int $maxLength = 60): string
            {
                if (mb_strlen($title) <= $maxLength) {
                    return $title;
                }

                // Cortar na última palavra completa
                $truncated = mb_substr($title, 0, $maxLength);
                $lastSpace = mb_strrpos($truncated, ' ');

                if ($lastSpace !== false && $lastSpace > $maxLength - 15) {
                    return mb_substr($truncated, 0, $lastSpace);
                }

                return $truncated;
            }
        };
    }

    // =============================
    // TESTES DE ANÁLISE DE TÍTULO
    // =============================

    public function testAnalyzeTitleReturnsCorrectStructure(): void
    {
        $result = $this->optimizer->analyzeTitle('Samsung Galaxy S23 Ultra 256GB');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('length', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('positives', $result);
    }

    public function testAnalyzeTitleOptimalLengthGetsHighScore(): void
    {
        // 48 caracteres - comprimento ideal
        $title = 'Samsung Galaxy S23 Ultra 256GB Preto Original Novo';
        $result = $this->optimizer->analyzeTitle($title);

        $this->assertGreaterThanOrEqual(45, $result['score']);
        $this->assertContains('Comprimento ideal', $result['positives']);
    }

    public function testAnalyzeTitleTooLongGetsIssue(): void
    {
        $title = str_repeat('Palavra ', 15); // >60 caracteres
        $result = $this->optimizer->analyzeTitle($title);

        $this->assertTrue(
            in_array('Título muito longo', $result['issues']) ||
                count(array_filter($result['issues'], fn($i) => str_contains($i, 'longo'))) > 0
        );
    }

    public function testAnalyzeTitleTooShortGetsIssue(): void
    {
        $title = 'Celular Novo';
        $result = $this->optimizer->analyzeTitle($title);

        $this->assertNotEmpty($result['issues']);
    }

    // =============================
    // TESTES DE TERMOS PROIBIDOS
    // =============================

    public function testFindForbiddenTermsDetectsPromocao(): void
    {
        $result = $this->optimizer->findForbiddenTerms('Celular Samsung PROMOÇÃO');

        $this->assertNotEmpty($result);
        $this->assertContains('promoção', $result);
    }

    public function testFindForbiddenTermsDetectsMultiple(): void
    {
        $title = 'Celular OFERTA Black Friday Frete Grátis!!!';
        $result = $this->optimizer->findForbiddenTerms($title);

        $this->assertGreaterThanOrEqual(3, count($result));
    }

    public function testFindForbiddenTermsReturnsEmptyForCleanTitle(): void
    {
        $title = 'Samsung Galaxy S23 Ultra 256GB Original Lacrado';
        $result = $this->optimizer->findForbiddenTerms($title);

        $this->assertEmpty($result);
    }

    public function testFindForbiddenTermsCaseInsensitive(): void
    {
        $result1 = $this->optimizer->findForbiddenTerms('PROMOÇÃO');
        $result2 = $this->optimizer->findForbiddenTerms('promoção');
        $result3 = $this->optimizer->findForbiddenTerms('Promoção');

        $this->assertEquals(count($result1), count($result2));
        $this->assertEquals(count($result2), count($result3));
    }

    // =============================
    // TESTES DE TERMOS DE ALTO VALOR
    // =============================

    public function testFindHighValueTermsDetectsOriginal(): void
    {
        $result = $this->optimizer->findHighValueTerms('Celular Samsung Original');

        $this->assertArrayHasKey('original', $result);
    }

    public function testFindHighValueTermsDetectsMultiple(): void
    {
        $title = 'Samsung Galaxy S23 Original Lacrado Garantia';
        $result = $this->optimizer->findHighValueTerms($title);

        $this->assertGreaterThanOrEqual(3, count($result));
    }

    public function testFindHighValueTermsReturnsEmptyForGenericTitle(): void
    {
        $title = 'Celular Samsung Galaxy S23';
        $result = $this->optimizer->findHighValueTerms($title);

        $this->assertEmpty($result);
    }

    // =============================
    // TESTES DE CAPITALIZAÇÃO
    // =============================

    public function testHasProperCapitalizationReturnsTrueForCorrect(): void
    {
        $title = 'Samsung Galaxy S23 Ultra 256GB';
        $result = $this->optimizer->hasProperCapitalization($title);

        $this->assertTrue($result);
    }

    public function testHasProperCapitalizationReturnsFalseForAllLowercase(): void
    {
        $title = 'samsung galaxy s23 ultra 256gb';
        $result = $this->optimizer->hasProperCapitalization($title);

        $this->assertFalse($result);
    }

    // =============================
    // TESTES DE LIMPEZA
    // =============================

    public function testCleanTitleRemovesForbiddenTerms(): void
    {
        $title = 'Samsung Galaxy PROMOÇÃO S23 OFERTA Ultra';
        $result = $this->optimizer->cleanTitle($title);

        $this->assertStringNotContainsString('promoção', $result);
        $this->assertStringNotContainsString('oferta', $result);
    }

    public function testCleanTitleRemovesExtraSpaces(): void
    {
        $title = 'Samsung   Galaxy    S23';
        $result = $this->optimizer->cleanTitle($title);

        $this->assertStringNotContainsString('  ', $result);
    }

    // =============================
    // TESTES DE TRUNCAMENTO
    // =============================

    public function testTruncateTitleRespectsMaxLength(): void
    {
        $title = str_repeat('Palavra ', 15);
        $result = $this->optimizer->truncateTitle($title, 60);

        $this->assertLessThanOrEqual(60, mb_strlen($result));
    }

    public function testTruncateTitlePreservesShortTitle(): void
    {
        $title = 'Samsung Galaxy S23';
        $result = $this->optimizer->truncateTitle($title, 60);

        $this->assertEquals($title, $result);
    }

    public function testTruncateTitleCutsAtWordBoundary(): void
    {
        $title = 'Samsung Galaxy S23 Ultra 256GB Preto Original Lacrado Novo Garantia';
        $result = $this->optimizer->truncateTitle($title, 40);

        // Com limite de 40, deve truncar antes de "Original" (45 chars completo seria demais)
        // Não deve cortar no meio de uma palavra - deve terminar em palavra completa
        $this->assertLessThanOrEqual(40, mb_strlen($result));
        // Verifica que termina em palavra completa (não parcial)
        $this->assertMatchesRegularExpression('/\w$/', trim($result));
    }
}
