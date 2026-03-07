<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Messenger\Middleware;

use MartinKup\OptimizationAdvisorBundle\Messenger\TraceRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessageTracingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TraceRegistry $traceRegistry)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $start = microtime(true);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } finally {
            $handledStamp = $envelope->last(HandledStamp::class);
            $isHandledSync = $handledStamp instanceof HandledStamp;
            $handlerClass = $handledStamp instanceof HandledStamp
                ? $handledStamp->getHandlerName()
                : null;

            $messageClass = $envelope->getMessage()::class;
            $pos = strrpos($messageClass, '\\');
            $messageShort = $pos !== false ? substr($messageClass, $pos + 1) : $messageClass;

            $this->traceRegistry->append([
                'is_handled_sync'     => $isHandledSync,
                'handler_class'       => $handlerClass,
                'handler_duration_ms' => (microtime(true) - $start) * 1000.0,
                'message_short'       => $messageShort,
            ]);
        }

        return $envelope;
    }
}
