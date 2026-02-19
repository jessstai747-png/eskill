#!/usr/bin/env php
<?php
/**
 * Manual Account Health Dashboard Test
 * Este script simula o acesso e execução de testes na página account-health
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Account Health Dashboard Manual Test ===\n\n";

// Configurar ambiente
$_SERVER['HTTP_HOST'] = 'localhost:3001';
$_SERVER['REQUEST_URI'] = '/dashboard/account-health';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = '';
$_ENV['APP_ENV'] = 'development';
$_ENV['FORCE_HTTPS'] = 'false';

echo "1. Iniciando teste de acesso ao dashboard...\n";

// Incluir autoloader e configuração
require_once __DIR__ . '/../autoload.php';

echo "2. Autoloader carregado com sucesso\n";

try {
    // Verificar se o controlador existe
    $controllerFile = __DIR__ . '/../app/Controllers/AccountHealthController.php';
    if (file_exists($controllerFile)) {
        echo "3. Controlador AccountHealthController encontrado\n";
        
        require_once $controllerFile;
        
        if (class_exists('App\Controllers\AccountHealthController')) {
            echo "4. Classe AccountHealthController carregada\n";
            
            // Tentar instanciar o controlador
            $controller = new App\Controllers\AccountHealthController();
            echo "5. Controlador instanciado com sucesso\n";
            
            // Verificar métodos disponíveis
            $methods = get_class_methods($controller);
            echo "6. Métodos disponíveis no controlador:\n";
            foreach ($methods as $method) {
                echo "   - $method\n";
            }
            
            // Tentar chamar o método index (caso exista)
            if (method_exists($controller, 'index')) {
                echo "\n7. Executando método index()...\n";
                ob_start();
                $controller->index();
                $output = ob_get_clean();
                
                echo "8. Saída capturada (" . strlen($output) . " bytes)\n";
                
                // Verificar se a saída contém HTML
                if (strpos($output, '<html') !== false || strpos($output, '<!DOCTYPE') !== false) {
                    echo "9. ✓ Página HTML válida detectada\n";
                    
                    // Extrair title
                    if (preg_match('/<title>(.*?)<\/title>/i', $output, $matches)) {
                        echo "   Título: " . $matches[1] . "\n";
                    }
                    
                    // Verificar elementos importantes
                    $checks = [
                        'form' => '<form',
                        'button' => '<button',
                        'table' => '<table',
                        'script' => '<script',
                        'input' => '<input',
                        'select' => '<select',
                        'health' => 'health',
                        'account' => 'account'
                    ];
                    
                    echo "\n10. Verificando elementos na página:\n";
                    foreach ($checks as $name => $pattern) {
                        $count = substr_count(strtolower($output), strtolower($pattern));
                        echo "    - $name: $count ocorrências\n";
                    }
                    
                    // Salvar output para inspeção
                    $outputFile = __DIR__ . '/../test-results/account-health-manual-output.html';
                    @mkdir(dirname($outputFile), 0755, true);
                    file_put_contents($outputFile, $output);
                    echo "\n11. Output salvo em: $outputFile\n";
                    
                } else {
                    echo "9. ✗ Output não parece ser HTML válido\n";
                    echo "   Primeiros 500 caracteres:\n";
                    echo "   " . substr($output, 0, 500) . "\n";
                }
            } else {
                echo "\n7. Método index() não encontrado. Tentando outros métodos...\n";
                
                // Verificar se há método show ou dashboard
                $methodsToTry = ['show', 'dashboard', 'health', 'accountHealth'];
                foreach ($methodsToTry as $methodName) {
                    if (method_exists($controller, $methodName)) {
                        echo "   Encontrado método: $methodName\n";
                        try {
                            ob_start();
                            $controller->$methodName();
                            $output = ob_get_clean();
                            echo "   ✓ Método executado com sucesso (" . strlen($output) . " bytes)\n";
                        } catch (Exception $e) {
                            echo "   ✗ Erro ao executar método: " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
            
        } else {
            echo "4. ✗ Classe AccountHealthController não encontrada\n";
        }
    } else {
        echo "3. ✗ Arquivo do controlador não encontrado: $controllerFile\n";
        
        // Buscar por arquivos relacionados
        echo "\nBuscando arquivos relacionados...\n";
        $files = glob(__DIR__ . '/../app/Controllers/*Health*.php');
        if (!empty($files)) {
            echo "Arquivos encontrados:\n";
            foreach ($files as $file) {
                echo "  - " . basename($file) . "\n";
            }
        } else {
            echo "Nenhum arquivo relacionado a 'Health' encontrado em Controllers/\n";
        }
    }
    
    // Verificar rotas
    echo "\n12. Verificando sistema de rotas...\n";
    $routerFile = __DIR__ . '/../app/Router.php';
    if (file_exists($routerFile)) {
        $routerContent = file_get_contents($routerFile);
        
        if (strpos($routerContent, 'account-health') !== false) {
            echo "    ✓ Rota 'account-health' encontrada no Router.php\n";
            
            // Extrair contexto da rota
            preg_match('/.*account-health.*/i', $routerContent, $matches);
            if (!empty($matches)) {
                echo "    Definição: " . trim($matches[0]) . "\n";
            }
        } else {
            echo "    ✗ Rota 'account-health' NÃO encontrada no Router.php\n";
            echo "    Buscando rotas similares...\n";
            
            // Buscar rotas que contenham 'dashboard' ou 'health'
            preg_match_all('/\'\/dashboard\/[^\']+\'/i', $routerContent, $matches);
            if (!empty($matches[0])) {
                echo "    Rotas dashboard encontradas:\n";
                foreach (array_unique($matches[0]) as $route) {
                    echo "      " . $route . "\n";
                }
            }
        }
    }
    
    echo "\n=== Teste Finalizado ===\n";
    
} catch (Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
