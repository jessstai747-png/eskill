#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Download GeoLite2 Database
 * Baixa o banco de dados gratuito do MaxMind
 */

echo "🌍 GeoIP Database Downloader\n\n";

$dataDir = __DIR__ . '/../storage/geoip';
@mkdir($dataDir, 0755, true);

echo "📁 Diretório: $dataDir\n\n";

// URLs do banco de dados GeoLite2 (versão gratuita)
// Nota: MaxMind requer conta gratuita para download direto
// Usando mirror alternativo ou versão local

$dbFile = $dataDir . '/GeoLite2-City.mmdb';

// Verificar se já existe
if (file_exists($dbFile)) {
    $fileAge = time() - filemtime($dbFile);
    $daysOld = floor($fileAge / 86400);

    echo "✓ Banco de dados existente encontrado\n";
    echo "  Arquivo: $dbFile\n";
    echo "  Idade: $daysOld dias\n";
    echo "  Tamanho: " . number_format(filesize($dbFile) / 1024 / 1024, 2) . " MB\n\n";

    if ($daysOld < 30) {
        echo "✅ Banco de dados é recente (menos de 30 dias)\n";
        echo "   Não é necessário atualizar.\n\n";

        $update = readline("Deseja atualizar mesmo assim? (y/n): ");
        if (strtolower($update) !== 'y') {
            echo "✅ Mantendo banco de dados atual.\n";
            exit(0);
        }
    }
}

echo "📥 Baixando banco de dados GeoLite2...\n";
echo "⚠️  IMPORTANTE: MaxMind GeoLite2 requer conta gratuita.\n\n";

echo "Opções:\n";
echo "1. Baixar de DB-IP (alternativa gratuita, sem cadastro)\n";
echo "2. Fornecer link do MaxMind (requer conta)\n";
echo "3. Usar arquivo local existente\n";
echo "4. Cancelar\n\n";

$option = readline("Escolha uma opção (1-4): ");

switch ($option) {
    case '1':
        downloadDbIP($dataDir);
        break;

    case '2':
        downloadMaxMind($dataDir);
        break;

    case '3':
        useLocalFile($dataDir);
        break;

    default:
        echo "❌ Operação cancelada.\n";
        exit(0);
}

function downloadDbIP(string $dataDir): void
{
    echo "\n📥 Baixando de DB-IP.com (versão gratuita)...\n";

    // DB-IP oferece versão lite gratuita sem cadastro
    $url = 'https://download.db-ip.com/free/dbip-city-lite-' . date('Y-m') . '.mmdb.gz';

    echo "URL: $url\n";
    echo "Baixando...\n";

    $gzFile = $dataDir . '/dbip-city-lite.mmdb.gz';
    $mmdbFile = $dataDir . '/GeoLite2-City.mmdb';

    // Download com curl
    $cmd = "curl -L -o " . escapeshellarg($gzFile) . " " . escapeshellarg($url) . " 2>&1";
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($gzFile)) {
        echo "❌ Erro ao baixar. Tentando versão anterior...\n";

        // Tentar mês anterior
        $prevMonth = date('Y-m', strtotime('-1 month'));
        $url = "https://download.db-ip.com/free/dbip-city-lite-{$prevMonth}.mmdb.gz";

        $cmd = "curl -L -o " . escapeshellarg($gzFile) . " " . escapeshellarg($url) . " 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($gzFile)) {
            echo "❌ Falha no download. Verifique a conexão ou use outra opção.\n";
            exit(1);
        }
    }

    echo "✓ Download concluído\n";
    echo "📦 Descompactando...\n";

    // Descompactar
    $gz = gzopen($gzFile, 'r');
    $out = fopen($mmdbFile, 'w');

    while (!gzeof($gz)) {
        fwrite($out, gzread($gz, 4096));
    }

    gzclose($gz);
    fclose($out);

    unlink($gzFile);

    echo "✅ Banco de dados instalado com sucesso!\n";
    echo "   Arquivo: $mmdbFile\n";
    echo "   Tamanho: " . number_format(filesize($mmdbFile) / 1024 / 1024, 2) . " MB\n";
}

function downloadMaxMind(string $dataDir): void
{
    echo "\n⚠️  Para baixar do MaxMind:\n";
    echo "1. Crie uma conta gratuita em: https://www.maxmind.com/en/geolite2/signup\n";
    echo "2. Gere uma license key\n";
    echo "3. Use o link de download permanente fornecido\n\n";

    $url = readline("Cole o link de download do MaxMind: ");

    if (empty($url)) {
        echo "❌ Link não fornecido.\n";
        exit(1);
    }

    // Validate URL to prevent SSRF — only allow trusted download domains
    if (!preg_match('#^https://(download\.maxmind\.com|download\.db-ip\.com|updates\.maxmind\.com)/#i', $url)) {
        echo "❌ URL inválida. Apenas links de maxmind.com e db-ip.com são aceitos.\n";
        exit(1);
    }

    $mmdbFile = $dataDir . '/GeoLite2-City.mmdb';
    $tarFile = $dataDir . '/GeoLite2-City.tar.gz';

    echo "📥 Baixando...\n";

    $cmd = "curl -L -o " . escapeshellarg($tarFile) . " " . escapeshellarg($url) . " 2>&1";
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        echo "❌ Erro ao baixar.\n";
        exit(1);
    }

    echo "✓ Download concluído\n";
    echo "📦 Extraindo...\n";

    // Extrair
    exec("cd " . escapeshellarg($dataDir) . " && tar -xzf " . escapeshellarg(basename($tarFile)) . " 2>&1", $output, $returnCode);

    // Encontrar o arquivo .mmdb extraído
    $files = glob($dataDir . '/GeoLite2-City_*/GeoLite2-City.mmdb');

    if (!empty($files)) {
        rename($files[0], $mmdbFile);

        // Limpar
        exec("rm -rf " . escapeshellarg($dataDir) . "/GeoLite2-City_* " . escapeshellarg($tarFile));

        echo "✅ Banco de dados instalado com sucesso!\n";
        echo "   Arquivo: $mmdbFile\n";
        echo "   Tamanho: " . number_format(filesize($mmdbFile) / 1024 / 1024, 2) . " MB\n";
    } else {
        echo "❌ Erro ao extrair banco de dados.\n";
        exit(1);
    }
}

function useLocalFile(string $dataDir): void
{
    $source = readline("Caminho completo do arquivo .mmdb: ");

    if (!file_exists($source)) {
        echo "❌ Arquivo não encontrado: $source\n";
        exit(1);
    }

    $dest = $dataDir . '/GeoLite2-City.mmdb';

    if (copy($source, $dest)) {
        echo "✅ Arquivo copiado com sucesso!\n";
        echo "   Destino: $dest\n";
        echo "   Tamanho: " . number_format(filesize($dest) / 1024 / 1024, 2) . " MB\n";
    } else {
        echo "❌ Erro ao copiar arquivo.\n";
        exit(1);
    }
}

echo "\n";
echo "💡 Dica: Configure atualização automática mensal:\n";
echo "   0 0 1 * * cd /home/eskill/htdocs/eskill.com.br && php bin/download-geoip-db.php\n";
echo "\n";
