<?php
/**
 * Gerador de EANs válidos para popular o inventário
 * 
 * Uso:
 *   php scripts/generate_eans.php 1000          # Gerar 1000 EANs
 *   php scripts/generate_eans.php 500 LOTE-001  # Gerar 500 EANs com lote específico
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Models\EanInventory;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Parâmetros da linha de comando
$quantity = (int) ($argv[1] ?? 100);
$batchName = $argv[2] ?? 'BATCH-' . date('Ymd-His');
$costPerEan = (float) ($argv[3] ?? 2.50);
$supplier = $argv[4] ?? 'Gerado Internamente';

if ($quantity < 1 || $quantity > 10000) {
    echo "Erro: Quantidade deve ser entre 1 e 10000\n";
    exit(1);
}

echo "=== Gerador de EANs ===\n\n";
echo "Quantidade: $quantity\n";
echo "Lote: $batchName\n";
echo "Custo por EAN: R$ " . number_format($costPerEan, 2, ',', '.') . "\n";
echo "Fornecedor: $supplier\n\n";

try {
    $db = Database::getInstance();
    $inventory = new EanInventory();
    
    // Verificar quantos EANs já existem para evitar colisões
    $stmt = $db->query("SELECT COUNT(*) as total FROM ean_inventory");
    $existingCount = (int) $stmt->fetch()['total'];
    echo "EANs existentes no inventário: $existingCount\n\n";
    
    // Gerar EANs únicos
    echo "Gerando $quantity EANs válidos...\n";
    $eans = [];
    $attempts = 0;
    $maxAttempts = $quantity * 3;
    
    // Usar prefixo 789 (Brasil) + contador único
    $basePrefix = '789';
    $counter = $existingCount + 1;
    
    while (count($eans) < $quantity && $attempts < $maxAttempts) {
        $attempts++;
        
        // Método 1: Baseado em contador (mais garantido de ser único)
        if ($counter < 999999999) {
            $ean = generateEanFromCounter($basePrefix, $counter);
            $counter++;
        } else {
            // Método 2: Aleatório como fallback
            $ean = $inventory->generateTestEan($basePrefix);
        }
        
        // Verificar se já não está na lista
        if (!in_array($ean, $eans)) {
            $eans[] = $ean;
            
            // Mostrar progresso
            if (count($eans) % 100 === 0) {
                echo "  " . count($eans) . " EANs gerados...\n";
            }
        }
    }
    
    echo "Total gerado: " . count($eans) . " EANs\n\n";
    
    // Inserir no banco em lotes de 100
    echo "Inserindo no banco de dados...\n";
    $inserted = 0;
    $duplicates = 0;
    $batchSize = 100;
    
    for ($i = 0; $i < count($eans); $i += $batchSize) {
        $batch = array_slice($eans, $i, $batchSize);
        $batchInserted = $inventory->addBatch($batch, $batchName, $costPerEan, $supplier);
        $inserted += $batchInserted;
        $duplicates += count($batch) - $batchInserted;
        
        if (($i + $batchSize) % 500 === 0 || $i + $batchSize >= count($eans)) {
            echo "  Progresso: " . min($i + $batchSize, count($eans)) . " / " . count($eans) . "\n";
        }
    }
    
    echo "\n=== Resultado ===\n";
    echo "✅ EANs inseridos: $inserted\n";
    if ($duplicates > 0) {
        echo "⚠️  Duplicados ignorados: $duplicates\n";
    }
    
    // Verificar novo total
    $stmt = $db->query("SELECT COUNT(*) as total FROM ean_inventory WHERE status = 'available'");
    $newTotal = (int) $stmt->fetch()['total'];
    echo "\n📦 Total disponível no inventário: $newTotal EANs\n";
    
    // Calcular receita potencial
    $stmt = $db->query("SELECT SUM(quantity * price) as potential FROM ean_packages WHERE is_active = 1");
    $packagesValue = $stmt->fetch();
    
    echo "\n💰 Investimento: R$ " . number_format($inserted * $costPerEan, 2, ',', '.') . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Gerar EAN-13 a partir de um contador
 */
function generateEanFromCounter(string $prefix, int $counter): string
{
    // Formatar contador com 9 dígitos (prefixo 3 + contador 9 = 12 dígitos + check)
    $base = $prefix . str_pad($counter, 9, '0', STR_PAD_LEFT);
    
    // Calcular dígito verificador
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$base[$i] * (($i % 2 === 0) ? 1 : 3);
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    
    return $base . $checkDigit;
}
