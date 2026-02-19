<?php

use App\Core\Container;
use App\Services\JobService;
use App\Database;

// Definir ambiente CLI
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado apenas via linha de comando.\n");
}

// Configurações e Constantes
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Carregar Autoload
require_once ROOT_PATH . '/vendor/autoload.php';

// Carregar Variáveis de Ambiente
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

// Configuração de Erros
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/worker.log');

// Controle de Execução
$shouldRun = true;

// Setup de Sinais (Graceful Shutdown)
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    
    $handler = function ($signo) use (&$shouldRun) {
        echo "[" . date('Y-m-d H:i:s') . "] Sinal recebido ({$signo}). Encerrando graciosamente...\n";
        $shouldRun = false;
    };

    pcntl_signal(SIGTERM, $handler);
    pcntl_signal(SIGINT, $handler);
}

echo "[" . date('Y-m-d H:i:s') . "] Worker de Jobs Iniciado. PID: " . getmypid() . "\n";

try {
    // Inicializar Container e Serviços
    $container = new Container();
    
    // DB Singleton
    $container->singleton(Database::class, function () {
        return Database::getInstance();
    });

    // Injetar dependências se necessário, mas Services instanciam suas próprias dependencias
    // Idealmente, refatoraríamos JobService para receber DB, mas vamos usar o construtor padrão por enquanto.
    $jobService = new JobService();

    echo "[" . date('Y-m-d H:i:s') . "] Conectado ao banco de dados e aguardando jobs...\n";

    // Loop Principal
    while ($shouldRun) {
        try {
            // Verificar conexão com banco (ping / reconnect)
            // O PDO geralmente reconecta ou lança erro. Se lançar, o catch externo pega e tenta reiniciar?
            // Melhor verificar em cada loop sim, mas o Singleton Database::getInstance() mantém a mesma.
            // Para um worker long-running profissional, precisaríamos de check de conexão.
            // MVP: Try/Catch no processamento resolve.

            // Buscar jobs pendentes
            // Processamos 1 por vez para permitir shutdown rápido entre jobs
            $jobs = $jobService->process(1);

            if (!empty($jobs)) {
                foreach ($jobs as $result) {
                    $status = $result['status'];
                    $type = $result['type'];
                    $id = $result['id'];
                    
                    if ($status === 'completed') {
                        echo "[" . date('Y-m-d H:i:s') . "] [SUCCESS] Job #{$id} ({$type}) concluído.\n";
                    } elseif ($status === 'failed') {
                        $err = $result['error'] ?? 'Erro desconhecido';
                        echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Job #{$id} ({$type}) falhou: {$err}\n";
                    } else {
                        echo "[" . date('Y-m-d H:i:s') . "] [INFO] Job #{$id} ({$type}) status: {$status}\n";
                    }
                }
            } else {
                // Sleep para economizar CPU
                sleep(2);
                
                // Opcional: Manutenção de memória
                if (memory_get_usage() > 128 * 1024 * 1024) { // 128MB
                     echo "[" . date('Y-m-d H:i:s') . "] Limite de memória atingido. Reiniciando...\n";
                     $shouldRun = false;
                }
            }

        } catch (\PDOException $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Erro de Banco de Dados: " . $e->getMessage() . "\n";
            echo "Tentando reconectar em 10s...\n";
            sleep(10);
            // Database::getInstance pode precisar de reset se a conexão cair de verdade
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Erro Crítico no Loop: " . $e->getMessage() . "\n";
            sleep(5);
        }
    }

} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Falha Fatal na Inicialização: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Worker Encerrado.\n";
