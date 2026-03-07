<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

/**
 * Estratégia de Cache Centralizada
 * Define TTLs e políticas de invalidação consistentes para todo o sistema
 */
class CacheStrategy
{
    /**
     * TTLs (Time To Live) em segundos para diferentes tipos de dados
     */
    const TTL = [
        // Análises de IA - custo alto, atualização moderada
        'ai_seo_analysis' => 7200,        // 2 horas
        'ai_title_analysis' => 7200,      // 2 horas  
        'ai_description_analysis' => 7200, // 2 horas
        'ai_keywords_analysis' => 86400,   // 24 horas
        'ai_content_generation' => 3600,   // 1 hora
        'ai_image_analysis' => 14400,      // 4 horas
        
        // Dados de mercado - atualização frequente
        'competitor_data' => 3600,         // 1 hora
        'competitor_benchmarks' => 21600,  // 6 horas - NOVO: dados reais de concorrentes
        'market_trends' => 7200,           // 2 horas
        'category_keywords' => 86400,      // 24 horas
        'trending_keywords' => 3600,       // 1 hora
        'price_data' => 1800,              // 30 minutos
        
        // Dados estáticos/semi-estáticos
        'category_attributes' => 604800,   // 7 dias
        'forbidden_words' => 86400,        // 24 horas - ATUALIZADO: agora busca dados reais
        'seo_rules' => 86400,              // 24 horas
        'seo_config' => 86400,             // 24 horas - NOVO: configurações SEO gerais
        
        // Otimizações e sugestões
        'optimization_results' => 7200,    // 2 horas
        'suggestions' => 3600,             // 1 hora
        
        // Métricas e analytics
        'performance_metrics' => 3600,     // 1 hora
        'conversion_data' => 7200,         // 2 horas
        
        // Padrão para outros tipos
        'default' => 3600                  // 1 hora
    ];

    /**
     * Namespaces de cache para organização
     */
    const NAMESPACES = [
        'ai' => 'ai_seo',
        'keywords' => 'keywords',
        'competitors' => 'seo_competition', // ATUALIZADO: alinhado com uso real
        'market' => 'market',
        'optimization' => 'optimization',
        'analytics' => 'analytics',
        'static' => 'static',
        'config' => 'seo_config' // NOVO: para forbidden_words e configurações
    ];

    /**
     * Tags para invalidação em grupo
     */
    const TAGS = [
        'product' => 'product_',
        'category' => 'category_',
        'account' => 'account_',
        'global' => 'global'
    ];

    /**
     * Obtém TTL para um tipo de dado
     */
    public static function getTTL(string $type): int
    {
        return self::TTL[$type] ?? self::TTL['default'];
    }

    /**
     * Obtém namespace para organização
     */
    public static function getNamespace(string $group): string
    {
        return self::NAMESPACES[$group] ?? 'default';
    }

    /**
     * Gera chave de cache consistente
     */
    public static function generateKey(string $prefix, array $data): string
    {
        // Normalizar dados para consistência
        ksort($data);
        $hash = md5(json_encode($data));
        return "{$prefix}_{$hash}";
    }

    /**
     * Gera chave com tag para invalidação
     */
    public static function generateTaggedKey(string $prefix, string $tag, string $id, array $data): string
    {
        $dataHash = md5(json_encode($data));
        return "{$prefix}_{$tag}{$id}_{$dataHash}";
    }

