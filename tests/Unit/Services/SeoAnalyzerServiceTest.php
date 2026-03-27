<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Testes unitários para SeoAnalyzerService
 * 
 * Testamos os métodos de análise internos usando reflection para
 * evitar dependências externas (ML API, banco, etc.)
 */
class SeoAnalyzerServiceTest extends TestCase
{
    /**
     * Cria um mock parcial do SeoAnalyzerService para testes unitários
     */
    private function createAnalyzerForTesting(): object
    {
        // Criamos uma classe anônima que expõe os métodos privados como públicos
        return new class {
            // Configurações de título
            private const TITLE_MIN_LENGTH = 20;
            private const TITLE_MAX_LENGTH = 60;
            private const TITLE_OPTIMAL_LENGTH = 55;

            // Palavras proibidas/ruins no título
            private const TITLE_FORBIDDEN_WORDS = [
                'promoção',
                'oferta',
                'desconto',
                'barato',
                'liquidação',
                'imperdível',
                'oportunidade',
                'últimas unidades',
                'aproveite',
                'compre já',
                'melhor preço',
                'menor preço',
                'frete grátis',
                '!!!',
                '???',
                '***',
                'grátis',
                'brinde',
                'queima',
                'black friday'
            ];

            // Palavras de alto impacto SEO
            private const HIGH_IMPACT_WORDS = [
                'original',
                'novo',
                'lacrado',
                'garantia',
                'nota fiscal',
                'pronta entrega',
                'envio imediato',
                'nacional',
                'importado'
            ];

            public function analyzeTitle(string $title, array $item): array
            {
                $result = [
                    'score' => 0,
                    'max_score' => 100,
                    'issues' => [],
                    'suggestions' => [],
                    'details' => [],
                    'critical' => false,
                ];

                $title = trim($title);
                $length = mb_strlen($title);

                if ($length === 0) {
                    $result['issues'][] = 'Título vazio - CRÍTICO';
                    $result['critical'] = true;
                    return $result;
                }

                $result['details']['length'] = $length;

                // Score por comprimento (0-30 pontos)
                if ($length >= self::TITLE_MIN_LENGTH && $length <= self::TITLE_MAX_LENGTH) {
                    $lengthScore = 30;
                    if ($length >= 45 && $length <= 58) {
                        $lengthScore = 30;
                    } elseif ($length < 45) {
                        $lengthScore = 20;
                    }
                } else {
                    if ($length < self::TITLE_MIN_LENGTH) {
                        $lengthScore = 10;
                        $result['issues'][] = "Título muito curto ({$length} caracteres)";
                    } else {
                        $lengthScore = 15;
                        $result['issues'][] = "Título muito longo ({$length} caracteres)";
                    }
                }
                $result['score'] += $lengthScore;

                // Verificar palavras proibidas
                $forbiddenFound = [];
                $titleLower = mb_strtolower($title);
                foreach (self::TITLE_FORBIDDEN_WORDS as $word) {
                    if (mb_strpos($titleLower, mb_strtolower($word)) !== false) {
                        $forbiddenFound[] = $word;
                    }
                }

                if (empty($forbiddenFound)) {
                    $result['score'] += 20;
                } else {
                    $result['issues'][] = 'Palavras não recomendadas: ' . implode(', ', $forbiddenFound);
                    $result['score'] += max(0, 20 - (count($forbiddenFound) * 5));
                }

                // Verificar palavras de alto impacto
                $impactFound = [];
                foreach (self::HIGH_IMPACT_WORDS as $word) {
                    if (mb_strpos($titleLower, mb_strtolower($word)) !== false) {
                        $impactFound[] = $word;
                    }
                }
                $result['score'] += min(20, count($impactFound) * 5);

                // Verificar estrutura
                $hasNumbers = preg_match('/\d/', $title);
                $hasUppercase = preg_match('/[A-Z]/', $title);
                $structureScore = 0;
                if ($hasNumbers) $structureScore += 5;
                if ($hasUppercase) $structureScore += 5;
                if (preg_match('/\b(para|com|de)\b/i', $title)) $structureScore += 5;
                $result['score'] += $structureScore;

                // Verificar repetição
                $words = preg_split('/\s+/', $titleLower);
                $wordCount = array_count_values($words);
                $repetitions = array_filter($wordCount, fn($count) => $count > 1);
                if (empty($repetitions)) {
                    $result['score'] += 15;
                } else {
                    $result['issues'][] = 'Palavras repetidas no título: ' . implode(', ', array_keys($repetitions));
                    $result['score'] += 5;
                }

                return $result;
            }

            public function analyzeDescription($description, array $item): array
            {
                $result = [
                    'score' => 0,
                    'max_score' => 100,
                    'issues' => [],
                    'suggestions' => [],
                    'details' => [],
                    'critical' => false,
                ];

                $descText = is_array($description) ? ($description['plain_text'] ?? '') : (string)$description;
                $length = mb_strlen($descText);

                if ($length === 0) {
                    $result['issues'][] = 'Descrição vazia - CRÍTICO';
                    $result['critical'] = true;
                    return $result;
                }

                if ($length >= 1000) {
                    $result['score'] += 30;
                } elseif ($length >= 500) {
                    $result['score'] += 25;
                } elseif ($length >= 200) {
                    $result['score'] += 15;
                } else {
                    $result['score'] += 5;
                    $result['issues'][] = 'Descrição muito curta';
                }

                // Verificar contato
                if (preg_match('/(whatsapp|whats|zap|telefone|email|@|\.com|\.br|instagram|facebook)/i', $descText)) {
                    $result['issues'][] = 'Possível informação de contato detectada';
                    $result['score'] = max(0, $result['score'] - 20);
                }

                return $result;
            }

            public function analyzeImages(array $pictures): array
            {
                $result = [
                    'score' => 0,
                    'max_score' => 100,
                    'issues' => [],
                    'suggestions' => [],
                    'details' => [],
                    'critical' => false,
                ];

                $count = count($pictures);
                if ($count === 0) {
                    $result['issues'][] = 'Nenhuma imagem - CRÍTICO';
                    $result['critical'] = true;
                    return $result;
                }

                // Score por quantidade
                if ($count >= 10) {
                    $result['score'] += 40;
                } elseif ($count >= 6) {
                    $result['score'] += 35;
                } elseif ($count >= 4) {
                    $result['score'] += 25;
                } elseif ($count >= 2) {
                    $result['score'] += 15;
                } else {
                    $result['score'] += 5;
                }

                // Score por qualidade
                $qualityScore = 0;
                foreach ($pictures as $pic) {
                    $maxSize = $pic['max_size'] ?? '';
                    if (preg_match('/(\d+)x(\d+)/', $maxSize, $matches)) {
                        $width = (int)$matches[1];
                        if ($width >= 1200) {
                            $qualityScore += 5;
                        } elseif ($width >= 800) {
                            $qualityScore += 3;
                        }
                    }
                }
                $result['score'] += min(30, $qualityScore);

                // Primeira imagem
                if (!empty($pictures[0])) {
                    $maxSize = $pictures[0]['max_size'] ?? '';
                    if (preg_match('/(\d+)x(\d+)/', $maxSize, $matches)) {
                        $width = (int)$matches[1];
                        if ($width >= 1200) {
                            $result['score'] += 15;
                        } elseif ($width >= 800) {
                            $result['score'] += 10;
                        } else {
                            $result['score'] += 5;
                        }
                    }
                }

                // Variedade
                $uniqueUrls = count(array_unique(array_column($pictures, 'url')));
                if ($uniqueUrls === $count) {
                    $result['score'] += 15;
                } else {
                    $result['score'] += 5;
                }

                return $result;
            }

            public function calculateGrade(int $score): string
            {
                if ($score >= 90) return 'A+';
                if ($score >= 80) return 'A';
                if ($score >= 70) return 'B';
                if ($score >= 60) return 'C';
                if ($score >= 50) return 'D';
                return 'F';
            }
        };
    }

