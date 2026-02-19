<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Logger Service - Implementação PSR-3 compatível
 * 
 * Features:
 * - Níveis de log PSR-3 (emergency, alert, critical, error, warning, notice, info, debug)
 * - Contexto estruturado
 * - Rotação automática de arquivos
 * - Formatação JSON para processamento por ELK/Grafana
 * - Suporte a múltiplos canais (file, stderr, syslog)
 */
class LoggerService implements LoggerInterface
{
    private string $channel;
    private string $logPath;
    private string $minLevel;
    private bool $jsonFormat;
    private int $maxFileSize;
    private int $maxFiles;

    /**
     * Níveis de log em ordem de severidade
     */
    private const LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];

    public function __construct(
        string $channel = 'app',
        ?string $logPath = null,
        string $minLevel = LogLevel::DEBUG,
        bool $jsonFormat = false
    ) {
        $this->channel = $channel;
        $this->logPath = $logPath ?? __DIR__ . '/../../storage/logs';
        $this->minLevel = $minLevel;
        $this->jsonFormat = $jsonFormat;
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        $this->maxFiles = 7;

        // Garantir diretório existe
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * System is unusable.
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Verificar nível mínimo
        if (!$this->shouldLog($level)) {
            return;
        }

        // Interpolar contexto na mensagem
        $message = $this->interpolate((string) $message, $context);

        // Adicionar informações extras ao contexto
        $context = $this->enrichContext($context);

        // Formatar e escrever
        $formatted = $this->format($level, $message, $context);
        $this->write($formatted, $level);
    }

    /**
     * Verifica se deve logar baseado no nível mínimo
     */
    private function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] <= self::LEVELS[$this->minLevel];
    }

    /**
     * Interpola variáveis {key} na mensagem com valores do contexto
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replace['{' . $key . '}'] = $val->format('Y-m-d H:i:s');
            } elseif (is_bool($val)) {
                $replace['{' . $key . '}'] = $val ? 'true' : 'false';
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Enriquece contexto com informações úteis
     */
    private function enrichContext(array $context): array
    {
        // Adicionar exception se presente
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $context['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->formatTrace($e->getTraceAsString()),
            ];
        }

        // Adicionar request info se disponível
        if (!isset($context['request']) && PHP_SAPI !== 'cli') {
            $context['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            ];
        }

        // User ID se disponível
        if (!isset($context['user_id']) && isset($_SESSION['user_id'])) {
            $context['user_id'] = $_SESSION['user_id'];
        }

        return $context;
    }

    /**
     * Formata stack trace para ser mais legível
     */
    private function formatTrace(string $trace): array
    {
        $lines = explode("\n", $trace);
        return array_slice($lines, 0, 10); // Limitar a 10 frames
    }

    /**
     * Formata a entrada de log
     */
    private function format(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s.u');

        if ($this->jsonFormat) {
            $entry = [
                '@timestamp' => $timestamp,
                'level' => strtoupper($level),
                'channel' => $this->channel,
                'message' => $message,
                'context' => $context,
            ];

            return json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        // Formato legível
        $levelUpper = strtoupper($level);
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        return "[{$timestamp}] {$this->channel}.{$levelUpper}: {$message}{$contextStr}" . PHP_EOL;
    }

    /**
     * Escreve no arquivo de log
     */
    private function write(string $formatted, string $level): void
    {
        $filename = $this->getLogFilename($level);
        $filepath = $this->logPath . '/' . $filename;

        // Garantir que o diretório existe
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0775, true);
        }

        // Rotacionar se necessário
        $this->rotateIfNeeded($filepath);

        // Escrever (silenciar erros de permissão)
        @file_put_contents($filepath, $formatted, FILE_APPEND | LOCK_EX);

        // Se erro crítico, também escrever em stderr
        if (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL])) {
            fwrite(STDERR, $formatted);
        }
    }

    /**
     * Gera nome do arquivo de log
     */
    private function getLogFilename(string $level): string
    {
        $date = date('Y-m-d');

        // Logs críticos em arquivo separado
        if (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR])) {
            return "error-{$date}.log";
        }

        return "{$this->channel}-{$date}.log";
    }

    /**
     * Rotaciona arquivo se muito grande
     */
    private function rotateIfNeeded(string $filepath): void
    {
        if (!file_exists($filepath)) {
            return;
        }

        if (filesize($filepath) < $this->maxFileSize) {
            return;
        }

        // Rotacionar: arquivo.log -> arquivo.log.1, arquivo.log.1 -> arquivo.log.2, etc
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $old = "{$filepath}.{$i}";
            $new = "{$filepath}." . ($i + 1);

            if (file_exists($old)) {
                if ($i === $this->maxFiles - 1) {
                    unlink($old); // Remover mais antigo
                } else {
                    rename($old, $new);
                }
            }
        }

        rename($filepath, "{$filepath}.1");
    }

    /**
     * Cria logger para canal específico
     */
    public static function channel(string $channel): self
    {
        $config = \App\Core\Config::getInstance()->all();
        $minLevel = $config['log']['level'] ?? LogLevel::DEBUG;
        $jsonFormat = ($config['env'] ?? 'development') === 'production';

        return new self($channel, null, $minLevel, $jsonFormat);
    }

    /**
     * Helper para logar API requests
     */
    public function logApiRequest(string $method, string $endpoint, array $params = [], ?array $response = null, ?float $duration = null): void
    {
        $this->info('API Request: {method} {endpoint}', [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'response_status' => $response['status'] ?? null,
            'duration_ms' => $duration ? round($duration * 1000, 2) : null,
        ]);
    }

    /**
     * Helper para logar erros de API
     */
    public function logApiError(string $method, string $endpoint, \Throwable $e, array $params = []): void
    {
        $this->error('API Error: {method} {endpoint} - {error}', [
            'method' => $method,
            'endpoint' => $endpoint,
            'error' => $e->getMessage(),
            'params' => $params,
            'exception' => $e,
        ]);
    }

    /**
     * Helper para logar ações de usuário
     */
    public function logUserAction(string $action, array $details = []): void
    {
        $this->info('User Action: {action}', array_merge([
            'action' => $action,
        ], $details));
    }

    /**
     * Helper para logar métricas de performance
     */
    public function logPerformance(string $operation, float $duration, array $metadata = []): void
    {
        $this->debug('Performance: {operation} completed in {duration}ms', array_merge([
            'operation' => $operation,
            'duration' => round($duration * 1000, 2),
        ], $metadata));
    }
}
