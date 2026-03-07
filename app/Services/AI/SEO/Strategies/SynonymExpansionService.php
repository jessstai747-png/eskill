<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\AI\ML\SynonymGenerator;
use Exception;
use PDO;

/**
 * SynonymExpansionService - Estratégia E1: Hierarquia de Sinônimos
 *
 * Sistema de expansão de sinônimos com 4 níveis hierárquicos:
 * - Nível 1 (Genérico): Termos principais → TÍTULO (peso 100%)
 * - Nível 2 (Qualificado): Termos compostos → MODELO (peso 70%)
 * - Nível 3 (Contexto): Termos com contexto → MODELO + DESCRIÇÃO (peso 50%)
 * - Nível 4 (Long Tail): Frases específicas → DESCRIÇÃO + KEYWORDS (peso 30%)
 *
 * @package App\Services\AI\SEO\Strategies
 */
class SynonymExpansionService
{
    private PDO $db;
    private ?MercadoLivreClient $mlClient = null;
    private ?SynonymGenerator $aiGenerator = null;
    private ?int $accountId;

    /**
     * Configuração padrão de hierarquia
     * Pode ser sobrescrita por categoria
     */
    private const DEFAULT_HIERARCHY = [
        'nivel_1' => [
            'name' => 'Genérico',
            'weight' => 1.00,
            'destination' => 'title',
            'max_words' => 3,
            'description' => 'Termos principais de busca direta'
        ],
        'nivel_2' => [
            'name' => 'Qualificado',
            'weight' => 0.70,
            'destination' => 'model',
            'max_words' => 5,
            'description' => 'Termos compostos e qualificados'
        ],
        'nivel_3' => [
            'name' => 'Contexto',
            'weight' => 0.50,
            'destination' => 'description',
            'max_words' => 7,
            'description' => 'Termos com contexto de uso'
        ],
        'nivel_4' => [
            'name' => 'Long Tail',
            'weight' => 0.30,
            'destination' => 'keywords',
            'max_words' => 10,
            'description' => 'Frases específicas e long tail'
        ]
    ];

    /**
     * Cache em memória para hierarquias carregadas
     */
    private array $hierarchyCache = [];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Expande sinônimos para um título/termo base
     *
     * @param string $title Título ou termo para expandir
     * @param string $categoryId ID da categoria do ML
     * @return array Lista de sinônimos organizados por nível
     */
    public function expand(string $title, string $categoryId): array
    {
        $title = trim($title);
        if (empty($title)) {
            return ['success' => false, 'error' => 'Título vazio'];
        }

        // Carregar hierarquia da categoria
        $hierarchy = $this->getHierarchy($categoryId);

        // Extrair palavras-chave do título
        $titleWords = $this->extractKeywords($title);

        // Buscar sinônimos por nível
        $synonyms = [
            'nivel_1' => [],
            'nivel_2' => [],
            'nivel_3' => [],
            'nivel_4' => []
        ];

        // 1. Buscar no banco de dados (cache curado)
        $dbSynonyms = $this->fetchFromDatabase($categoryId, $titleWords);

        // 2. Se não houver dados suficientes, usar arquitetura híbrida
        if ($this->needsAIGeneration($dbSynonyms)) {
            $aiSynonyms = $this->generateViaAI($title, $categoryId);
            $synonyms = $this->mergeSynonyms($dbSynonyms, $aiSynonyms);
        } else {
            $synonyms = $dbSynonyms;
        }

        // 3. Filtrar sinônimos que já estão no título
        $synonyms = $this->filterExistingInTitle($synonyms, $titleWords);

        // 4. Calcular scores
        $synonyms = $this->calculateScores($synonyms, $title, $categoryId);

        return [
            'success' => true,
            'title' => $title,
            'category_id' => $categoryId,
            'hierarchy' => $hierarchy,
            'synonyms' => $synonyms,
            'total_count' => $this->countSynonyms($synonyms),
            'source' => $this->identifySource($dbSynonyms, $synonyms)
        ];
    }

