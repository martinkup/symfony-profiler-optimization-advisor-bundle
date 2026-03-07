<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests;

use ArrayObject;
use MartinKup\OptimizationAdvisorBundle\Analyzer\CacheAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\DatabaseAnalyzer;
use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use MartinKup\OptimizationAdvisorBundle\DependencyInjection\Compiler\MessageTracingMiddlewarePass;
use MartinKup\OptimizationAdvisorBundle\Engine\AdvisorEngine;
use MartinKup\OptimizationAdvisorBundle\Messenger\Middleware\MessageTracingMiddleware;
use MartinKup\OptimizationAdvisorBundle\Messenger\TraceRegistry;
use MartinKup\OptimizationAdvisorBundle\OptimizationAdvisorBundle;
use MartinKup\OptimizationAdvisorBundle\Sql\SqlNormalizer;
use MartinKup\OptimizationAdvisorBundle\Twig\SqlFormatterExtension;
use MartinKup\OptimizationAdvisorBundle\Twig\SqlFormatterFallbackExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

use const E_USER_WARNING;

#[CoversClass(OptimizationAdvisorBundle::class)]
#[Group('unit')]
final class OptimizationAdvisorBundleTest extends TestCase
{
    private OptimizationAdvisorBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new OptimizationAdvisorBundle();
    }

    public function testBootTriggersWarningInProdEnvironment(): void
    {
        $container = new Container();
        $container->setParameter('kernel.environment', 'prod');
        $this->bundle->setContainer($container);

        /** @var ArrayObject<int|string, mixed> $captured */
        $captured = new ArrayObject();
        set_error_handler(static function (int $errno, string $errstr) use ($captured): bool {
            $captured['warning'] = $errstr;

            return true;
        }, E_USER_WARNING);

        try {
            $this->bundle->boot();
        } finally {
            restore_error_handler();
        }

        self::assertArrayHasKey('warning', $captured);
        $warning = $captured['warning'];
        self::assertIsString($warning);
        self::assertStringContainsString('OptimizationAdvisorBundle', $warning);
        self::assertStringContainsString('production', $warning);
    }

    public function testBootDoesNotTriggerWarningInDevEnvironment(): void
    {
        $container = new Container();
        $container->setParameter('kernel.environment', 'dev');
        $this->bundle->setContainer($container);

        /** @var ArrayObject<int|string, mixed> $captured */
        $captured = new ArrayObject();
        set_error_handler(static function (int $errno, string $errstr) use ($captured): bool {
            $captured['warning'] = $errstr;

            return true;
        }, E_USER_WARNING);

        try {
            $this->bundle->boot();
        } finally {
            restore_error_handler();
        }

        self::assertArrayNotHasKey('warning', $captured);
    }

    public function testGetPathReturnsProjectRoot(): void
    {
        $path = $this->bundle->getPath();

        self::assertDirectoryExists($path);
        self::assertFileExists($path . '/composer.json');
    }

    public function testConfigureDefinesDefaultValues(): void
    {
        $processed = $this->processConfig([]);
        $thresholds = $this->extractThresholds($processed);

        self::assertSame(30.0, $thresholds['slow_query_ms']);
        self::assertSame(10, $thresholds['n_plus_one_count']);
        self::assertSame(10.0, $thresholds['slow_listener_ms']);
        self::assertSame(200, $thresholds['max_items']);
        self::assertSame('App\\', $processed['app_namespace_prefix']);
        self::assertSame(['doctrine_migration_versions'], $processed['infra_db_tables']);
        self::assertSame(
            ['cache.app', 'cache.doctrine.result', 'cache.doctrine.orm'],
            $processed['app_cache_pool_prefixes'],
        );
        self::assertSame(['cache.profiler'], $processed['profiler_cache_pool_prefixes']);
        self::assertSame(['@WebProfiler/'], $processed['profiler_template_prefixes']);
        self::assertSame(
            ['Symfony\\Bundle\\WebProfilerBundle\\'],
            $processed['profiler_event_namespace_prefixes'],
        );
        self::assertSame(
            ['Symfony\\Component\\HttpKernel\\EventListener\\ProfilerListener'],
            $processed['profiler_event_classes'],
        );
        self::assertTrue($processed['redact_sensitive_data']);
        self::assertSame(
            [
                'email', 'password', 'passwd', 'token', 'secret', 'auth', 'credential',
                'phone', 'address', 'ssn', 'card', 'iban',
            ],
            $processed['sensitive_param_patterns'],
        );
        self::assertSame(['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'], $processed['sensitive_value_patterns']);
        self::assertSame(
            [
                'token', 'api_key', 'apikey', 'secret', 'password', 'auth', 'access_token', 'refresh_token',
                'session_id', '_token', 'email', 'session', 'cookie',
            ],
            $processed['sensitive_query_params'],
        );
    }

    public function testConfigureAcceptsCustomValues(): void
    {
        $custom = [
            'thresholds' => [
                'slow_query_ms' => 100.0,
                'max_items' => 50,
            ],
            'app_namespace_prefix' => 'MyApp\\',
        ];

        $processed = $this->processConfig($custom);
        $thresholds = $this->extractThresholds($processed);

        self::assertSame(100.0, $thresholds['slow_query_ms']);
        self::assertSame(50, $thresholds['max_items']);
        self::assertSame('MyApp\\', $processed['app_namespace_prefix']);
        self::assertSame(10, $thresholds['n_plus_one_count']);
        self::assertSame(10.0, $thresholds['slow_listener_ms']);
    }

    public function testLoadExtensionSetsContainerParameters(): void
    {
        $builder = $this->loadBundle();

        self::assertSame(30.0, $builder->getParameter('optimization_advisor.slow_query_ms'));
        self::assertSame(10, $builder->getParameter('optimization_advisor.n_plus_one_count'));
        self::assertSame(10.0, $builder->getParameter('optimization_advisor.slow_listener_ms'));
        self::assertSame(200, $builder->getParameter('optimization_advisor.max_items'));
        self::assertSame('App\\', $builder->getParameter('optimization_advisor.app_namespace_prefix'));
        self::assertSame(
            ['doctrine_migration_versions'],
            $builder->getParameter('optimization_advisor.infra_db_tables'),
        );
        self::assertSame(
            ['cache.app', 'cache.doctrine.result', 'cache.doctrine.orm'],
            $builder->getParameter('optimization_advisor.app_cache_pool_prefixes'),
        );
        self::assertSame(
            ['cache.profiler'],
            $builder->getParameter('optimization_advisor.profiler_cache_pool_prefixes'),
        );
        self::assertSame(
            ['@WebProfiler/'],
            $builder->getParameter('optimization_advisor.profiler_template_prefixes'),
        );
        self::assertSame(
            ['Symfony\\Bundle\\WebProfilerBundle\\'],
            $builder->getParameter('optimization_advisor.profiler_event_namespace_prefixes'),
        );
        self::assertSame(
            ['Symfony\\Component\\HttpKernel\\EventListener\\ProfilerListener'],
            $builder->getParameter('optimization_advisor.profiler_event_classes'),
        );
        self::assertTrue($builder->getParameter('optimization_advisor.redact_sensitive_data'));
        self::assertSame(
            [
                'email', 'password', 'passwd', 'token', 'secret', 'auth', 'credential',
                'phone', 'address', 'ssn', 'card', 'iban',
            ],
            $builder->getParameter('optimization_advisor.sensitive_param_patterns'),
        );
        self::assertSame(
            ['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'],
            $builder->getParameter('optimization_advisor.sensitive_value_patterns'),
        );
        self::assertSame(
            [
                'token', 'api_key', 'apikey', 'secret', 'password', 'auth', 'access_token', 'refresh_token',
                'session_id', '_token', 'email', 'session', 'cookie',
            ],
            $builder->getParameter('optimization_advisor.sensitive_query_params'),
        );
    }

    public function testLoadExtensionRegistersServices(): void
    {
        $builder = $this->loadBundle();

        self::assertTrue($builder->hasDefinition(AdvisorEngine::class));
        self::assertTrue($builder->hasDefinition(DatabaseAnalyzer::class));
        self::assertTrue($builder->hasDefinition(CacheAnalyzer::class));
        self::assertTrue($builder->hasDefinition(OptimizationAdvisorDataCollector::class));
        self::assertTrue($builder->hasDefinition(SqlNormalizer::class));
        self::assertTrue($builder->hasDefinition(TraceRegistry::class));
        self::assertTrue($builder->hasDefinition(MessageTracingMiddleware::class));
    }

    public function testDebugDataHolderUsesNullOnInvalidReference(): void
    {
        $builder = $this->loadBundle();

        $definition = $builder->getDefinition(OptimizationAdvisorDataCollector::class);
        $argument = $definition->getArgument('$debugDataHolder');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $argument->getInvalidBehavior());
    }

    public function testOptionalServicesUseNullOnInvalidReference(): void
    {
        $builder = $this->loadBundle();

        $definition = $builder->getDefinition(OptimizationAdvisorDataCollector::class);

        $optionalArgs = ['$debugDataHolder', '$httpClientDataCollector', '$stopwatch'];

        foreach ($optionalArgs as $argName) {
            $argument = $definition->getArgument($argName);
            self::assertInstanceOf(Reference::class, $argument, sprintf('Argument %s should be a Reference', $argName));
            self::assertSame(
                ContainerInterface::NULL_ON_INVALID_REFERENCE,
                $argument->getInvalidBehavior(),
                sprintf('Argument %s should use NULL_ON_INVALID_REFERENCE', $argName),
            );
        }
    }

    public function testLoadExtensionRemovesFallbackWhenSqlFormatterAvailable(): void
    {
        $builder = $this->loadBundle();

        self::assertTrue($builder->hasDefinition(SqlFormatterExtension::class));
        self::assertFalse($builder->hasDefinition(SqlFormatterFallbackExtension::class));
    }

    public function testLoadExtensionWithCustomThresholds(): void
    {
        $builder = $this->loadBundle(['thresholds' => ['slow_query_ms' => 100.0]]);

        self::assertSame(100.0, $builder->getParameter('optimization_advisor.slow_query_ms'));
    }

    public function testBuildRegistersMessageTracingMiddlewarePass(): void
    {
        $container = new ContainerBuilder();

        $this->bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        $found = false;

        foreach ($passes as $pass) {
            if ($pass instanceof MessageTracingMiddlewarePass) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, 'MessageTracingMiddlewarePass should be registered as a before-optimization pass');
    }

    /**
     * @param array<string, mixed> $userConfig
     *
     * @return array<string, mixed>
     */
    private function processConfig(array $userConfig): array
    {
        $extension = $this->getExtension();
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration(
            $configuration,
            $userConfig !== [] ? [$userConfig] : [],
        );

        return $processed;
    }

    /** @param array<string, mixed>|null $config */
    private function loadBundle(?array $config = null): ContainerBuilder
    {
        $builder = new ContainerBuilder();

        return $this->loadBundleInto($builder, $config);
    }

    /** @param array<string, mixed>|null $config */
    private function loadBundleInto(ContainerBuilder $builder, ?array $config = null): ContainerBuilder
    {
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = $this->getExtension();
        $configs = $config !== null ? [$config] : [];
        $extension->load($configs, $builder);

        return $builder;
    }

    private function getExtension(): Extension
    {
        $extension = $this->bundle->getContainerExtension();
        self::assertInstanceOf(Extension::class, $extension);

        return $extension;
    }

    /**
     * @param array<string, mixed> $processed
     *
     * @return array<mixed, mixed>
     */
    private function extractThresholds(array $processed): array
    {
        self::assertIsArray($processed['thresholds']);

        return $processed['thresholds'];
    }
}
