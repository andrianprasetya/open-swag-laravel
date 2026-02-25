<?php

declare(strict_types=1);

use OpenSwag\Laravel\SpecGenerator;

beforeEach(function () {
    $this->app->singleton(SpecGenerator::class, function ($app) {
        return new SpecGenerator($app['config']->get('openswag', []));
    });
});

it('exports spec to default openapi.json file', function () {
    // Use a temp path to avoid writing to the project root
    $outputPath = sys_get_temp_dir() . '/openswag_export_' . uniqid() . '.json';

    try {
        $this->artisan('openapi:export', ['--output' => $outputPath])
            ->expectsOutput("OpenAPI spec exported to {$outputPath}")
            ->assertExitCode(0);

        expect(file_exists($outputPath))->toBeTrue();

        $content = json_decode(file_get_contents($outputPath), true);
        expect($content)->toBeArray()
            ->and($content['openapi'])->toBe('3.0.0')
            ->and($content['info'])->toHaveKey('title')
            ->and($content['info'])->toHaveKey('version');
    } finally {
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
});

it('exports pretty-printed JSON by default', function () {
    $outputPath = sys_get_temp_dir() . '/openswag_export_' . uniqid() . '.json';

    try {
        $this->artisan('openapi:export', ['--output' => $outputPath])
            ->assertExitCode(0);

        $raw = file_get_contents($outputPath);
        // Pretty-printed JSON contains newlines
        expect($raw)->toContain("\n");
    } finally {
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
});

it('creates parent directories when output path has nested dirs', function () {
    $dir = sys_get_temp_dir() . '/openswag_export_' . uniqid();
    $outputPath = $dir . '/nested/spec.json';

    try {
        $this->artisan('openapi:export', ['--output' => $outputPath])
            ->assertExitCode(0);

        expect(file_exists($outputPath))->toBeTrue();
    } finally {
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

it('returns exit code 1 for unsupported format', function () {
    $this->artisan('openapi:export', ['--format' => 'yaml'])
        ->expectsOutputToContain("Unsupported format: yaml")
        ->assertExitCode(1);
});

it('returns exit code 1 when generation fails', function () {
    $this->app->singleton(SpecGenerator::class, function () {
        $mock = Mockery::mock(SpecGenerator::class);
        $mock->shouldReceive('discoverRoutes')->andThrow(new \RuntimeException('Test error'));
        return $mock;
    });

    $this->artisan('openapi:export')
        ->expectsOutputToContain('Failed to export OpenAPI spec')
        ->assertExitCode(1);
});

it('uses json format by default', function () {
    $outputPath = sys_get_temp_dir() . '/openswag_export_' . uniqid() . '.json';

    try {
        $this->artisan('openapi:export', ['--output' => $outputPath])
            ->assertExitCode(0);

        $content = json_decode(file_get_contents($outputPath), true);
        expect($content)->toBeArray()
            ->and($content)->toHaveKey('openapi')
            ->and($content)->toHaveKey('paths');
    } finally {
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
});
