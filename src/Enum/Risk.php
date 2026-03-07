<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Enum;

enum Risk: string
{
    case LOW = 'low';

    case MED = 'med';

    case HIGH = 'high';
}
