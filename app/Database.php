<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';
            $connection = $config['connections']['mysql'];
            
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $connection['host'],
                    $connection['port'],
                    $connection['database'],
                    $connection['charset']
                );
                
                self::$instance = new PDO(
                    $dsn,
                    $connection['username'],
                    $connection['password'],
                    $connection['options']
                );
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new \Exception("Erro ao conectar ao banco de dados");
            }
        } else {
            // In testing environment, fail fast if an existing PDO instance is not MySQL.
            $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? null;
            if ($appEnv === 'testing') {
                try {
                    $driver = self::$instance->getAttribute(PDO::ATTR_DRIVER_NAME);
                    if ($driver !== 'mysql') {
                        throw new \Exception("Detected PDO driver '{$driver}' in testing environment — tests must use MySQL.");
                    }
                } catch (PDOException $e) {
                    throw new \Exception("Could not verify PDO driver in testing environment: " . $e->getMessage());
                }
            }
        }
        
        return self::$instance;
    }
    
    public static function disconnect(): void
    {
        self::$instance = null;
    }
}

