<?php

declare(strict_types=1);

use OpenSwag\Laravel\SpecGenerator;

beforeEach(function () {
    // Ensure SpecGenerator is bound with default config
    $this->app->singleton(SpecGenerator::class, function ($app) {
        return new SpecGenerator($app['config']->get('openswag', []));
    });
});

it('outputs JSON to stdout and returns exit code 0', function () {
    $this->artisan('openapi:generate')
        ->assertExitCode(0);
});

it('outputs pretty-printed JSON when --pretty flag is used', function () {
    $this->artisan('openapi:generate', ['--pretty' => true])
        ->assertExitCode(0);
});

it('writes JSON to a file when --output is provided', function () {
    $outputPath = sys_get_temp_dir() . '/openswag_test_' . uniqid() . '.json';

    try {
        $this->artisan('openapi:generate', ['--output' => $outputPath])
            ->expectsOutput("OpenAPI spec written to {$outputPath}")
            ->assertExitCode(0);

        expect(file_exists($outputPath))->toBeTrue();

        $content = json_decode(file_get_contents($outputPath), true);
        expect($content)->toBeArray()
            ->and($content['openapi'])->toBe('3.0.0');
    } finally {
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
});

it('creates parent directories when output path has nested dirs', function () {
    $dir = sys_get_temp_dir() . '/openswag_test_' . uniqid();
    $outputPath = $dir . '/nested/spec.json';

    try {
        $this->artisan('openapi:generate', ['--output' => $outputPath])
            ->assertExitCode(0);

        expect(file_exists($outputPath))->toBeTrue();
    } finally {
        // Cleanup
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
        if (is_dir($dir . '/nested')) {
            rmdir($dir . '/nested');
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
});

it('returns exit code 1 when generation fails', function () {
    // Bind a SpecGenerator that throws on discoverRoutes
    $this->app->singleton(SpecGenerator::class, function () {
        $mock = Mockery::mock(SpecGenerator::class);
        $mock->shouldReceive('discoverRoutes')->andThrow(new \RuntimeException('Test error'));
        return $mock;
    });

    $this->artisan('openapi:generate')
        ->expectsOutputToContain('Failed to generate OpenAPI spec')
        ->assertExitCode(1);
});

it('generates valid OpenAPI 3.0 structure in stdout output', function () {
    // Capture the output by writing to a file
    $outputPath = sys_get_temp_dir() . '/openswag_test_' . uniqid() . '.json';

    try {
        $this->artisan('openapi:generate', ['--output' => $outputPath])
            ->assertExitCode(0);

        $content = json_decode(file_get_contents($outputPath), true);
        expect($content)
            ->toHaveKey('openapi')
            ->toHaveKey('info')
            ->toHaveKey('paths')
            ->and($content['openapi'])->toBe('3.0.0')
            ->and($content['info'])->toHaveKey('title')
            ->and($content['info'])->toHaveKey('version');
    } finally {
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
});
