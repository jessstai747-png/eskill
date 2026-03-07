<?php
declare(strict_types=1);

namespace App\Controllers\Mobile;

use App\Database;

class DashboardController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function overview(): void
    {
        header('Content-Type: application/json');
        
        // 1. Quick Stats (Kpis)
        $today = date('Y-m-d');
        
        // Revenue Today
        $stmt = $this->db->prepare("SELECT SUM(total_amount) as total FROM ml_orders WHERE date_created >= ? AND status = 'paid'");
        $stmt->execute([$today . ' 00:00:00']);
        $revenue = $stmt->fetchColumn() ?: 0;

        // Pending Questions
        $stmt = $this->db->query("SELECT COUNT(*) FROM ml_questions WHERE status = 'UNANSWERED'");
        $pendingQuestions = $stmt->fetchColumn() ?: 0;
        
        // Pending Orders (To Ship)
        // Logic: Paid but not popped (shipped_at is null)
        $stmt = $this->db->query("SELECT COUNT(*) FROM ml_orders WHERE status = 'paid' AND shipped_at IS NULL");
        $toShip = $stmt->fetchColumn() ?: 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'revenue_today' => (float)$revenue,
                'pending_questions' => (int)$pendingQuestions,
                'orders_to_ship' => (int)$toShip,
                'alerts' => [] // Future: Low stock, etc
            ]
        ]);
    }
}
