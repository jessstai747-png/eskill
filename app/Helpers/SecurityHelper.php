<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Services\SecurityService;

class SecurityHelper
{
    private static ?SecurityService $security = null;

    /**
     * Obtém instância do SecurityService
     */
    private static function getSecurity(): SecurityService
    {
        if (self::$security === null) {
            self::$security = new SecurityService();
        }

        return self::$security;
    }

    /**
     * Gera token CSRF para formulários
     */
    public static function csrfToken(): string
    {
        return self::getSecurity()->getCsrfToken();
    }

    /**
     * Gera campo hidden com token CSRF
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Sanitiza string para prevenir XSS
     */
    public static function e(string $string): string
    {
        return self::getSecurity()->sanitize($string);
    }
}

// ============================================================================
// Função global e() — Escape seguro para views (previne XSS)
// ============================================================================
if (!function_exists('e')) {
    /**
     * Escape HTML para prevenir XSS
     * 
     * @param string $value String a ser escapada
     * @return string String segura para output em HTML
     */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
