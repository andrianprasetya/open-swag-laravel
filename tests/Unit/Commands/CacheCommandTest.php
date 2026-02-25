<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use OpenSwag\Laravel\Commands\CacheCommand;
use OpenSwag\Laravel\SpecGenerator;

beforeEach(function () {
    $this->app->singleton(SpecGenerator::class, function ($app) {
        return new SpecGenerator($app['config']->get('openswag', []));
    });

    Cache::flush();
});

it('caches the OpenAPI spec and returns exit code 0', function () {
    $this->artisan('openapi:cache')
        ->expectsOutputToContain('OpenAPI spec cached successfully [' . CacheCommand::CACHE_KEY . ']')
        ->assertExitCode(0);

    $cached = Cache::get(CacheCommand::CACHE_KEY);
    expect($cached)->toBeArray()
        ->and($cached['openapi'])->toBe('3.0.0')
        ->and($cached)->toHaveKey('info')
        ->and($cached)->toHaveKey('paths');
});

it('clears the cached spec when --clear is passed', function () {
    // First cache something
    Cache::forever(CacheCommand::CACHE_KEY, ['openapi' => '3.0.0']);
    expect(Cache::get(CacheCommand::CACHE_KEY))->not->toBeNull();

    $this->artisan('openapi:cache', ['--clear' => true])
        ->expectsOutputToContain('OpenAPI spec cache cleared')
        ->assertExitCode(0);

    expect(Cache::get(CacheCommand::CACHE_KEY))->toBeNull();
});

it('returns exit code 0 when clearing with no existing cache', function () {
    expect(Cache::get(CacheCommand::CACHE_KEY))->toBeNull();

    $this->artisan('openapi:cache', ['--clear' => true])
        ->expectsOutputToContain('OpenAPI spec cache cleared')
        ->assertExitCode(0);
});

it('stores a valid OpenAPI spec structure in cache', function () {
    $this->artisan('openapi:cache')
        ->assertExitCode(0);

    $cached = Cache::get(CacheCommand::CACHE_KEY);
    expect($cached)
        ->toBeArray()
        ->toHaveKey('openapi')
        ->toHaveKey('info')
        ->toHaveKey('paths')
        ->and($cached['info'])->toHaveKey('title')
        ->and($cached['info'])->toHaveKey('version');
});

it('returns exit code 1 when generation fails', function () {
    $this->app->singleton(SpecGenerator::class, function () {
        $mock = Mockery::mock(SpecGenerator::class);
        $mock->shouldReceive('discoverRoutes')->andThrow(new \RuntimeException('Test error'));
        return $mock;
    });

    $this->artisan('openapi:cache')
        ->expectsOutputToContain('Failed to cache OpenAPI spec')
        ->assertExitCode(1);
});

it('overwrites previously cached spec on re-cache', function () {
    Cache::forever(CacheCommand::CACHE_KEY, ['old' => 'data']);

    $this->artisan('openapi:cache')
        ->assertExitCode(0);

    $cached = Cache::get(CacheCommand::CACHE_KEY);
    expect($cached)->toBeArray()
        ->and($cached)->toHaveKey('openapi')
        ->and($cached)->not->toHaveKey('old');
});