    /**
     * Retorna a hierarquia de sinônimos para uma categoria
     *
     * @param string $categoryId ID da categoria
     * @return array Configuração da hierarquia
     */
    public function getHierarchy(string $categoryId): array
    {
        // Verificar cache
        if (isset($this->hierarchyCache[$categoryId])) {
            return $this->hierarchyCache[$categoryId];
        }

        // Buscar no banco
        $customHierarchy = $this->loadHierarchyFromDB($categoryId);

        if ($customHierarchy !== null) {
            $this->hierarchyCache[$categoryId] = $customHierarchy;
            return $customHierarchy;
        }

        // Usar padrão
        $this->hierarchyCache[$categoryId] = self::DEFAULT_HIERARCHY;
        return self::DEFAULT_HIERARCHY;
    }

    /**
     * Identifica o nível hierárquico de um texto
     *
     * @param string $text Texto para analisar
     * @return string Nível identificado (nivel_1, nivel_2, nivel_3, nivel_4)
     */
    public function identifyLevel(string $text): string
    {
        $wordCount = str_word_count($text);

        if ($wordCount <= 2) {
            return 'nivel_1';
        } elseif ($wordCount <= 4) {
            return 'nivel_2';
        } elseif ($wordCount <= 6) {
            return 'nivel_3';
        }

        return 'nivel_4';
    }

    /**
     * Seleciona sinônimos para um campo específico
     *
     * @param string $title Título base
     * @param string $field Campo destino (title, model, description, keywords)
     * @param string $categoryId ID da categoria
     * @return array Sinônimos selecionados para o campo
     */
    public function selectForField(string $title, string $field, string $categoryId): array
    {
        $expansion = $this->expand($title, $categoryId);

        if (!$expansion['success']) {
            return [];
        }

        $selected = [];
        $hierarchy = $expansion['hierarchy'];

        foreach ($expansion['synonyms'] as $level => $levelSynonyms) {
            $levelConfig = $hierarchy[$level] ?? self::DEFAULT_HIERARCHY[$level];

            if ($levelConfig['destination'] === $field) {
                foreach ($levelSynonyms as $synonym) {
                    $selected[] = [
                        'word' => $synonym['word'],
                        'level' => $level,
                        'weight' => $levelConfig['weight'],
                        'score' => $synonym['score'] ?? 0.5
                    ];
                }
            }
        }

        // Ordenar por score
        usort($selected, fn($a, $b) => $b['score'] <=> $a['score']);

        // Limitar quantidade
        $limits = [
            'title' => 3,
            'model' => 5,
            'description' => 10,
            'keywords' => 15
        ];

        return array_slice($selected, 0, $limits[$field] ?? 10);
    }

    /**
     * Gera campo MODELO otimizado com sinônimos
     *
     * @param string $title Título original
     * @param string $categoryId ID da categoria
     * @return array Resultado com campo MODELO gerado
     */
    public function generateOptimizedModel(string $title, string $categoryId): array
    {
        // Selecionar sinônimos para MODELO (níveis 2 e 3)
        $synonyms = $this->selectForField($title, 'model', $categoryId);

        if (empty($synonyms)) {
            return [
                'success' => false,
                'error' => 'Nenhum sinônimo disponível para MODELO',
                'model' => '',
                'synonyms_used' => []
            ];
        }

        // Construir campo MODELO
        $modelParts = [];
        $synonymsUsed = [];

        foreach ($synonyms as $synonym) {
            // Limite de 255 caracteres para campo MODELO
            $testModel = implode(' ', array_merge($modelParts, [$synonym['word']]));
            if (mb_strlen($testModel) <= 250) {
                $modelParts[] = $synonym['word'];
                $synonymsUsed[] = $synonym;
            }
        }

        $model = implode(' ', $modelParts);

        return [
            'success' => true,
            'model' => $model,
            'synonyms_used' => $synonymsUsed,
            'character_count' => mb_strlen($model),
            'word_count' => count($modelParts),
            'score' => $this->calculateModelScore($synonymsUsed)
        ];
    }

