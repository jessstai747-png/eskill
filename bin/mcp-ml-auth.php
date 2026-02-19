#!/usr/bin/env php
<?php
/**
 * Gera URL de autorização OAuth do Mercado Livre e opcionalmente abre no navegador.
 * 
 * Após autorizar, o callback salva o token automaticamente no banco.
 * O MCP Server do ML vai usar esse token automaticamente.
 * 
 * Uso:
 *   php bin/mcp-ml-auth.php              # Mostra URL para copiar
 *   php bin/mcp-ml-auth.php --open       # Abre no navegador (se disponível)
 *   php bin/mcp-ml-auth.php --status     # Mostra status das contas e tokens
 *   php bin/mcp-ml-auth.php --test       # Testa o MCP Server localmente
 *   php bin/mcp-ml-auth.php --refresh    # Tenta refresh de todas as contas
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';
require $basePath . '/autoload.php';

$action = $argv[1] ?? '--url';

// Cores para terminal
$green  = "\033[32m";
$red    = "\033[31m";
$yellow = "\033[33m";
$cyan   = "\033[36m";
$bold   = "\033[1m";
$reset  = "\033[0m";

echo "\n{$bold}{$cyan}╔══════════════════════════════════════════════════════╗{$reset}\n";
echo "{$bold}{$cyan}║       MCP Mercado Livre — Gerenciador de Token       ║{$reset}\n";
echo "{$bold}{$cyan}╚══════════════════════════════════════════════════════╝{$reset}\n\n";

$db = \App\Database::getInstance();

switch ($action) {
    case '--status':
        showStatus($db, $basePath);
        break;
    case '--refresh':
        refreshTokens($db);
        break;
    case '--test':
        testMcpConnection($basePath);
        break;
    case '--open':
        $url = getAuthUrl();
        echo "{$green}Abrindo navegador...{$reset}\n\n";
        echo "URL: {$cyan}{$url}{$reset}\n\n";
        
        // Tentar abrir no navegador
        if (PHP_OS_FAMILY === 'Linux') {
            exec("xdg-open " . escapeshellarg($url) . " 2>/dev/null &");
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            exec("open " . escapeshellarg($url));
        } else {
            exec("start " . escapeshellarg($url));
        }
        
        echo "Após autorizar, o token será salvo automaticamente.\n";
        echo "O MCP Server usará o token da conta ativa mais recente.\n\n";
        break;
    case '--url':
    default:
        $url = getAuthUrl();
        echo "{$bold}1. Copie e acesse esta URL no navegador:{$reset}\n\n";
        echo "   {$cyan}{$url}{$reset}\n\n";
        echo "{$bold}2. Autorize a aplicação no Mercado Livre{$reset}\n\n";
        echo "{$bold}3. Após autorizar, o token será salvo automaticamente no banco.{$reset}\n";
        echo "   O MCP Server do ML vai usar esse token para funcionar.\n\n";
        echo "{$yellow}Dica:{$reset} Use {$cyan}php bin/mcp-ml-auth.php --status{$reset} para verificar o status.\n\n";
        break;
}

// ────────────────────────────────────────────────────────────────
// Funções
// ────────────────────────────────────────────────────────────────

function getAuthUrl(): string
{
    $clientId = $_ENV['ML_CLIENT_ID'] ?? $_ENV['ML_APP_ID'] ?? '';
    $redirectUri = $_ENV['ML_REDIRECT_URI'] ?? 'https://eskill.com.br/auth/callback';
    
    if (empty($clientId)) {
        fwrite(STDERR, "ERRO: ML_CLIENT_ID não configurado no .env\n");
        exit(2);
    }
    
    $state = bin2hex(random_bytes(16));
    
    return sprintf(
        'https://auth.mercadolivre.com.br/authorization?response_type=code&client_id=%s&redirect_uri=%s&state=%s',
        urlencode($clientId),
        urlencode($redirectUri),
        $state
    );
}

function showStatus(\PDO $db, string $basePath): void
{
    global $green, $red, $yellow, $cyan, $bold, $reset;
    
    $rows = $db->query("
        SELECT id, ml_user_id, nickname, status, token_expires_at, 
               refresh_failure_count, last_refresh_error, last_refresh_at,
               access_token IS NOT NULL AND access_token != '' as has_token,
               tokens_encrypted
        FROM ml_accounts 
        ORDER BY token_expires_at DESC
    ")->fetchAll();
    
    if (empty($rows)) {
        echo "{$red}Nenhuma conta ML encontrada.{$reset}\n";
        echo "Use {$cyan}php bin/mcp-ml-auth.php{$reset} para conectar uma conta.\n\n";
        return;
    }
    
    echo "{$bold}Contas Mercado Livre:{$reset}\n\n";
    printf("  %-5s %-25s %-10s %-22s %-8s %s\n", 
        "ID", "Nickname", "Status", "Token Expira", "Erros", "Observação");
    echo str_repeat("─", 95) . "\n";
    
    foreach ($rows as $r) {
        $statusColor = match($r['status']) {
            'active' => $green,
            'expired' => $yellow,
            default => $red,
        };
        
        $expiry = $r['token_expires_at'] ?? 'N/A';
        $isExpired = $expiry !== 'N/A' && strtotime($expiry) < time();
        $expiryColor = $isExpired ? $red : $green;
        $hasToken = $r['has_token'] ? '✓ Token' : '✗ Sem token';
        $tokenColor = $r['has_token'] ? $green : $red;
        
        $note = '';
        if ($r['refresh_failure_count'] > 0 && $r['last_refresh_error']) {
            $note = substr($r['last_refresh_error'], 0, 30) . '...';
        }
        
        printf("  %-5s %-25s %s%-10s%s %s%-22s%s %-8s %s%s%s\n",
            '#' . $r['id'],
            substr($r['nickname'], 0, 25),
            $statusColor, $r['status'], $reset,
            $expiryColor, $expiry, $reset,
            $r['refresh_failure_count'],
            $tokenColor, $hasToken, $reset
        );
        
        if ($note) {
            echo "        {$red}└─ {$note}{$reset}\n";
        }
    }
    
    echo "\n";
    
    // Mostrar se o MCP conseguiria obter token
    echo "{$bold}Status do MCP:{$reset}\n";
    $tokenOutput = [];
    $tokenExitCode = 0;
    exec("php " . escapeshellarg($basePath . '/bin/mcp-ml-token.php') . " --format=raw 2>&1", $tokenOutput, $tokenExitCode);
    
    if ($tokenExitCode === 0) {
        echo "  {$green}✓ Token disponível para o MCP Server{$reset}\n";
    } else {
        echo "  {$red}✗ Nenhum token válido para o MCP Server{$reset}\n";
        echo "  {$yellow}  Reconecte via: php bin/mcp-ml-auth.php --open{$reset}\n";
    }
    
    echo "\n";
}

function refreshTokens(\PDO $db): void
{
    global $green, $red, $yellow, $cyan, $bold, $reset;
    
    $auth = new \App\Services\MercadoLivreAuthService();
    
    $accounts = $db->query("
        SELECT id, nickname, status 
        FROM ml_accounts 
        WHERE access_token IS NOT NULL AND access_token != '' AND refresh_token IS NOT NULL AND refresh_token != ''
        ORDER BY token_expires_at DESC
    ")->fetchAll();
    
    if (empty($accounts)) {
        echo "{$red}Nenhuma conta com tokens para refresh.{$reset}\n\n";
        return;
    }
    
    foreach ($accounts as $acc) {
        echo "Refreshing #{$acc['id']} ({$acc['nickname']})... ";
        
        try {
            $result = $auth->refreshToken((int)$acc['id']);
            if ($result) {
                echo "{$green}✓ Sucesso!{$reset}\n";
            } else {
                echo "{$red}✗ Falhou{$reset}\n";
            }
        } catch (\Throwable $e) {
            echo "{$red}✗ Erro: {$e->getMessage()}{$reset}\n";
        }
    }
    
    echo "\n";
}

function testMcpConnection(string $basePath): void
{
    global $green, $red, $yellow, $cyan, $bold, $reset;
    
    echo "{$bold}Testando componentes do MCP Mercado Livre...{$reset}\n\n";
    
    // 1. Testar extração de token
    echo "  1. Extração de token do banco... ";
    $output = [];
    $exitCode = 0;
    exec("php " . escapeshellarg($basePath . '/bin/mcp-ml-token.php') . " --format=bearer 2>/dev/null", $output, $exitCode);
    
    if ($exitCode === 0 && !empty($output[0])) {
        $token = $output[0];
        echo "{$green}✓{$reset} (" . strlen($token) . " chars)\n";
    } else {
        echo "{$red}✗ Nenhum token disponível{$reset}\n";
        echo "\n  {$yellow}Precisa conectar uma conta via OAuth primeiro.{$reset}\n";
        echo "  Use: {$cyan}php bin/mcp-ml-auth.php --open{$reset}\n\n";
        return;
    }
    
    // 2. Testar validade do token contra API do ML
    echo "  2. Validação do token na API ML... ";
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code === 200) {
        $data = json_decode($resp, true);
        echo "{$green}✓{$reset} Conectado como: {$cyan}{$data['nickname']}{$reset} (ID: {$data['id']})\n";
    } else {
        echo "{$red}✗ HTTP {$code}{$reset} — Token expirado/inválido\n";
        echo "  {$yellow}Reconecte via: php bin/mcp-ml-auth.php --open{$reset}\n\n";
        return;
    }
    
    // 3. Testar se npx e mcp-remote estão disponíveis
    echo "  3. npx / mcp-remote... ";
    $npxOutput = [];
    exec("which npx 2>/dev/null", $npxOutput, $npxExit);
    if ($npxExit === 0) {
        echo "{$green}✓{$reset} npx encontrado\n";
    } else {
        echo "{$red}✗ npx não encontrado — instale Node.js{$reset}\n";
        return;
    }
    
    // 4. Testar conexão com MCP endpoint
    echo "  4. Endpoint MCP ML... ";
    $ch = curl_init('https://mcp.mercadolibre.com/mcp');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code >= 200 && $code < 500) {
        echo "{$green}✓{$reset} Endpoint acessível (HTTP {$code})\n";
    } else {
        echo "{$yellow}? HTTP {$code} — pode estar OK (MCP usa SSE/WebSocket){$reset}\n";
    }
    
    echo "\n{$bold}{$green}MCP Server do Mercado Livre está pronto!{$reset}\n";
    echo "Recarregue o VS Code (Ctrl+Shift+P > Reload Window) para ativar.\n\n";
}
