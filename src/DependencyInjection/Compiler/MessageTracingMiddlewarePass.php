<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\DependencyInjection\Compiler;

use MartinKup\OptimizationAdvisorBundle\Messenger\Middleware\MessageTracingMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MessageTracingMiddlewarePass implements CompilerPassInterface
{
    private const string TRACEABLE_MIDDLEWARE_ID = 'traceable';

    public function process(ContainerBuilder $container): void
    {
        $busIds = array_keys($container->findTaggedServiceIds('messenger.bus'));

        foreach ($busIds as $busId) {
            $parameterName = $busId . '.middleware';

            if (!$container->hasParameter($parameterName)) {
                continue;
            }

            /** @var list<array{id: string, arguments?: list<mixed>}> $middleware */
            $middleware = $container->getParameter($parameterName);

            if ($this->alreadyRegistered($middleware)) {
                continue;
            }

            $entry = ['id' => MessageTracingMiddleware::class, 'arguments' => []];
            $insertPosition = $this->findInsertPosition($middleware);

            array_splice($middleware, $insertPosition, 0, [$entry]);
            $container->setParameter($parameterName, $middleware);
        }
    }

    /** @param list<array{id: string, arguments?: list<mixed>}> $middleware */
    private function alreadyRegistered(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if ($entry['id'] === MessageTracingMiddleware::class) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{id: string, arguments?: list<mixed>}> $middleware */
    private function findInsertPosition(array $middleware): int
    {
        foreach ($middleware as $index => $entry) {
            if ($entry['id'] === self::TRACEABLE_MIDDLEWARE_ID) {
                return $index + 1;
            }
        }

        return 0;
    }
}
