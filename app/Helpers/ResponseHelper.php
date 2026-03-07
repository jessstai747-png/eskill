<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para respostas JSON consistentes e sanitizadas
 */
class ResponseHelper
{
    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Retorna resposta JSON sanitizada
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = [], int $jsonFlags = self::DEFAULT_JSON_FLAGS): void
    {
        http_response_code($statusCode);

        $hasContentType = false;
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                $hasContentType = true;
            }
        }

        if (!$hasContentType) {
            header('Content-Type: application/json; charset=utf-8');
        }

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode(
            self::sanitizeData($payload),
            $jsonFlags
        );
    }

    /**
     * Resposta de sucesso padronizada
     */
    public static function success(array $data = [], int $statusCode = 200, array $headers = []): void
    {
        $payload = ['success' => true];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        self::json($payload, $statusCode, $headers);
    }

    /**
     * Resposta de erro padronizada
     */
    public static function error(string $message, int $statusCode = 400, array $extra = [], array $headers = []): void
    {
        $payload = array_merge(['success' => false, 'error' => $message], $extra);
        self::json($payload, $statusCode, $headers);
    }

    /**
     * Sanitiza recursivamente strings do payload preservando demais tipos
     *
     * @param mixed $data
     * @return mixed
     */
    private static function sanitizeData($data)
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = self::sanitizeData($value);
            }
            return $sanitized;
        }

        if (is_object($data)) {
            if ($data instanceof \JsonSerializable) {
                return self::sanitizeData($data->jsonSerialize());
            }

            return self::sanitizeData(get_object_vars($data));
        }

        if (is_string($data)) {
            return SecurityHelper::e($data);
        }

        return $data;
    }
}
