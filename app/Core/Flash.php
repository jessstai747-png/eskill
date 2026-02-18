<?php

declare(strict_types=1);

namespace App\Core;

class Flash
{
    const KEY = 'flash_messages';

    /**
     * Add a success message
     */
    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    /**
     * Add an error message
     */
    public static function error(string $message): void
    {
        self::add('danger', $message);
    }

    /**
     * Add a warning message
     */
    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    /**
     * Add an info message
     */
    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /**
     * Internal add method
     */
    private static function add(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }

        $_SESSION[self::KEY][] = [
            'type' => $type,
            'message' => $message
        ];
    }

    /**
     * Retrieve and clear messages
     */
    public static function get(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return $messages;
    }
}
