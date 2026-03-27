<?php

declare(strict_types=1);

namespace App\Traits;

trait ShipmentSyncHelpers
{
    private function getArrayValueOrNull(array $data, string $key): ?array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            return null;
        }

        return $data[$key];
    }

    private function getStringValue(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || !is_scalar($data[$key])) {
            return null;
        }

        return $this->normalizeStringValue((string)$data[$key]);
    }

    private function getNestedStringValue(array $data, array $path): ?string
    {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return is_scalar($current) ? $this->normalizeStringValue((string)$current) : null;
    }

    private function getNumericValue(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data) || !is_numeric($data[$key])) {
            return null;
        }

        return (int)$data[$key];
    }

    private function normalizeStringValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function isShipmentDelayed(array $shipment): bool
    {
        $status = strtolower((string)($shipment['status'] ?? ''));
        if ($status === 'delayed') {
            return true;
        }

        $substatus = strtolower((string)($shipment['substatus'] ?? ''));
        if ($substatus !== '' && $this->matchesDelaySubstatus($substatus)) {
            return true;
        }

        return $this->hasDelayedHistory($shipment);
    }

    private function matchesDelaySubstatus(string $substatus): bool
    {
        foreach (self::DELAY_KEYWORDS as $keyword) {
            if (str_contains($substatus, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function hasDelayedHistory(array $shipment): bool
    {
        $statusHistory = $shipment['status_history'] ?? [];
        return is_array($statusHistory) && !empty($statusHistory['date_delayed']);
    }

    private function sanitizeDays(int $days): int
    {
        if ($days <= 0) {
            return self::DEFAULT_DAYS;
        }

        return max(1, min(self::MAX_DAYS, $days));
    }

    private function sanitizeLimit(int $limit, int $max): int
    {
        if ($limit <= 0) {
            return $max;
        }

        return max(1, min($max, $limit));
    }

    private function encodeShipmentData(array $shipment): string
    {
        $encoded = json_encode($shipment, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? '{}' : $encoded;
    }
}
