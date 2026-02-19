<?php

/**
 * Script para gerar chaves VAPID para Push Notifications
 * 
 * Uso: php scripts/generate_vapid_keys.php
 * 
 * Adicione as chaves geradas ao arquivo .env:
 * VAPID_PUBLIC_KEY=...
 * VAPID_PRIVATE_KEY=...
 */

echo "===========================================\n";
echo "  Gerador de Chaves VAPID\n";
echo "  Push Notifications - Web Push API\n";
echo "===========================================\n\n";

// Verificar se a extensão OpenSSL está disponível
if (!extension_loaded('openssl')) {
    die("Erro: Extensão OpenSSL não está disponível.\n");
}

// Gerar par de chaves ECDSA P-256
$config = [
    'curve_name' => 'prime256v1', // P-256
    'private_key_type' => OPENSSL_KEYTYPE_EC,
];

$key = openssl_pkey_new($config);

if (!$key) {
    die("Erro ao gerar chaves: " . openssl_error_string() . "\n");
}

// Extrair detalhes da chave
$details = openssl_pkey_get_details($key);

if (!$details) {
    die("Erro ao obter detalhes da chave: " . openssl_error_string() . "\n");
}

// Chave pública em formato não comprimido (65 bytes)
$x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
$y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
$publicKey = "\x04" . $x . $y; // 0x04 = uncompressed point format

// Chave privada (32 bytes)
$privateKey = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);

// Codificar em base64url (RFC 4648)
function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$publicKeyBase64 = base64url_encode($publicKey);
$privateKeyBase64 = base64url_encode($privateKey);

echo "Chaves VAPID geradas com sucesso!\n\n";
echo "Adicione as seguintes linhas ao seu arquivo .env:\n\n";
echo "-------------------------------------------\n";
echo "VAPID_PUBLIC_KEY={$publicKeyBase64}\n";
echo "VAPID_PRIVATE_KEY={$privateKeyBase64}\n";
echo "VAPID_SUBJECT=mailto:seu-email@dominio.com\n";
echo "-------------------------------------------\n\n";

echo "IMPORTANTE:\n";
echo "- A chave pública deve ser compartilhada com o navegador\n";
echo "- A chave privada deve ser mantida em segredo\n";
echo "- Atualize VAPID_SUBJECT com seu email de contato\n\n";

// Validação
echo "Validação das chaves:\n";
echo "- Chave pública: " . strlen($publicKey) . " bytes (esperado: 65)\n";
echo "- Chave privada: " . strlen($privateKey) . " bytes (esperado: 32)\n";
echo "- Formato: Base64URL\n\n";

// Opcionalmente salvar em arquivo
$saveToFile = readline("Deseja salvar as chaves em um arquivo? (s/n): ");

if (strtolower(trim($saveToFile)) === 's') {
    $content = "# Chaves VAPID geradas em " . date('Y-m-d H:i:s') . "\n";
    $content .= "VAPID_PUBLIC_KEY={$publicKeyBase64}\n";
    $content .= "VAPID_PRIVATE_KEY={$privateKeyBase64}\n";
    $content .= "VAPID_SUBJECT=mailto:admin@eskill.com.br\n";

    $filename = __DIR__ . '/../storage/vapid_keys.txt';

    if (file_put_contents($filename, $content)) {
        echo "\nChaves salvas em: {$filename}\n";
        echo "ATENÇÃO: Mova as chaves para .env e delete este arquivo!\n";
    } else {
        echo "\nErro ao salvar arquivo.\n";
    }
}

echo "\nConcluído!\n";
