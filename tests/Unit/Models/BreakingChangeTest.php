<?php

use OpenSwag\Laravel\Models\BreakingChange;

test('BreakingChange has sensible defaults', function () {
    $bc = new BreakingChange();

    expect($bc->path)->toBe('');
    expect($bc->method)->toBe('');
    expect($bc->reason)->toBe('');
    expect($bc->migration)->toBe('');
});

test('BreakingChange toArray serializes all properties', function () {
    $bc = new BreakingChange(
        path: '/api/users/{id}',
        method: 'DELETE',
        reason: 'Endpoint removed',
        migration: 'Use /api/v2/users/{id} instead',
    );

    $array = $bc->toArray();

    expect($array)->toBe([
        'path' => '/api/users/{id}',
        'method' => 'DELETE',
        'reason' => 'Endpoint removed',
        'migration' => 'Use /api/v2/users/{id} instead',
    ]);
});

test('BreakingChange fromArray reconstructs from array', function () {
    $data = [
        'path' => '/api/orders',
        'method' => 'POST',
        'reason' => 'New required field added',
        'migration' => 'Add "priority" field to request body',
    ];

    $bc = BreakingChange::fromArray($data);

    expect($bc->path)->toBe('/api/orders');
    expect($bc->method)->toBe('POST');
    expect($bc->reason)->toBe('New required field added');
    expect($bc->migration)->toBe('Add "priority" field to request body');
});

test('BreakingChange toArray/fromArray round-trip preserves data', function () {
    $original = new BreakingChange(
        path: '/api/users',
        method: 'PUT',
        reason: 'Required param added',
        migration: 'Include "email" in all requests',
    );

    $restored = BreakingChange::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('BreakingChange fromArray handles missing keys with defaults', function () {
    $bc = BreakingChange::fromArray([]);

    expect($bc->path)->toBe('');
    expect($bc->method)->toBe('');
    expect($bc->reason)->toBe('');
    expect($bc->migration)->toBe('');
});

test('BreakingChange toJson/fromJson round-trip preserves data', function () {
    $original = new BreakingChange(
        path: '/api/products',
        method: 'GET',
        reason: 'Response code 200 removed',
        migration: 'Handle 204 response instead',
    );

    $restored = BreakingChange::fromJson($original->toJson());

    expect($restored->toArray())->toBe($original->toArray());
});
