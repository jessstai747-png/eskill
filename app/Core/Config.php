<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Config Singleton - carrega config/app.php uma única vez e fornece acesso global.
 * 
 * Uso:
 *   $config = \App\Core\Config::getInstance();
 *   $mlConfig = $config->get('mercadolivre');
 *   $appKey = $config->get('app_key');
 *   $debug = $config->get('debug', false);
 */
class Config
{
    private static ?Config $instance = null;
    private array $data = [];

    private function __construct()
    {
        $configPath = dirname(__DIR__, 2) . '/config/app.php';
        if (file_exists($configPath)) {
            $this->data = require $configPath;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obter valor de configuração com suporte a dot notation.
     * Ex: $config->get('mercadolivre.client_id')
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        // Suporte a dot notation: 'mercadolivre.client_id'
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Retornar toda a configuração.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Verificar se uma chave existe.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Impedir clonagem.
     */
    private function __clone() {}

    /**
     * Impedir desserialização.
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
