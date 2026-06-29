<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Services;

use App\Contracts\Billing\OutboxEventHandler;
use App\Models\Billing\BillingOutboxEvent;
use App\Services\Billing\OutboxEventDispatcher;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class OutboxEventDispatcherTest extends TestCase
{
    #[Test]
    public function it_dispatches_a_known_event_to_its_handler(): void
    {
        $container = new Container;
        $handler = new class implements OutboxEventHandler
        {
            public ?BillingOutboxEvent $received = null;

            public function handle(BillingOutboxEvent $event): void
            {
                $this->received = $event;
            }
        };
        $container->instance($handler::class, $handler);

        $dispatcher = new OutboxEventDispatcher(
            handlers: ['subscription.state-changed' => $handler::class],
            container: $container,
        );

        $row = new BillingOutboxEvent;
        $row->id = '01J0000000000000000000ABCD';
        $row->event_type = 'subscription.state-changed';

        $dispatcher->dispatch($row);

        $this->assertSame($row, $handler->received);
    }

    #[Test]
    public function it_treats_unmapped_event_as_silent_noop(): void
    {
        $container = new Container;
        $dispatcher = new OutboxEventDispatcher(handlers: [], container: $container);

        $row = new BillingOutboxEvent;
        $row->id = '01J0000000000000000000EFGH';
        $row->event_type = 'subscription.unknown';

        $dispatcher->dispatch($row);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function handler_exceptions_propagate(): void
    {
        $container = new Container;
        $handler = new class implements OutboxEventHandler
        {
            public function handle(BillingOutboxEvent $event): void
            {
                throw new RuntimeException('boom');
            }
        };
        $container->instance($handler::class, $handler);

        $dispatcher = new OutboxEventDispatcher(
            handlers: ['subscription.state-changed' => $handler::class],
            container: $container,
        );

        $row = new BillingOutboxEvent;
        $row->id = '01J0000000000000000000IJKL';
        $row->event_type = 'subscription.state-changed';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $dispatcher->dispatch($row);
    }

    #[Test]
    public function it_dispatches_to_multiple_handlers_in_order(): void
    {
        $container = new Container;

        $h1 = new class implements OutboxEventHandler
        {
            /** @var list<string> */
            public array $log = [];

            public function handle(BillingOutboxEvent $event): void
            {
                $this->log[] = 'h1';
            }
        };
        $h2 = new class implements OutboxEventHandler
        {
            /** @var list<string> */
            public array $log = [];

            public function handle(BillingOutboxEvent $event): void
            {
                $this->log[] = 'h2';
            }
        };

        $container->instance($h1::class, $h1);
        $container->instance($h2::class, $h2);

        $dispatcher = new OutboxEventDispatcher(
            handlers: ['subscription.state-changed' => [$h1::class, $h2::class]],
            container: $container,
        );

        $row = new BillingOutboxEvent;
        $row->id = '01J0000000000000000000MNOP';
        $row->event_type = 'subscription.state-changed';

        $dispatcher->dispatch($row);

        $this->assertSame(['h1'], $h1->log);
        $this->assertSame(['h2'], $h2->log);
    }

    #[Test]
    public function exception_in_first_handler_stops_the_chain(): void
    {
        $container = new Container;

        $h1 = new class implements OutboxEventHandler
        {
            public function handle(BillingOutboxEvent $event): void
            {
                throw new RuntimeException('h1 boom');
            }
        };
        $h2 = new class implements OutboxEventHandler
        {
            public bool $invoked = false;

            public function handle(BillingOutboxEvent $event): void
            {
                $this->invoked = true;
            }
        };

        $container->instance($h1::class, $h1);
        $container->instance($h2::class, $h2);

        $dispatcher = new OutboxEventDispatcher(
            handlers: ['subscription.state-changed' => [$h1::class, $h2::class]],
            container: $container,
        );

        $row = new BillingOutboxEvent;
        $row->id = '01J0000000000000000000QRST';
        $row->event_type = 'subscription.state-changed';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('h1 boom');

        try {
            $dispatcher->dispatch($row);
        } finally {
            $this->assertFalse($h2->invoked, 'second handler must not be invoked');
        }
    }
}
