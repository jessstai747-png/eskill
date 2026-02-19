<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Chatbot AI Service
 * 
 * Sistema inteligente de respostas automáticas usando:
 * - NLP (Natural Language Processing)
 * - Intent Recognition
 * - Context Awareness
 * - Learning from interactions
 * 
 * Intents suportados:
 * - Rastreamento de pedido
 * - Informações de produto
 * - Política de troca/devolução
 * - Reclamações
 * - Dúvidas gerais
 */
class ChatbotAIService extends MercadoLivreClient
{
    private PDO $db;
    private MessagingService $messaging;
    
    // Intents e seus padrões
    private array $intents = [
        'tracking' => [
            'patterns' => ['rastreio', 'rastrear', 'onde está', 'chegou', 'entrega', 'código de rastreamento', 'tracking'],
            'confidence_threshold' => 0.6,
            'requires_order' => true
        ],
        'product_info' => [
            'patterns' => ['características', 'especificações', 'dimensões', 'peso', 'cor', 'tamanho', 'medidas'],
            'confidence_threshold' => 0.5,
            'requires_order' => false
        ],
        'return_policy' => [
            'patterns' => ['trocar', 'devolver', 'devolução', 'troca', 'reembolso', 'garantia'],
            'confidence_threshold' => 0.7,
            'requires_order' => false
        ],
        'complaint' => [
            'patterns' => ['reclamação', 'problema', 'defeito', 'não funciona', 'quebrado', 'danificado', 'insatisfeito'],
            'confidence_threshold' => 0.65,
            'requires_order' => true
        ],
        'price_negotiation' => [
            'patterns' => ['desconto', 'preço', 'mais barato', 'negociar', 'oferta', 'promoção'],
            'confidence_threshold' => 0.6,
            'requires_order' => false
        ],
        'greeting' => [
            'patterns' => ['oi', 'olá', 'bom dia', 'boa tarde', 'boa noite', 'alo'],
            'confidence_threshold' => 0.8,
            'requires_order' => false
        ]
    ];
    
