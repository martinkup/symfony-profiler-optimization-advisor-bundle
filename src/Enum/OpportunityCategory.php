<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Enum;

enum OpportunityCategory: string
{
    case DB = 'db';

    case CACHE = 'cache';

    case TWIG = 'twig';

    case EVENTS = 'events';

    case HTTP = 'http';

    case MESSENGER = 'messenger';
}
