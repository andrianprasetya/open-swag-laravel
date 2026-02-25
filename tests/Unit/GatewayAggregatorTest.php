<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenSwag\Laravel\GatewayAggregator;
use OpenSwag\Laravel\Models\ServiceConfig;
use Psr\SimpleCache\CacheInterface;

// --- merge() tests ---

it('merges multiple service specs with path prefixes', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs', pathPrefix: '/api/orders'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'openapi' => '3.0.0',
            'paths' => [
                '/list' => [
                    'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/{id}' => [
                    'get' => ['summary' => 'Get user', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ],
        'orders' => [
            'openapi' => '3.0.0',
            'paths' => [
                '/list' => [
                    'get' => ['summary' => 'List orders', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    expect($merged['paths'])->toHaveKey('/api/users/list');
    expect($merged['paths'])->toHaveKey('/api/users/{id}');
    expect($merged['paths'])->toHaveKey('/api/orders/list');
    expect($merged['paths']['/api/users/list']['get']['summary'])->toBe('List users');
    expect($merged['paths']['/api/orders/list']['get']['summary'])->toBe('List orders');
});

it('tags operations with service name', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/list' => [
                    'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    expect($merged['paths']['/api/users/list']['get']['tags'])->toContain('users');
});

it('preserves existing tags and prepends service name', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/list' => [
                    'get' => [
                        'summary' => 'List users',
                        'tags' => ['admin'],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    $tags = $merged['paths']['/api/users/list']['get']['tags'];
    expect($tags[0])->toBe('users');
    expect($tags)->toContain('admin');
});

it('creates service tags in merged spec', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs', pathPrefix: '/api/orders'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
        'orders' => [
            'paths' => [
                '/' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    $tagNames = array_column($merged['tags'], 'name');
    expect($tagNames)->toContain('users');
    expect($tagNames)->toContain('orders');
});

it('prefixes schema names with service name to avoid collisions', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs', pathPrefix: '/api/orders'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ],
            ],
        ],
        'orders' => [
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Order' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    expect($merged['components']['schemas'])->toHaveKey('users_User');
    expect($merged['components']['schemas'])->toHaveKey('orders_Order');
    expect($merged['components']['schemas'])->not->toHaveKey('User');
    expect($merged['components']['schemas'])->not->toHaveKey('Order');
});

it('prefixes $ref references in operations', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/' => [
                    'get' => [
                        'summary' => 'List users',
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/User'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    $ref = $merged['paths']['/api/users/']['get']['responses']['200']['content']['application/json']['schema']['$ref'];
    expect($ref)->toBe('#/components/schemas/users_User');
});

it('skips non-HTTP method keys in paths', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/list' => [
                    'get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]],
                    'parameters' => [['name' => 'shared', 'in' => 'query']],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    expect($merged['paths']['/api/users/list'])->toHaveKey('get');
    expect($merged['paths']['/api/users/list'])->not->toHaveKey('parameters');
});

it('handles empty specs gracefully', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $merged = $aggregator->merge([]);

    expect($merged['paths'])->toBeEmpty();
    expect($merged)->not->toHaveKey('components');
    expect($merged)->not->toHaveKey('tags');
});

it('handles specs with no paths', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => ['openapi' => '3.0.0', 'info' => ['title' => 'Users', 'version' => '1.0.0']],
    ];

    $merged = $aggregator->merge($specs);

    expect($merged['paths'])->toBeEmpty();
});

// --- fetchAll() tests ---

it('fetches specs from healthy services', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $userSpec = [
        'openapi' => '3.0.0',
        'paths' => [
            '/list' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
        ],
    ];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response($userSpec, 200),
    ]);

    $aggregator = new GatewayAggregator($services);
    $specs = $aggregator->fetchAll();

    expect($specs)->toHaveKey('users');
    expect($specs['users']['paths'])->toHaveKey('/list');
});

it('performs health check before fetching spec', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    Http::fake([
        'users:8080/health' => Http::response('', 503),
        'users:8080/docs/json' => Http::response(['paths' => []], 200),
    ]);

    Log::shouldReceive('warning')->once();

    $aggregator = new GatewayAggregator($services);
    $specs = $aggregator->fetchAll();

    // Service excluded because health check failed and no cache
    expect($specs)->toBeEmpty();
});

it('uses service url as health url when healthUrl is null', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: null,
        ),
    ];

    $userSpec = ['openapi' => '3.0.0', 'paths' => ['/list' => ['get' => ['summary' => 'List']]]];

    Http::fake([
        'users:8080/docs/json' => Http::response($userSpec, 200),
    ]);

    $aggregator = new GatewayAggregator($services);
    $specs = $aggregator->fetchAll();

    expect($specs)->toHaveKey('users');
});

