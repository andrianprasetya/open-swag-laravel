<?php

declare(strict_types=1);

use OpenSwag\Laravel\Models\Endpoint;
use OpenSwag\Laravel\Models\Parameter;
use OpenSwag\Laravel\Models\RequestBody;
use OpenSwag\Laravel\Models\ResponseDefinition;
use OpenSwag\Laravel\SpecGenerator;

function makeConfig(array $overrides = []): array
{
    return array_merge([
        'info' => [
            'title' => 'Test API',
            'version' => '1.0.0',
            'description' => '',
            'contact' => ['name' => '', 'url' => '', 'email' => ''],
            'license' => ['name' => '', 'url' => ''],
        ],
        'servers' => [],
        'tags' => [],
    ], $overrides);
}

test('buildSpec produces valid OpenAPI 3.0 structure with minimal config', function () {
    $gen = new SpecGenerator(makeConfig());
    $spec = $gen->buildSpec();

    expect($spec['openapi'])->toBe('3.0.0');
    expect($spec['info']['title'])->toBe('Test API');
    expect($spec['info']['version'])->toBe('1.0.0');
    expect($spec['paths'])->toBe([]);
    expect($spec)->not->toHaveKey('servers');
    expect($spec)->not->toHaveKey('tags');
    expect($spec)->not->toHaveKey('components');
});

test('buildSpec includes info description, contact, and license when provided', function () {
    $gen = new SpecGenerator(makeConfig([
        'info' => [
            'title' => 'My API',
            'version' => '2.0.0',
            'description' => 'A great API',
            'contact' => ['name' => 'Dev Team', 'url' => '', 'email' => 'dev@example.com'],
            'license' => ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'],
        ],
    ]));

    $spec = $gen->buildSpec();

    expect($spec['info']['description'])->toBe('A great API');
    expect($spec['info']['contact'])->toBe(['name' => 'Dev Team', 'email' => 'dev@example.com']);
    expect($spec['info']['license'])->toBe(['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT']);
});

test('buildSpec includes servers from config', function () {
    $gen = new SpecGenerator(makeConfig([
        'servers' => [
            ['url' => 'https://api.example.com', 'description' => 'Production'],
            ['url' => 'https://staging.example.com', 'description' => 'Staging'],
        ],
    ]));

    $spec = $gen->buildSpec();

    expect($spec['servers'])->toHaveCount(2);
    expect($spec['servers'][0]['url'])->toBe('https://api.example.com');
    expect($spec['servers'][1]['url'])->toBe('https://staging.example.com');
});

test('buildSpec includes tags from config', function () {
    $gen = new SpecGenerator(makeConfig([
        'tags' => [
            ['name' => 'Users', 'description' => 'User operations'],
            ['name' => 'Posts', 'description' => 'Post operations'],
        ],
    ]));

    $spec = $gen->buildSpec();

    expect($spec['tags'])->toHaveCount(2);
    expect($spec['tags'][0]['name'])->toBe('Users');
    expect($spec['tags'][1]['name'])->toBe('Posts');
});

test('addEndpoint adds a single endpoint to the spec', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/users',
        summary: 'List users',
    ));

    $spec = $gen->buildSpec();

    expect($spec['paths'])->toHaveKey('/users');
    expect($spec['paths']['/users'])->toHaveKey('get');
    expect($spec['paths']['/users']['get']['summary'])->toBe('List users');
});

test('addEndpoints adds multiple endpoints', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoints(
        new Endpoint(method: 'GET', path: '/users', summary: 'List users'),
        new Endpoint(method: 'POST', path: '/users', summary: 'Create user'),
        new Endpoint(method: 'GET', path: '/posts', summary: 'List posts'),
    );

    $spec = $gen->buildSpec();

    expect($spec['paths'])->toHaveCount(2);
    expect($spec['paths']['/users'])->toHaveKeys(['get', 'post']);
    expect($spec['paths']['/posts'])->toHaveKey('get');
});

