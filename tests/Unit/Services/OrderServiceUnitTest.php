<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\OrderService;
use ReflectionClass;
use ReflectionMethod;

/**
 * Testes unitarios puros para OrderService (sem DB, sem mocks)
 *
 * @covers \App\Services\OrderService
 */
class OrderServiceUnitTest extends TestCase
{
    private OrderService $svc;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(OrderService::class);
        $this->svc = $ref->newInstanceWithoutConstructor();

        // Initialize accountId to null (typed property must be set before access)
        $prop = $ref->getProperty('accountId');
        $prop->setAccessible(true);
        $prop->setValue($this->svc, null);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $rm = new ReflectionMethod(OrderService::class, $method);
        $rm->setAccessible(true);
        return $rm->invoke($this->svc, ...$args);
    }

    // normalizeDateForMl

    public function testNormalizeDateForMlAddsStartOfDay(): void
    {
        $result = $this->invoke('normalizeDateForMl', '2024-01-15', false);
        $this->assertSame('2024-01-15T00:00:00.000-03:00', $result);
    }

    public function testNormalizeDateForMlAddsEndOfDay(): void
    {
        $result = $this->invoke('normalizeDateForMl', '2024-01-15', true);
        $this->assertSame('2024-01-15T23:59:59.000-03:00', $result);
    }

    public function testNormalizeDateForMlSkipsWhenAlreadyFormatted(): void
    {
        $input = '2024-01-15T10:30:00.000-03:00';
        $result = $this->invoke('normalizeDateForMl', $input, false);
        $this->assertSame($input, $result);
    }

    public function testNormalizeDateForMlSkipsEndOfDayWhenAlreadyFormatted(): void
    {
        $input = '2024-06-01T18:00:00.000-03:00';
        $result = $this->invoke('normalizeDateForMl', $input, true);
        $this->assertSame($input, $result);
    }

    public function testNormalizeDateForMlDefaultEndOfDayIsFalse(): void
    {
        $result = $this->invoke('normalizeDateForMl', '2025-12-31');
        $this->assertSame('2025-12-31T00:00:00.000-03:00', $result);
    }

    public function testNormalizeDateForMlTrimsTrailingSpaces(): void
    {
        $result = $this->invoke('normalizeDateForMl', '2024-03-10  ', false);
        $this->assertSame('2024-03-10T00:00:00.000-03:00', $result);
    }

    // normalizeDateForLocalFilter

    public function testNormalizeDateForLocalFilterAddsStartOfDay(): void
    {
        $result = $this->invoke('normalizeDateForLocalFilter', '2024-01-15', false);
        $this->assertSame('2024-01-15 00:00:00', $result);
    }

    public function testNormalizeDateForLocalFilterAddsEndOfDay(): void
    {
        $result = $this->invoke('normalizeDateForLocalFilter', '2024-01-15', true);
        $this->assertSame('2024-01-15 23:59:59', $result);
    }

    public function testNormalizeDateForLocalFilterSkipsWhenTimePresent(): void
    {
        $input = '2024-01-15 14:30:00';
        $result = $this->invoke('normalizeDateForLocalFilter', $input, false);
        $this->assertSame($input, $result);
    }

    public function testNormalizeDateForLocalFilterSkipsEndWhenTimePresent(): void
    {
        $input = '2024-06-01 08:15:30';
        $result = $this->invoke('normalizeDateForLocalFilter', $input, true);
        $this->assertSame($input, $result);
    }

    // formatMlApiErrorMessage

    public function testFormatMlApiErrorMessageBasicPrefix(): void
    {
        $result = $this->invoke('formatMlApiErrorMessage', [], 'Erro na API');
        $this->assertSame('Erro na API', $result);
    }

    public function testFormatMlApiErrorMessageWithMessage(): void
    {
        $error = ['message' => 'Token expired'];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'Auth fail');
        $this->assertSame('Auth fail: Token expired', $result);
    }

    public function testFormatMlApiErrorMessageWithErrorKey(): void
    {
        $error = ['error' => 'not_found'];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'Falha');
        $this->assertSame('Falha: not_found', $result);
    }

    public function testFormatMlApiErrorMessagePrefersMessageOverError(): void
    {
        $error = ['message' => 'primary msg', 'error' => 'fallback'];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'Prefix');
        $this->assertSame('Prefix: primary msg', $result);
    }

    public function testFormatMlApiErrorMessageWithStatus(): void
    {
        $error = ['message' => 'Bad request', 'status' => 400];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'API Error');
        $this->assertSame('API Error: Bad request (HTTP 400)', $result);
    }

    public function testFormatMlApiErrorMessageWithEndpoint(): void
    {
        $error = ['message' => 'Not found', 'endpoint' => '/orders/123'];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'GET');
        $this->assertSame('GET: Not found [/orders/123]', $result);
    }

    public function testFormatMlApiErrorMessageFull(): void
    {
        $error = [
            'message' => 'Forbidden',
            'status' => 403,
            'endpoint' => '/orders/search',
        ];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'ML API');
        $this->assertSame('ML API: Forbidden (HTTP 403) [/orders/search]', $result);
    }

    public function testFormatMlApiErrorMessageIgnoresEmptyMessage(): void
    {
        $error = ['message' => '', 'status' => 500];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'Erro');
        $this->assertSame('Erro (HTTP 500)', $result);
    }

    public function testFormatMlApiErrorMessageIgnoresZeroStatus(): void
    {
        $error = ['message' => 'Fail', 'status' => 0];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'Test');
        $this->assertSame('Test: Fail', $result);
    }

    public function testFormatMlApiErrorMessageIgnoresEmptyEndpoint(): void
    {
        $error = ['message' => 'Something', 'endpoint' => ''];
        $result = $this->invoke('formatMlApiErrorMessage', $error, 'X');
        $this->assertSame('X: Something', $result);
    }

    // emptyOrdersPayload

    public function testEmptyOrdersPayloadDefaults(): void
    {
        $result = $this->invoke('emptyOrdersPayload', 50, 1, 0);
        $this->assertSame([
            'success' => false,
            'results' => [],
            'orders' => [],
            'page' => 1,
            'pages' => 1,
            'limit' => 50,
            'total' => 0,
            'has_more' => false,
            'offset' => 0,
        ], $result);
    }

    public function testEmptyOrdersPayloadCustomPagination(): void
    {
        $result = $this->invoke('emptyOrdersPayload', 20, 3, 40);
        $this->assertSame(20, $result['limit']);
        $this->assertSame(3, $result['page']);
        $this->assertSame(40, $result['offset']);
        $this->assertSame(0, $result['total']);
        $this->assertFalse($result['success']);
    }

    public function testEmptyOrdersPayloadMergesExtra(): void
    {
        $extra = ['warning' => 'Seller not found', 'reason' => 'test'];
        $result = $this->invoke('emptyOrdersPayload', 10, 1, 0, $extra);
        $this->assertSame('Seller not found', $result['warning']);
        $this->assertSame('test', $result['reason']);
        $this->assertFalse($result['success']);
    }

    public function testEmptyOrdersPayloadExtraOverridesBase(): void
    {
        $extra = ['success' => true, 'total' => 999];
        $result = $this->invoke('emptyOrdersPayload', 10, 1, 0, $extra);
        $this->assertTrue($result['success']);
        $this->assertSame(999, $result['total']);
    }

    // normalizeOrderSummary

    public function testNormalizeOrderSummaryFullData(): void
    {
        $order = [
            'id' => 12345,
            'status' => 'paid',
            'total_amount' => 150.50,
            'date_created' => '2024-01-15T10:00:00.000-03:00',
            'buyer' => ['id' => 99, 'nickname' => 'BUYER'],
            'order_items' => [['item' => ['id' => 'MLB1']]],
            'shipping' => ['id' => 555],
            'payments' => [['id' => 777]],
        ];
        $result = $this->invoke('normalizeOrderSummary', $order);

        $this->assertSame(12345, $result['id']);
        $this->assertSame('paid', $result['status']);
        $this->assertSame(150.50, $result['total_amount']);
        $this->assertSame('2024-01-15T10:00:00.000-03:00', $result['date_created']);
        $this->assertSame(['id' => 99, 'nickname' => 'BUYER'], $result['buyer']);
        $this->assertCount(1, $result['order_items']);
        $this->assertSame(['id' => 555], $result['shipping']);
        $this->assertCount(1, $result['payments']);
        $this->assertNull($result['account_nickname']);
    }

    public function testNormalizeOrderSummaryEmptyData(): void
    {
        $result = $this->invoke('normalizeOrderSummary', []);
        $this->assertNull($result['id']);
        $this->assertNull($result['status']);
        $this->assertSame(0.0, $result['total_amount']);
        $this->assertNull($result['date_created']);
        $this->assertNull($result['buyer']);
        $this->assertSame([], $result['order_items']);
        $this->assertNull($result['shipping']);
        $this->assertSame([], $result['payments']);
        $this->assertNull($result['account_nickname']);
    }

    public function testNormalizeOrderSummaryPartialData(): void
    {
        $order = [
            'id' => 999,
            'status' => 'cancelled',
        ];
        $result = $this->invoke('normalizeOrderSummary', $order);
        $this->assertSame(999, $result['id']);
        $this->assertSame('cancelled', $result['status']);
        $this->assertSame(0.0, $result['total_amount']);
        $this->assertSame([], $result['order_items']);
        $this->assertSame([], $result['payments']);
    }

    public function testNormalizeOrderSummaryCastsTotalAmountToFloat(): void
    {
        $order = ['total_amount' => '299.90'];
        $result = $this->invoke('normalizeOrderSummary', $order);
        $this->assertIsFloat($result['total_amount']);
        $this->assertSame(299.90, $result['total_amount']);
    }

    public function testNormalizeOrderSummaryTotalAmountZeroWhenMissing(): void
    {
        $result = $this->invoke('normalizeOrderSummary', ['id' => 1]);
        $this->assertSame(0.0, $result['total_amount']);
    }
}
