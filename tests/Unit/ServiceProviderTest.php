<?php

use Illuminate\Support\Facades\Route;
use OpenSwag\Laravel\GatewayAggregator;
use OpenSwag\Laravel\Http\Middleware\DocsAuthMiddleware;
use OpenSwag\Laravel\ScalarRenderer;
use OpenSwag\Laravel\SchemaConverter;
use OpenSwag\Laravel\SpecGenerator;

it('registers SpecGenerator as a singleton', function () {
    $instance1 = app(SpecGenerator::class);
    $instance2 = app(SpecGenerator::class);

    expect($instance1)
        ->toBeInstanceOf(SpecGenerator::class)
        ->toBe($instance2);
});

it('registers SchemaConverter as a singleton', function () {
    $instance1 = app(SchemaConverter::class);
    $instance2 = app(SchemaConverter::class);

    expect($instance1)
        ->toBeInstanceOf(SchemaConverter::class)
        ->toBe($instance2);
});

it('registers GatewayAggregator as a singleton', function () {
    $instance1 = app(GatewayAggregator::class);
    $instance2 = app(GatewayAggregator::class);

    expect($instance1)
        ->toBeInstanceOf(GatewayAggregator::class)
        ->toBe($instance2);
});

it('registers ScalarRenderer as a singleton', function () {
    $instance1 = app(ScalarRenderer::class);
    $instance2 = app(ScalarRenderer::class);

    expect($instance1)
        ->toBeInstanceOf(ScalarRenderer::class)
        ->toBe($instance2);
});

it('aliases SpecGenerator as openswag for Facade access', function () {
    $instance = app('openswag');

    expect($instance)->toBeInstanceOf(SpecGenerator::class);
});

it('merges config from the package config file', function () {
    $config = config('openswag');

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['info', 'servers', 'tags', 'route', 'ui', 'docs_auth', 'gateway', 'examples']);
});

it('publishes config file under openswag-config tag', function () {
    $publishes = \Illuminate\Support\ServiceProvider::$publishGroups['openswag-config'] ?? [];

    // The key is the source path as registered by the provider
    $keys = array_keys($publishes);
    $hasConfigKey = false;
    foreach ($keys as $key) {
        if (str_contains($key, 'config' . DIRECTORY_SEPARATOR . 'openswag.php') || str_contains($key, 'config/openswag.php')) {
            $hasConfigKey = true;
            break;
        }
    }

    expect($hasConfigKey)->toBeTrue();
});

it('publishes views under openswag-views tag', function () {
    $publishes = \Illuminate\Support\ServiceProvider::$publishGroups['openswag-views'] ?? [];

    $keys = array_keys($publishes);
    $hasViewsKey = false;
    foreach ($keys as $key) {
        if (str_contains($key, 'resources' . DIRECTORY_SEPARATOR . 'views') || str_contains($key, 'resources/views')) {
            $hasViewsKey = true;
            break;
        }
    }

    expect($hasViewsKey)->toBeTrue();
});