test('buildSpec groups endpoints by path and method', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(method: 'GET', path: '/users', summary: 'List'));
    $gen->addEndpoint(new Endpoint(method: 'POST', path: '/users', summary: 'Create'));
    $gen->addEndpoint(new Endpoint(method: 'DELETE', path: '/users/{id}', summary: 'Delete'));

    $spec = $gen->buildSpec();

    expect($spec['paths']['/users']['get']['summary'])->toBe('List');
    expect($spec['paths']['/users']['post']['summary'])->toBe('Create');
    expect($spec['paths']['/users/{id}']['delete']['summary'])->toBe('Delete');
});

test('buildSpec auto-detects path parameters from {param} placeholders', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/users/{userId}/posts/{postId}',
        summary: 'Get user post',
    ));

    $spec = $gen->buildSpec();
    $params = $spec['paths']['/users/{userId}/posts/{postId}']['get']['parameters'];

    expect($params)->toHaveCount(2);
    expect($params[0]['name'])->toBe('userId');
    expect($params[0]['in'])->toBe('path');
    expect($params[0]['required'])->toBeTrue();
    expect($params[0]['schema'])->toBe(['type' => 'string']);
    expect($params[1]['name'])->toBe('postId');
});

test('auto-detected path params do not duplicate declared params', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/users/{id}',
        summary: 'Get user',
        parameters: [
            new Parameter(name: 'id', in: 'path', description: 'User ID', required: true, schema: ['type' => 'integer']),
        ],
    ));

    $spec = $gen->buildSpec();
    $params = $spec['paths']['/users/{id}']['get']['parameters'];

    expect($params)->toHaveCount(1);
    expect($params[0]['name'])->toBe('id');
    expect($params[0]['schema'])->toBe(['type' => 'integer']);
    expect($params[0]['description'])->toBe('User ID');
});

test('buildSpec includes operation details: description, tags, deprecated', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'PUT',
        path: '/users/{id}',
        summary: 'Update user',
        description: 'Updates an existing user',
        tags: ['Users'],
        deprecated: true,
    ));

    $spec = $gen->buildSpec();
    $op = $spec['paths']['/users/{id}']['put'];

    expect($op['summary'])->toBe('Update user');
    expect($op['description'])->toBe('Updates an existing user');
    expect($op['tags'])->toBe(['Users']);
    expect($op['deprecated'])->toBeTrue();
});

test('buildSpec includes request body', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'POST',
        path: '/users',
        summary: 'Create user',
        requestBody: new RequestBody(
            description: 'User data',
            required: true,
            schema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            contentType: 'application/json',
        ),
    ));

    $spec = $gen->buildSpec();
    $body = $spec['paths']['/users']['post']['requestBody'];

    expect($body['description'])->toBe('User data');
    expect($body['required'])->toBeTrue();
    expect($body['content']['application/json']['schema'])->toBe([
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
    ]);
});

test('buildSpec includes responses', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/users',
        summary: 'List users',
        responses: [
            200 => new ResponseDefinition(
                description: 'Success',
                schema: ['type' => 'array', 'items' => ['type' => 'object']],
            ),
            404 => new ResponseDefinition(description: 'Not found'),
        ],
    ));

    $spec = $gen->buildSpec();
    $responses = $spec['paths']['/users']['get']['responses'];

    expect($responses)->toHaveKeys(['200', '404']);
    expect($responses['200']['description'])->toBe('Success');
    expect($responses['200']['content']['application/json']['schema'])->toBe([
        'type' => 'array',
        'items' => ['type' => 'object'],
    ]);
    expect($responses['404']['description'])->toBe('Not found');
    expect($responses['404'])->not->toHaveKey('content');
});

