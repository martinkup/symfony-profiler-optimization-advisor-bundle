<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Messenger\Middleware;

use MartinKup\OptimizationAdvisorBundle\Messenger\Middleware\MessageTracingMiddleware;
use MartinKup\OptimizationAdvisorBundle\Messenger\TraceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Unit tests for MessageTracingMiddleware tracing and TraceRegistry population.
 *
 * Test Coverage:
 * - Sync handler record appended with correct data (HandledStamp present)
 * - Async record appended when no HandledStamp (is_handled_sync=false, handler_class=null)
 * - Message short name extraction from namespaced class
 * - Exception propagation with record still appended
 *
 * @see MessageTracingMiddleware
 */
#[CoversClass(MessageTracingMiddleware::class)]
#[Group('unit')]
final class MessageTracingMiddlewareTest extends TestCase
{
    private TraceRegistry $traceRegistry;

    private MessageTracingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->traceRegistry = new TraceRegistry();
        $this->middleware = new MessageTracingMiddleware($this->traceRegistry);
    }

    public function testAppendsSyncHandlerRecord(): void
    {
        $message = new stdClass();
        $envelope = new Envelope($message);
        $handledStamp = new HandledStamp('result', 'App\\Handler\\TestHandler');
        $returnedEnvelope = $envelope->with($handledStamp);

        $stack = $this->createNextStack($returnedEnvelope);

        $result = $this->middleware->handle($envelope, $stack);

        self::assertSame($returnedEnvelope, $result);

        $records = $this->traceRegistry->getRecords();

        self::assertCount(1, $records);

        $record = $records[1];

        self::assertTrue($record['is_handled_sync']);
        self::assertSame('App\\Handler\\TestHandler', $record['handler_class']);
        self::assertIsFloat($record['handler_duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $record['handler_duration_ms']);
        self::assertSame('stdClass', $record['message_short']);
    }

    public function testAppendsAsyncRecord(): void
    {
        $message = new stdClass();
        $envelope = new Envelope($message);

        $stack = $this->createNextStack($envelope);

        $this->middleware->handle($envelope, $stack);

        $records = $this->traceRegistry->getRecords();

        self::assertCount(1, $records);

        $record = $records[1];

        self::assertFalse($record['is_handled_sync']);
        self::assertNull($record['handler_class']);
        self::assertSame('stdClass', $record['message_short']);
    }

    public function testMessageShortExtractionForNamespacedClass(): void
    {
        $message = new TraceRegistry();
        $envelope = new Envelope($message);

        $stack = $this->createNextStack($envelope);

        $this->middleware->handle($envelope, $stack);

        $records = $this->traceRegistry->getRecords();

        self::assertSame('TraceRegistry', $records[1]['message_short']);
    }

    public function testExceptionPropagatesAndRecordStillAppended(): void
    {
        $message = new stdClass();
        $envelope = new Envelope($message);

        $nextMiddleware = self::createStub(MiddlewareInterface::class);
        $nextMiddleware->method('handle')
            ->willThrowException(new RuntimeException('Test exception'));

        $stack = self::createStub(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        $caught = false;

        try {
            $this->middleware->handle($envelope, $stack);
        } catch (RuntimeException $e) {
            $caught = true;
            self::assertSame('Test exception', $e->getMessage());
        }

        self::assertTrue($caught, 'Exception should have been thrown');

        $records = $this->traceRegistry->getRecords();

        self::assertCount(1, $records);

        $record = $records[1];

        self::assertFalse($record['is_handled_sync']);
        self::assertNull($record['handler_class']);
    }

    private function createNextStack(Envelope $returnedEnvelope): StackInterface
    {
        $nextMiddleware = self::createStub(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($returnedEnvelope);

        $stack = self::createStub(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }
}
