<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * MessagingService - Gestão de Mensagens e Atendimento
 * 
 * Gerencia mensagens com compradores, templates e automação
 * - Envio de mensagens
 * - Templates personalizados
 * - Respostas automáticas
 * - Histórico de conversas
 * 
 * @link https://developers.mercadolivre.com.br/pt_br/mensagens
 */
class MessagingService extends MercadoLivreClient
{
    private PDO $db;

    public function __construct(?int $accountId = null)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Lista conversas (threads)
     * 
     * @param array $filters Filtros
     * @return array Lista de conversas
     */
    public function listConversations(array $filters = []): array
    {
        try {
            $userId = $this->getSellerId();
            $params = array_merge([
                'status' => $filters['status'] ?? 'active',
                'limit' => $filters['limit'] ?? 50,
            ], $filters);

            $response = $this->get("/messages/threads?user_id={$userId}", $params);

            if (isset($response['error'])) {
                return ['total' => 0, 'conversations' => []];
            }

            return [
                'total' => $response['paging']['total'] ?? 0,
                'conversations' => $this->formatConversations($response['results'] ?? []),
                'paging' => $response['paging'] ?? [],
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao listar conversas', [
                'service' => 'MessagingService',
                'error' => $e->getMessage(),
            ]);
            return ['total' => 0, 'conversations' => []];
        }
    }

    /**
     * Obtém mensagens de uma conversa
     * 
     * @param string $threadId ID da conversa
     * @return array Mensagens
     */
    public function getMessages(string $threadId): array
    {
        try {
            $response = $this->get("/messages/threads/{$threadId}");

            if (isset($response['error'])) {
                return ['total' => 0, 'messages' => []];
            }

            return [
                'thread_id' => $threadId,
                'total' => count($response['messages'] ?? []),
                'messages' => $this->formatMessages($response['messages'] ?? []),
                'participants' => $response['participants'] ?? [],
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'messages' => []];
        }
    }

    public function getMessage(string $messageId): array
    {
        try {
            $response = $this->get("/messages/{$messageId}");
            if (isset($response['error'])) {
                return ['error' => $response['message'] ?? 'Erro ao obter mensagem'];
            }

            $message = $response['messages'][0] ?? $response;
            $formatted = [
                'id' => $message['id'] ?? $messageId,
                'text' => $message['text'] ?? '',
                'from' => $message['from']['user_id'] ?? null,
                'to' => $message['to']['user_id'] ?? null,
                'date' => $message['date_created'] ?? null,
                'status' => $message['status'] ?? 'unknown',
                'thread_id' => $message['conversation_id'] ?? $message['thread_id'] ?? null,
            ];

            if (!empty($formatted['id'])) {
                $this->saveMessage($formatted);
            }

            return $formatted;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Envia mensagem
     * 
     * @param string $recipientId ID do destinatário
     * @param string $message Mensagem
     * @param array $options Opções adicionais
     * @return array Resultado
     */
    public function sendMessage(string $recipientId, string $message, array $options = []): array
    {
        try {
            $userId = $this->getSellerId();
            
            $payload = [
                'from' => ['user_id' => $userId],
                'to' => ['user_id' => $recipientId],
                'text' => $message,
            ];

            // Contexto (ordem, item, etc)
            if (isset($options['context'])) {
                $payload['context'] = $options['context'];
            }

            $response = $this->post("/messages/packs/{$recipientId}/sellers/{$userId}", $payload);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao enviar mensagem',
                ];
            }

            // Salvar localmente
            $this->saveMessage($response);

            return [
                'success' => true,
                'message_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? 'sent',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cria template de mensagem
     * 
     * @param array $template Dados do template
     * @return array Resultado
     */
    public function createTemplate(array $template): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO message_templates (
                    account_id, name, subject, content,
                    category, variables, active, created_at
                ) VALUES (
                    :account_id, :name, :subject, :content,
                    :category, :variables, 1, NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'name' => $template['name'],
                'subject' => $template['subject'] ?? '',
                'content' => $template['content'],
                'category' => $template['category'] ?? 'general',
                'variables' => json_encode($template['variables'] ?? []),
            ]);

            return [
                'success' => true,
                'template_id' => $this->db->lastInsertId(),
                'message' => 'Template criado',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista templates
     * 
     * @param array $filters Filtros
     * @return array Lista de templates
     */
    public function listTemplates(array $filters = []): array
    {
        try {
            $category = $filters['category'] ?? null;
            
            $sql = "
                SELECT id, name, subject, content, category, variables
                FROM message_templates
                WHERE account_id = :account_id
                AND active = 1
            ";

            if ($category) {
                $sql .= " AND category = :category";
            }

            $sql .= " ORDER BY name";

            $stmt = $this->db->prepare($sql);
            $params = ['account_id' => $this->accountId];
            
            if ($category) {
                $params['category'] = $category;
            }

            $stmt->execute($params);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total' => count($templates),
                'templates' => array_map(function($t) {
                    $t['variables'] = json_decode($t['variables'], true);
                    return $t;
                }, $templates),
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'templates' => []];
        }
    }

    /**
     * Envia mensagem usando template
     * 
     * @param string $recipientId ID do destinatário
     * @param int $templateId ID do template
     * @param array $variables Variáveis para substituir
     * @return array Resultado
     */
    public function sendFromTemplate(string $recipientId, int $templateId, array $variables = []): array
    {
        try {
            // Buscar template
            $stmt = $this->db->prepare("
                SELECT content
                FROM message_templates
                WHERE id = :id
                AND account_id = :account_id
                AND active = 1
            ");

            $stmt->execute([
                'id' => $templateId,
                'account_id' => $this->accountId,
            ]);

            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return ['success' => false, 'error' => 'Template não encontrado'];
            }

            // Substituir variáveis
            $message = $this->replaceVariables($template['content'], $variables);

            // Enviar
            return $this->sendMessage($recipientId, $message);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Configura resposta automática
     * 
     * @param array $config Configuração
     * @return array Resultado
     */
    public function setAutoResponse(array $config): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auto_responses (
                    account_id, trigger_keyword, response_message,
                    enabled, created_at
                ) VALUES (
                    :account_id, :trigger, :response, 1, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    response_message = VALUES(response_message),
                    enabled = 1,
                    updated_at = NOW()
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'trigger' => $config['trigger_keyword'],
                'response' => $config['response_message'],
            ]);

            return [
                'success' => true,
                'message' => 'Resposta automática configurada',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista respostas automáticas
     * 
     * @return array Lista de auto-respostas
     */
    public function listAutoResponses(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, trigger_keyword, response_message, enabled
                FROM auto_responses
                WHERE account_id = :account_id
                ORDER BY trigger_keyword
            ");

            $stmt->execute(['account_id' => $this->accountId]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total' => count($responses),
                'auto_responses' => $responses,
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'auto_responses' => []];
        }
    }

    /**
     * Processa mensagem recebida (webhook)
     * 
     * @param array $messageData Dados da mensagem
     * @return array Resultado do processamento
     */
    public function processIncomingMessage(array $messageData): array
    {
        try {
            $messageText = $messageData['text'] ?? '';
            $senderId = $messageData['from']['user_id'] ?? null;

            if (!$senderId) {
                return ['processed' => false, 'error' => 'Sender inválido'];
            }

            // Verificar respostas automáticas
            $autoResponse = $this->findAutoResponse($messageText);

            if ($autoResponse) {
                $this->sendMessage($senderId, $autoResponse['response_message']);
                
                return [
                    'processed' => true,
                    'auto_response_sent' => true,
                    'trigger' => $autoResponse['trigger_keyword'],
                ];
            }

            return [
                'processed' => true,
                'auto_response_sent' => false,
            ];
        } catch (\Exception $e) {
            return ['processed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Estatísticas de mensagens
     * 
     * @param array $filters Filtros
     * @return array Métricas
     */
    public function getMessagingStats(array $filters = []): array
    {
        try {
            $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $filters['end_date'] ?? date('Y-m-d');

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN direction = 'sent' THEN 1 END) as sent,
                    COUNT(CASE WHEN direction = 'received' THEN 1 END) as received,
                    AVG(response_time_seconds) as avg_response_time
                FROM messages
                WHERE account_id = :account_id
                AND created_at BETWEEN :start_date AND :end_date
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total_messages' => $data['total_messages'] ?? 0,
                'sent' => $data['sent'] ?? 0,
                'received' => $data['received'] ?? 0,
                'avg_response_time_minutes' => round(($data['avg_response_time'] ?? 0) / 60, 2),
                'response_rate' => $this->calculateResponseRate($data),
            ];
        } catch (\Exception $e) {
            return [
                'total_messages' => 0,
                'sent' => 0,
                'received' => 0,
            ];
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function formatConversations(array $conversations): array
    {
        return array_map(function($conv) {
            return [
                'thread_id' => $conv['id'],
                'user_id' => $conv['user_id'] ?? null,
                'user_nickname' => $conv['user']['nickname'] ?? 'N/A',
                'last_message' => $conv['last_message']['text'] ?? '',
                'last_message_date' => $conv['last_message']['date_created'] ?? null,
                'unread' => $conv['unread'] ?? false,
                'status' => $conv['status'] ?? 'active',
            ];
        }, $conversations);
    }

    private function formatMessages(array $messages): array
    {
        return array_map(function($msg) {
            return [
                'id' => $msg['id'],
                'text' => $msg['text'],
                'from' => $msg['from']['user_id'] ?? null,
                'to' => $msg['to']['user_id'] ?? null,
                'date' => $msg['date_created'] ?? null,
                'status' => $msg['status'] ?? 'unknown',
            ];
        }, $messages);
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }
        return $content;
    }

    private function findAutoResponse(string $messageText): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT trigger_keyword, response_message
                FROM auto_responses
                WHERE account_id = :account_id
                AND enabled = 1
            ");

            $stmt->execute(['account_id' => $this->accountId]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($responses as $response) {
                if (stripos($messageText, $response['trigger_keyword']) !== false) {
                    return $response;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function saveMessage(array $message): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO messages (
                    account_id, message_id, thread_id,
                    direction, content, status, created_at
                ) VALUES (
                    :account_id, :message_id, :thread_id,
                    'sent', :content, :status, NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'message_id' => $message['id'] ?? null,
                'thread_id' => $message['thread_id'] ?? null,
                'content' => $message['text'] ?? '',
                'status' => $message['status'] ?? 'sent',
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao salvar mensagem no banco', [
                'service' => 'MessagingService',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function calculateResponseRate(array $data): float
    {
        $received = $data['received'] ?? 0;
        $sent = $data['sent'] ?? 0;

        if ($received === 0) return 0;

        return round(($sent / $received) * 100, 2);
    }
}
