<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\Event;
use App\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for App\Events\EventDispatcher
 *
 * Listeners live in a static registry, so each test dispatches its own
 * anonymous event class and never touches the real domain events.
 */
class EventDispatcherTest extends TestCase
{
    public function testDispatchCallsListenersInOrderWithEvent(): void
    {
        $calls = [];
        $event = new class (['order_id' => 42]) extends Event {};

        EventDispatcher::addListener($event::class, function ($e) use (&$calls) {
            $calls[] = ['first', $e->payload['order_id']];
        });
        EventDispatcher::addListener($event::class, function ($e) use (&$calls) {
            $calls[] = ['second', $e->payload['order_id']];
        });

        EventDispatcher::dispatch($event);

        $this->assertSame([['first', 42], ['second', 42]], $calls);
    }

    public function testListenerExceptionDoesNotStopOtherListeners(): void
    {
        $reached = false;
        $event = new class extends Event {};
        $previousLog = ini_set('error_log', '/dev/null');

        EventDispatcher::addListener($event::class, function () {
            throw new \RuntimeException('Telegram is down');
        });
        EventDispatcher::addListener($event::class, function () use (&$reached) {
            $reached = true;
        });

        EventDispatcher::dispatch($event);
        ini_set('error_log', (string) $previousLog);

        $this->assertTrue($reached);
    }

    public function testDispatchWithoutListenersDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();
        EventDispatcher::dispatch(new \stdClass());
    }
}
