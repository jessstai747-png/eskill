#!/usr/bin/env php
<?php
/**
 * Script de Validação de Hardenings de Segurança
 * Executa verificações sem depender do terminal sandbox
 */

define('ROOT_PATH', dirname(__DIR__));

class HardeningValidator
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║   Validação de Hardenings de Segurança - 2026-02-15      ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";

        $this->validatePHPSyntax();
        $this->validateSecurityFlags();
        $this->validateMethodReferences();
        $this->validateFileStructure();
        $this->validateConfiguration();

        $this->printResults();
    }

    private function validatePHPSyntax(): void
    {
        echo "📋 Validando Sintaxe PHP...\n";

        $files = [
            'app/Core/ExceptionHandler.php',
            'app/Middleware/SecurityMiddleware.php',
            'app/Middleware/RateLimitMiddleware.php',
            'app/Middleware/SecurityHeadersMiddleware.php',
            'app/Controllers/CloneAdvancedController.php',
            'app/Controllers/SettingsController.php',
            'tests/Unit/ExceptionHandlerTest.php',
        ];

        foreach ($files as $file) {
            $fullPath = ROOT_PATH . '/' . $file;
            if (!file_exists($fullPath)) {
                $this->fail("Arquivo não encontrado: $file");
                continue;
            }

            $output = [];
            $returnCode = 0;
            exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                $this->pass("Sintaxe OK: $file");
            } else {
                $this->fail("Erro de sintaxe em $file: " . implode("\n", $output));
            }
        }
        echo "\n";
    }

    private function validateSecurityFlags(): void
    {
        echo "🔒 Validando Flags de Segurança...\n";

        // 1. SecurityMiddleware - Rate Limit Duplicado
        $securityMw = file_get_contents(ROOT_PATH . '/app/Middleware/SecurityMiddleware.php');
        if (strpos($securityMw, 'SECURITY_MW_RATE_LIMIT_ENABLED') !== false) {
            $this->pass("Flag SECURITY_MW_RATE_LIMIT_ENABLED presente");
        } else {
            $this->fail("Flag SECURITY_MW_RATE_LIMIT_ENABLED ausente");
        }

        if (strpos($securityMw, "'rate_limit_enabled' => (\$securityMwRateLimitEnabled ?: 'false') === 'true'") !== false) {
            $this->pass("Rate limit desabilitado por padrão no SecurityMiddleware");
        } else {
            $this->fail("Rate limit não desabilitado corretamente");
        }

        // 2. SecurityHeadersMiddleware - Legacy Headers
        $headersContent = file_get_contents(ROOT_PATH . '/app/Middleware/SecurityHeadersMiddleware.php');
        if (strpos($headersContent, 'SECURITY_HEADERS_LEGACY_ENABLED') !== false) {
            $this->pass("Flag SECURITY_HEADERS_LEGACY_ENABLED presente");
        } else {
            $this->fail("Flag SECURITY_HEADERS_LEGACY_ENABLED ausente");
        }

        // 3. ExceptionHandler - wantsJson
        $exceptionHandler = file_get_contents(ROOT_PATH . '/app/Core/ExceptionHandler.php');
        if (strpos($exceptionHandler, 'private static function wantsJson(): bool') !== false) {
            $this->pass("Método wantsJson() implementado");
        } else {
            $this->fail("Método wantsJson() ausente");
        }

        if (strpos($exceptionHandler, "strpos(\$path, '/api/') === 0") !== false) {
            $this->pass("Detecção de API path implementada");
        } else {
            $this->fail("Detecção de API path ausente");
        }

        echo "\n";
    }

    private function validateMethodReferences(): void
    {
        echo "🔍 Validando Referências de Métodos...\n";

        // CloneAdvancedController deve usar updateSettings (não saveSettings)
        $cloneController = file_get_contents(ROOT_PATH . '/app/Controllers/CloneAdvancedController.php');
        if (strpos($cloneController, '$seo->updateSettings($data)') !== false) {
            $this->pass("CloneAdvancedController usa updateSettings() correto");
        } else {
            $this->fail("CloneAdvancedController não usa updateSettings()");
        }

        // SettingsController deve usar SessionHelper::getUserAccounts
        $settingsController = file_get_contents(ROOT_PATH . '/app/Controllers/SettingsController.php');
        if (strpos($settingsController, 'SessionHelper::getUserAccounts()') !== false) {
            $this->pass("SettingsController usa SessionHelper::getUserAccounts()");
        } else {
            $this->fail("SettingsController não usa SessionHelper correto");
        }

        echo "\n";
    }

    private function validateFileStructure(): void
    {
        echo "📁 Validando Estrutura de Arquivos...\n";

        $requiredFiles = [
            'install-sandbox-deps.sh' => 'Script de instalação de dependências',
            'docs/VALIDATION_GUIDE.md' => 'Guia de validação',
            'docs/SECURITY_AUDIT_REPORT.md' => 'Relatório de auditoria',
            'app/Core/ExceptionHandler.php' => 'Exception Handler',
            'app/Middleware/SecurityMiddleware.php' => 'Security Middleware',
            'app/Middleware/SecurityHeadersMiddleware.php' => 'Security Headers Middleware',
            'tests/Unit/ExceptionHandlerTest.php' => 'Testes do ExceptionHandler',
        ];

        foreach ($requiredFiles as $file => $description) {
            if (file_exists(ROOT_PATH . '/' . $file)) {
                $this->pass("$description existe");
            } else {
                $this->fail("$description ausente: $file");
            }
        }

        echo "\n";
    }

    private function validateConfiguration(): void
    {
        echo "⚙️  Validando Configuração...\n";

        // Verificar se .env existe
        if (file_exists(ROOT_PATH . '/.env')) {
            $this->pass("Arquivo .env existe");

            $env = file_get_contents(ROOT_PATH . '/.env');
            
            // Verificar APP_ENV
            if (preg_match('/APP_ENV\s*=\s*(\w+)/', $env, $matches)) {
                $appEnv = $matches[1];
                $this->pass("APP_ENV configurado: $appEnv");
            } else {
                $this->fail("APP_ENV não encontrado no .env");
            }
        } else {
            $this->fail("Arquivo .env não encontrado");
        }

        // Verificar public/index.php
        $index = file_get_contents(ROOT_PATH . '/public/index.php');
        if (strpos($index, 'ExceptionHandler::register()') !== false) {
            $this->pass("Exception handler registrado no entrypoint");
        } else {
            $this->fail("Exception handler não registrado");
        }

        if (strpos($index, 'RateLimitMiddleware') !== false) {
            $this->pass("RateLimitMiddleware em uso no entrypoint");
        } else {
            $this->fail("RateLimitMiddleware ausente no entrypoint");
        }

        echo "\n";
    }

    private function pass(string $message): void
    {
        $this->passed++;
        $this->results[] = ['status' => 'pass', 'message' => $message];
        echo "  ✅ $message\n";
    }

    private function fail(string $message): void
    {
        $this->failed++;
        $this->results[] = ['status' => 'fail', 'message' => $message];
        echo "  ❌ $message\n";
    }

    private function printResults(): void
    {
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║                    RESULTADO FINAL                        ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";

        echo "Total de Testes: $total\n";
        echo "✅ Passou: $this->passed\n";
        echo "❌ Falhou: $this->failed\n";
        echo "📊 Taxa de Sucesso: $percentage%\n\n";

        if ($this->failed === 0) {
            echo "🎉 TODOS OS HARDENINGS VALIDADOS COM SUCESSO!\n\n";
            echo "Próximos passos:\n";
            echo "1. Execute: composer test-unit\n";
            echo "2. Revise: docs/SECURITY_AUDIT_REPORT.md\n";
            echo "3. Deploy com confiança! ✨\n";
            exit(0);
        } else {
            echo "⚠️  ALGUNS TESTES FALHARAM\n\n";
            echo "Revise os erros acima e corrija antes do deploy.\n";
            exit(1);
        }
    }
}

// Executar validação
$validator = new HardeningValidator();
$validator->run();
