<?php

declare(strict_types=1);

namespace App\Services\SEO;

class CompatibilityService
{
    private const MOTO_BRANDS = [
        'honda' => ['CG 160', 'Titan', 'Fan', 'Bros', 'CB 300', 'CB 500', 'XRE 300', 'Pop'],
        'yamaha' => ['Factor', 'Fazer', 'XTZ', 'Lander', 'MT-03', 'Crosser', 'Neo'],
        'suzuki' => ['Yes', 'Intruder', 'GSX-S', 'V-Storm', 'Burgman'],
        'dafra' => ['Apache', 'Riva', 'Next', 'Speed', 'Citycom'],
        'kawasaki' => ['Ninja', 'Z400', 'Versys', 'Vulcan']
    ];

    private const CATEGORY_COMPATIBILITY = [
        'MLB3530' => self::MOTO_BRANDS,
        'MLB1071' => self::MOTO_BRANDS,
    ];

    public function __construct(?int $accountId = null)
    {
        // Initialization code if needed
    }

    /**
     * Retorna lista de compatibilidade para categoria
     */
    public function getCompatibilityList(string $categoryId): array
    {
        if (isset(self::CATEGORY_COMPATIBILITY[$categoryId])) {
            return self::CATEGORY_COMPATIBILITY[$categoryId];
        }

        return self::MOTO_BRANDS;
    }

    /**
     * Gera texto de compatibilidade
     */
    public function generateCompatibilityText(array $compatibilities): string
    {
        if (empty($compatibilities)) {
            return "Compatibilidade variável, consulte as especificações do seu modelo.";
        }

        $text = "Compatível com: ";
        $items = [];

        foreach ($compatibilities as $brand => $models) {
            if (is_array($models)) {
                $items[] = $this->formatBrandName($brand) . " (" . implode(", ", $models) . ")";
            } else {
                $items[] = $models;
            }
        }

        return $text . implode(", ", $items) . ".";
    }

    /**
     * Detecta compatibilidade do título
     */
    public function detectFromTitle(string $title): array
    {
        $titleLower = mb_strtolower($title);
        $detectedCompatibility = [];

        foreach (self::MOTO_BRANDS as $brand => $models) {
            // Check if brand is mentioned in title
            if (strpos($titleLower, $brand) !== false) {
                $detectedCompatibility[$brand] = [];

                // Check for specific models
                foreach ($models as $model) {
                    if (strpos($titleLower, mb_strtolower($model)) !== false) {
                        $detectedCompatibility[$brand][] = $model;
                    }
                }

                // If no specific models matched, add all models of this brand
                if (empty($detectedCompatibility[$brand])) {
                    $detectedCompatibility[$brand] = $models;
                }
            }
        }

        if (empty($detectedCompatibility)) {
            $detectedCompatibility = $this->detectModelsWithoutBrand($titleLower);
        }

        return $detectedCompatibility;
    }

    /**
     * Expands compatibility list with related models
     */
    public function expandCompatibilityList(array $initialCompatibility): array
    {
        $expanded = $initialCompatibility;

        foreach ($initialCompatibility as $brand => $models) {
            if (isset(self::MOTO_BRANDS[$brand])) {
                // If specific models were detected, add related models from the same brand
                if (!empty($models)) {
                    foreach (self::MOTO_BRANDS[$brand] as $potentialModel) {
                        if (!in_array($potentialModel, $models)) {
                            // Check if the potential model is related to any detected model
                            // For simplicity, we'll add all models of the same brand
                            $expanded[$brand][] = $potentialModel;
                        }
                    }
                    // Remove duplicates
                    $expanded[$brand] = array_unique($expanded[$brand]);
                } else {
                    // If no specific models were detected, add all models of the brand
                    $expanded[$brand] = self::MOTO_BRANDS[$brand];
                }
            }
        }

        return $expanded;
    }

    /**
     * Validates compatibility against category requirements
     */
    public function validateCompatibilityForCategory(array $compatibility, string $categoryId): array
    {
        // This would typically validate against category-specific requirements
        // For now, returning the compatibility as is
        return $compatibility;
    }

    /**
     * Generates compatibility keywords for SEO
     */
    public function generateCompatibilityKeywords(array $compatibility): array
    {
        $keywords = [];

        foreach ($compatibility as $brand => $models) {
            $keywords[] = $brand;
            foreach ($models as $model) {
                $keywords[] = $brand . ' ' . $model;
                $keywords[] = $model;
            }
        }

        // Add general compatibility terms
        $keywords = array_merge($keywords, [
            'compatível',
            'compatibilidade',
            'ajuste perfeito',
            'encaixe',
            'instalação fácil'
        ]);

        return array_unique($keywords);
    }

    private function formatBrandName(string $brand): string
    {
        return mb_strtoupper(mb_substr($brand, 0, 1)) . mb_substr($brand, 1);
    }

    private function detectModelsWithoutBrand(string $titleLower): array
    {
        $detected = [];

        foreach (self::MOTO_BRANDS as $brand => $models) {
            foreach ($models as $model) {
                if (strpos($titleLower, mb_strtolower($model)) !== false) {
                    $detected[$brand][] = $model;
                }
            }
        }

        foreach ($detected as $brand => $models) {
            $detected[$brand] = array_values(array_unique($models));
        }

        return $detected;
    }
}
