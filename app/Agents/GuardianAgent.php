<?php

namespace App\Agents;

use App\Services\NotificationService;

class GuardianAgent extends BaseAgent
{
    private NotificationService $notifier;

    public function __construct()
    {
        parent::__construct('guardian');
        $this->notifier = new NotificationService();
    }

    public function run(): void
    {
        $this->checkLowStock();
        $this->checkStagnantAds();
        $this->updateLastRun();
    }

    private function checkLowStock(): void
    {
        $threshold = $this->config['stock_threshold'] ?? 5;

        $sql = "
            SELECT ml_item_id, title, available_quantity
            FROM items
            WHERE status = 'active'
            AND available_quantity < ?
            AND available_quantity > 0
            LIMIT 50
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$threshold]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $this->log(
                'warning',
                "Baixo Estoque detectado: {$item['title']} ({$item['available_quantity']} un).",
                ['item_id' => $item['ml_item_id'], 'stock' => $item['available_quantity']]
            );
        }

        // Send Summary Notification
        $count = count($items);
        $msg = "⚠️ <b>Alerta de Estoque Baixo</b>\n\nDetectamos $count produtos com estoque crítico (menor que $threshold unidades).\n\nExemplos:\n";

        // List first 3 items
        $examples = array_slice($items, 0, 3);
        foreach ($examples as $ex) {
            $msg .= "- {$ex['title']} ({$ex['available_quantity']} un)\n";
        }

        if ($count > 3) {
            $msg .= "... e mais " . ($count - 3) . " itens.";
        }

        $this->notifier->sendTelegram("Baixo Estoque ($count)", $msg, 'HIGH');
        $this->log('info', "Notificação enviada: $count itens com estoque baixo.");
    }

    private function checkStagnantAds(): void
    {
        // Finds active ads created > 60 days ago with 0 sales
        $sql = "
            SELECT ml_item_id, title, price, created_at as date_created
            FROM items
            WHERE status = 'active'
            AND sold_quantity = 0
            AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
            LIMIT 20
        ";

        $stmt = $this->db->query($sql);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $this->log(
                'info',
                "Anúncio Estagnado (Zombie): {$item['title']} está ativo há mais de 60 dias sem vendas.",
                ['item_id' => $item['ml_item_id'], 'suggestion' => 'Revisar preço ou título.']
            );
        }

        // Send Summary Notification
        $count = count($items);
        $msg = "🧟 <b>Anúncios Zumbis Detectados</b>\n\n$count anúncios estão ativos há >60 dias sem nenhuma venda.\nRecomendamos pausar ou otimizar (Preço/Título).\n\nExemplos:\n";

        $examples = array_slice($items, 0, 3);
        foreach ($examples as $ex) {
            $msg .= "- {$ex['title']} (R$ {$ex['price']})\n";
        }

        $this->notifier->sendTelegram("Anúncios Zumbis ($count)", $msg, 'MEDIUM');
    }
}
