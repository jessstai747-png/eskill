<?php

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\NotificationService;
use PDO;

/**
 * 📊 Automated Reporting System
 * 
 * Sistema de relatórios automatizados:
 * - Relatórios diários/semanais/mensais
 * - Envio automático por email
 * - Insights e recomendações personalizadas
 * - Performance tracking
 * 
 * @version 1.0.0
 */
class AutomatedReporting
{
    private PDO $db;
    private int $accountId;
    private NotificationService $notificationService;
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Gera e envia relatório diário
     * 
     * @return array Status do envio
     */
    public function sendDailyReport(): array
    {
        try {
            $data = $this->generateDailyData();
            
            if (empty($data['optimizations']) && empty($data['alerts'])) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'reason' => 'Sem atividades para reportar',
                ];
            }
            
            $html = $this->buildDailyReportHtml($data);
            
            // Buscar email do usuário
            $stmt = $this->db->prepare("
                SELECT u.email, u.name
                FROM users u
                JOIN ml_accounts ma ON ma.user_id = u.id
                WHERE ma.id = :account_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !$user['email']) {
                return [
                    'success' => false,
                    'error' => 'Email do usuário não encontrado',
                ];
            }
            
            $result = $this->notificationService->sendEmail(
                $user['email'],
                '📊 SEO Killer - Relatório Diário',
                $html,
                ['html' => true]
            );
            
            // Salvar log
            $this->logReport('daily', $result['success']);
            
