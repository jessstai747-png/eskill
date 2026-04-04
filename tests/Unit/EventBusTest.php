<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\EventBus;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Core\EventBus
 */
class EventBusTest extends TestCase
{
    private EventBus $bus;

    protected function setUp(): void
    {
        $this->bus = new EventBus();
    }

    // ─── listen / dispatch ───────────────────────────────────────────────────

    public function testListenerIsCalledOnDispatch(): void
    {
        $called = false;
        $this->bus->listen('test.event', function () use (&$called) {
            $called = true;
        });

        $this->bus->dispatch('test.event');
        $this->assertTrue($called);
    }

    public function testPayloadIsPassedToListener(): void
    {
        $received = null;
        $this->bus->listen('item.optimized', function (array $payload) use (&$received) {
            $received = $payload;
        });

        $this->bus->dispatch('item.optimized', ['item_id' => 42, 'score' => 98]);

        $this->assertEquals(['item_id' => 42, 'score' => 98], $received);
    }

    public function testNoListenersDoesNotThrow(): void
    {
        // Should not throw even when no listeners are registered
        $this->bus->dispatch('unregistered.event', ['data' => 'value']);
        $this->assertTrue(true); // If we reached here, no exception was thrown
    }

    public function testMultipleListenersAllReceiveEvent(): void
    {
        $log = [];
        $this->bus->listen('multi', function () use (&$log) { $log[] = 'A'; });
        $this->bus->listen('multi', function () use (&$log) { $log[] = 'B'; });
        $this->bus->listen('multi', function () use (&$log) { $log[] = 'C'; });

        $this->bus->dispatch('multi');

        $this->assertCount(3, $log);
        $this->assertContains('A', $log);
        $this->assertContains('B', $log);
        $this->assertContains('C', $log);
    }

    // ─── priority ────────────────────────────────────────────────────────────

    public function testHigherPriorityListenerRunsFirst(): void
    {
        $order = [];
        $this->bus->listen('ordered', function () use (&$order) { $order[] = 'low'; }, 0);
        $this->bus->listen('ordered', function () use (&$order) { $order[] = 'high'; }, 10);
        $this->bus->listen('ordered', function () use (&$order) { $order[] = 'medium'; }, 5);

        $this->bus->dispatch('ordered');

        $this->assertEquals(['high', 'medium', 'low'], $order);
    }

    // ─── hasListeners ────────────────────────────────────────────────────────

    public function testHasListenersReturnsTrueAfterRegistration(): void
    {
        $this->assertFalse($this->bus->hasListeners('my.event'));

        $this->bus->listen('my.event', fn() => null);

        $this->assertTrue($this->bus->hasListeners('my.event'));
    }

    public function testHasListenersReturnsFalseForUnregisteredEvent(): void
    {
        $this->assertFalse($this->bus->hasListeners('does.not.exist'));
    }

    // ─── forget ──────────────────────────────────────────────────────────────

    public function testForgetRemovesListenersForEvent(): void
    {
        $this->bus->listen('to.remove', fn() => null);
        $this->assertTrue($this->bus->hasListeners('to.remove'));

        $this->bus->forget('to.remove');

        $this->assertFalse($this->bus->hasListeners('to.remove'));
    }

    public function testForgetDoesNotAffectOtherEvents(): void
    {
        $this->bus->listen('keep.this', fn() => null);
        $this->bus->listen('remove.this', fn() => null);

        $this->bus->forget('remove.this');

        $this->assertTrue($this->bus->hasListeners('keep.this'));
        $this->assertFalse($this->bus->hasListeners('remove.this'));
    }

    public function testForgetAllClearsAllListeners(): void
    {
        $this->bus->listen('event.a', fn() => null);
        $this->bus->listen('event.b', fn() => null);

        $this->bus->forgetAll();

        $this->assertFalse($this->bus->hasListeners('event.a'));
        $this->assertFalse($this->bus->hasListeners('event.b'));
    }

    // ─── fail-safe behavior ──────────────────────────────────────────────────

    public function testFailingListenerDoesNotPreventOtherListeners(): void
    {
        $secondCalled = false;

        $this->bus->listen('risky', function () {
            throw new \RuntimeException('Listener falhou!');
        });
        $this->bus->listen('risky', function () use (&$secondCalled) {
            $secondCalled = true;
        });

        $this->bus->dispatch('risky');

        $this->assertTrue($secondCalled, 'Second listener must run even if first throws');
    }

    // ─── subscriber ──────────────────────────────────────────────────────────

    public function testSubscriberIsRegistered(): void
    {
        $log = [];

        $subscriber = new class ($log) {
            /** @var array<int, string> */
            private array $log;

            public function __construct(array &$log)
            {
                $this->log = &$log;
            }

            public function getSubscribedEvents(): array
            {
                return [
                    'order.placed'    => 'onOrderPlaced',
                    'order.cancelled' => ['onOrderCancelled', 10],
                ];
            }

            public function onOrderPlaced(array $payload): void
            {
                $this->log[] = 'placed:' . ($payload['id'] ?? '?');
            }

            public function onOrderCancelled(array $payload): void
            {
                $this->log[] = 'cancelled:' . ($payload['id'] ?? '?');
            }
        };

        $this->bus->subscribe($subscriber);

        $this->bus->dispatch('order.placed', ['id' => 1]);
        $this->bus->dispatch('order.cancelled', ['id' => 2]);

        $this->assertContains('placed:1', $log);
        $this->assertContains('cancelled:2', $log);
    }

    public function testSubscriberWithoutGetSubscribedEventsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->bus->subscribe(new \stdClass());
    }
}
