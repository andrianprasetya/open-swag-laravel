<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Tests\Unit;

use Illuminate\Routing\Router;
use OpenSwag\Laravel\AttributeReader;
use OpenSwag\Laravel\Attributes\OpenApiOperation;
use OpenSwag\Laravel\Attributes\OpenApiParameter;
use OpenSwag\Laravel\Attributes\OpenApiResponse;
use OpenSwag\Laravel\Models\Endpoint;
use OpenSwag\Laravel\RouteDiscoverer;
use Orchestra\Testbench\TestCase;

class RouteDiscovererTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new AttributeReader();
    }

    public function test_discovers_basic_get_route(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users', [TestUserController::class, 'index']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);
        $this->assertInstanceOf(Endpoint::class, $endpoints[0]);
        $this->assertSame('GET', $endpoints[0]->method);
        $this->assertSame('/api/users', $endpoints[0]->path);
    }

    public function test_discovers_post_route(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->post('/api/users', [TestUserController::class, 'store']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);
        $this->assertSame('POST', $endpoints[0]->method);
        $this->assertSame('/api/users', $endpoints[0]->path);
    }

    public function test_discovers_multiple_routes(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users', [TestUserController::class, 'index']);
        $router->post('/api/users', [TestUserController::class, 'store']);
        $router->get('/api/users/{id}', [TestUserController::class, 'show']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(3, $endpoints);
    }

    public function test_skips_closure_routes(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users', [TestUserController::class, 'index']);
        $router->get('/api/health', fn () => 'ok');

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);
        $this->assertSame('/api/users', $endpoints[0]->path);
    }

    public function test_auto_detects_path_parameters(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users/{id}', [TestUserController::class, 'show']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);

        $pathParams = array_filter(
            $endpoints[0]->parameters,
            fn ($p) => $p->in === 'path',
        );
        $this->assertCount(1, $pathParams);

        $param = array_values($pathParams)[0];
        $this->assertSame('id', $param->name);
        $this->assertSame('path', $param->in);
        $this->assertTrue($param->required);
        $this->assertSame(['type' => 'string'], $param->schema);
    }

    public function test_auto_detects_multiple_path_parameters(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/teams/{teamId}/users/{userId}', [TestUserController::class, 'showTeamUser']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);

        $pathParams = array_filter(
            $endpoints[0]->parameters,
            fn ($p) => $p->in === 'path',
        );
        $this->assertCount(2, $pathParams);

        $names = array_map(fn ($p) => $p->name, array_values($pathParams));
        $this->assertContains('teamId', $names);
        $this->assertContains('userId', $names);
    }

    public function test_extracts_attribute_metadata(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users', [TestAnnotatedController::class, 'index']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);
        $this->assertSame('List users', $endpoints[0]->summary);
        $this->assertSame('Returns all users', $endpoints[0]->description);
        $this->assertSame(['Users'], $endpoints[0]->tags);
    }

    public function test_attribute_parameters_take_precedence_over_auto_detected(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users/{id}', [TestAnnotatedController::class, 'show']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);

        $pathParams = array_filter(
            $endpoints[0]->parameters,
            fn ($p) => $p->in === 'path' && $p->name === 'id',
        );
        $this->assertCount(1, $pathParams);

        // The attribute-declared parameter should take precedence (type integer, not string)
        $param = array_values($pathParams)[0];
        $this->assertSame(['type' => 'integer'], $param->schema);
        $this->assertSame('User ID', $param->description);
    }

    public function test_extracts_responses_from_attributes(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/api/users', [TestAnnotatedController::class, 'index']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertCount(1, $endpoints);
        $this->assertArrayHasKey(200, $endpoints[0]->responses);
        $this->assertSame('Success', $endpoints[0]->responses[200]->description);
    }

    public function test_handles_put_delete_patch_methods(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->put('/api/users/{id}', [TestUserController::class, 'update']);
        $router->delete('/api/users/{id}', [TestUserController::class, 'destroy']);
        $router->patch('/api/users/{id}', [TestUserController::class, 'patch']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $methods = array_map(fn ($e) => $e->method, $endpoints);
        $this->assertContains('PUT', $methods);
        $this->assertContains('DELETE', $methods);
        $this->assertContains('PATCH', $methods);
    }

    public function test_returns_empty_array_when_no_routes(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertSame([], $endpoints);
    }

    public function test_returns_empty_array_when_only_closure_routes(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('/health', fn () => 'ok');
        $router->get('/ping', fn () => 'pong');

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertSame([], $endpoints);
    }

    public function test_uri_always_starts_with_slash(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->get('api/users', [TestUserController::class, 'index']);

        $discoverer = new RouteDiscoverer($router, $this->reader);
        $endpoints = $discoverer->discover();

        $this->assertStringStartsWith('/', $endpoints[0]->path);
    }
}

// --- Test fixture controllers ---

class TestUserController
{
    public function index(): void {}
    public function store(): void {}
    public function show(): void {}
    public function update(): void {}
    public function destroy(): void {}
    public function patch(): void {}
    public function showTeamUser(): void {}
}

class TestAnnotatedController
{
    #[OpenApiOperation(
        summary: 'List users',
        description: 'Returns all users',
        tags: ['Users'],
    )]
    #[OpenApiResponse(status: 200, description: 'Success')]
    public function index(): void {}

    #[OpenApiOperation(summary: 'Show user')]
    #[OpenApiParameter(name: 'id', in: 'path', required: true, type: 'integer', description: 'User ID')]
    #[OpenApiResponse(status: 200, description: 'User found')]
    public function show(): void {}
}
