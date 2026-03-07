<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Enum;

enum OpportunityCode: string
{
    case PG_SLOW_QUERY_GROUP = 'PG_SLOW_QUERY_GROUP';

    case PG_N_PLUS_ONE_SUSPECTED = 'PG_N_PLUS_ONE_SUSPECTED';

    case PG_DUPLICATE_QUERY = 'PG_DUPLICATE_QUERY';

    case PG_LARGE_RESULTSET = 'PG_LARGE_RESULTSET';

    case CACHE_CANDIDATE_DB_RESULTS = 'CACHE_CANDIDATE_DB_RESULTS';

    case CACHE_LOW_HITRATE_POOL = 'CACHE_LOW_HITRATE_POOL';

    case DOCTRINE_2LC_OPPORTUNITY = 'DOCTRINE_2LC_OPPORTUNITY';

    case TWIG_HOT_TEMPLATE = 'TWIG_HOT_TEMPLATE';

    case TWIG_DUP_RENDER = 'TWIG_DUP_RENDER';

    case EVENTS_TOO_MANY_LISTENERS = 'EVENTS_TOO_MANY_LISTENERS';

    case EVENTS_SLOW_LISTENER = 'EVENTS_SLOW_LISTENER';

    case HTTP_SLOW_ENDPOINT = 'HTTP_SLOW_ENDPOINT';

    case HTTP_DUP_CALL = 'HTTP_DUP_CALL';

    case MESSENGER_SYNC_HEAVY = 'MESSENGER_SYNC_HEAVY';

    public function label(): string
    {
        return match ($this) {
            self::PG_SLOW_QUERY_GROUP => 'Slow query group detected',
            self::PG_N_PLUS_ONE_SUSPECTED => 'N+1 query pattern suspected',
            self::PG_DUPLICATE_QUERY => 'Duplicate query executed',
            self::PG_LARGE_RESULTSET => 'Large resultset suspected',
            self::CACHE_CANDIDATE_DB_RESULTS => 'Cache candidate: repeated SELECT',
            self::CACHE_LOW_HITRATE_POOL => 'Low hit-rate cache pool',
            self::DOCTRINE_2LC_OPPORTUNITY => 'Doctrine 2LC opportunity',
            self::TWIG_HOT_TEMPLATE => 'Hot Twig template',
            self::TWIG_DUP_RENDER => 'Duplicate Twig render',
            self::EVENTS_TOO_MANY_LISTENERS => 'Excessive listener calls',
            self::EVENTS_SLOW_LISTENER => 'Slow event listener',
            self::HTTP_SLOW_ENDPOINT => 'Slow HTTP endpoint',
            self::HTTP_DUP_CALL => 'Duplicate HTTP call',
            self::MESSENGER_SYNC_HEAVY => 'Heavy sync Messenger handler',
        };
    }
}
