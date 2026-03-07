<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Enum;

enum DataOrigin: string
{
    case APP = 'app';

    case INFRA = 'infra';

    case PROFILER = 'profiler';
}