test('buildSpec includes security on operations and only referenced schemes in components', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/users',
        summary: 'List users',
        security: ['bearerAuth'],
    ));
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/public',
        summary: 'Public endpoint',
    ));

    $spec = $gen->buildSpec();

    // Operation security
    expect($spec['paths']['/users']['get']['security'])->toBe([['bearerAuth' => []]]);
    expect($spec['paths']['/public']['get'])->not->toHaveKey('security');

    // Components: only bearerAuth included
    expect($spec['components']['securitySchemes'])->toHaveKey('bearerAuth');
    expect($spec['components']['securitySchemes'])->not->toHaveKey('basicAuth');
    expect($spec['components']['securitySchemes']['bearerAuth']['type'])->toBe('http');
    expect($spec['components']['securitySchemes']['bearerAuth']['scheme'])->toBe('bearer');
});

test('buildSpec omits components when no security schemes are referenced', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(method: 'GET', path: '/public', summary: 'Public'));

    $spec = $gen->buildSpec();

    expect($spec)->not->toHaveKey('components');
});

test('buildSpec includes all predefined security scheme types when referenced', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoints(
        new Endpoint(method: 'GET', path: '/a', security: ['bearerAuth']),
        new Endpoint(method: 'GET', path: '/b', security: ['basicAuth']),
        new Endpoint(method: 'GET', path: '/c', security: ['apiKeyHeader']),
        new Endpoint(method: 'GET', path: '/d', security: ['apiKeyQuery']),
        new Endpoint(method: 'GET', path: '/e', security: ['cookieAuth']),
        new Endpoint(method: 'GET', path: '/f', security: ['oauth2']),
    );

    $spec = $gen->buildSpec();
    $schemes = $spec['components']['securitySchemes'];

    expect($schemes)->toHaveCount(6);
    expect($schemes['bearerAuth']['type'])->toBe('http');
    expect($schemes['basicAuth']['scheme'])->toBe('basic');
    expect($schemes['apiKeyHeader']['in'])->toBe('header');
    expect($schemes['apiKeyQuery']['in'])->toBe('query');
    expect($schemes['cookieAuth']['in'])->toBe('cookie');
    expect($schemes['oauth2']['type'])->toBe('oauth2');
    expect($schemes['oauth2']['flows']['authorizationCode'])->toHaveKeys(['authorizationUrl', 'tokenUrl', 'scopes']);
});

test('specJson returns valid JSON string', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(method: 'GET', path: '/users', summary: 'List users'));

    $json = $gen->specJson();

    expect($json)->toBeString();
    $decoded = json_decode($json, true);
    expect($decoded)->not->toBeNull();
    expect($decoded['openapi'])->toBe('3.0.0');
    expect($decoded['paths']['/users']['get']['summary'])->toBe('List users');
});

test('specJson round-trip produces equivalent array', function () {
    $gen = new SpecGenerator(makeConfig([
        'servers' => [['url' => 'https://api.example.com']],
        'tags' => [['name' => 'Users']],
    ]));
    $gen->addEndpoints(
        new Endpoint(
            method: 'GET',
            path: '/users/{id}',
            summary: 'Get user',
            description: 'Retrieve a user by ID',
            tags: ['Users'],
            parameters: [
                new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
            ],
            responses: [
                200 => new ResponseDefinition(description: 'Success', schema: ['type' => 'object']),
            ],
            security: ['bearerAuth'],
        ),
        new Endpoint(
            method: 'POST',
            path: '/users',
            summary: 'Create user',
            requestBody: new RequestBody(required: true, schema: ['type' => 'object']),
        ),
    );

    $original = $gen->buildSpec();
    $json = $gen->specJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBe($original);
});

test('specJson with pretty=false produces compact JSON', function () {
    $gen = new SpecGenerator(makeConfig());
    $json = $gen->specJson(pretty: false);

    expect($json)->not->toContain("\n");
});