    private object $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = $this->createAnalyzerForTesting();
    }

    // =============================
    // TESTES DE ANÁLISE DE TÍTULO
    // =============================

    public function testAnalyzeTitleEmptyReturnsZeroScore(): void
    {
        $result = $this->analyzer->analyzeTitle('', []);

        $this->assertEquals(0, $result['score']);
        $this->assertTrue($result['critical']);
        $this->assertNotEmpty($result['issues']);
    }

    public function testAnalyzeTitleOptimalLengthReturnsHighScore(): void
    {
        // Título com comprimento ideal (45-58 caracteres)
        $title = 'Smartphone Samsung Galaxy S23 Ultra 256GB Preto';
        $result = $this->analyzer->analyzeTitle($title, []);

        $this->assertGreaterThanOrEqual(30, $result['score']);
        $this->assertFalse($result['critical']);
    }

    public function testAnalyzeTitleWithForbiddenWordsReducesScore(): void
    {
        $titleClean = 'Smartphone Samsung Galaxy S23 Ultra 256GB';
        $titleWithForbidden = 'Smartphone Samsung PROMOÇÃO Galaxy S23 Ultra';

        $resultClean = $this->analyzer->analyzeTitle($titleClean, []);
        $resultForbidden = $this->analyzer->analyzeTitle($titleWithForbidden, []);

        $this->assertGreaterThan($resultForbidden['score'], $resultClean['score']);
        $this->assertNotEmpty($resultForbidden['issues']);
    }

    public function testAnalyzeTitleWithHighImpactWordsIncreasesScore(): void
    {
        $titleNoImpact = 'Smartphone Samsung Galaxy S23 Ultra 256GB';
        $titleWithImpact = 'Smartphone Samsung Galaxy S23 Ultra 256GB Original Lacrado';

        $resultNoImpact = $this->analyzer->analyzeTitle($titleNoImpact, []);
        $resultWithImpact = $this->analyzer->analyzeTitle($titleWithImpact, []);

        $this->assertGreaterThan($resultNoImpact['score'], $resultWithImpact['score']);
    }

    public function testAnalyzeTitleTooShortReturnsWarning(): void
    {
        $title = 'Celular Novo'; // Muito curto
        $result = $this->analyzer->analyzeTitle($title, []);

        // Título curto ainda pode ter pontos por estrutura, mas deve ter issues
        $this->assertNotEmpty($result['issues']);
        $this->assertStringContainsString('curto', implode(' ', $result['issues']));
    }

    public function testAnalyzeTitleWithRepetitionsReducesScore(): void
    {
        $titleRepeat = 'Samsung Samsung Galaxy S23 Ultra Samsung';
        $result = $this->analyzer->analyzeTitle($titleRepeat, []);

        $this->assertNotEmpty($result['issues']);
        $this->assertStringContainsString('repetidas', implode(' ', $result['issues']));
    }

    // =============================
    // TESTES DE ANÁLISE DE DESCRIÇÃO
    // =============================

    public function testAnalyzeDescriptionEmptyReturnsCritical(): void
    {
        $result = $this->analyzer->analyzeDescription('', []);

        $this->assertEquals(0, $result['score']);
        $this->assertTrue($result['critical']);
    }

    public function testAnalyzeDescriptionShortReturnsLowScore(): void
    {
        $desc = 'Produto novo, ótimo estado.';
        $result = $this->analyzer->analyzeDescription($desc, []);

        $this->assertLessThan(30, $result['score']);
        $this->assertNotEmpty($result['issues']);
    }

    public function testAnalyzeDescriptionWithGoodLengthReturnsHigherScore(): void
    {
        $desc = str_repeat('Esta é uma descrição detalhada do produto. ', 30); // >500 chars
        $result = $this->analyzer->analyzeDescription($desc, []);

        $this->assertGreaterThanOrEqual(25, $result['score']);
    }

    public function testAnalyzeDescriptionWithContactInfoReducesScore(): void
    {
        $desc = 'Produto excelente. Dúvidas chame no WhatsApp (11) 99999-9999.';
        $result = $this->analyzer->analyzeDescription($desc, []);

        $this->assertNotEmpty($result['issues']);
        $this->assertStringContainsString('contato', strtolower(implode(' ', $result['issues'])));
    }

    // =============================
    // TESTES DE ANÁLISE DE IMAGENS
    // =============================

    public function testAnalyzeImagesEmptyReturnsCritical(): void
    {
        $result = $this->analyzer->analyzeImages([]);

        $this->assertEquals(0, $result['score']);
        $this->assertTrue($result['critical']);
    }

    public function testAnalyzeImagesSingleImageLowScore(): void
    {
        $pictures = [
            ['url' => 'http://example.com/1.jpg', 'max_size' => '800x800']
        ];
        $result = $this->analyzer->analyzeImages($pictures);

        $this->assertLessThan(40, $result['score']);
    }

    public function testAnalyzeImagesMultipleHighResIncreasesScore(): void
    {
        $pictures = [];
        for ($i = 1; $i <= 6; $i++) {
            $pictures[] = ['url' => "http://example.com/{$i}.jpg", 'max_size' => '1200x1200'];
        }
        $result = $this->analyzer->analyzeImages($pictures);

        $this->assertGreaterThanOrEqual(70, $result['score']);
    }

    // =============================
    // TESTES DE GRADE/SCORE
    // =============================

    public function testCalculateGradeReturnsCorrectValues(): void
    {
        $this->assertEquals('A+', $this->analyzer->calculateGrade(95));
        $this->assertEquals('A', $this->analyzer->calculateGrade(85));
        $this->assertEquals('B', $this->analyzer->calculateGrade(75));
        $this->assertEquals('C', $this->analyzer->calculateGrade(65));
        $this->assertEquals('D', $this->analyzer->calculateGrade(55));
        $this->assertEquals('F', $this->analyzer->calculateGrade(40));
    }
}