            return $result;
            
        } catch (\Exception $e) {
            log_error('Erro ao enviar relatório diário SEO', [
                'service' => 'AutomatedReporting',
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Gera e envia relatório semanal
     */
    public function sendWeeklyReport(): array
    {
        try {
            $data = $this->generateWeeklyData();
            $html = $this->buildWeeklyReportHtml($data);
            
            $stmt = $this->db->prepare("
                SELECT u.email, u.name
                FROM users u
                JOIN ml_accounts ma ON ma.user_id = u.id
                WHERE ma.id = :account_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !$user['email']) {
                return ['success' => false, 'error' => 'Email não encontrado'];
            }
            
            $result = $this->notificationService->sendEmail(
                $user['email'],
                '📈 SEO Killer - Relatório Semanal',
                $html,
                ['html' => true]
            );
            
            $this->logReport('weekly', $result['success']);
            
            return $result;
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Gera e envia relatório mensal
     */
    public function sendMonthlyReport(): array
    {
        try {
            $data = $this->generateMonthlyData();
            $html = $this->buildMonthlyReportHtml($data);
            
            $stmt = $this->db->prepare("
                SELECT u.email, u.name
                FROM users u
                JOIN ml_accounts ma ON ma.user_id = u.id
                WHERE ma.id = :account_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !$user['email']) {
                return ['success' => false, 'error' => 'Email não encontrado'];
            }
            
            // Gerar PDF anexo
            $pdfExporter = new PdfExporter($this->accountId);
            $pdfResult = $pdfExporter->exportMonthlyReport();
            
            $result = $this->notificationService->sendEmail(
                $user['email'],
                '🎯 SEO Killer - Relatório Mensal',
                $html,
                [
                    'html' => true,
                    'attachments' => $pdfResult['success'] ? [$pdfResult['file']] : [],
                ]
            );
            
            $this->logReport('monthly', $result['success']);
            
            return $result;
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Coleta dados para relatório diário
     */
    private function generateDailyData(): array
    {
        $yesterday = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $today = date('Y-m-d 23:59:59');
        
        // Otimizações realizadas ontem
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                AVG(score_improvement) as avg_improvement
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $yesterday,
            'end' => $today,
        ]);
        $optimizations = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Alertas gerados ontem
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
            FROM competitor_alerts
            WHERE watchlist_id IN (
                SELECT id FROM competitor_watchlist WHERE account_id = :account_id
            )
            AND created_at BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $yesterday,
            'end' => $today,
        ]);
        $alerts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Top 3 produtos otimizados
        $stmt = $this->db->prepare("
            SELECT 
                item_id,
                optimization_type,
                score_improvement,
                created_at
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
            ORDER BY score_improvement DESC
            LIMIT 3
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $yesterday,
            'end' => $today,
        ]);
        $topOptimizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'date' => date('d/m/Y', strtotime('-1 day')),
            'optimizations' => $optimizations,
            'alerts' => $alerts,
            'top_optimizations' => $topOptimizations,
        ];
    }
    
    /**
     * Coleta dados para relatório semanal
     */
    private function generateWeeklyData(): array
    {
        $weekStart = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $weekEnd = date('Y-m-d 23:59:59');
        
        // Total de otimizações na semana
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                AVG(score_improvement) as avg_improvement,
                SUM(score_improvement) as total_improvement
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $weekStart,
            'end' => $weekEnd,
        ]);
        $optimizations = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Distribuição por tipo
        $stmt = $this->db->prepare("
            SELECT 
                optimization_type,
                COUNT(*) as count
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
            GROUP BY optimization_type
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $weekStart,
            'end' => $weekEnd,
        ]);
        $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Performance metrics
        $stmt = $this->db->prepare("
            SELECT 
                AVG(current_score) as avg_score,
                SUM(views_change) as total_views_increase,
                SUM(sales_change) as total_sales_increase
            FROM seo_performance_metrics
            WHERE account_id = :account_id
              AND date BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $weekStart,
            'end' => $weekEnd,
        ]);
        $performance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Alertas de concorrentes
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM competitor_alerts
            WHERE watchlist_id IN (
                SELECT id FROM competitor_watchlist WHERE account_id = :account_id
            )
            AND created_at BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $weekStart,
            'end' => $weekEnd,
        ]);
        $alerts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Top 5 performers
        $stmt = $this->db->prepare("
            SELECT 
                item_id,
                SUM(score_improvement) as total_improvement,
                COUNT(*) as optimization_count
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
            GROUP BY item_id
            ORDER BY total_improvement DESC
            LIMIT 5
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $weekStart,
            'end' => $weekEnd,
        ]);
        $topPerformers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'week_start' => date('d/m/Y', strtotime('-7 days')),
            'week_end' => date('d/m/Y'),
            'optimizations' => $optimizations,
            'by_type' => $byType,
            'performance' => $performance,
            'alerts' => $alerts,
            'top_performers' => $topPerformers,
        ];
    }
    
    /**
     * Coleta dados para relatório mensal
     */
    private function generateMonthlyData(): array
    {
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');
        
        // Estatísticas gerais
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_optimizations,
                AVG(score_improvement) as avg_improvement,
                SUM(score_improvement) as total_improvement
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $monthStart,
            'end' => $monthEnd,
        ]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Performance
        $stmt = $this->db->prepare("
            SELECT 
                AVG(current_score) as avg_score,
                MAX(current_score) as max_score,
                SUM(views_change) as total_views,
                SUM(sales_change) as total_sales
            FROM seo_performance_metrics
            WHERE account_id = :account_id
              AND date BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $monthStart,
            'end' => $monthEnd,
        ]);
        $performance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Tendências diárias
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as optimizations
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $monthStart,
            'end' => $monthEnd,
        ]);
        $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ROI estimado
        $avgProductPrice = 150; // Preço médio estimado
        $conversionRate = 0.03; // 3% conversão
        $estimatedRevenue = ($performance['total_sales'] ?? 0) * $avgProductPrice;
        
        return [
            'month' => date('F Y'),
            'stats' => $stats,
            'performance' => $performance,
            'daily_trends' => $dailyTrends,
            'roi' => [
                'estimated_revenue' => $estimatedRevenue,
                'sales_increase' => $performance['total_sales'] ?? 0,
                'views_increase' => $performance['total_views'] ?? 0,
            ],
        ];
    }
    
    /**
     * Constrói HTML do relatório diário
     */
    private function buildDailyReportHtml(array $data): string
    {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .metric-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; }
        .metric-value { font-size: 32px; font-weight: bold; color: #667eea; }
        .metric-label { color: #666; font-size: 14px; }
        .alert-high { background: #fff3cd; border-left-color: #ff6b6b; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; }
        .btn { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔥 SEO Killer - Relatório Diário</h1>
            <p>' . $data['date'] . '</p>
        </div>
        
        <div class="content">
            <h2>📊 Resumo do Dia</h2>
            
            <div class="metric-box">
                <div class="metric-value">' . ($data['optimizations']['total'] ?? 0) . '</div>
                <div class="metric-label">Otimizações Realizadas</div>
            </div>
            
            <div class="metric-box">
                <div class="metric-value">+' . round($data['optimizations']['avg_improvement'] ?? 0, 1) . '</div>
                <div class="metric-label">Pontos de Melhoria Média</div>
            </div>
            
            <div class="metric-box alert-high">
                <div class="metric-value">' . ($data['alerts']['total'] ?? 0) . '</div>
                <div class="metric-label">Alertas de Concorrentes (' . ($data['alerts']['high_priority'] ?? 0) . ' alta prioridade)</div>
            </div>
            
            <h3>🏆 Top 3 Otimizações</h3>';
        
        foreach ($data['top_optimizations'] as $opt) {
            $html .= '<div class="metric-box">
                <strong>' . htmlspecialchars($opt['item_id']) . '</strong><br>
                Tipo: ' . htmlspecialchars($opt['optimization_type']) . '<br>
                Melhoria: +' . round($opt['score_improvement'], 1) . ' pontos
            </div>';
        }
        
        $html .= '
            <a href="https://eskill.com.br/dashboard/seo-killer" class="btn">Ver Dashboard Completo</a>
        </div>
        
        <div class="footer">
            <p>© 2025 Eskill - Mercado Livre Manager</p>
            <p>Este é um relatório automatizado. Para dúvidas, contate o suporte.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Constrói HTML do relatório semanal
     */
    private function buildWeeklyReportHtml(array $data): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 650px; margin: 20px auto; background: white; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 40px; text-align: center; }
        .content { padding: 30px; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: bold; color: #11998e; }
        .stat-label { color: #666; font-size: 13px; margin-top: 5px; }
        .performer-item { background: #fff; border: 1px solid #e0e0e0; padding: 15px; margin: 10px 0; border-radius: 6px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 SEO Killer - Relatório Semanal</h1>
            <p>' . $data['week_start'] . ' - ' . $data['week_end'] . '</p>
        </div>
        
        <div class="content">
            <h2>🎯 Performance da Semana</h2>
            
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-value">' . ($data['optimizations']['total'] ?? 0) . '</div>
                    <div class="stat-label">Total de Otimizações</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">+' . round($data['optimizations']['avg_improvement'] ?? 0, 1) . '</div>
                    <div class="stat-label">Melhoria Média</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">+' . ($data['performance']['total_views_increase'] ?? 0) . '</div>
                    <div class="stat-label">Aumento de Visualizações</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">+' . ($data['performance']['total_sales_increase'] ?? 0) . '</div>
                    <div class="stat-label">Aumento de Vendas</div>
                </div>
            </div>
            
            <h3>🏆 Top 5 Produtos</h3>';
        
        foreach ($data['top_performers'] as $performer) {
            $html .= '<div class="performer-item">
                <strong>' . htmlspecialchars($performer['item_id']) . '</strong><br>
                Melhoria Total: +' . round($performer['total_improvement'], 1) . ' pontos<br>
                Otimizações: ' . $performer['optimization_count'] . 'x
            </div>';
        }
        
        $html .= '</div>
        <div class="footer">
            <p>© 2025 Eskill - SEO Killer v1.8.0</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Constrói HTML do relatório mensal
     */
    private function buildMonthlyReportHtml(array $data): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 700px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 50px; text-align: center; }
        .content { padding: 40px; }
        .highlight { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; text-align: center; margin: 20px 0; }
        .roi-value { font-size: 48px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Relatório Mensal</h1>
            <h2>' . $data['month'] . '</h2>
        </div>
        
        <div class="content">
            <div class="highlight">
                <div class="roi-value">R$ ' . number_format($data['roi']['estimated_revenue'], 2, ',', '.') . '</div>
                <p>Receita Estimada Gerada</p>
            </div>
            
            <h3>📊 Estatísticas do Mês</h3>
            <p><strong>Total de Otimizações:</strong> ' . ($data['stats']['total_optimizations'] ?? 0) . '</p>
            <p><strong>Score Médio:</strong> ' . round($data['performance']['avg_score'] ?? 0) . '/100</p>
            <p><strong>Aumento de Vendas:</strong> +' . ($data['roi']['sales_increase'] ?? 0) . '</p>
            
            <p style="margin-top: 40px; text-align: center;">
                <strong>Relatório completo em anexo (PDF)</strong>
            </p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Salva log de relatório enviado
     */
    private function logReport(string $type, bool $success): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO system_logs (account_id, type, message, metadata, created_at)
            VALUES (:account_id, 'automated_report', :message, :metadata, NOW())
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'message' => ($success ? 'Success' : 'Failed') . " - {$type} report",
            'metadata' => json_encode([
                'report_type' => $type,
                'success' => $success,
                'timestamp' => time(),
            ]),
        ]);
    }
}
