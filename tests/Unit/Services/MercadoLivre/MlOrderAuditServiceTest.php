<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\MercadoLivre\MlOrderAuditService;
use Tests\TestCase;

/**
 * @covers \App\Services\MercadoLivre\MlOrderAuditService
 */
class MlOrderAuditServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Builds a PDOStatement mock that returns $row from fetch() once.
     *
     * @param array<string, mixed>|false $row
     */
    private function buildFetchStmt(array|false $row): \PDOStatement
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        return $stmt;
    }

    /**
     * Builds a PDOStatement mock that returns $rows from fetchAll() once.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function buildFetchAllStmt(array $rows): \PDOStatement
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        return $stmt;
    }

    /**
     * Builds a PDO mock where getOrderTrail() finds no order (1 prepare call).
     */
    private function buildPdoMockNoOrder(): \PDO
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($this->buildFetchStmt(false));
        return $db;
    }

    /**
     * Builds a PDO mock for a full trail query (4 consecutive prepare calls):
     *   1 → fetchOrder (fetch)
     *   2 → fetchPayments (fetchAll)
     *   3 → fetchClaims (fetchAll)
     *   4 → fetchFeedback (fetchAll)
     *
     * @param array<string, mixed>          $orderRow
     * @param list<array<string, mixed>>    $paymentRows
     * @param list<array<string, mixed>>    $claimRows
     * @param list<array<string, mixed>>    $feedbackRows
     */
    private function buildPdoMock(
        array $orderRow,
        array $paymentRows = [],
        array $claimRows   = [],
        array $feedbackRows = []
    ): \PDO {
        $db = $this->createMock(\PDO::class);
        $db->expects($this->exactly(4))
           ->method('prepare')
           ->willReturnOnConsecutiveCalls(
               $this->buildFetchStmt($orderRow),
               $this->buildFetchAllStmt($paymentRows),
               $this->buildFetchAllStmt($claimRows),
               $this->buildFetchAllStmt($feedbackRows)
           );
        return $db;
    }

    /** Returns a minimal valid order row. */
    private function orderRow(string $orderId = '100001', float $total = 199.90): array
    {
        return [
            'ml_order_id'  => $orderId,
            'ml_account_id' => 1,
            'status'        => 'paid',
            'total_amount'  => (string)$total,
            'date_created'  => '2025-04-01 10:00:00',
            'synced_at'     => '2025-04-01 10:01:00',
        ];
    }

    /** Returns a minimal approved payment row. */
    private function approvedPayment(float $amount = 199.90): array
    {
        return [
            'payment_id'     => 'PAY001',
            'status'         => 'approved',
            'amount'         => (string)$amount,
            'currency_id'    => 'BRL',
            'payment_method' => 'credit_card',
            'paid_at'        => '2025-04-01 10:05:00',
        ];
    }

    /** Returns a closed claim row. */
    private function closedClaim(): array
    {
        return [
            'id'           => '999',
            'type'         => 'return',
            'status'       => 'closed',
            'stage'        => 'resolution',
            'reason'       => 'item_not_as_described',
            'amount'       => null,
            'date_created' => '2025-04-02 09:00:00',
            'last_updated' => '2025-04-03 11:00:00',
        ];
    }

    /** Returns an open claim row. */
    private function openClaim(): array
    {
        return array_merge($this->closedClaim(), ['status' => 'under_review']);
    }

    // -----------------------------------------------------------------------
    // getOrderTrail — order-not-found
    // -----------------------------------------------------------------------

    public function testGetOrderTrailReturnsNullWhenOrderNotFound(): void
    {
        $db      = $this->buildPdoMockNoOrder();
        $service = new MlOrderAuditService($db);

        $this->assertNull($service->getOrderTrail('999999'));
    }

    // -----------------------------------------------------------------------
    // getOrderTrail — response structure
    // -----------------------------------------------------------------------

    public function testGetOrderTrailReturnsAllTopLevelKeys(): void
    {
        $db      = $this->buildPdoMock($this->orderRow());
        $service = new MlOrderAuditService($db);

        $trail = $service->getOrderTrail('100001');

        $this->assertArrayHasKey('order', $trail);
        $this->assertArrayHasKey('payments', $trail);
        $this->assertArrayHasKey('claims', $trail);
        $this->assertArrayHasKey('feedback', $trail);
        $this->assertArrayHasKey('reconciliation', $trail);
        $this->assertArrayHasKey('generated_at', $trail);
    }

    public function testGetOrderTrailGeneratedAtIsISO8601(): void
    {
        $db      = $this->buildPdoMock($this->orderRow());
        $service = new MlOrderAuditService($db);

        $trail = $service->getOrderTrail('100001');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            (string)$trail['generated_at']
        );
    }

    public function testGetOrderTrailOrderDataIsReturned(): void
    {
        $order   = $this->orderRow('123456', 299.00);
        $db      = $this->buildPdoMock($order);
        $service = new MlOrderAuditService($db);

        $trail = $service->getOrderTrail('123456');

        $this->assertSame('123456', $trail['order']['ml_order_id']);
        $this->assertSame('paid', $trail['order']['status']);
    }

    public function testGetOrderTrailEmptyPaymentsClaimsFeedback(): void
    {
        $db      = $this->buildPdoMock($this->orderRow());
        $service = new MlOrderAuditService($db);

        $trail = $service->getOrderTrail('100001');

        $this->assertSame([], $trail['payments']);
        $this->assertSame([], $trail['claims']);
        $this->assertSame([], $trail['feedback']);
    }

    public function testGetOrderTrailFeedbackIsReturned(): void
    {
        $feedback = [['feedback_id' => 'FB01', 'rating' => 5, 'message' => 'Perfeito!', 'status' => 'active', 'fulfilled' => 1, 'feedback_date' => '2025-04-05 08:00:00']];
        $db       = $this->buildPdoMock($this->orderRow(), [], [], $feedback);
        $service  = new MlOrderAuditService($db);

        $trail = $service->getOrderTrail('100001');

        $this->assertCount(1, $trail['feedback']);
        $this->assertSame('FB01', $trail['feedback'][0]['feedback_id']);
        $this->assertSame(5, $trail['feedback'][0]['rating']);
    }

    // -----------------------------------------------------------------------
    // getOrderTrail — reconciliation
    // -----------------------------------------------------------------------

    public function testGetOrderTrailReconciliationReconciledWhenPaymentApproved(): void
    {
        $db      = $this->buildPdoMock($this->orderRow('100001', 199.90), [$this->approvedPayment(199.90)]);
        $service = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertSame('reconciled', $rec['status']);
        $this->assertSame(false, $rec['has_open_claim']);
    }

    public function testGetOrderTrailReconciliationPendingPaymentWhenNoApprovedPayments(): void
    {
        $pendingPayment = array_merge($this->approvedPayment(), ['status' => 'pending']);
        $db             = $this->buildPdoMock($this->orderRow('100001', 199.90), [$pendingPayment]);
        $service        = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertSame('pending_payment', $rec['status']);
        $this->assertSame(0.0, $rec['total_paid']);
    }

    public function testGetOrderTrailReconciliationStatusHasOpenClaimsWhenOpenClaim(): void
    {
        $db      = $this->buildPdoMock($this->orderRow(), [$this->approvedPayment()], [$this->openClaim()]);
        $service = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertSame('has_open_claims', $rec['status']);
        $this->assertTrue($rec['has_open_claim']);
    }

    public function testGetOrderTrailCancelledClaimDoesNotSetHasOpenClaim(): void
    {
        $cancelledClaim = array_merge($this->closedClaim(), ['status' => 'cancelled']);
        $db             = $this->buildPdoMock($this->orderRow(), [$this->approvedPayment()], [$cancelledClaim]);
        $service        = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertFalse($rec['has_open_claim']);
        $this->assertSame('reconciled', $rec['status']);
    }

    public function testGetOrderTrailTotalPaidSumsOnlyApprovedPayments(): void
    {
        $payments = [
            $this->approvedPayment(100.00),
            array_merge($this->approvedPayment(), ['status' => 'rejected', 'amount' => '50.00']),
            $this->approvedPayment(75.00),
        ];
        $db      = $this->buildPdoMock($this->orderRow(), $payments);
        $service = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertEqualsWithDelta(175.0, $rec['total_paid'], 0.001);
    }

    public function testGetOrderTrailReconciliationPaymentsCountIsAccurate(): void
    {
        $payments = [$this->approvedPayment(50.0), $this->approvedPayment(100.0)];
        $db       = $this->buildPdoMock($this->orderRow(), $payments);
        $service  = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertSame(2, $rec['payments_count']);
    }

    public function testGetOrderTrailReconciliationClaimsCountIsAccurate(): void
    {
        $claims = [$this->closedClaim(), $this->closedClaim()];
        $db     = $this->buildPdoMock($this->orderRow(), [], $claims);
        $service = new MlOrderAuditService($db);

        $rec = $service->getOrderTrail('100001')['reconciliation'];

        $this->assertSame(2, $rec['claims_count']);
    }

    // -----------------------------------------------------------------------
    // getOrderTrail — scoping / account filtering
    // -----------------------------------------------------------------------

    public function testGetOrderTrailAccountIdIsPassedToOrderQuery(): void
    {
        $orderStmt = $this->createMock(\PDOStatement::class);
        $orderStmt->method('fetch')->willReturn($this->orderRow());

        $capturedParams = null;
        $orderStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;
                return true;
            });

        $db = $this->createMock(\PDO::class);
        $db->expects($this->exactly(4))
           ->method('prepare')
           ->willReturnOnConsecutiveCalls(
               $orderStmt,
               $this->buildFetchAllStmt([]),
               $this->buildFetchAllStmt([]),
               $this->buildFetchAllStmt([])
           );

        $service = new MlOrderAuditService($db);
        $service->getOrderTrail('100001', 5);

        $this->assertIsArray($capturedParams);
        $this->assertArrayHasKey(':account_id', $capturedParams);
        $this->assertSame(5, $capturedParams[':account_id']);
        $this->assertSame('100001', $capturedParams[':order_id']);
    }

    public function testGetOrderTrailNoAccountIdExcludesAccountFilterFromQuery(): void
    {
        $orderStmt = $this->createMock(\PDOStatement::class);
        $orderStmt->method('fetch')->willReturn($this->orderRow());

        $capturedParams = null;
        $orderStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;
                return true;
            });

        $db = $this->createMock(\PDO::class);
        $db->expects($this->exactly(4))
           ->method('prepare')
           ->willReturnOnConsecutiveCalls(
               $orderStmt,
               $this->buildFetchAllStmt([]),
               $this->buildFetchAllStmt([]),
               $this->buildFetchAllStmt([])
           );

        $service = new MlOrderAuditService($db);
        $service->getOrderTrail('100001', null);

        $this->assertIsArray($capturedParams);
        $this->assertArrayNotHasKey(':account_id', $capturedParams);
        $this->assertSame('100001', $capturedParams[':order_id']);
    }
}