    /**
     * Salva hierarquia customizada para uma categoria
     *
     * @param string $categoryId ID da categoria
     * @param array $hierarchy Configuração da hierarquia
     * @return bool Sucesso da operação
     */
    public function saveHierarchy(string $categoryId, array $hierarchy): bool
    {
        try {
            // Validar estrutura
            foreach (['nivel_1', 'nivel_2', 'nivel_3', 'nivel_4'] as $level) {
                if (!isset($hierarchy[$level])) {
                    throw new Exception("Nível {$level} não encontrado");
                }
            }

            // Salvar no banco (como JSON na tabela de configurações)
            $stmt = $this->db->prepare("
                INSERT INTO seo_category_config (category_id, config_key, config_value, updated_at)
                VALUES (:category_id, 'synonym_hierarchy', :config_value, NOW())
                ON DUPLICATE KEY UPDATE config_value = :config_value2, updated_at = NOW()
            ");

            $stmt->execute([
                'category_id' => $categoryId,
                'config_value' => json_encode($hierarchy),
                'config_value2' => json_encode($hierarchy)
            ]);

            // Limpar cache
            unset($this->hierarchyCache[$categoryId]);

            return true;
        } catch (Exception $e) {
            log_warning('Erro ao salvar hierarquia de sinônimos', [
                'service' => 'SynonymExpansionService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Gera hierarquia automaticamente para nova categoria (via AI + ML API)
     * Usado quando não há dados pré-populados no banco
     *
     * @param string $categoryId ID da categoria
     * @return array Hierarquia gerada com sinônimos
     */
    public function generateHierarchyForCategory(string $categoryId): array
    {
        try {
            // 1. Buscar informações da categoria via ML API
            $categoryInfo = $this->fetchCategoryInfo($categoryId);

            if (!$categoryInfo) {
                return [
                    'success' => false,
                    'error' => 'Categoria não encontrada'
                ];
            }

            // 2. Gerar sinônimos base via AI
            $generator = $this->getAIGenerator();
            $baseSynonyms = $generator->generateForCategory(
                $categoryInfo['name'],
                $categoryInfo['path'] ?? []
            );

            // 3. Classificar sinônimos por nível
            $hierarchy = $this->classifySynonymsByLevel($baseSynonyms);

            // 4. Salvar no banco para cache
            $this->saveSynonymsToDatabase($categoryId, $hierarchy);

            return [
                'success' => true,
                'category_id' => $categoryId,
                'category_name' => $categoryInfo['name'],
                'synonyms' => $hierarchy,
                'source' => 'ai_generated'
            ];
        } catch (Exception $e) {
            log_error('Erro ao gerar hierarquia de sinônimos', [
                'service' => 'SynonymExpansionService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Adiciona sinônimo manualmente à hierarquia
     *
     * @param string $categoryId ID da categoria
     * @param string $word Palavra/sinônimo
     * @param string $level Nível (nivel_1, nivel_2, nivel_3, nivel_4)
     * @param float $weight Peso opcional (0.0-1.0)
     * @return bool Sucesso da operação
     */
    public function addSynonym(
        string $categoryId,
        string $word,
        string $level,
        float $weight = 1.0
    ): bool {
        try {
            $levelConfig = self::DEFAULT_HIERARCHY[$level] ?? null;
            if (!$levelConfig) {
                throw new Exception("Nível inválido: {$level}");
            }

            $stmt = $this->db->prepare("
                INSERT INTO seo_synonym_hierarchy
                (category_id, level, word, weight, destination, is_active, created_at)
                VALUES (:category_id, :level, :word, :weight, :destination, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    weight = :weight2,
                    is_active = 1,
                    updated_at = NOW()
            ");

            $stmt->execute([
                'category_id' => $categoryId,
                'level' => $level,
                'word' => mb_strtolower(trim($word)),
                'weight' => min(1.0, max(0.0, $weight)),
                'destination' => $levelConfig['destination'],
                'weight2' => min(1.0, max(0.0, $weight))
            ]);

            return true;
        } catch (Exception $e) {
            log_warning('Erro ao adicionar sinônimo', [
                'service' => 'SynonymExpansionService',
                'category_id' => $categoryId,
                'word' => $word,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove sinônimo da hierarquia
     *
     * @param string $categoryId ID da categoria
     * @param string $word Palavra a remover
     * @return bool Sucesso da operação
     */
    public function removeSynonym(string $categoryId, string $word): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE seo_synonym_hierarchy
                SET is_active = 0, updated_at = NOW()
                WHERE category_id = :category_id AND word = :word
            ");

            $stmt->execute([
                'category_id' => $categoryId,
                'word' => mb_strtolower(trim($word))
            ]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            log_warning('Erro ao remover sinônimo', [
                'service' => 'SynonymExpansionService',
                'category_id' => $categoryId,
                'word' => $word,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Lista todos os sinônimos de uma categoria
     *
     * @param string $categoryId ID da categoria
     * @return array Lista de sinônimos por nível
     */
    public function listSynonyms(string $categoryId): array
    {
        $stmt = $this->db->prepare("
            SELECT level, word, weight, destination, created_at
            FROM seo_synonym_hierarchy
            WHERE category_id = :category_id AND is_active = 1
            ORDER BY level, weight DESC, word
        ");

        $stmt->execute(['category_id' => $categoryId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'nivel_1' => [],
            'nivel_2' => [],
            'nivel_3' => [],
            'nivel_4' => []
        ];

        foreach ($rows as $row) {
            $result[$row['level']][] = [
                'word' => $row['word'],
                'weight' => (float) $row['weight'],
                'destination' => $row['destination'],
                'created_at' => $row['created_at']
            ];
        }

        return $result;
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Busca sinônimos do banco de dados
     */
    private function fetchFromDatabase(string $categoryId, array $titleWords): array
    {
        $result = [
            'nivel_1' => [],
            'nivel_2' => [],
            'nivel_3' => [],
            'nivel_4' => []
        ];

        try {
            $stmt = $this->db->prepare("
                SELECT level, word, weight, destination
                FROM seo_synonym_hierarchy
                WHERE category_id = :category_id
                AND is_active = 1
                ORDER BY level, weight DESC
            ");

            $stmt->execute(['category_id' => $categoryId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $result[$row['level']][] = [
                    'word' => $row['word'],
                    'weight' => (float) $row['weight'],
                    'destination' => $row['destination'],
                    'source' => 'database'
                ];
            }
        } catch (Exception $e) {
            log_warning('Erro ao buscar sinônimos do banco', [
                'service' => 'SynonymExpansionService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Verifica se precisa gerar sinônimos via AI
     */
    private function needsAIGeneration(array $dbSynonyms): bool
    {
        $totalCount = $this->countSynonyms($dbSynonyms);
        return $totalCount < 10; // Mínimo de 10 sinônimos
    }

    /**
     * Gera sinônimos via AI/LLM
     */
    private function generateViaAI(string $title, string $categoryId): array
    {
        try {
            $generator = $this->getAIGenerator();
            return $generator->expandSynonyms($title, $categoryId);
        } catch (Exception $e) {
            log_warning('Erro ao gerar sinônimos via AI', [
                'service' => 'SynonymExpansionService',
                'title' => $title,
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [
                'nivel_1' => [],
                'nivel_2' => [],
                'nivel_3' => [],
                'nivel_4' => []
            ];
        }
    }

    /**
     * Obtém instância do gerador AI
     */
    private function getAIGenerator(): SynonymGenerator
    {
        if ($this->aiGenerator === null) {
            $this->aiGenerator = new SynonymGenerator($this->accountId);
        }
        return $this->aiGenerator;
    }

    /**
     * Mescla sinônimos do banco com gerados por AI
     */
    private function mergeSynonyms(array $dbSynonyms, array $aiSynonyms): array
    {
        $merged = $dbSynonyms;

        foreach ($aiSynonyms as $level => $synonyms) {
            $existingWords = array_column($merged[$level] ?? [], 'word');

            foreach ($synonyms as $synonym) {
                if (!in_array($synonym['word'], $existingWords)) {
                    $synonym['source'] = 'ai';
                    $merged[$level][] = $synonym;
                }
            }
        }

        return $merged;
    }

    /**
     * Filtra sinônimos que já existem no título
     */
    private function filterExistingInTitle(array $synonyms, array $titleWords): array
    {
        $titleWordsLower = array_map('mb_strtolower', $titleWords);

        foreach ($synonyms as $level => $levelSynonyms) {
            $synonyms[$level] = array_filter($levelSynonyms, function ($synonym) use ($titleWordsLower) {
                $synonymWords = explode(' ', mb_strtolower($synonym['word']));
                foreach ($synonymWords as $word) {
                    if (in_array($word, $titleWordsLower)) {
                        return false; // Já existe no título
                    }
                }
                return true;
            });

            // Reindexar array
            $synonyms[$level] = array_values($synonyms[$level]);
        }

        return $synonyms;
    }

    /**
     * Calcula scores para os sinônimos
     */
    private function calculateScores(array $synonyms, string $title, string $categoryId): array
    {
        $hierarchy = $this->getHierarchy($categoryId);

        foreach ($synonyms as $level => $levelSynonyms) {
            $levelWeight = $hierarchy[$level]['weight'] ?? 0.5;

            foreach ($levelSynonyms as $index => $synonym) {
                $baseScore = $synonym['weight'] ?? 0.5;
                $synonyms[$level][$index]['score'] = round($baseScore * $levelWeight, 2);
            }

            // Ordenar por score
            usort($synonyms[$level], fn($a, $b) => $b['score'] <=> $a['score']);
        }

        return $synonyms;
    }

    /**
     * Conta total de sinônimos
     */
    private function countSynonyms(array $synonyms): int
    {
        return array_sum(array_map('count', $synonyms));
    }

    /**
     * Identifica fonte dos dados
     */
    private function identifySource(array $dbSynonyms, array $finalSynonyms): string
    {
        $dbCount = $this->countSynonyms($dbSynonyms);
        $finalCount = $this->countSynonyms($finalSynonyms);

        if ($dbCount === $finalCount) {
            return 'database';
        } elseif ($dbCount === 0) {
            return 'ai';
        }

        return 'hybrid';
    }

    /**
     * Extrai palavras-chave do título
     */
    private function extractKeywords(string $title): array
    {
        // Remover stopwords
        $stopwords = ['de', 'da', 'do', 'e', 'para', 'com', 'em', 'a', 'o', 'um', 'uma'];

        $words = preg_split('/\s+/', mb_strtolower($title));
        $words = array_filter($words, fn($w) => !in_array($w, $stopwords) && mb_strlen($w) > 2);

        return array_values($words);
    }

    /**
     * Carrega hierarquia customizada do banco
     */
    private function loadHierarchyFromDB(string $categoryId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT config_value
                FROM seo_category_config
                WHERE category_id = :category_id AND config_key = 'synonym_hierarchy'
            ");

            $stmt->execute(['category_id' => $categoryId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['config_value'])) {
                return json_decode($row['config_value'], true);
            }
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }

        return null;
    }

    /**
     * Busca informações da categoria via ML API
     */
    private function fetchCategoryInfo(string $categoryId): ?array
    {
        try {
            if ($this->mlClient === null) {
                $this->mlClient = new MercadoLivreClient($this->accountId);
            }

            return $this->mlClient->getCategory($categoryId);
        } catch (Exception $e) {
            log_warning('Erro ao buscar categoria no ML', [
                'service' => 'SynonymExpansionService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Classifica sinônimos por nível baseado na quantidade de palavras
     */
    private function classifySynonymsByLevel(array $synonyms): array
    {
        $result = [
            'nivel_1' => [],
            'nivel_2' => [],
            'nivel_3' => [],
            'nivel_4' => []
        ];

        foreach ($synonyms as $synonym) {
            $word = is_array($synonym) ? $synonym['word'] : $synonym;
            $level = $this->identifyLevel($word);

            $result[$level][] = [
                'word' => $word,
                'weight' => is_array($synonym) ? ($synonym['weight'] ?? 1.0) : 1.0,
                'source' => 'ai'
            ];
        }

        return $result;
    }

    /**
     * Salva sinônimos gerados no banco de dados
     */
    private function saveSynonymsToDatabase(string $categoryId, array $hierarchy): void
    {
        foreach ($hierarchy as $level => $synonyms) {
            foreach ($synonyms as $synonym) {
                $this->addSynonym(
                    $categoryId,
                    $synonym['word'],
                    $level,
                    $synonym['weight'] ?? 1.0
                );
            }
        }
    }

    /**
     * Calcula score do campo MODELO gerado
     */
    private function calculateModelScore(array $synonymsUsed): float
    {
        if (empty($synonymsUsed)) {
            return 0.0;
        }

        $totalScore = array_sum(array_column($synonymsUsed, 'score'));
        return round($totalScore / count($synonymsUsed), 2);
    }
}
