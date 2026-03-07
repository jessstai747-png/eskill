<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para validação de variáveis de ambiente
 * Verifica se todas as variáveis obrigatórias estão configuradas
 */
class EnvValidator
{
    /**
     * Variáveis obrigatórias em qualquer ambiente
     */
    private const REQUIRED_ALWAYS = [
        'APP_KEY' => 'Chave de criptografia da aplicação (mínimo 32 caracteres)',
        'DB_HOST' => 'Host do banco de dados',
        'DB_DATABASE' => 'Nome do banco de dados',
        'DB_USERNAME' => 'Usuário do banco de dados',
        'DB_PASSWORD' => 'Senha do banco de dados',
    ];

    /**
     * Variáveis obrigatórias apenas em produção
     */
    private const REQUIRED_PRODUCTION = [
        'APP_URL' => 'URL da aplicação (ex: https://seusite.com)',
        'ML_APP_ID' => 'ID do App no Mercado Livre',
        'ML_CLIENT_SECRET' => 'Client Secret do Mercado Livre',
        'ML_REDIRECT_URI' => 'URL de callback do OAuth',
    ];

    /**
     * Variáveis recomendadas (warning, não crítico)
     */
    private const RECOMMENDED = [
        'EMAIL_ENABLED' => 'Habilitar envio de emails',
        'TELEGRAM_ENABLED' => 'Habilitar notificações Telegram',
    ];

    private array $errors = [];
    private array $warnings = [];

    /**
     * Valida todas as variáveis de ambiente
     * 
     * @param bool $isProduction Se está em ambiente de produção
     * @return bool True se todas as variáveis obrigatórias estão presentes
     */
    public function validate(bool $isProduction = false): bool
    {
        $this->errors = [];
        $this->warnings = [];

        // Verificar variáveis sempre obrigatórias
        foreach (self::REQUIRED_ALWAYS as $var => $description) {
            if (!$this->hasValidValue($var)) {
                $this->errors[] = [
                    'variable' => $var,
                    'description' => $description,
                    'type' => 'required',
                ];
            }
        }

        // Verificar APP_KEY especificamente
        $appKey = $_ENV['APP_KEY'] ?? '';
        if ($appKey && strlen($appKey) < 32) {
            $this->errors[] = [
                'variable' => 'APP_KEY',
                'description' => 'APP_KEY deve ter no mínimo 32 caracteres',
                'type' => 'invalid',
            ];
        }

        // Verificar variáveis de produção
        if ($isProduction) {
            foreach (self::REQUIRED_PRODUCTION as $var => $description) {
                if (!$this->hasValidValue($var)) {
                    $this->errors[] = [
                        'variable' => $var,
                        'description' => $description,
                        'type' => 'required_production',
                    ];
                }
            }

            // Verificar debug desligado em produção
            $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($debug) {
                $this->warnings[] = [
                    'variable' => 'APP_DEBUG',
                    'description' => 'APP_DEBUG deve ser false em produção',
                    'type' => 'security',
                ];
            }
        }

        // Verificar variáveis recomendadas
        foreach (self::RECOMMENDED as $var => $description) {
            if (!$this->hasValidValue($var)) {
                $this->warnings[] = [
                    'variable' => $var,
                    'description' => $description,
                    'type' => 'recommended',
                ];
            }
        }

        // Validar formato de URLs
        $this->validateUrls();

        return empty($this->errors);
    }

    /**
     * Verifica se uma variável tem valor válido
     */
    private function hasValidValue(string $var): bool
    {
        $value = $_ENV[$var] ?? getenv($var) ?: null;
        return $value !== null && $value !== '' && $value !== 'null';
    }

    /**
     * Valida formato de URLs
     */
    private function validateUrls(): void
    {
        $urlVars = ['APP_URL', 'ML_REDIRECT_URI'];

        foreach ($urlVars as $var) {
            $value = $_ENV[$var] ?? '';
            if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                $this->errors[] = [
                    'variable' => $var,
                    'description' => "Formato de URL inválido: {$value}",
                    'type' => 'invalid_format',
                ];
            }
        }
    }

    /**
     * Retorna erros encontrados
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retorna warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Gera página de erro HTML
     */
    public function renderErrorPage(): string
    {
        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro de Configuração</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 700px;
            width: 100%;
        }
        h1 { color: #dc3545; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .error-list { margin-bottom: 30px; }
        .error-item {
            background: #fff5f5;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }
        .error-item code {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .error-item p { margin-top: 8px; color: #666; font-size: 14px; }
        .warning-item {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }
        .warning-item code {
            background: #ffc107;
            color: #333;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .solution {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .solution h3 { color: #2e7d32; margin-bottom: 10px; }
        .solution pre {
            background: #333;
            color: #4caf50;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Erro de Configuração</h1>
        <p class="subtitle">O arquivo .env está incompleto ou possui valores inválidos.</p>
        
        <div class="error-list">
            <h2 style="color:#dc3545;margin-bottom:15px;">Erros Críticos</h2>';

        foreach ($this->errors as $error) {
            $html .= "
            <div class=\"error-item\">
                <code>{$error['variable']}</code>
                <p>{$error['description']}</p>
            </div>";
        }

        if (!empty($this->warnings)) {
            $html .= '<h2 style="color:#ffc107;margin:25px 0 15px;">Avisos</h2>';
            foreach ($this->warnings as $warning) {
                $html .= "
            <div class=\"warning-item\">
                <code>{$warning['variable']}</code>
                <p>{$warning['description']}</p>
            </div>";
            }
        }

        $html .= '
        </div>
        
        <div class="solution">
            <h3>🔧 Como resolver</h3>
            <p style="margin-bottom:15px;">Edite o arquivo <code>.env</code> na raiz do projeto:</p>
            <pre># Exemplo de configuração mínima
APP_KEY=' . bin2hex(random_bytes(16)) . '
APP_ENV=development
APP_DEBUG=true

DB_HOST=localhost
DB_DATABASE=mercadolivre_manager
DB_USERNAME=root
DB_PASSWORD=sua_senha

ML_APP_ID=seu_app_id
ML_CLIENT_SECRET=seu_client_secret
ML_REDIRECT_URI=http://localhost/callback</pre>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Valida e para execução se houver erros
     * 
     * @param bool $isProduction Se está em produção
     * @throws void Exibe página de erro e para execução
     */
    public static function validateOrDie(bool $isProduction = false): void
    {
        $validator = new self();

        if (!$validator->validate($isProduction)) {
            http_response_code(500);
            echo $validator->renderErrorPage();
            exit;
        }

        // Logar warnings em produção
        if ($isProduction && !empty($validator->getWarnings())) {
            foreach ($validator->getWarnings() as $warning) {
                log_warning('Variável de ambiente com problema', [
                    'variable' => $warning['variable'],
                    'description' => $warning['description'],
                ]);
            }
        }
    }
}
