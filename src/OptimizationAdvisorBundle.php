<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle;

use Doctrine\SqlFormatter\SqlFormatter;
use MartinKup\OptimizationAdvisorBundle\DependencyInjection\Compiler\MessageTracingMiddlewarePass;
use MartinKup\OptimizationAdvisorBundle\Messenger\Middleware\MessageTracingMiddleware;
use MartinKup\OptimizationAdvisorBundle\Twig\SqlFormatterExtension;
use MartinKup\OptimizationAdvisorBundle\Twig\SqlFormatterFallbackExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

use const E_USER_WARNING;

final class OptimizationAdvisorBundle extends AbstractBundle
{
    public function boot(): void
    {
        $container = $this->container;

        if ($container === null || $container->getParameter('kernel.environment') !== 'prod') {
            return;
        }

        @trigger_error(
            'Using OptimizationAdvisorBundle in production is not supported'
                . ' and puts your project at risk, disable it.',
            E_USER_WARNING,
        );
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if (!interface_exists(MiddlewareInterface::class)) {
            return;
        }

        $container->addCompilerPass(new MessageTracingMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('thresholds')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('slow_query_ms')->defaultValue(30.0)->end()
                        ->integerNode('n_plus_one_count')->defaultValue(10)->end()
                        ->floatNode('slow_listener_ms')->defaultValue(10.0)->end()
                        ->integerNode('max_items')->defaultValue(200)->end()
                    ->end()
                ->end()
                ->scalarNode('app_namespace_prefix')->defaultValue('App\\')->end()
                ->booleanNode('redact_sensitive_data')->defaultTrue()->end()
                ->arrayNode('sensitive_param_patterns')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        'email', 'password', 'passwd', 'token', 'secret', 'auth', 'credential',
                        'phone', 'address', 'ssn', 'card', 'iban',
                    ])
                ->end()
                ->arrayNode('sensitive_value_patterns')
                    ->scalarPrototype()->end()
                    ->defaultValue(['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'])
                ->end()
                ->arrayNode('sensitive_query_params')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        'token',
                        'api_key',
                        'apikey',
                        'secret',
                        'password',
                        'auth',
                        'access_token',
                        'refresh_token',
                        'session_id',
                        '_token',
                        'email',
                        'session',
                        'cookie',
                    ])
                ->end()
                ->arrayNode('infra_db_tables')
                    ->scalarPrototype()->end()
                    ->defaultValue(['doctrine_migration_versions'])
                ->end()
                ->arrayNode('app_cache_pool_prefixes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['cache.app', 'cache.doctrine.result', 'cache.doctrine.orm'])
                ->end()
                ->arrayNode('profiler_cache_pool_prefixes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['cache.profiler'])
                ->end()
                ->arrayNode('profiler_template_prefixes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['@WebProfiler/'])
                ->end()
                ->arrayNode('profiler_event_namespace_prefixes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['Symfony\\Bundle\\WebProfilerBundle\\'])
                ->end()
                ->arrayNode('profiler_event_classes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['Symfony\\Component\\HttpKernel\\EventListener\\ProfilerListener'])
                ->end()
            ->end();
    }

    /** @param array<array-key, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        /** @var array<string, mixed> $thresholds */
        $thresholds = $config['thresholds'];

        $container->parameters()
            ->set('optimization_advisor.slow_query_ms', $thresholds['slow_query_ms'])
            ->set('optimization_advisor.n_plus_one_count', $thresholds['n_plus_one_count'])
            ->set('optimization_advisor.slow_listener_ms', $thresholds['slow_listener_ms'])
            ->set('optimization_advisor.max_items', $thresholds['max_items'])
            ->set('optimization_advisor.app_namespace_prefix', $config['app_namespace_prefix'])
            ->set('optimization_advisor.redact_sensitive_data', $config['redact_sensitive_data'])
            ->set('optimization_advisor.sensitive_param_patterns', $config['sensitive_param_patterns'])
            ->set('optimization_advisor.sensitive_value_patterns', $config['sensitive_value_patterns'])
            ->set('optimization_advisor.sensitive_query_params', $config['sensitive_query_params'])
            ->set('optimization_advisor.infra_db_tables', $config['infra_db_tables'])
            ->set('optimization_advisor.app_cache_pool_prefixes', $config['app_cache_pool_prefixes'])
            ->set('optimization_advisor.profiler_cache_pool_prefixes', $config['profiler_cache_pool_prefixes'])
            ->set('optimization_advisor.profiler_template_prefixes', $config['profiler_template_prefixes'])
            ->set(
                'optimization_advisor.profiler_event_namespace_prefixes',
                $config['profiler_event_namespace_prefixes'],
            )
            ->set('optimization_advisor.profiler_event_classes', $config['profiler_event_classes']);

        if (!class_exists(SqlFormatter::class)) {
            $builder->removeDefinition(SqlFormatterExtension::class);
        } else {
            $builder->removeDefinition(SqlFormatterFallbackExtension::class);
        }

        if (interface_exists(MiddlewareInterface::class)) {
            return;
        }

        $builder->removeDefinition(MessageTracingMiddleware::class);
    }
}
