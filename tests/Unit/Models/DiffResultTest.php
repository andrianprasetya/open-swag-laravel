<?php

use OpenSwag\Laravel\Models\Change;
use OpenSwag\Laravel\Models\BreakingChange;
use OpenSwag\Laravel\Models\DiffSummary;
use OpenSwag\Laravel\Models\DiffResult;

test('DiffResult has sensible defaults', function () {
    $result = new DiffResult();

    expect($result->oldVersion)->toBe('');
    expect($result->newVersion)->toBe('');
    expect($result->changes)->toBe([]);
    expect($result->breaking)->toBe([]);
    expect($result->summary)->toBeInstanceOf(DiffSummary::class);
    expect($result->summary->addedEndpoints)->toBe(0);
});

test('DiffResult summary defaults to new DiffSummary when null', function () {
    $result = new DiffResult(summary: null);

    expect($result->summary)->toBeInstanceOf(DiffSummary::class);
});

test('DiffResult hasBreakingChanges returns true when breaking changes exist', function () {
    $result = new DiffResult(
        breaking: [
            new BreakingChange(path: '/api/users', method: 'DELETE', reason: 'Removed'),
        ],
    );

    expect($result->hasBreakingChanges())->toBeTrue();
});

test('DiffResult hasBreakingChanges returns false when no breaking changes', function () {
    $result = new DiffResult();

    expect($result->hasBreakingChanges())->toBeFalse();
});

test('DiffResult toArray serializes all properties', function () {
    $result = new DiffResult(
        oldVersion: '1.0.0',
        newVersion: '2.0.0',
        changes: [
            new Change(type: 'added', path: '/api/orders', method: 'POST', description: 'New endpoint'),
            new Change(type: 'removed', path: '/api/legacy', method: 'GET', description: 'Removed', isBreaking: true),
        ],
        breaking: [
            new BreakingChange(path: '/api/legacy', method: 'GET', reason: 'Endpoint removed', migration: 'Use /api/v2/legacy'),
        ],
        summary: new DiffSummary(addedEndpoints: 1, removedEndpoints: 1, modifiedEndpoints: 0, breakingChanges: 1),
    );

    $array = $result->toArray();

    expect($array['oldVersion'])->toBe('1.0.0');
    expect($array['newVersion'])->toBe('2.0.0');
    expect($array['changes'])->toHaveCount(2);
    expect($array['changes'][0]['type'])->toBe('added');
    expect($array['changes'][1]['isBreaking'])->toBeTrue();
    expect($array['breaking'])->toHaveCount(1);
    expect($array['breaking'][0]['reason'])->toBe('Endpoint removed');
    expect($array['summary']['addedEndpoints'])->toBe(1);
    expect($array['summary']['breakingChanges'])->toBe(1);
});

test('DiffResult fromArray reconstructs from array', function () {
    $data = [
        'oldVersion' => '1.0.0',
        'newVersion' => '1.1.0',
        'changes' => [
            ['type' => 'added', 'path' => '/api/items', 'method' => 'GET', 'description' => 'List items', 'isBreaking' => false],
        ],
        'breaking' => [],
        'summary' => ['addedEndpoints' => 1, 'removedEndpoints' => 0, 'modifiedEndpoints' => 0, 'breakingChanges' => 0],
    ];

    $result = DiffResult::fromArray($data);

    expect($result->oldVersion)->toBe('1.0.0');
    expect($result->newVersion)->toBe('1.1.0');
    expect($result->changes)->toHaveCount(1);
    expect($result->changes[0])->toBeInstanceOf(Change::class);
    expect($result->changes[0]->path)->toBe('/api/items');
    expect($result->breaking)->toBe([]);
    expect($result->summary)->toBeInstanceOf(DiffSummary::class);
    expect($result->summary->addedEndpoints)->toBe(1);
});

test('DiffResult toArray/fromArray round-trip preserves data', function () {
    $original = new DiffResult(
        oldVersion: '2.0.0',
        newVersion: '3.0.0',
        changes: [
            new Change(type: 'modified', path: '/api/users', method: 'PUT', description: 'Schema changed', isBreaking: true),
        ],
        breaking: [
            new BreakingChange(path: '/api/users', method: 'PUT', reason: 'Required field added', migration: 'Add "role" field'),
        ],
        summary: new DiffSummary(addedEndpoints: 0, removedEndpoints: 0, modifiedEndpoints: 1, breakingChanges: 1),
    );

    $restored = DiffResult::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('DiffResult toJson/fromJson round-trip preserves data', function () {
    $original = new DiffResult(
        oldVersion: '1.0.0',
        newVersion: '2.0.0',
        changes: [
            new Change(type: 'added', path: '/api/products', method: 'GET', description: 'List products'),
            new Change(type: 'removed', path: '/api/old', method: 'DELETE', description: 'Removed', isBreaking: true),
        ],
        breaking: [
            new BreakingChange(path: '/api/old', method: 'DELETE', reason: 'Endpoint removed', migration: 'No replacement'),
        ],
        summary: new DiffSummary(addedEndpoints: 1, removedEndpoints: 1, modifiedEndpoints: 0, breakingChanges: 1),
    );

    $json = $original->toJson();
    $restored = DiffResult::fromJson($json);

    expect($restored->toArray())->toBe($original->toArray());
});

test('DiffResult fromArray handles missing keys with defaults', function () {
    $result = DiffResult::fromArray([]);

    expect($result->oldVersion)->toBe('');
    expect($result->newVersion)->toBe('');
    expect($result->changes)->toBe([]);
    expect($result->breaking)->toBe([]);
    expect($result->summary)->toBeInstanceOf(DiffSummary::class);
});

test('DiffResult toMarkdown produces markdown output', function () {
    $result = new DiffResult(
        oldVersion: '1.0.0',
        newVersion: '2.0.0',
        changes: [
            new Change(type: 'added', path: '/api/orders', method: 'POST', description: 'Create order'),
            new Change(type: 'removed', path: '/api/legacy', method: 'GET', description: 'Legacy endpoint', isBreaking: true),
        ],
        breaking: [
            new BreakingChange(path: '/api/legacy', method: 'GET', reason: 'Endpoint removed', migration: 'Use v2'),
        ],
        summary: new DiffSummary(addedEndpoints: 1, removedEndpoints: 1, modifiedEndpoints: 0, breakingChanges: 1),
    );

    $md = $result->toMarkdown();

    expect($md)->toContain('# Changelog: 1.0.0 → 2.0.0');
    expect($md)->toContain('## Added (1)');
    expect($md)->toContain('`POST /api/orders`');
    expect($md)->toContain('## Removed (1)');
    expect($md)->toContain('`GET /api/legacy`');
    expect($md)->toContain('## Breaking Changes (1)');
    expect($md)->toContain('Endpoint removed');
});
