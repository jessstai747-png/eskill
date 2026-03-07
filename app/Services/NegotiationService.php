<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class NegotiationService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Avalia uma pergunta e decide se negocia.
     * Retorna array ['action' => 'REPLY', 'text' => '...'] ou null.
     */
    public function processNegotiation(string $questionText, string $mlItemId): ?array
    {
        // 1. Get Item Config
        $item = $this->getItemSettings($mlItemId);
        if (!$item || !$item['auto_negotiate'] || !$item['min_price']) {
            return null; // Feature disabled for this item
        }

        // 2. Extract Offer
        $offer = $this->extractOfferValue($questionText);
        if (!$offer) {
            return null; // No concrete number found
        }

        // 3. Logic
        // Offer is the buyer's proposed price.
        // MinPrice is our floor.
        
        $currentPrice = (float)$item['price'];
        $minPrice = (float)$item['min_price'];

        if ($offer >= $minPrice) {
            // DEAL!
            // In a real scenario, we might create a custom coupon or link.
            // For now, we simulate approval.
            $appUrl = $_ENV['APP_URL'] ?? 'https://eskill.com.br';
            $checkoutUrl = "{$appUrl}/checkout/{$mlItemId}?price=" . number_format($offer, 2, '.', '');
            
            return [
                'action' => 'ACCEPT',
                'text' => "Olá! Aceitamos sua oferta de R$ " . number_format($offer, 2, ',', '.') . ". Pode comprar neste link que ajustamos para você: $checkoutUrl"
            ];
        } else {
            // REJECT / COUNTER
            // If offer is > 95% of min_price, maybe counter?
            if ($offer >= ($minPrice * 0.95)) {
                return [
                    'action' => 'COUNTER',
                    'text' => "Infelizmente não consigo chegar nesse valor. O mínimo que posso fazer hoje é R$ " . number_format($minPrice, 2, ',', '.') . ". Aproveite!"
                ];
            } else {
                return [
                    'action' => 'REJECT',
                    'text' => "Olá! Nosso preço já está no limite promocional, infelizmente não conseguimos descontos adicionais neste item."
                ];
            }
        }
    }

    private function getItemSettings(string $mlItemId): ?array
    {
        $stmt = $this->db->prepare("SELECT price, min_price, auto_negotiate FROM items WHERE ml_item_id = ?");
        $stmt->execute([$mlItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function extractOfferValue(string $text): ?float
    {
        // Normalize
        $text = strtolower($text);
        
        // Regex for "faz x", "aceita x", "fecha em x"
        // Matches: "faz 200", "faz R$ 200", "aceita 200,00"
        if (preg_match('/(faz|aceita|fecha|por|r\$)\s*[:\s]?\s*(r\$)?\s*([\d\.]+)(,\d{2})?/', $text, $matches)) {
            // $matches[3] is the number part (e.g. 200 or 200.50)
            // Handle brazilian float format (dots as thousands separators sometimes, comma decimals)
            // But usually regex approach is simpler if we assume simple ints or standard floats.
            // Let's assume user types "200" or "200,00"
            
            $numStr = $matches[3];
            $decimal = $matches[4] ?? ''; // ,00
            
            // If text has "2.000,00", regex might be tricky.
            // Simplified approach: Extract all numbers, check if likely price.
            
            // Clean string to float
            $clean = str_replace('.', '', $numStr); // remove thousands dot
            if ($decimal) {
                $clean .= str_replace(',', '.', $decimal);
            }
            
            $val = (float)$clean;
            
            // Sanity check: Offer usually shouldn't be effectively zero
            if ($val > 0) return $val;
        }
        
        // Fallback for just number at end? "top paga 300"
        if (preg_match('/(\d+)/', $text, $matches)) {
            // risky without context
        }

        return null;
    }
}
