<?php

declare(strict_types=1);

use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('MartinKup\\OptimizationAdvisorBundle\\', '../src/')
        ->exclude([
            '../src/AiMate/',
            '../src/OptimizationAdvisorBundle.php',
            '../src/DependencyInjection/',
        ]);

    $services->set(OptimizationAdvisorDataCollector::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$twigProfile', service('twig.profile'))
        ->arg('$cacheDataCollector', service('data_collector.cache'))
        ->arg('$debugDataHolder', service('doctrine.debug_data_holder')->nullOnInvalid())
        ->arg('$httpClientDataCollector', service('data_collector.http_client')->nullOnInvalid())
        ->arg('$stopwatch', service('debug.stopwatch')->nullOnInvalid());
};
