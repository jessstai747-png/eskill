<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * Testes de integração para validar a estrutura geral da aplicação
 */
class ApplicationBootstrapTest extends TestCase
{
    // =============================
    // TESTES DE AUTOLOAD
    // =============================

    public function testAutoloadLoadsControllers(): void
    {
        $this->assertTrue(
            class_exists('App\\Controllers\\AuthController'),
            'AuthController deve ser carregado via autoload'
        );
    }

    public function testAutoloadLoadsServices(): void
    {
        $this->assertTrue(
            class_exists('App\\Services\\SecurityService'),
            'SecurityService deve ser carregado via autoload'
        );

        $this->assertTrue(
            class_exists('App\\Services\\SeoAnalyzerService'),
            'SeoAnalyzerService deve ser carregado via autoload'
        );
    }

    public function testAutoloadLoadsHelpers(): void
    {
        $this->assertTrue(
            class_exists('App\\Helpers\\SecurityHelper'),
            'SecurityHelper deve ser carregado via autoload'
        );

        $this->assertTrue(
            class_exists('App\\Helpers\\EnvValidator'),
            'EnvValidator deve ser carregado via autoload'
        );
    }

    public function testAutoloadLoadsMiddleware(): void
    {
        $this->assertTrue(
            class_exists('App\\Middleware\\CsrfMiddleware'),
            'CsrfMiddleware deve ser carregado via autoload'
        );

        $this->assertTrue(
            class_exists('App\\Middleware\\SecurityMiddleware'),
            'SecurityMiddleware deve ser carregado via autoload'
        );
    }

    // =============================
    // TESTES DE CONFIGURAÇÃO
    // =============================

    public function testAppConfigExists(): void
    {
        $configPath = __DIR__ . '/../../config/app.php';
        $this->assertFileExists($configPath);

        $config = require $configPath;

        $this->assertIsArray($config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('mercadolivre', $config);
    }

    public function testDatabaseConfigExists(): void
    {
        $configPath = __DIR__ . '/../../config/database.php';
        $this->assertFileExists($configPath);

        $config = require $configPath;

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('connections', $config);
        $this->assertArrayHasKey('mysql', $config['connections']);
    }

    public function testProductionConfigExists(): void
    {
        $configPath = __DIR__ . '/../../config/production.php';
        $this->assertFileExists($configPath);
    }

    // =============================
    // TESTES DE DIRETÓRIOS
    // =============================

    public function testStorageDirectoryExists(): void
    {
        $storagePath = __DIR__ . '/../../storage';
        $this->assertDirectoryExists($storagePath);
    }

    public function testLogsDirectoryExists(): void
    {
        $logsPath = __DIR__ . '/../../storage/logs';
        $this->assertDirectoryExists($logsPath);
    }

    public function testCacheDirectoryExists(): void
    {
        $cachePath = __DIR__ . '/../../storage/cache';
        $this->assertDirectoryExists($cachePath);
    }

    public function testPublicDirectoryExists(): void
    {
        $publicPath = __DIR__ . '/../../public';
        $this->assertDirectoryExists($publicPath);

        $this->assertFileExists($publicPath . '/index.php');
        $this->assertFileExists($publicPath . '/index.html');
    }

    // =============================
    // TESTES DE ARQUIVOS CRÍTICOS
    // =============================

    public function testComposerJsonValid(): void
    {
        $composerPath = __DIR__ . '/../../composer.json';
        $this->assertFileExists($composerPath);

        $content = file_get_contents($composerPath);
        $json = json_decode($content, true);

        $this->assertNotNull($json, 'composer.json deve ser JSON válido');
        $this->assertArrayHasKey('require', $json);
        $this->assertArrayHasKey('autoload', $json);
    }

    public function testEnvExampleExists(): void
    {
        $envExamplePath = __DIR__ . '/../../.env.example';
        $this->assertFileExists($envExamplePath, '.env.example deve existir como template');
    }

    // =============================
    // TESTES DE INTEGRAÇÃO DE CLASSES
    // =============================

    public function testRouterCanBeInstantiated(): void
    {
        $router = new \App\Router();

        $this->assertInstanceOf(\App\Router::class, $router);
    }

    public function testRouterCanRegisterRoutes(): void
    {
        $router = new \App\Router();

        // Não deve lançar exceção
        $router->get('/test', 'App\\Controllers\\AuthController', 'index');
        $router->post('/test', 'App\\Controllers\\AuthController', 'index');

        $this->assertTrue(true); // Se chegou aqui, passou
    }

    public function testSecurityServiceCanBeInstantiated(): void
    {
        $service = new \App\Services\SecurityService();

        $this->assertInstanceOf(\App\Services\SecurityService::class, $service);
    }

    public function testCacheServiceCanBeInstantiated(): void
    {
        $service = new \App\Services\CacheService();

        $this->assertInstanceOf(\App\Services\CacheService::class, $service);
    }

    // =============================
    // TESTES DE DEPENDÊNCIAS
    // =============================

    public function testGuzzleIsInstalled(): void
    {
        $this->assertTrue(
            class_exists('GuzzleHttp\\Client'),
            'Guzzle HTTP Client deve estar instalado'
        );
    }

    public function testDotenvIsInstalled(): void
    {
        $this->assertTrue(
            class_exists('Dotenv\\Dotenv'),
            'vlucas/phpdotenv deve estar instalado'
        );
    }

    public function testPhpMailerIsInstalled(): void
    {
        $this->assertTrue(
            class_exists('PHPMailer\\PHPMailer\\PHPMailer'),
            'PHPMailer deve estar instalado'
        );
    }
}
