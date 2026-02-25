<?php

declare(strict_types=1);

namespace OpenSwag\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OpenSwag\Laravel\Http\Middleware\DocsAuthMiddleware;
use OpenSwag\Laravel\View\Components\DocsComponent;

class OpenSwagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/openswag.php', 'openswag');

        $this->registerSingletons();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'openswag');
        $this->loadViewComponentsAs('openswag', [DocsComponent::class]);

        $this->configurePublishing();
        $this->registerRoutes();
        $this->registerCommands();
    }

    private function registerSingletons(): void
    {
        $this->app->singleton(SpecGenerator::class, function ($app) {
            $generator = new SpecGenerator($app['config']->get('openswag', []));

            if ($app['config']->get('openswag.gateway.enabled', false)) {
                $generator->setGatewayAggregator($app->make(GatewayAggregator::class));
            }

            return $generator;
        });

        $this->app->singleton(ExampleGenerator::class, function ($app) {
            return new ExampleGenerator($app['config']->get('openswag.examples', []));
        });

        $this->app->singleton(SchemaConverter::class, function ($app) {
            $converter = new SchemaConverter();

            $converter->setExampleGenerator($app->make(ExampleGenerator::class));

            return $converter;
        });

        $this->app->singleton(GatewayAggregator::class, function ($app) {
            $config = $app['config']->get('openswag.gateway', []);

            return new GatewayAggregator(
                servicesConfig: $config['services'] ?? [],
                cache: $app->bound(\Psr\SimpleCache\CacheInterface::class)
                    ? $app->make(\Psr\SimpleCache\CacheInterface::class)
                    : null,
                cacheTtl: $config['cache_ttl'] ?? 300,
                healthCheckTimeout: $config['health_check_timeout'] ?? 5,
            );
        });

        $this->app->singleton(ScalarRenderer::class, function ($app) {
            return new ScalarRenderer($app['config']->get('openswag.ui', []));
        });

        // Alias for Facade access
        $this->app->alias(SpecGenerator::class, 'openswag');
    }

    private function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/openswag.php' => config_path('openswag.php'),
            ], 'openswag-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/openswag'),
            ], 'openswag-views');
        }
    }

    private function registerRoutes(): void
    {
        $prefix = $this->app['config']->get('openswag.route.prefix', 'api/docs');
        $middleware = $this->app['config']->get('openswag.route.middleware', []);
        $docsAuthEnabled = $this->app['config']->get('openswag.docs_auth.enabled', false);

        if ($docsAuthEnabled) {
            $middleware[] = DocsAuthMiddleware::class;
        }

        Route::group([
            'prefix' => $prefix,
            'middleware' => $middleware,
        ], function () {
            Route::get('/', function () {
                /** @var ScalarRenderer $renderer */
                $renderer = app(ScalarRenderer::class);
                $title = config('openswag.info.title', 'API Documentation');
                $specUrl = url(config('openswag.route.prefix', 'api/docs') . '/json');

                return response($renderer->render($specUrl, $title), 200, [
                    'Content-Type' => 'text/html',
                ]);
            });

            Route::get('/json', function () {
                /** @var SpecGenerator $generator */
                $generator = app(SpecGenerator::class);
                $generator->discoverRoutes();

                return response($generator->specJson(), 200, [
                    'Content-Type' => 'application/json',
                ]);
            });
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $commands = array_filter([
            \OpenSwag\Laravel\Commands\GenerateCommand::class,
            \OpenSwag\Laravel\Commands\DiffCommand::class,
            \OpenSwag\Laravel\Commands\ExportCommand::class,
            \OpenSwag\Laravel\Commands\CacheCommand::class,
        ], fn (string $class) => class_exists($class));

        if (! empty($commands)) {
            $this->commands($commands);
        }
    }
}
