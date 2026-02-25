<?php

use OpenSwag\Laravel\Models\Change;

test('Change has sensible defaults', function () {
    $change = new Change();

    expect($change->type)->toBe('');
    expect($change->path)->toBe('');
    expect($change->method)->toBe('');
    expect($change->description)->toBe('');
    expect($change->isBreaking)->toBeFalse();
});

test('Change toArray serializes all properties', function () {
    $change = new Change(
        type: 'removed',
        path: '/api/users/{id}',
        method: 'DELETE',
        description: 'Endpoint removed',
        isBreaking: true,
    );

    $array = $change->toArray();

    expect($array)->toBe([
        'type' => 'removed',
        'path' => '/api/users/{id}',
        'method' => 'DELETE',
        'description' => 'Endpoint removed',
        'isBreaking' => true,
    ]);
});

test('Change fromArray reconstructs from array', function () {
    $data = [
        'type' => 'added',
        'path' => '/api/orders',
        'method' => 'POST',
        'description' => 'New endpoint',
        'isBreaking' => false,
    ];

    $change = Change::fromArray($data);

    expect($change->type)->toBe('added');
    expect($change->path)->toBe('/api/orders');
    expect($change->method)->toBe('POST');
    expect($change->description)->toBe('New endpoint');
    expect($change->isBreaking)->toBeFalse();
});

test('Change toArray/fromArray round-trip preserves data', function () {
    $original = new Change(
        type: 'modified',
        path: '/api/users',
        method: 'PUT',
        description: 'Schema changed',
        isBreaking: true,
    );

    $restored = Change::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('Change fromArray handles missing keys with defaults', function () {
    $change = Change::fromArray([]);

    expect($change->type)->toBe('');
    expect($change->path)->toBe('');
    expect($change->method)->toBe('');
    expect($change->description)->toBe('');
    expect($change->isBreaking)->toBeFalse();
});

test('Change toJson/fromJson round-trip preserves data', function () {
    $original = new Change(
        type: 'removed',
        path: '/api/products/{id}',
        method: 'DELETE',
        description: 'Product endpoint removed',
        isBreaking: true,
    );

    $restored = Change::fromJson($original->toJson());

    expect($restored->toArray())->toBe($original->toArray());
});
