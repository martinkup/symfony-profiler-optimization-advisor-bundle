<?php

declare(strict_types=1);

use MartinKup\OptimizationAdvisorBundle\AiMate\Capability\OptimizationAdvisorTool;
use MartinKup\OptimizationAdvisorBundle\AiMate\Formatter\OptimizationAdvisorCollectorFormatter;
use MartinKup\OptimizationAdvisorBundle\AiMate\SecurityRedactor;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $configurator->parameters()
        ->set('optimization_advisor.redact_sensitive_data', true)
        ->set(
            'optimization_advisor.sensitive_param_patterns',
            [
            'email', 'password', 'passwd', 'token', 'secret', 'auth', 'credential',
            'phone', 'address', 'ssn', 'card', 'iban',
            ],
        )
        ->set('optimization_advisor.sensitive_value_patterns', ['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'])
        ->set(
            'optimization_advisor.sensitive_query_params',
            [
            'token', 'api_key', 'apikey', 'secret', 'password', 'auth',
            'access_token', 'refresh_token', 'session_id', '_token',
            'email', 'session', 'cookie',
            ],
        );

    $services = $configurator->services();

    $services->set(SecurityRedactor::class)
        ->args(
            [
                param('optimization_advisor.redact_sensitive_data'),
                param('optimization_advisor.sensitive_param_patterns'),
                param('optimization_advisor.sensitive_value_patterns'),
                param('optimization_advisor.sensitive_query_params'),
            ],
        );

    $services->set(OptimizationAdvisorCollectorFormatter::class)
        ->args([service(SecurityRedactor::class)])
        ->tag('ai_mate.profiler_collector_formatter');

    $services->set(OptimizationAdvisorTool::class)
        ->args([service(ProfilerDataProvider::class), service(SecurityRedactor::class)]);
};
