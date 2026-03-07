<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\OtherSignalsAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OtherSignalsAnalyzer messenger sync handler analysis and origin classification.
 *
 * Test Coverage:
 * - Filtering only sync-handled records (is_handled_sync === true)
 * - Sorting sync handlers by duration_ms descending
 * - Computing total sync milliseconds across all handlers
 * - Returning empty results for empty input records
 * - Classifying handlers as APP or INFRA by namespace prefix
 * - Split aggregates for app/infra sync handler calls and timing
 * - Profiler zero-value fields (profiler_sync_ms, profiler_sync_count) always 0
 *
 * @see OtherSignalsAnalyzer
 */
#[CoversClass(OtherSignalsAnalyzer::class)]
#[Group('unit')]
final class OtherSignalsAnalyzerTest extends TestCase
{
    private OtherSignalsAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new OtherSignalsAnalyzer();
    }

    public function testAnalyzeFiltersSyncHandlersOnly(): void
    {
        $records = [
            1 => $this->buildRecord(
                handlerClass: 'App\\Backend\\User\\Handler\\ActivateUserHandler',
                messageShort: 'ActivateUserCommand',
                handlerDurationMs: 25.0,
                isHandledSync: true,
            ),
            2 => $this->buildRecord(
                handlerClass: 'App\\Backend\\User\\Handler\\SendEmailHandler',
                messageShort: 'SendEmailCommand',
                handlerDurationMs: 100.0,
                isHandledSync: false,
            ),
            3 => $this->buildRecord(
                handlerClass: 'App\\Backend\\Order\\Handler\\CreateOrderHandler',
                messageShort: 'CreateOrderCommand',
                handlerDurationMs: 50.0,
                isHandledSync: true,
            ),
        ];

        $result = $this->analyzer->analyze($records);

        self::assertSame(2, $result['messenger']['sync_count']);
        self::assertCount(2, $result['messenger']['sync_handlers']);

        $handlerClasses = array_column($result['messenger']['sync_handlers'], 'handler_class');

        self::assertContains('ActivateUserHandler', $handlerClasses);
        self::assertContains('CreateOrderHandler', $handlerClasses);
        self::assertNotContains('SendEmailHandler', $handlerClasses);

        self::assertSame('app', $result['messenger']['sync_handlers'][0]['origin']);
        self::assertSame(2, $result['messenger']['app_sync_count']);
        self::assertEqualsWithDelta(75.0, $result['messenger']['app_sync_ms'], 0.01);
        self::assertSame(0, $result['messenger']['infra_sync_count']);
        self::assertSame(0.0, $result['messenger']['infra_sync_ms']);
        self::assertSame(0, $result['messenger']['profiler_sync_count']);
        self::assertSame(0.0, $result['messenger']['profiler_sync_ms']);
    }

    public function testAnalyzeSortsByDurationDescending(): void
    {
        $records = [
            1 => $this->buildRecord(
                handlerClass: 'App\\Handler\\FastHandler',
                messageShort: 'FastCommand',
                handlerDurationMs: 5.0,
                isHandledSync: true,
            ),
            2 => $this->buildRecord(
                handlerClass: 'App\\Handler\\SlowHandler',
                messageShort: 'SlowCommand',
                handlerDurationMs: 150.0,
                isHandledSync: true,
            ),
            3 => $this->buildRecord(
                handlerClass: 'App\\Handler\\MediumHandler',
                messageShort: 'MediumCommand',
                handlerDurationMs: 30.0,
                isHandledSync: true,
            ),
        ];

        $result = $this->analyzer->analyze($records);

        $handlers = $result['messenger']['sync_handlers'];

        self::assertSame('SlowHandler', $handlers[0]['handler_class']);
        self::assertSame('MediumHandler', $handlers[1]['handler_class']);
        self::assertSame('FastHandler', $handlers[2]['handler_class']);
    }

    public function testAnalyzeComputesTotalSyncMs(): void
    {
        $records = [
            1 => $this->buildRecord(
                handlerClass: 'App\\Handler\\HandlerA',
                messageShort: 'CmdA',
                handlerDurationMs: 10.0,
                isHandledSync: true,
            ),
            2 => $this->buildRecord(
                handlerClass: 'App\\Handler\\HandlerB',
                messageShort: 'CmdB',
                handlerDurationMs: 25.5,
                isHandledSync: true,
            ),
            3 => $this->buildRecord(
                handlerClass: 'App\\Handler\\HandlerC',
                messageShort: 'CmdC',
                handlerDurationMs: 100.0,
                isHandledSync: false,
            ),
        ];

        $result = $this->analyzer->analyze($records);

        self::assertEqualsWithDelta(35.5, $result['messenger']['total_sync_ms'], 0.01);
        self::assertSame(2, $result['messenger']['sync_count']);
    }

    public function testAnalyzeEmptyRecords(): void
    {
        $result = $this->analyzer->analyze([]);

        self::assertSame([], $result['messenger']['sync_handlers']);
        self::assertSame(0.0, $result['messenger']['total_sync_ms']);
        self::assertSame(0, $result['messenger']['sync_count']);
        self::assertSame(0, $result['messenger']['app_sync_count']);
        self::assertSame(0.0, $result['messenger']['app_sync_ms']);
        self::assertSame(0, $result['messenger']['infra_sync_count']);
        self::assertSame(0.0, $result['messenger']['infra_sync_ms']);
        self::assertSame(0, $result['messenger']['profiler_sync_count']);
        self::assertSame(0.0, $result['messenger']['profiler_sync_ms']);
    }

    public function testClassifiesHandlersByOrigin(): void
    {
        $records = [
            1 => $this->buildRecord(
                handlerClass: 'App\\Backend\\User\\Handler\\GetUserHandler',
                messageShort: 'GetUserQuery',
                handlerDurationMs: 15.0,
                isHandledSync: true,
            ),
            2 => $this->buildRecord(
                handlerClass: 'Symfony\\Component\\Mailer\\Handler\\SendMailHandler',
                messageShort: 'SendMailCommand',
                handlerDurationMs: 30.0,
                isHandledSync: true,
            ),
        ];

        $result = $this->analyzer->analyze($records);

        self::assertSame(2, $result['messenger']['sync_count']);
        self::assertSame(1, $result['messenger']['app_sync_count']);
        self::assertEqualsWithDelta(15.0, $result['messenger']['app_sync_ms'], 0.01);
        self::assertSame(1, $result['messenger']['infra_sync_count']);
        self::assertEqualsWithDelta(30.0, $result['messenger']['infra_sync_ms'], 0.01);

        $handlers = $result['messenger']['sync_handlers'];

        self::assertSame('app', $handlers[1]['origin']);
        self::assertSame('infra', $handlers[0]['origin']);
    }

    /** @return array<string, mixed> */
    private function buildRecord(
        string $handlerClass,
        string $messageShort,
        float $handlerDurationMs,
        bool $isHandledSync,
    ): array {
        return [
            'handler_class'       => $handlerClass,
            'message_short'       => $messageShort,
            'handler_duration_ms' => $handlerDurationMs,
            'is_handled_sync'     => $isHandledSync,
        ];
    }
}