    /**
     * Verifica se cache deve ser invalidado baseado em mudanças
     */
    public static function shouldInvalidate(array $oldData, array $newData, array $watchedFields = []): bool
    {
        if (empty($watchedFields)) {
            // Se não especificado, invalida se qualquer campo mudou
            return $oldData !== $newData;
        }

        // Verifica apenas campos específicos
        foreach ($watchedFields as $field) {
            $oldValue = self::getNestedValue($oldData, $field);
            $newValue = self::getNestedValue($newData, $field);
            
            if ($oldValue !== $newValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém valor aninhado de array usando dot notation
     */
    private static function getNestedValue(array $data, string $key)
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Define políticas de warming (pré-aquecimento) de cache
     */
    public static function getWarmingPolicy(string $type): array
    {
        $policies = [
            'category_keywords' => [
                'enabled' => true,
                'priority' => 'high',
                'schedule' => '0 2 * * *', // Diariamente às 2h
                'batch_size' => 50
            ],
            'trending_keywords' => [
                'enabled' => true,
                'priority' => 'high',
                'schedule' => '0 */6 * * *', // A cada 6 horas
                'batch_size' => 100
            ],
            'competitor_data' => [
                'enabled' => true,
                'priority' => 'medium',
                'schedule' => '0 4 * * *', // Diariamente às 4h
                'batch_size' => 30
            ],
            'market_trends' => [
                'enabled' => true,
                'priority' => 'medium',
                'schedule' => '0 3 * * *', // Diariamente às 3h
                'batch_size' => 20
            ]
        ];

        return $policies[$type] ?? [
            'enabled' => false,
            'priority' => 'low',
            'schedule' => '0 0 * * *',
            'batch_size' => 10
        ];
    }

    /**
     * Obtém estratégia de compressão para o tipo de dados
     */
    public static function getCompressionStrategy(string $type): array
    {
        // Dados grandes devem ser comprimidos
        $compressibleTypes = [
            'ai_seo_analysis',
            'ai_content_generation',
            'competitor_data',
            'market_trends',
            'optimization_results'
        ];

        return [
            'enabled' => in_array($type, $compressibleTypes),
            'algorithm' => 'gzip',
            'level' => 6 // Balanceamento entre compressão e velocidade
        ];
    }

    /**
     * Define prioridades para limpeza de cache quando limite é atingido
     */
    public static function getEvictionPriority(string $type): int
    {
        $priorities = [
            // Alta prioridade (mantém mais tempo) = 1-3
            'category_attributes' => 1,
            'forbidden_words' => 1,
            'seo_rules' => 2,
            'category_keywords' => 2,
            
            // Média prioridade = 4-6
            'ai_seo_analysis' => 4,
            'trending_keywords' => 5,
            'competitor_data' => 5,
            'market_trends' => 5,
            
            // Baixa prioridade (remove primeiro) = 7-10
            'suggestions' => 7,
            'performance_metrics' => 8,
            'ai_content_generation' => 8,
            'optimization_results' => 9,
            'price_data' => 10
        ];

        return $priorities[$type] ?? 5; // Média por padrão
    }

    /**
     * Retorna configuração completa para um tipo de cache
     */
    public static function getConfig(string $type): array
    {
        return [
            'ttl' => self::getTTL($type),
            'namespace' => self::determineNamespace($type),
            'warming_policy' => self::getWarmingPolicy($type),
            'compression' => self::getCompressionStrategy($type),
            'eviction_priority' => self::getEvictionPriority($type),
            'versioning_enabled' => self::shouldEnableVersioning($type)
        ];
    }

    /**
     * Determina namespace automaticamente baseado no tipo
     */
    private static function determineNamespace(string $type): string
    {
        if (str_starts_with($type, 'ai_')) {
            return self::NAMESPACES['ai'];
        } elseif (str_contains($type, 'keyword')) {
            return self::NAMESPACES['keywords'];
        } elseif (str_contains($type, 'competitor')) {
            return self::NAMESPACES['competitors'];
        } elseif (str_contains($type, 'market') || str_contains($type, 'trend')) {
            return self::NAMESPACES['market'];
        } elseif (str_contains($type, 'optimization') || str_contains($type, 'suggestion')) {
            return self::NAMESPACES['optimization'];
        } elseif (str_contains($type, 'metric') || str_contains($type, 'analytics') || str_contains($type, 'performance')) {
            return self::NAMESPACES['analytics'];
        }
        
        return self::NAMESPACES['static'];
    }

    /**
     * Define se deve habilitar versionamento para o tipo
     */
    private static function shouldEnableVersioning(string $type): bool
    {
        // Tipos críticos que precisam de versionamento para rollback
        $versionedTypes = [
            'ai_seo_analysis',
            'optimization_results',
            'ai_content_generation',
            'seo_rules'
        ];

        return in_array($type, $versionedTypes);
    }
}
