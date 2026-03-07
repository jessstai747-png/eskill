<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\SEO\HiddenAttributesDetector as SeoHiddenAttributesDetector;

class HiddenAttributesDetector
{
    private SeoHiddenAttributesDetector $detector;

    public function __construct(?int $accountId = null)
    {
        $this->detector = new SeoHiddenAttributesDetector($accountId);
    }

    public function detect(string $itemId): array
    {
        $analysis = $this->detector->detectKeywordFields($itemId);
        $fieldsDetected = [];
        $fieldsMissing = [];
        $currentValues = [];

        foreach ($analysis as $fieldId => $data) {
            if (($data['detected'] ?? false) === true) {
                $fieldsMissing[] = $fieldId;
            } else {
                $fieldsDetected[] = $fieldId;
            }

            if (array_key_exists('current_value', $data)) {
                $currentValues[$fieldId] = $data['current_value'];
            }
        }

        return [
            'item_id' => $itemId,
            'fields_detected' => $fieldsDetected,
            'fields_missing' => $fieldsMissing,
            'current_values' => $currentValues,
            'suggestions' => array_map(static fn ($data) => $data['suggestion'] ?? '', $analysis),
        ];
    }

    public function detectKeywordFields(string $itemId): array
    {
        $analysis = $this->detector->detectKeywordFields($itemId);
        $fieldsDetected = [];
        $suggestions = [];

        foreach ($analysis as $fieldId => $data) {
            $fieldsDetected[] = [
                'field_name' => $fieldId,
                'current_value' => $data['current_value'] ?? '',
                'recommended_action' => ($data['detected'] ?? false) ? 'add' : 'verify',
                'confidence' => $data['confidence'] ?? 0,
            ];
            $suggestions[$fieldId] = $data['suggestion'] ?? '';
        }

        return [
            'item_id' => $itemId,
            'fields_detected' => $fieldsDetected,
            'suggestions' => $suggestions,
        ];
    }

    public function generateKeywordsFieldValue(string $title, array $synonyms): string
    {
        return $this->detector->generateKeywordsFieldValue($title, $synonyms);
    }

    public function generateMPNValue(array $item): string
    {
        return $this->detector->generateMPNValue($item);
    }

    public function generateLineValue(array $item): string
    {
        return $this->detector->generateLineValue($item);
    }

    public function applyHiddenFields(string $itemId, array $fields, ?int $userId = null): array
    {
        return $this->detector->applyHiddenFields($itemId, $fields, $userId);
    }
}