test('buildSpec handles query and header parameters', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/search',
        summary: 'Search',
        parameters: [
            new Parameter(name: 'q', in: 'query', description: 'Search query', required: true, schema: ['type' => 'string']),
            new Parameter(name: 'X-Request-Id', in: 'header', description: 'Request ID', schema: ['type' => 'string']),
        ],
    ));

    $spec = $gen->buildSpec();
    $params = $spec['paths']['/search']['get']['parameters'];

    expect($params)->toHaveCount(2);
    expect($params[0]['name'])->toBe('q');
    expect($params[0]['in'])->toBe('query');
    expect($params[0]['required'])->toBeTrue();
    expect($params[1]['name'])->toBe('X-Request-Id');
    expect($params[1]['in'])->toBe('header');
});

test('buildSpec omits empty optional fields from operations', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/simple',
    ));

    $spec = $gen->buildSpec();
    $op = $spec['paths']['/simple']['get'];

    expect($op)->not->toHaveKey('summary');
    expect($op)->not->toHaveKey('description');
    expect($op)->not->toHaveKey('tags');
    expect($op)->not->toHaveKey('parameters');
    expect($op)->not->toHaveKey('requestBody');
    expect($op)->not->toHaveKey('responses');
    expect($op)->not->toHaveKey('security');
    expect($op)->not->toHaveKey('deprecated');
});

test('response without schema omits content key', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'DELETE',
        path: '/users/{id}',
        responses: [
            204 => new ResponseDefinition(description: 'No content'),
        ],
    ));

    $spec = $gen->buildSpec();
    $response = $spec['paths']['/users/{id}']['delete']['responses']['204'];

    expect($response['description'])->toBe('No content');
    expect($response)->not->toHaveKey('content');
});

test('response with empty description gets default', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/test',
        responses: [
            200 => new ResponseDefinition(description: '', schema: ['type' => 'object']),
        ],
    ));

    $spec = $gen->buildSpec();
    expect($spec['paths']['/test']['get']['responses']['200']['description'])->toBe('Response');
});

test('setRouteDiscoverer and discoverRoutes delegates to RouteDiscoverer', function () {
    $endpoint1 = new Endpoint(method: 'GET', path: '/users', summary: 'List users');
    $endpoint2 = new Endpoint(method: 'POST', path: '/users', summary: 'Create user');

    $discoverer = Mockery::mock(\OpenSwag\Laravel\RouteDiscoverer::class);
    $discoverer->shouldReceive('discover')
        ->once()
        ->andReturn([$endpoint1, $endpoint2]);

    $gen = new SpecGenerator(makeConfig());
    $gen->setRouteDiscoverer($discoverer);
    $gen->discoverRoutes();

    $spec = $gen->buildSpec();

    expect($spec['paths'])->toHaveCount(1); // /users with 2 methods
    expect($spec['paths']['/users'])->toHaveKeys(['get', 'post']);
    expect($spec['paths']['/users']['get']['summary'])->toBe('List users');
    expect($spec['paths']['/users']['post']['summary'])->toBe('Create user');
});

test('discoverRoutes does nothing when no RouteDiscoverer is set', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->discoverRoutes();

    $spec = $gen->buildSpec();

    expect($spec['paths'])->toBe([]);
});


// --- Gateway Aggregator wiring tests ---

test('buildSpec works as before when no gateway aggregator is set', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(method: 'GET', path: '/users', summary: 'List users'));

    $spec = $gen->buildSpec();

    expect($spec['openapi'])->toBe('3.0.0');
    expect($spec['paths'])->toHaveKey('/users');
    expect($spec['paths']['/users']['get']['summary'])->toBe('List users');
    expect($spec)->not->toHaveKey('components');
});

