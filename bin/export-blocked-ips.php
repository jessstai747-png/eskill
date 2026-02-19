#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Nginx Rate Limiting Rules Generator
 * Gera regras para Nginx baseadas nos IPs bloqueados
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configuração
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_DATABASE'] ?? 'meli';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ Erro de conexão: {$e->getMessage()}\n";
    exit(1);
}

$outputFormat = $argv[1] ?? 'nginx';

echo "🔧 Gerando regras de bloqueio para $outputFormat...\n";

// Buscar IPs bloqueados (ativos e permanentes)
$stmt = $db->query("
    SELECT ip_address, reason, is_permanent, expires_at
    FROM auth_blocked_ips
    WHERE is_permanent = 1 OR expires_at > NOW()
    ORDER BY is_permanent DESC, blocked_at DESC
");

$blockedIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($blockedIPs)) {
    echo "✅ Nenhum IP bloqueado no momento.\n";
    exit(0);
}

echo "📊 Encontrados " . count($blockedIPs) . " IPs bloqueados\n\n";

// Gerar configuração baseada no formato
if ($outputFormat === 'nginx') {
    generateNginxConfig($blockedIPs);
} elseif ($outputFormat === 'apache') {
    generateApacheConfig($blockedIPs);
} elseif ($outputFormat === 'json') {
    generateJSON($blockedIPs);
} else {
    echo "❌ Formato inválido. Use: nginx, apache ou json\n";
    exit(1);
}

function generateNginxConfig(array $ips): void
{
    $output = "# Auth Monitor - Blocked IPs Configuration\n";
    $output .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "# Total IPs: " . count($ips) . "\n\n";
    
    $output .= "# Adicione estas linhas no bloco http {} do nginx.conf\n";
    $output .= "# ou em /etc/nginx/conf.d/blocked-ips.conf\n\n";
    
    $output .= "geo \$blocked_ip {\n";
    $output .= "    default 0;\n\n";
    
    foreach ($ips as $ip) {
        $status = $ip['is_permanent'] ? 'PERMANENT' : 'TEMP until ' . $ip['expires_at'];
        $comment = preg_replace('/[^\w\s-]/', '', $ip['reason']);
        $output .= "    {$ip['ip_address']} 1; # $status - $comment\n";
    }
    
    $output .= "}\n\n";
    
    $output .= "# No bloco server {}, adicione:\n";
    $output .= "# if (\$blocked_ip) {\n";
    $output .= "#     return 403 \"Access Denied - IP Blocked\";\n";
    $output .= "# }\n\n";
    
    $output .= "# Ou use limit_req_zone para rate limiting:\n";
    $output .= "limit_req_zone \$binary_remote_addr zone=auth_limit:10m rate=5r/m;\n\n";
    $output .= "# No location /login ou /auth:\n";
    $output .= "# limit_req zone=auth_limit burst=3 nodelay;\n";
    
    $file = __DIR__ . '/../storage/nginx-blocked-ips.conf';
    file_put_contents($file, $output);
    
    echo "✅ Configuração Nginx gerada em: $file\n\n";
    echo $output;
}

function generateApacheConfig(array $ips): void
{
    $output = "# Auth Monitor - Blocked IPs Configuration for Apache\n";
    $output .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "# Total IPs: " . count($ips) . "\n\n";
    
    $output .= "# Adicione estas linhas em .htaccess ou httpd.conf\n\n";
    
    $output .= "<IfModule mod_authz_core.c>\n";
    $output .= "    <RequireAll>\n";
    $output .= "        Require all granted\n";
    
    foreach ($ips as $ip) {
        $status = $ip['is_permanent'] ? 'PERMANENT' : 'TEMP';
        $comment = preg_replace('/[^\w\s-]/', '', $ip['reason']);
        $output .= "        Require not ip {$ip['ip_address']} # $status - $comment\n";
    }
    
    $output .= "    </RequireAll>\n";
    $output .= "</IfModule>\n\n";
    
    $output .= "# Fallback para Apache 2.2\n";
    $output .= "<IfModule !mod_authz_core.c>\n";
    $output .= "    Order Allow,Deny\n";
    $output .= "    Allow from all\n";
    
    foreach ($ips as $ip) {
        $output .= "    Deny from {$ip['ip_address']}\n";
    }
    
    $output .= "</IfModule>\n";
    
    $file = __DIR__ . '/../storage/apache-blocked-ips.conf';
    file_put_contents($file, $output);
    
    echo "✅ Configuração Apache gerada em: $file\n\n";
    echo $output;
}

function generateJSON(array $ips): void
{
    $output = [
        'generated_at' => date('c'),
        'total_ips' => count($ips),
        'blocked_ips' => array_map(function($ip) {
            return [
                'ip_address' => $ip['ip_address'],
                'reason' => $ip['reason'],
                'is_permanent' => (bool)$ip['is_permanent'],
                'expires_at' => $ip['expires_at']
            ];
        }, $ips)
    ];
    
    $json = json_encode($output, JSON_PRETTY_PRINT);
    
    $file = __DIR__ . '/../storage/blocked-ips.json';
    file_put_contents($file, $json);
    
    echo "✅ JSON gerado em: $file\n\n";
    echo $json . "\n";
}

echo "\n";
echo "📝 Próximos Passos:\n";
echo "   1. Revisar o arquivo gerado\n";
echo "   2. Copiar para a configuração do servidor web\n";
echo "   3. Recarregar a configuração: sudo nginx -s reload ou sudo systemctl reload apache2\n";
echo "   4. Testar o bloqueio acessando de um IP bloqueado\n";
echo "\n";
echo "💡 Dica: Adicione este script ao cron para atualizar automaticamente:\n";
echo "   */30 * * * * cd " . dirname(__DIR__) . " && php bin/export-blocked-ips.php nginx\n";
echo "\n";