it('falls back to cache when service returns HTTP error', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $cachedSpec = ['openapi' => '3.0.0', 'paths' => ['/cached' => ['get' => ['summary' => 'Cached']]]];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response('Server Error', 500),
    ]);

    Log::shouldReceive('error')->once();

    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('get')
        ->with('openswag_gateway_users')
        ->andReturn($cachedSpec);

    $aggregator = new GatewayAggregator($services, $cache);
    $specs = $aggregator->fetchAll();

    expect($specs)->toHaveKey('users');
    expect($specs['users']['paths'])->toHaveKey('/cached');
});

it('falls back to cache when service returns invalid JSON', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $cachedSpec = ['openapi' => '3.0.0', 'paths' => ['/cached' => ['get' => ['summary' => 'Cached']]]];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response('not json at all', 200, ['Content-Type' => 'text/plain']),
    ]);

    Log::shouldReceive('error')->once();

    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('get')
        ->with('openswag_gateway_users')
        ->andReturn($cachedSpec);

    $aggregator = new GatewayAggregator($services, $cache);
    $specs = $aggregator->fetchAll();

    expect($specs)->toHaveKey('users');
    expect($specs['users']['paths'])->toHaveKey('/cached');
});

it('excludes service when unreachable and no cache exists', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    Http::fake([
        'users:8080/health' => Http::response('', 503),
    ]);

    Log::shouldReceive('warning')->once();

    $aggregator = new GatewayAggregator($services);
    $specs = $aggregator->fetchAll();

    expect($specs)->toBeEmpty();
});

// --- Cache behavior tests ---

it('caches fetched specs', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $userSpec = ['openapi' => '3.0.0', 'paths' => ['/list' => ['get' => ['summary' => 'List']]]];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response($userSpec, 200),
    ]);

    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('set')
        ->with('openswag_gateway_users', $userSpec, 300)
        ->once();

    $aggregator = new GatewayAggregator($services, $cache, cacheTtl: 300);
    $aggregator->fetchAll();
});

it('degrades gracefully on cache write failure', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $userSpec = ['openapi' => '3.0.0', 'paths' => ['/list' => ['get' => ['summary' => 'List']]]];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response($userSpec, 200),
    ]);

    Log::shouldReceive('warning')->once();

    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('set')
        ->andThrow(new \RuntimeException('Cache write failed'));

    $aggregator = new GatewayAggregator($services, $cache);
    $specs = $aggregator->fetchAll();

    // Should still return the spec despite cache failure
    expect($specs)->toHaveKey('users');
});

it('degrades gracefully on cache read failure', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    Http::fake([
        'users:8080/health' => Http::response('', 503),
    ]);

    Log::shouldReceive('warning')->twice(); // health check warning + cache read warning

    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('get')
        ->andThrow(new \RuntimeException('Cache read failed'));

    $aggregator = new GatewayAggregator($services, $cache);
    $specs = $aggregator->fetchAll();

    expect($specs)->toBeEmpty();
});

// --- aggregatedSpec() and aggregatedSpecJson() ---

it('aggregatedSpec fetches and merges', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $userSpec = [
        'openapi' => '3.0.0',
        'paths' => [
            '/list' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
        ],
    ];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response($userSpec, 200),
    ]);

    $aggregator = new GatewayAggregator($services);
    $result = $aggregator->aggregatedSpec();

    expect($result['paths'])->toHaveKey('/api/users/list');
    expect($result['paths']['/api/users/list']['get']['tags'])->toContain('users');
});

it('aggregatedSpecJson returns valid JSON', function () {
    $services = [
        new ServiceConfig(
            name: 'users',
            url: 'http://users:8080/docs/json',
            pathPrefix: '/api/users',
            healthUrl: 'http://users:8080/health',
        ),
    ];

    $userSpec = [
        'openapi' => '3.0.0',
        'paths' => [
            '/list' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
        ],
    ];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response($userSpec, 200),
    ]);

    $aggregator = new GatewayAggregator($services);
    $json = $aggregator->aggregatedSpecJson();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray();
    expect($decoded['paths'])->toHaveKey('/api/users/list');
});