test('buildSpec merges external paths from gateway aggregator', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(method: 'GET', path: '/local', summary: 'Local endpoint'));

    $aggregator = Mockery::mock(\OpenSwag\Laravel\GatewayAggregator::class);
    $aggregator->shouldReceive('aggregatedSpec')
        ->once()
        ->andReturn([
            'paths' => [
                '/api/users/list' => [
                    'get' => ['summary' => 'List users', 'tags' => ['users']],
                ],
                '/api/orders/list' => [
                    'get' => ['summary' => 'List orders', 'tags' => ['orders']],
                ],
            ],
            'tags' => [
                ['name' => 'users', 'description' => 'Operations from users service'],
                ['name' => 'orders', 'description' => 'Operations from orders service'],
            ],
            'components' => [
                'schemas' => [
                    'users_User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ],
            ],
        ]);

    $gen->setGatewayAggregator($aggregator);
    $spec = $gen->buildSpec();

    // Local paths still present
    expect($spec['paths'])->toHaveKey('/local');
    expect($spec['paths']['/local']['get']['summary'])->toBe('Local endpoint');

    // External paths merged
    expect($spec['paths'])->toHaveKey('/api/users/list');
    expect($spec['paths']['/api/users/list']['get']['summary'])->toBe('List users');
    expect($spec['paths'])->toHaveKey('/api/orders/list');

    // External tags merged
    expect($spec['tags'])->toBeArray();
    $tagNames = array_column($spec['tags'], 'name');
    expect($tagNames)->toContain('users');
    expect($tagNames)->toContain('orders');

    // External schemas merged
    expect($spec['components']['schemas'])->toHaveKey('users_User');
});

test('buildSpec merges external tags without duplicating existing tags', function () {
    $gen = new SpecGenerator(makeConfig([
        'tags' => [
            ['name' => 'users', 'description' => 'Local users tag'],
        ],
    ]));

    $aggregator = Mockery::mock(\OpenSwag\Laravel\GatewayAggregator::class);
    $aggregator->shouldReceive('aggregatedSpec')
        ->once()
        ->andReturn([
            'paths' => [],
            'tags' => [
                ['name' => 'users', 'description' => 'Operations from users service'],
                ['name' => 'orders', 'description' => 'Operations from orders service'],
            ],
        ]);

    $gen->setGatewayAggregator($aggregator);
    $spec = $gen->buildSpec();

    // Should not duplicate the 'users' tag
    $tagNames = array_column($spec['tags'], 'name');
    expect(array_count_values($tagNames)['users'])->toBe(1);
    expect($tagNames)->toContain('orders');
});

test('buildSpec merges external schemas into existing components', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(
        method: 'GET',
        path: '/secure',
        summary: 'Secure endpoint',
        security: ['bearerAuth'],
    ));

    $aggregator = Mockery::mock(\OpenSwag\Laravel\GatewayAggregator::class);
    $aggregator->shouldReceive('aggregatedSpec')
        ->once()
        ->andReturn([
            'paths' => [],
            'components' => [
                'schemas' => [
                    'users_User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ],
            ],
        ]);

    $gen->setGatewayAggregator($aggregator);
    $spec = $gen->buildSpec();

    // Local security schemes still present
    expect($spec['components']['securitySchemes'])->toHaveKey('bearerAuth');

    // External schemas merged alongside
    expect($spec['components']['schemas'])->toHaveKey('users_User');
});

test('buildSpec handles gateway aggregator returning empty spec', function () {
    $gen = new SpecGenerator(makeConfig());
    $gen->addEndpoint(new Endpoint(method: 'GET', path: '/local', summary: 'Local'));

    $aggregator = Mockery::mock(\OpenSwag\Laravel\GatewayAggregator::class);
    $aggregator->shouldReceive('aggregatedSpec')
        ->once()
        ->andReturn([
            'openapi' => '3.0.0',
            'info' => ['title' => 'API Gateway', 'version' => '1.0.0'],
            'paths' => [],
        ]);

    $gen->setGatewayAggregator($aggregator);
    $spec = $gen->buildSpec();

    // Local spec unchanged
    expect($spec['paths'])->toHaveKey('/local');
    expect($spec['paths'])->toHaveCount(1);
});
