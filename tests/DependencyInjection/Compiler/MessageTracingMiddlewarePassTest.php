<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\DependencyInjection\Compiler;

use MartinKup\OptimizationAdvisorBundle\DependencyInjection\Compiler\MessageTracingMiddlewarePass;
use MartinKup\OptimizationAdvisorBundle\Messenger\Middleware\MessageTracingMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(MessageTracingMiddlewarePass::class)]
#[Group('unit')]
final class MessageTracingMiddlewarePassTest extends TestCase
{
    private MessageTracingMiddlewarePass $pass;

    protected function setUp(): void
    {
        $this->pass = new MessageTracingMiddlewarePass();
    }

    public function testPrependsMiddlewareToAllBuses(): void
    {
        $container = new ContainerBuilder();
        $this->registerBus($container, 'command.bus', [
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => []],
        ]);
        $this->registerBus($container, 'query.bus', [
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => []],
        ]);

        $this->pass->process($container);

        /** @var list<array{id: string}> $commandMiddleware */
        $commandMiddleware = $container->getParameter('command.bus.middleware');
        self::assertSame(MessageTracingMiddleware::class, $commandMiddleware[0]['id']);

        /** @var list<array{id: string}> $queryMiddleware */
        $queryMiddleware = $container->getParameter('query.bus.middleware');
        self::assertSame(MessageTracingMiddleware::class, $queryMiddleware[0]['id']);
    }

    public function testInsertsAfterTraceableMiddleware(): void
    {
        $container = new ContainerBuilder();
        $this->registerBus($container, 'command.bus', [
            ['id' => 'traceable', 'arguments' => []],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => []],
        ]);

        $this->pass->process($container);

        /** @var list<array{id: string}> $middleware */
        $middleware = $container->getParameter('command.bus.middleware');

        self::assertSame('traceable', $middleware[0]['id']);
        self::assertSame(MessageTracingMiddleware::class, $middleware[1]['id']);
        self::assertSame('add_bus_name_stamp_middleware', $middleware[2]['id']);
    }

    public function testDoesNotDuplicateWhenAlreadyPresent(): void
    {
        $container = new ContainerBuilder();
        $this->registerBus($container, 'command.bus', [
            ['id' => MessageTracingMiddleware::class, 'arguments' => []],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => []],
        ]);

        $this->pass->process($container);

        /** @var list<array{id: string}> $middleware */
        $middleware = $container->getParameter('command.bus.middleware');

        $count = array_reduce(
            $middleware,
            static fn (int $carry, array $entry): int => $entry['id'] === MessageTracingMiddleware::class
                ? $carry + 1
                : $carry,
            0,
        );

        self::assertSame(1, $count);
    }

    public function testSkipsBusWithoutMiddlewareParameter(): void
    {
        $container = new ContainerBuilder();

        $definition = new Definition('stdClass');
        $definition->addTag('messenger.bus');
        $container->setDefinition('command.bus', $definition);

        $this->pass->process($container);

        self::assertFalse($container->hasParameter('command.bus.middleware'));
    }

    public function testDoesNothingWhenNoBusesExist(): void
    {
        $container = new ContainerBuilder();

        $this->pass->process($container);

        self::assertSame([], $container->findTaggedServiceIds('messenger.bus'));
    }

    public function testHandlesMultipleBusesWithMixedState(): void
    {
        $container = new ContainerBuilder();
        $this->registerBus($container, 'command.bus', [
            ['id' => MessageTracingMiddleware::class, 'arguments' => []],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => []],
        ]);
        $this->registerBus($container, 'query.bus', [
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => []],
        ]);

        $this->pass->process($container);

        /** @var list<array{id: string}> $commandMiddleware */
        $commandMiddleware = $container->getParameter('command.bus.middleware');
        self::assertCount(2, $commandMiddleware);

        /** @var list<array{id: string}> $queryMiddleware */
        $queryMiddleware = $container->getParameter('query.bus.middleware');
        self::assertCount(2, $queryMiddleware);
        self::assertSame(MessageTracingMiddleware::class, $queryMiddleware[0]['id']);
    }

    /** @param list<array{id: string, arguments?: list<mixed>}> $middleware */
    private function registerBus(ContainerBuilder $container, string $busId, array $middleware): void
    {
        $definition = new Definition('stdClass');
        $definition->addTag('messenger.bus');
        $container->setDefinition($busId, $definition);
        $container->setParameter($busId . '.middleware', $middleware);
    }
}
