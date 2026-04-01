<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

/**
 * Builds an auditable trail per ML order by joining data from ml_orders,
 * ml_payments, ml_claims, and ml_feedback.
 *
 * Enables reconciliation of financial and post-sale events per order/account.
 *
 * "Pronto quando: eventos financeiros e de pós-venda estiverem reconciliados."
 * (ML-BLG-060)
 */
final class MlOrderAuditService
{
    public function __construct(private readonly \PDO $db) {}

    /**
     * Returns a complete audit trail for the given ML order ID.
     *
     * @param string   $orderId   ML order ID (ml_orders.ml_order_id).
     * @param int|null $accountId Scope to a specific ML account (optional).
     *
     * @return array{
     *   order: array<string, mixed>,
     *   payments: list<array<string, mixed>>,
     *   claims: list<array<string, mixed>>,
     *   feedback: list<array<string, mixed>>,
     *   reconciliation: array{payments_count: int, total_paid: float, claims_count: int, has_open_claim: bool, status: string},
     *   generated_at: string
     * }|null  null if the order is not found in ml_orders.
     */
    public function getOrderTrail(string $orderId, ?int $accountId = null): ?array
    {
        $order = $this->fetchOrder($orderId, $accountId);
        if ($order === null) {
            return null;
        }

        $payments       = $this->fetchPayments($orderId, $accountId);
        $claims         = $this->fetchClaims($orderId, $accountId);
        $feedback       = $this->fetchFeedback($orderId, $accountId);
        $reconciliation = $this->buildReconciliation($order, $payments, $claims);

        return [
            'order'          => $order,
            'payments'       => $payments,
            'claims'         => $claims,
            'feedback'       => $feedback,
            'reconciliation' => $reconciliation,
            'generated_at'   => date('c'),
        ];
    }

    // -----------------------------------------------------------------------
    // Private DB fetch helpers
    // -----------------------------------------------------------------------

    /**
     * Fetches the ml_orders row for the given order ID.
     *
     * @return array<string, mixed>|null  null when the order does not exist.
     */
    private function fetchOrder(string $orderId, ?int $accountId): ?array
    {
        $parts  = ['ml_order_id = :order_id'];
        $params = [':order_id' => $orderId];

        if ($accountId !== null) {
            $parts[]               = 'ml_account_id = :account_id';
            $params[':account_id'] = $accountId;
        }

        $where = implode(' AND ', $parts);
        $stmt  = $this->db->prepare(
            "SELECT ml_order_id, ml_account_id, status, total_amount, date_created, synced_at
             FROM ml_orders WHERE {$where} LIMIT 1"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Fetches ml_payments rows linked to this order.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchPayments(string $orderId, ?int $accountId): array
    {
        $parts  = ['order_id = :order_id'];
        $params = [':order_id' => $orderId];

        if ($accountId !== null) {
            $parts[]               = 'ml_account_id = :account_id';
            $params[':account_id'] = $accountId;
        }

        $where = implode(' AND ', $parts);
        $stmt  = $this->db->prepare(
            "SELECT payment_id, status, amount, currency_id, payment_method, paid_at
             FROM ml_payments WHERE {$where} ORDER BY paid_at ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetches ml_claims rows linked to this order.
     *
     * Note: ml_claims uses column `account_id` (not `ml_account_id`).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchClaims(string $orderId, ?int $accountId): array
    {
        $parts  = ['order_id = :order_id'];
        $params = [':order_id' => $orderId];

        if ($accountId !== null) {
            $parts[]               = 'account_id = :account_id';
            $params[':account_id'] = $accountId;
        }

        $where = implode(' AND ', $parts);
        $stmt  = $this->db->prepare(
            "SELECT id, type, status, stage, reason, amount, date_created, last_updated
             FROM ml_claims WHERE {$where} ORDER BY date_created ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetches ml_feedback rows linked to this order.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchFeedback(string $orderId, ?int $accountId): array
    {
        $parts  = ['order_id = :order_id'];
        $params = [':order_id' => $orderId];

        if ($accountId !== null) {
            $parts[]               = 'ml_account_id = :account_id';
            $params[':account_id'] = $accountId;
        }

        $where = implode(' AND ', $parts);
        $stmt  = $this->db->prepare(
            "SELECT feedback_id, rating, message, status, fulfilled, feedback_date
             FROM ml_feedback WHERE {$where} ORDER BY feedback_date ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------------
    // Reconciliation logic
    // -----------------------------------------------------------------------

    /**
     * Builds the reconciliation block for an order.
     *
     * @param array<string, mixed>          $order
     * @param list<array<string, mixed>>    $payments
     * @param list<array<string, mixed>>    $claims
     * @return array{payments_count: int, total_paid: float, claims_count: int, has_open_claim: bool, status: string}
     */
    private function buildReconciliation(array $order, array $payments, array $claims): array
    {
        $totalPaid    = $this->sumApprovedPayments($payments);
        $hasOpenClaim = $this->hasOpenClaims($claims);

        return [
            'payments_count' => count($payments),
            'total_paid'     => $totalPaid,
            'claims_count'   => count($claims),
            'has_open_claim' => $hasOpenClaim,
            'status'         => $this->deriveReconciliationStatus($order, $totalPaid, $hasOpenClaim),
        ];
    }

    /**
     * Sums the amounts of payments with status 'approved'.
     *
     * @param list<array<string, mixed>> $payments
     */
    private function sumApprovedPayments(array $payments): float
    {
        $total = 0.0;
        foreach ($payments as $p) {
            if ((string)$p['status'] === 'approved') {
                $total += (float)$p['amount'];
            }
        }
        return $total;
    }

    /**
     * Returns true if any claim has a status that is not closed/resolved/cancelled.
     *
     * @param list<array<string, mixed>> $claims
     */
    private function hasOpenClaims(array $claims): bool
    {
        foreach ($claims as $c) {
            if (!in_array((string)$c['status'], ['closed', 'resolved', 'cancelled'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Derives the overall reconciliation status for the order.
     *
     * @param array<string, mixed> $order
     */
    private function deriveReconciliationStatus(array $order, float $totalPaid, bool $hasOpenClaim): string
    {
        if ($hasOpenClaim) {
            return 'has_open_claims';
        }

        $orderAmount = (float)$order['total_amount'];
        if ($orderAmount > 0.0 && $totalPaid <= 0.0) {
            return 'pending_payment';
        }

        return 'reconciled';
    }
}
