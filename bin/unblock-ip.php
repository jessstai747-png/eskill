#!/usr/bin/env php
<?php
/**
 * Script para desbloquear IP
 * 
 * Uso: php unblock-ip.php <IP>
 * Exemplo: php unblock-ip.php 192.168.1.100
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

if ($argc < 2) {
    echo "Uso: php unblock-ip.php <IP>\n";
    echo "Exemplo: php unblock-ip.php 192.168.1.100\n";
    echo "\nPara ver IPs bloqueados: php unblock-ip.php --list\n";
    exit(1);
}

$ip = $argv[1];

try {
    $db = App\Database::getInstance();
    
    // Listar IPs bloqueados
    if ($ip === '--list' || $ip === '-l') {
        echo "=== IPs BLOQUEADOS ===\n\n";
        $stmt = $db->query("
            SELECT ip_address, reason, blocked_until, attempts, created_at 
            FROM blocked_ips 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        
        $blocked = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($blocked)) {
            echo "✅ Nenhum IP bloqueado no momento.\n";
        } else {
            printf("%-20s %-10s %-20s %-20s %s\n", 
                "IP", "Tentativas", "Bloqueado até", "Criado em", "Motivo");
            echo str_repeat("-", 100) . "\n";
            
            foreach ($blocked as $row) {
                printf("%-20s %-10d %-20s %-20s %s\n",
                    $row['ip_address'],
                    $row['attempts'],
                    $row['blocked_until'] ?? 'permanente',
                    $row['created_at'],
                    $row['reason'] ?? 'N/A'
                );
            }
            
            echo "\nTotal: " . count($blocked) . " IP(s) bloqueado(s)\n";
        }
        exit(0);
    }
    
    // Validar IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        echo "❌ IP inválido: $ip\n";
        exit(1);
    }
    
    // Verificar se está bloqueado
    $stmt = $db->prepare("SELECT * FROM blocked_ips WHERE ip_address = :ip");
    $stmt->execute(['ip' => $ip]);
    $blocked = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$blocked) {
        echo "ℹ️  IP $ip não está bloqueado.\n";
        exit(0);
    }
    
    // Desbloquear
    $stmt = $db->prepare("DELETE FROM blocked_ips WHERE ip_address = :ip");
    $stmt->execute(['ip' => $ip]);
    
    echo "✅ IP $ip desbloqueado com sucesso!\n";
    echo "   Motivo original: " . ($blocked['reason'] ?? 'N/A') . "\n";
    echo "   Tentativas: " . ($blocked['attempts'] ?? 0) . "\n";
    
    // Limpar cache de rate limit também
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip_address = :ip");
    $stmt->execute(['ip' => $ip]);
    echo "✅ Cache de rate limit limpo.\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
