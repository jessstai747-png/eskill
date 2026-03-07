<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

/**
 * StartupValidator - Valida requisitos do sistema na inicialização
 */
class StartupValidator
{
    /**
     * Valida requisitos básicos do sistema
     * @throws Exception se algum requisito não for atendido
     */
    public static function validate(): void
    {
        // Validar PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            throw new Exception('PHP 8.0 ou superior é necessário. Versão atual: ' . PHP_VERSION);
        }

        // Validar extensões PHP necessárias
        $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }

        if (!empty($missingExtensions)) {
            throw new Exception('Extensões PHP faltando: ' . implode(', ', $missingExtensions));
        }

        // Validar diretórios writable
        $writableDirs = [
            __DIR__ . '/../../storage/logs',
            __DIR__ . '/../../storage/cache',
        ];

        foreach ($writableDirs as $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                throw new Exception("Diretório sem permissão de escrita: {$dir}");
            }
        }

        // Validar .env existe (não validar conteúdo aqui pois pode ser vazio em dev)
        $envFile = __DIR__ . '/../../.env';
        if (!file_exists($envFile)) {
            // Warning apenas, não bloquear startup
            log_warning('.env file not found. System may not work correctly.', ['service' => 'StartupValidator']);
        }
    }

    /**
     * Valida configuração de banco de dados
     * @return bool True se DB está configurado corretamente
     */
    public static function validateDatabase(): bool
    {
        try {
            $db = \App\Database::getInstance();
            return true;
        } catch (Exception $e) {
            log_error('Database validation failed', ['service' => 'StartupValidator', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Retorna status do sistema
     * @return array Status de cada componente
     */
    public static function getSystemStatus(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_version_ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'database_ok' => self::validateDatabase(),
            'storage_writable' => is_writable(__DIR__ . '/../../storage'),
            'env_exists' => file_exists(__DIR__ . '/../../.env'),
        ];
    }
}