it('registers the UI route at the configured prefix', function () {
    $routes = Route::getRoutes();
    $prefix = config('openswag.route.prefix', 'api/docs');

    $uiRoute = $routes->getByAction('Closure');

    // Find the route matching our prefix
    $found = false;
    foreach ($routes->getRoutes() as $route) {
        if ($route->uri() === $prefix && in_array('GET', $route->methods())) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue();
});

it('registers the JSON spec route at prefix/json', function () {
    $routes = Route::getRoutes();
    $prefix = config('openswag.route.prefix', 'api/docs');

    $found = false;
    foreach ($routes->getRoutes() as $route) {
        if ($route->uri() === $prefix . '/json' && in_array('GET', $route->methods())) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue();
});

it('registers routes with custom prefix from config', function () {
    config()->set('openswag.route.prefix', 'docs/v2');

    // Re-register routes by re-booting the provider
    $provider = new \OpenSwag\Laravel\OpenSwagServiceProvider(app());
    $provider->boot();

    $routes = Route::getRoutes();

    $found = false;
    foreach ($routes->getRoutes() as $route) {
        if ($route->uri() === 'docs/v2' && in_array('GET', $route->methods())) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue();
});

it('applies DocsAuthMiddleware when docs_auth is enabled', function () {
    config()->set('openswag.docs_auth.enabled', true);
    config()->set('openswag.docs_auth.username', 'admin');
    config()->set('openswag.docs_auth.password', 'secret');

    // Re-boot provider with auth enabled
    $provider = new \OpenSwag\Laravel\OpenSwagServiceProvider(app());
    $provider->boot();

    $routes = Route::getRoutes();
    $prefix = config('openswag.route.prefix', 'api/docs');

    $hasMiddleware = false;
    foreach ($routes->getRoutes() as $route) {
        if ($route->uri() === $prefix && in_array('GET', $route->methods())) {
            $middleware = $route->gatherMiddleware();
            if (in_array(DocsAuthMiddleware::class, $middleware)) {
                $hasMiddleware = true;
            }
            break;
        }
    }

    expect($hasMiddleware)->toBeTrue();
});

it('does not apply DocsAuthMiddleware when docs_auth is disabled', function () {
    config()->set('openswag.docs_auth.enabled', false);

    $routes = Route::getRoutes();
    $prefix = config('openswag.route.prefix', 'api/docs');

    $hasMiddleware = false;
    foreach ($routes->getRoutes() as $route) {
        if ($route->uri() === $prefix && in_array('GET', $route->methods())) {
            $middleware = $route->gatherMiddleware();
            if (in_array(DocsAuthMiddleware::class, $middleware)) {
                $hasMiddleware = true;
            }
            break;
        }
    }

    expect($hasMiddleware)->toBeFalse();
});

it('applies configured middleware to routes', function () {
    config()->set('openswag.route.middleware', ['throttle:60,1']);

    $provider = new \OpenSwag\Laravel\OpenSwagServiceProvider(app());
    $provider->boot();

    $routes = Route::getRoutes();
    $prefix = config('openswag.route.prefix', 'api/docs');

    $hasMiddleware = false;
    foreach ($routes->getRoutes() as $route) {
        if ($route->uri() === $prefix && in_array('GET', $route->methods())) {
            $middleware = $route->gatherMiddleware();
            if (in_array('throttle:60,1', $middleware)) {
                $hasMiddleware = true;
            }
            break;
        }
    }

    expect($hasMiddleware)->toBeTrue();
});

it('loads views from the package views directory', function () {
    $viewFactory = app('view');

    expect($viewFactory->exists('openswag::scalar'))->toBeTrue();
});


// --- Gateway Aggregator wiring into SpecGenerator ---

it('wires GatewayAggregator into SpecGenerator when gateway is enabled', function () {
    config()->set('openswag.gateway.enabled', true);
    config()->set('openswag.gateway.services', [
        ['name' => 'users', 'url' => 'http://users:8080/docs', 'pathPrefix' => '/api/users'],
    ]);

    // Clear the singleton so it gets re-resolved with the new config
    app()->forgetInstance(SpecGenerator::class);

    // Re-register the provider to pick up the new config
    $provider = new \OpenSwag\Laravel\OpenSwagServiceProvider(app());
    $provider->register();

    $generator = app(SpecGenerator::class);

    // Use reflection to verify the gatewayAggregator property is set
    $reflection = new \ReflectionClass($generator);
    $prop = $reflection->getProperty('gatewayAggregator');
    $prop->setAccessible(true);

    expect($prop->getValue($generator))->toBeInstanceOf(GatewayAggregator::class);
});

it('does not wire GatewayAggregator into SpecGenerator when gateway is disabled', function () {
    config()->set('openswag.gateway.enabled', false);

    // Clear the singleton so it gets re-resolved
    app()->forgetInstance(SpecGenerator::class);

    $provider = new \OpenSwag\Laravel\OpenSwagServiceProvider(app());
    $provider->register();

    $generator = app(SpecGenerator::class);

    $reflection = new \ReflectionClass($generator);
    $prop = $reflection->getProperty('gatewayAggregator');
    $prop->setAccessible(true);

    expect($prop->getValue($generator))->toBeNull();
});
