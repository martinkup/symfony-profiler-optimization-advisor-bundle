<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\AiMate;

use MartinKup\OptimizationAdvisorBundle\AiMate\SecurityRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** @see SecurityRedactor */
#[CoversClass(SecurityRedactor::class)]
#[Group('unit')]
final class SecurityRedactorTest extends TestCase
{
    private const array DEFAULT_PARAM_PATTERNS = [
        'email',
        'password',
        'passwd',
        'token',
        'secret',
        'auth',
        'credential',
        'phone',
        'address',
        'ssn',
        'card',
        'iban',
    ];

    private const array DEFAULT_VALUE_PATTERNS = ['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'];

    private const array DEFAULT_QUERY_PARAMS = [
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
    ];

    private SecurityRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new SecurityRedactor(
            true,
            self::DEFAULT_PARAM_PATTERNS,
            self::DEFAULT_VALUE_PATTERNS,
            self::DEFAULT_QUERY_PARAMS,
        );
    }

    public function testRedactRedactsSensitiveQueryParams(): void
    {
        $data = ['origin' => ['uri' => '/api/users?token=abc&page=2']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        self::assertSame('/api/users?token=%2A%2A%2AREDACTED%2A%2A%2A&page=2', $origin['uri']);
    }

    public function testRedactPreservesNonSensitiveQueryParams(): void
    {
        $data = ['origin' => ['uri' => '/api/users?page=2&sort=name']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        self::assertSame('/api/users?page=2&sort=name', $origin['uri']);
    }

    public function testRedactPreservesUriWithoutQueryString(): void
    {
        $data = ['origin' => ['uri' => '/api/users']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        self::assertSame('/api/users', $origin['uri']);
    }

    public function testRedactRedactsParamsByKeyMatch(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users WHERE email = ?',
                            'example_sql' => "SELECT * FROM users WHERE email = 'a@b.com'",
                            'example_params' => ['email' => 'a@b.com'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<string, string> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame('***REDACTED***', $params['email']);
    }

    public function testRedactRedactsParamsByValueMatch(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users WHERE email = ?',
                            'example_sql' => "SELECT * FROM users WHERE email = 'a@b.com'",
                            'example_params' => ['a@b.com'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<int, string> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame('***REDACTED***', $params[0]);
    }

    public function testRedactPreservesNonMatchingParams(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users WHERE id = ? AND name = ?',
                            'example_sql' => 'SELECT * FROM users WHERE id = 42 AND name = ?',
                            'example_params' => [42, 'John'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<int, mixed> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame(42, $params[0]);
        self::assertSame('John', $params[1]);
    }

    public function testRedactHandlesNestedParamArrays(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'INSERT INTO ...',
                            'example_sql' => 'INSERT INTO ...',
                            'example_params' => [
                                'name' => 'John Doe',
                                'contact' => ['email' => 'john@example.com', 'city' => 'Prague'],
                            ],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<string, mixed> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame('John Doe', $params['name']);
        self::assertIsArray($params['contact']);
        /** @var array<string, string> $contact */
        $contact = $params['contact'];
        self::assertSame('***REDACTED***', $contact['email']);
        self::assertSame('Prague', $contact['city']);
    }

    public function testRedactReplacesExampleSqlWithPattern(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users WHERE email = ?',
                            'example_sql' => "SELECT * FROM users WHERE email = 'admin@example.com'",
                            'example_params' => [],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        self::assertSame('SELECT * FROM users WHERE email = ?', $signals['db']['query_groups'][0]['example_sql']);
    }

    public function testRedactSetsRunnableToFalse(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT 1',
                            'example_sql' => 'SELECT 1',
                            'example_params' => [],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        self::assertFalse($signals['db']['query_groups'][0]['runnable']);
    }

    public function testRedactPreservesNonSensitiveFields(): void
    {
        $data = [
            'origin' => ['route' => 'app_home', 'controller' => 'HomeController', 'method' => 'GET', 'uri' => '/'],
            'summary' => ['opportunity_count' => 3, 'optimization_score' => 85],
            'signals' => [
                'db' => [
                    'total_time' => 42.5,
                    'query_count' => 5,
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users',
                            'example_sql' => 'SELECT * FROM users',
                            'example_params' => [],
                            'fingerprint' => 'abc123',
                            'tables' => ['users'],
                            'count' => 3,
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        self::assertSame('app_home', $origin['route']);
        /** @var array<string, mixed> $summary */
        $summary = $result['summary'];
        self::assertSame(3, $summary['opportunity_count']);

        /** @var array<string, array<string, mixed>> $signals */
        $signals = $result['signals'];
        self::assertSame(42.5, $signals['db']['total_time']);
        self::assertSame(5, $signals['db']['query_count']);

        /** @var array<int, array<string, mixed>> $queryGroups */
        $queryGroups = $signals['db']['query_groups'];
        self::assertSame('SELECT * FROM users', $queryGroups[0]['pattern']);
        self::assertSame('abc123', $queryGroups[0]['fingerprint']);
        self::assertSame(['users'], $queryGroups[0]['tables']);
        self::assertSame(3, $queryGroups[0]['count']);
    }

    public function testRedactHandlesEmptySignals(): void
    {
        $data = ['signals' => []];

        $result = $this->redactor->redact($data);

        self::assertSame([], $result['signals']);
    }

    public function testRedactHandlesMissingKeys(): void
    {
        $data = ['summary' => ['optimization_score' => 100]];

        $result = $this->redactor->redact($data);

        self::assertSame(['summary' => ['optimization_score' => 100]], $result);
    }

    public function testRedactDisabledPassesThrough(): void
    {
        $redactor = new SecurityRedactor(false);

        $data = [
            'origin' => ['uri' => '/api/users?token=secret'],
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT ?',
                            'example_sql' => "SELECT 'sensitive'",
                            'example_params' => ['sensitive'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $redactor->redact($data);

        self::assertSame($data, $result);
    }

    public function testRedactProcessesMultipleQueryGroups(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users WHERE id = ?',
                            'example_sql' => 'SELECT * FROM users WHERE id = 42',
                            'example_params' => [42],
                            'runnable' => true,
                        ],
                        [
                            'pattern' => 'SELECT * FROM orders WHERE email = ?',
                            'example_sql' => "SELECT * FROM orders WHERE email = 'a@b.com'",
                            'example_params' => ['email' => 'a@b.com'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        self::assertCount(2, $signals['db']['query_groups']);
        self::assertSame('SELECT * FROM users WHERE id = ?', $signals['db']['query_groups'][0]['example_sql']);
        self::assertSame('SELECT * FROM orders WHERE email = ?', $signals['db']['query_groups'][1]['example_sql']);
        self::assertSame([42], $signals['db']['query_groups'][0]['example_params']);
        self::assertSame(['email' => '***REDACTED***'], $signals['db']['query_groups'][1]['example_params']);
        self::assertFalse($signals['db']['query_groups'][0]['runnable']);
        self::assertFalse($signals['db']['query_groups'][1]['runnable']);
    }

    public function testRedactPreservesNonDbSignals(): void
    {
        $data = [
            'signals' => [
                'cache' => ['total_time' => 10.0, 'hit_rate' => 0.95],
                'twig' => ['template_count' => 15],
                'http' => ['request_count' => 3],
                'events' => ['listener_count' => 42],
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT 1',
                            'example_sql' => 'SELECT 1',
                            'example_params' => [],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $signals */
        $signals = $result['signals'];
        self::assertSame(['total_time' => 10.0, 'hit_rate' => 0.95], $signals['cache']);
        self::assertSame(['template_count' => 15], $signals['twig']);
        self::assertSame(['request_count' => 3], $signals['http']);
        self::assertSame(['listener_count' => 42], $signals['events']);
    }

    public function testRedactWithCustomParamPatterns(): void
    {
        $redactor = new SecurityRedactor(true, ['ssn', 'national_id'], [], []);

        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM citizens WHERE ssn = ?',
                            'example_sql' => 'SELECT * FROM citizens WHERE ssn = ?',
                            'example_params' => ['ssn' => '123-45-6789', 'name' => 'John'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<string, string> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame('***REDACTED***', $params['ssn']);
        self::assertSame('John', $params['name']);
    }

    public function testRedactWithCustomValuePatterns(): void
    {
        $redactor = new SecurityRedactor(true, [], ['\\d{3}-\\d{2}-\\d{4}'], []);

        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM citizens WHERE id = ?',
                            'example_sql' => 'SELECT * FROM citizens WHERE id = ?',
                            'example_params' => ['123-45-6789', 'John'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<int, string> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame('***REDACTED***', $params[0]);
        self::assertSame('John', $params[1]);
    }

    public function testRedactWithCustomQueryParams(): void
    {
        $redactor = new SecurityRedactor(true, [], [], ['session_id', 'csrf']);

        $data = ['origin' => ['uri' => '/api/data?session_id=abc&page=1&csrf=xyz']];

        $result = $redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        $uri = $origin['uri'];
        self::assertIsString($uri);
        self::assertStringContainsString('page=1', $uri);
        self::assertStringContainsString('session_id=%2A%2A%2AREDACTED%2A%2A%2A', $uri);
        self::assertStringContainsString('csrf=%2A%2A%2AREDACTED%2A%2A%2A', $uri);
    }

    public function testRedactFailsClosedOnInvalidRegex(): void
    {
        $redactor = new SecurityRedactor(true, [], ['[invalid'], []);

        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT ?',
                            'example_sql' => 'SELECT ?',
                            'example_params' => ['some-value'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        /** @var array<int, string> $params */
        $params = $signals['db']['query_groups'][0]['example_params'];
        self::assertSame('***REDACTED***', $params[0]);
    }

    public function testRedactHandlesNestedQueryParams(): void
    {
        $data = ['origin' => ['uri' => '/api/search?user%5Bemail%5D=a%40b.com&page=1']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        $uri = $origin['uri'];
        self::assertIsString($uri);
        self::assertStringContainsString('page=1', $uri);
        self::assertStringNotContainsString('a%40b.com', $uri);
        self::assertStringNotContainsString('a@b.com', $uri);
    }

    public function testRedactRedactsPathSegmentPii(): void
    {
        $data = ['origin' => ['uri' => '/users/john@example.com/profile']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        self::assertSame('/users/***REDACTED***/profile', $origin['uri']);
    }

    public function testRedactAppliesParamPatternsToQueryParams(): void
    {
        $data = ['origin' => ['uri' => '/api/search?email=a@b.com&page=1']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        $uri = $origin['uri'];
        self::assertIsString($uri);
        self::assertStringContainsString('page=1', $uri);
        self::assertStringContainsString('email=%2A%2A%2AREDACTED%2A%2A%2A', $uri);
    }

    public function testRedactPreservesMissingPatternKey(): void
    {
        $data = [
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'example_sql' => 'SELECT 1',
                            'example_params' => [],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->redactor->redact($data);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        self::assertSame('SELECT 1', $signals['db']['query_groups'][0]['example_sql']);
    }

    public function testRedactQueryParamCaseInsensitive(): void
    {
        $data = ['origin' => ['uri' => '/api/data?Token=abc&page=1']];

        $result = $this->redactor->redact($data);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        $uri = $origin['uri'];
        self::assertIsString($uri);
        self::assertStringContainsString('page=1', $uri);
        self::assertStringNotContainsString('abc', $uri);
    }
}
