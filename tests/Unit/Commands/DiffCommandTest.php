<?php

declare(strict_types=1);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/openswag_diff_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    // Cleanup temp files
    $files = glob($this->tempDir . '/*');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($this->tempDir);
});

function createSpecFile(string $dir, string $name, array $spec): string
{
    $path = $dir . '/' . $name;
    file_put_contents($path, json_encode($spec, JSON_THROW_ON_ERROR));

    return $path;
}

function baseSpec(array $paths = [], string $version = '1.0.0'): array
{
    return [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => $version],
        'paths' => $paths,
    ];
}

it('compares two identical specs and shows zero changes', function () {
    $spec = baseSpec([
        '/api/users' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
    ]);

    $oldPath = createSpecFile($this->tempDir, 'old.json', $spec);
    $newPath = createSpecFile($this->tempDir, 'new.json', $spec);

    $this->artisan('openapi:diff', ['old' => $oldPath, 'new' => $newPath])
        ->expectsOutputToContain('Added endpoints:    0')
        ->expectsOutputToContain('Removed endpoints:  0')
        ->expectsOutputToContain('Breaking changes:   0')
        ->assertExitCode(0);
});

it('detects added endpoints', function () {
    $oldSpec = baseSpec([
        '/api/users' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
    ]);
    $newSpec = baseSpec([
        '/api/users' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
        '/api/posts' => ['get' => ['summary' => 'List posts', 'responses' => ['200' => ['description' => 'OK']]]],
    ]);

    $oldPath = createSpecFile($this->tempDir, 'old.json', $oldSpec);
    $newPath = createSpecFile($this->tempDir, 'new.json', $newSpec);

    $this->artisan('openapi:diff', ['old' => $oldPath, 'new' => $newPath])
        ->expectsOutputToContain('Added endpoints:    1')
        ->assertExitCode(0);
});

it('detects removed endpoints and shows breaking changes', function () {
    $oldSpec = baseSpec([
        '/api/users' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
        '/api/posts' => ['get' => ['summary' => 'List posts', 'responses' => ['200' => ['description' => 'OK']]]],
    ]);
    $newSpec = baseSpec([
        '/api/users' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
    ]);

    $oldPath = createSpecFile($this->tempDir, 'old.json', $oldSpec);
    $newPath = createSpecFile($this->tempDir, 'new.json', $newSpec);

    $this->artisan('openapi:diff', ['old' => $oldPath, 'new' => $newPath])
        ->expectsOutputToContain('Removed endpoints:  1')
        ->expectsOutputToContain('Breaking changes:   1')
        ->expectsOutputToContain('Breaking Changes:')
        ->assertExitCode(0);
});

it('outputs JSON when --format=json is used', function () {
    $spec = baseSpec([
        '/api/users' => ['get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]]],
    ]);

    $oldPath = createSpecFile($this->tempDir, 'old.json', $spec);
    $newPath = createSpecFile($this->tempDir, 'new.json', $spec);

    $this->artisan('openapi:diff', ['old' => $oldPath, 'new' => $newPath, '--format' => 'json'])
        ->expectsOutputToContain('"summary"')
        ->assertExitCode(0);
});

it('returns exit code 1 when old file does not exist', function () {
    $newPath = createSpecFile($this->tempDir, 'new.json', baseSpec());

    $this->artisan('openapi:diff', ['old' => '/nonexistent/old.json', 'new' => $newPath])
        ->expectsOutputToContain('Spec file not found')
        ->assertExitCode(1);
});

it('returns exit code 1 when new file does not exist', function () {
    $oldPath = createSpecFile($this->tempDir, 'old.json', baseSpec());

    $this->artisan('openapi:diff', ['old' => $oldPath, 'new' => '/nonexistent/new.json'])
        ->expectsOutputToContain('Spec file not found')
        ->assertExitCode(1);
});

it('shows migration guide when breaking changes exist', function () {
    $oldSpec = baseSpec([
        '/api/users' => [
            'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
            'post' => ['summary' => 'Create user', 'responses' => ['201' => ['description' => 'Created']]],
        ],
    ]);
    $newSpec = baseSpec([
        '/api/users' => [
            'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
        ],
    ]);

    $oldPath = createSpecFile($this->tempDir, 'old.json', $oldSpec);
    $newPath = createSpecFile($this->tempDir, 'new.json', $newSpec);

    $this->artisan('openapi:diff', ['old' => $oldPath, 'new' => $newPath])
        ->expectsOutputToContain('Breaking Changes:')
        ->expectsOutputToContain('Migration Guide:')
        ->assertExitCode(0);
});