it('aggregatedSpecJson round-trips correctly', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs', pathPrefix: '/api/orders'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/list' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
            'components' => [
                'schemas' => [
                    'User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ],
            ],
        ],
        'orders' => [
            'paths' => [
                '/{id}' => ['get' => ['summary' => 'Get order', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);
    $json = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $decoded = json_decode($json, true);

    expect($decoded)->toBe($merged);
});

// --- fromArray() ---

it('reconstructs from array config', function () {
    $data = [
        'services' => [
            ['name' => 'users', 'url' => 'http://users:8080/docs', 'pathPrefix' => '/api/users', 'healthUrl' => 'http://users:8080/health'],
            ['name' => 'orders', 'url' => 'http://orders:8080/docs', 'pathPrefix' => '/api/orders', 'healthUrl' => null],
        ],
        'cache_ttl' => 600,
        'health_check_timeout' => 10,
    ];

    $aggregator = GatewayAggregator::fromArray($data);

    // Verify it works by merging some specs
    $specs = [
        'users' => [
            'paths' => [
                '/' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);
    expect($merged['paths'])->toHaveKey('/api/users/');
});

it('fromArray handles empty config', function () {
    $aggregator = GatewayAggregator::fromArray([]);

    $merged = $aggregator->merge([]);
    expect($merged['paths'])->toBeEmpty();
});

// --- Constructor accepts raw arrays ---

it('accepts raw arrays as service config', function () {
    $services = [
        ['name' => 'users', 'url' => 'http://users:8080/docs', 'pathPrefix' => '/api/users'],
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);
    expect($merged['paths'])->toHaveKey('/api/users/');
});

// --- Multiple services with same schema names ---

it('avoids schema name collisions across services', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs', pathPrefix: '/api/orders'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Item' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                ],
            ],
        ],
        'orders' => [
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Item' => ['type' => 'object', 'properties' => ['quantity' => ['type' => 'integer']]],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    expect($merged['components']['schemas'])->toHaveKey('users_Item');
    expect($merged['components']['schemas'])->toHaveKey('orders_Item');
    expect($merged['components']['schemas']['users_Item']['properties'])->toHaveKey('name');
    expect($merged['components']['schemas']['orders_Item']['properties'])->toHaveKey('quantity');
});

// --- Path normalization ---

it('normalizes double slashes in prefixed paths', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users/'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [
                '/list' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    // Should not have double slashes
    foreach (array_keys($merged['paths']) as $path) {
        expect($path)->not->toContain('//');
    }
});

// --- Nested $ref prefixing ---

it('prefixes nested $ref in schema definitions', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs', pathPrefix: '/api/users'),
    ];

    $aggregator = new GatewayAggregator($services);

    $specs = [
        'users' => [
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'address' => ['$ref' => '#/components/schemas/Address'],
                        ],
                    ],
                    'Address' => [
                        'type' => 'object',
                        'properties' => [
                            'street' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $merged = $aggregator->merge($specs);

    $userSchema = $merged['components']['schemas']['users_User'];
    expect($userSchema['properties']['address']['$ref'])->toBe('#/components/schemas/users_Address');
});

// --- fetchAll with multiple services ---

it('fetches from multiple services', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs/json', pathPrefix: '/api/users', healthUrl: 'http://users:8080/health'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs/json', pathPrefix: '/api/orders', healthUrl: 'http://orders:8080/health'),
    ];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response(['paths' => ['/u' => ['get' => ['summary' => 'U']]]], 200),
        'orders:8080/health' => Http::response('OK', 200),
        'orders:8080/docs/json' => Http::response(['paths' => ['/o' => ['get' => ['summary' => 'O']]]], 200),
    ]);

    $aggregator = new GatewayAggregator($services);
    $specs = $aggregator->fetchAll();

    expect($specs)->toHaveCount(2);
    expect($specs)->toHaveKey('users');
    expect($specs)->toHaveKey('orders');
});

it('excludes only unreachable services while including healthy ones', function () {
    $services = [
        new ServiceConfig(name: 'users', url: 'http://users:8080/docs/json', pathPrefix: '/api/users', healthUrl: 'http://users:8080/health'),
        new ServiceConfig(name: 'orders', url: 'http://orders:8080/docs/json', pathPrefix: '/api/orders', healthUrl: 'http://orders:8080/health'),
    ];

    Http::fake([
        'users:8080/health' => Http::response('OK', 200),
        'users:8080/docs/json' => Http::response(['paths' => ['/u' => ['get' => ['summary' => 'U']]]], 200),
        'orders:8080/health' => Http::response('', 503),
    ]);

    Log::shouldReceive('warning')->once();

    $aggregator = new GatewayAggregator($services);
    $specs = $aggregator->fetchAll();

    expect($specs)->toHaveCount(1);
    expect($specs)->toHaveKey('users');
    expect($specs)->not->toHaveKey('orders');
});