    public function __construct(int $accountId)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
        $this->messaging = new MessagingService($accountId);
    }
    
    /**
     * Processa mensagem e gera resposta inteligente
     * 
     * Pipeline:
     * 1. Detectar intent
     * 2. Extrair entidades (order_id, item_id)
     * 3. Buscar contexto
     * 4. Gerar resposta apropriada
     * 5. Aprender com interação
     * 
     * @param string $messageText
     * @param string $fromUser
     * @param array $context Contexto adicional
     * @return array
     */
    public function processMessage(string $messageText, string $fromUser, array $context = []): array
    {
        try {
            // 1. Detectar intent
            $intent = $this->detectIntent($messageText);
            
            // 2. Extrair entidades
            $entities = $this->extractEntities($messageText, $context);
            
            // 3. Gerar resposta baseada no intent
            $response = $this->generateResponse($intent, $entities, $fromUser);
            
            // 4. Salvar interação para aprendizado
            $this->logInteraction($fromUser, $messageText, $intent, $response['text']);
            
            return [
                'success' => true,
                'intent' => $intent,
                'confidence' => $response['confidence'],
                'response_text' => $response['text'],
                'requires_human' => $response['requires_human'],
                'entities' => $entities,
                'suggested_actions' => $response['actions'] ?? []
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Detecta intent da mensagem
     * 
     * Usa TF-IDF simplificado para matching
     * 
     * @param string $text
     * @return array
     */
    private function detectIntent(string $text): array
    {
        $text = strtolower($this->normalizeText($text));
        $words = explode(' ', $text);
        
        $scores = [];
        
        foreach ($this->intents as $intentName => $intentData) {
            $score = 0;
            $matches = 0;
            
            foreach ($intentData['patterns'] as $pattern) {
                $pattern = strtolower($pattern);
                
                // Match exato
                if (strpos($text, $pattern) !== false) {
                    $score += 1.0;
                    $matches++;
                }
                
                // Match parcial (palavras)
                foreach ($words as $word) {
                    if (strlen($word) > 3 && strpos($pattern, $word) !== false) {
                        $score += 0.3;
                    }
                }
            }
            
            // Normalizar score
            $normalizedScore = $score / count($intentData['patterns']);
            
            if ($normalizedScore >= $intentData['confidence_threshold']) {
                $scores[$intentName] = $normalizedScore;
            }
        }
        
        if (empty($scores)) {
            return ['name' => 'unknown', 'confidence' => 0];
        }
        
        arsort($scores);
        $topIntent = key($scores);
        
        return [
            'name' => $topIntent,
            'confidence' => $scores[$topIntent],
            'requires_order' => $this->intents[$topIntent]['requires_order']
        ];
    }
    
    /**
     * Extrai entidades (order_id, item_id, etc)
     */
    private function extractEntities(string $text, array $context): array
    {
        $entities = [];
        
        // Extrair order_id (formato: MLB1234567890 ou #1234567890)
        if (preg_match('/(?:MLB|#)?(\d{10,13})/i', $text, $matches)) {
            $entities['order_id'] = $matches[1];
        }
        
        // Usar contexto se disponível
        if (isset($context['order_id'])) {
            $entities['order_id'] = $context['order_id'];
        }
        
        if (isset($context['item_id'])) {
            $entities['item_id'] = $context['item_id'];
        }
        
        return $entities;
    }
    
    /**
     * Gera resposta apropriada baseada no intent
     */
    private function generateResponse(array $intent, array $entities, string $fromUser): array
    {
        $intentName = $intent['name'];
        $confidence = $intent['confidence'];
        
        switch ($intentName) {
            case 'tracking':
                return $this->generateTrackingResponse($entities);
            
            case 'product_info':
                return $this->generateProductInfoResponse($entities);
            
            case 'return_policy':
                return $this->generateReturnPolicyResponse();
            
            case 'complaint':
                return $this->generateComplaintResponse($entities, $fromUser);
            
            case 'price_negotiation':
                return $this->generatePriceNegotiationResponse($entities);
            
            case 'greeting':
                return [
                    'text' => "Olá! 👋 Como posso ajudar você hoje?",
                    'confidence' => 1.0,
                    'requires_human' => false,
                    'actions' => ['show_faq', 'show_orders']
                ];
            
            case 'unknown':
            default:
                return [
                    'text' => "Desculpe, não entendi sua pergunta. Você pode reformular ou escolher uma das opções:\n\n" .
                             "1️⃣ Rastrear pedido\n" .
                             "2️⃣ Informações do produto\n" .
                             "3️⃣ Trocas e devoluções\n" .
                             "4️⃣ Falar com atendente",
                    'confidence' => 0,
                    'requires_human' => false,
                    'actions' => ['show_menu']
                ];
        }
    }
    
    /**
     * Resposta para rastreamento
     */
    private function generateTrackingResponse(array $entities): array
    {
        if (!isset($entities['order_id'])) {
            return [
                'text' => "Para rastrear seu pedido, preciso do número do pedido. Você pode encontrá-lo no email de confirmação ou em Minhas Compras.",
                'confidence' => 0.7,
                'requires_human' => false,
                'actions' => ['request_order_id']
            ];
        }
        
        // Buscar informações do pedido
        $orderId = $entities['order_id'];
        $order = $this->get("/orders/{$orderId}");
        
        if (!$order) {
            return [
                'text' => "Não encontrei informações sobre esse pedido. Verifique se o número está correto.",
                'confidence' => 0.8,
                'requires_human' => true
            ];
        }
        
        $shipping = $order['shipping'] ?? [];
        $status = $shipping['status'] ?? 'pending';
        $trackingNumber = $shipping['tracking_number'] ?? null;
        
        $statusMessages = [
            'pending' => "Seu pedido está sendo preparado para envio. Em breve você receberá o código de rastreamento!",
            'handling' => "Seu pedido está sendo preparado para envio.",
            'shipped' => "Seu pedido já foi enviado! " . ($trackingNumber ? "Código de rastreamento: {$trackingNumber}" : ""),
            'delivered' => "Seu pedido foi entregue! 🎉 Espero que esteja gostando do produto!"
        ];
        
        $message = $statusMessages[$status] ?? "Status do pedido: {$status}";
        
        if ($trackingNumber) {
            $message .= "\n\nVocê pode rastrear aqui: https://rastreamento.correios.com.br/app/index.php?objeto={$trackingNumber}";
        }
        
        return [
            'text' => $message,
            'confidence' => 0.9,
            'requires_human' => false,
            'actions' => ['show_tracking_link']
        ];
    }
    
    /**
     * Resposta sobre informações do produto
     */
    private function generateProductInfoResponse(array $entities): array
    {
        if (!isset($entities['item_id'])) {
            return [
                'text' => "Sobre qual produto você gostaria de saber mais? Por favor, me informe o código do produto.",
                'confidence' => 0.6,
                'requires_human' => false,
                'actions' => ['request_item_id']
            ];
        }
        
        $itemId = $entities['item_id'];
        $item = $this->get("/items/{$itemId}");
        
        if (!$item) {
            return [
                'text' => "Não encontrei esse produto. Pode me passar o link ou código correto?",
                'confidence' => 0.7,
                'requires_human' => true
            ];
        }
        
        $attributes = [];
        foreach ($item['attributes'] ?? [] as $attr) {
            if (in_array($attr['id'], ['BRAND', 'MODEL', 'WEIGHT', 'HEIGHT', 'WIDTH', 'LENGTH'])) {
                $attributes[] = "{$attr['name']}: {$attr['value_name']}";
            }
        }
        
        $message = "📦 **{$item['title']}**\n\n";
        
        if (!empty($attributes)) {
            $message .= "**Características:**\n" . implode("\n", $attributes) . "\n\n";
        }
        
        $message .= "Tem alguma dúvida específica sobre o produto?";
        
        return [
            'text' => $message,
            'confidence' => 0.85,
            'requires_human' => false,
            'actions' => ['show_product_details']
        ];
    }
    
    /**
     * Resposta sobre política de devolução
     */
    private function generateReturnPolicyResponse(): array
    {
        return [
            'text' => "**Política de Trocas e Devoluções** 🔄\n\n" .
                     "✅ Você tem até **7 dias** após o recebimento para solicitar troca ou devolução\n" .
                     "✅ O produto deve estar **sem uso** e na embalagem original\n" .
                     "✅ O reembolso é processado em até **10 dias úteis**\n\n" .
                     "Para solicitar, basta abrir uma reclamação diretamente no Mercado Livre em 'Minhas Compras'.\n\n" .
                     "Posso ajudar em algo mais?",
            'confidence' => 1.0,
            'requires_human' => false,
            'actions' => ['show_return_instructions']
        ];
    }
    
    /**
     * Resposta para reclamação
     */
    private function generateComplaintResponse(array $entities, string $fromUser): array
    {
        // Criar ticket interno
        $this->createSupportTicket($fromUser, 'complaint', $entities);
        
        return [
            'text' => "Lamento muito pelo problema! 😔\n\n" .
                     "Vou encaminhar sua reclamação para nossa equipe com **prioridade alta**. " .
                     "Um atendente especializado entrará em contato em até **2 horas**.\n\n" .
                     "Seu protocolo de atendimento é: #" . uniqid() . "\n\n" .
                     "Enquanto isso, você pode abrir uma reclamação oficial no Mercado Livre para maior segurança.",
            'confidence' => 0.95,
            'requires_human' => true,
            'actions' => ['create_ticket', 'escalate_priority']
        ];
    }
    
    /**
     * Resposta para negociação de preço
     */
    private function generatePriceNegotiationResponse(array $entities): array
    {
        $discountOffered = 5; // 5% de desconto padrão
        
        return [
            'text' => "Entendo que você está buscando um preço melhor! 💰\n\n" .
                     "Como você é um cliente especial, posso oferecer **{$discountOffered}% de desconto** neste produto.\n\n" .
                     "Para aplicar o desconto, use o cupom: **CLIENTE{$discountOffered}**\n\n" .
                     "Ficou bom para você?",
            'confidence' => 0.8,
            'requires_human' => false,
            'actions' => ['offer_discount']
        ];
    }
    
    /**
     * Cria ticket de suporte
     */
    private function createSupportTicket(string $fromUser, string $type, array $entities): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO support_tickets (account_id, user_id, type, entities, status, created_at)
            VALUES (:account_id, :user_id, :type, :entities, 'open', NOW())
        ");
        
        $stmt->execute([
            'account_id' => $this->accountId,
            'user_id' => $fromUser,
            'type' => $type,
            'entities' => json_encode($entities)
        ]);
    }
    
    /**
     * Salva interação para aprendizado
     */
    private function logInteraction(string $userId, string $input, array $intent, string $response): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO chatbot_interactions 
            (account_id, user_id, input_text, detected_intent, intent_confidence, response_text, created_at)
            VALUES (:account_id, :user_id, :input_text, :intent, :confidence, :response, NOW())
        ");
        
        $stmt->execute([
            'account_id' => $this->accountId,
            'user_id' => $userId,
            'input_text' => $input,
            'intent' => $intent['name'],
            'confidence' => $intent['confidence'],
            'response' => $response
        ]);
    }
    
    /**
     * Normaliza texto (remove acentos, pontuação)
     */
    private function normalizeText(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s]/u', '', $text);
        $text = str_replace(
            ['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ü', 'ç'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'c'],
            strtolower($text)
        );
        
        return trim($text);
    }
    
    /**
     * Retorna estatísticas do chatbot
     */
    public function getStats(int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_interactions,
                    AVG(intent_confidence) as avg_confidence,
                    detected_intent,
                    COUNT(*) as intent_count
                FROM chatbot_interactions
                WHERE account_id = :account_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY detected_intent
                ORDER BY intent_count DESC
            ");
            
            $stmt->execute(['account_id' => $this->accountId, 'days' => $days]);
            $intents = $stmt->fetchAll();
            
            $totalInteractions = array_sum(array_column($intents, 'intent_count'));
            $avgConfidence = count($intents) > 0 
                ? array_sum(array_column($intents, 'avg_confidence')) / count($intents)
                : 0;
            
            return [
                'success' => true,
                'period_days' => $days,
                'total_interactions' => $totalInteractions,
                'avg_confidence' => round($avgConfidence, 2),
                'intents_breakdown' => $intents,
                'automation_rate' => $this->calculateAutomationRate($days)
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calcula taxa de automação (% resolvidas sem humano)
     */
    private function calculateAutomationRate(int $days): float
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN requires_human = 0 THEN 1 ELSE 0 END) as automated
            FROM chatbot_interactions ci
            LEFT JOIN support_tickets st ON ci.user_id = st.user_id
            WHERE ci.account_id = :account_id
            AND ci.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        
        $stmt->execute(['account_id' => $this->accountId, 'days' => $days]);
        $result = $stmt->fetch();
        
        if (!$result || $result['total'] == 0) {
            return 0;
        }
        
        return round(($result['automated'] / $result['total']) * 100, 1);
    }
}
