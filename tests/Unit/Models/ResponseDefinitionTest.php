<?php

use OpenSwag\Laravel\Models\ResponseDefinition;

test('ResponseDefinition has sensible defaults', function () {
    $response = new ResponseDefinition();

    expect($response->description)->toBe('');
    expect($response->schema)->toBeNull();
});

test('ResponseDefinition toArray serializes all properties', function () {
    $response = new ResponseDefinition(
        description: 'Successful response',
        schema: ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
    );

    $array = $response->toArray();

    expect($array)->toBe([
        'description' => 'Successful response',
        'schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
    ]);
});

test('ResponseDefinition fromArray reconstructs from array', function () {
    $data = [
        'description' => 'Not found',
        'schema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]],
    ];

    $response = ResponseDefinition::fromArray($data);

    expect($response->description)->toBe('Not found');
    expect($response->schema)->toBe(['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]);
});

test('ResponseDefinition toArray/fromArray round-trip preserves data', function () {
    $original = new ResponseDefinition(
        description: 'Created',
        schema: ['type' => 'object'],
    );

    $restored = ResponseDefinition::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('ResponseDefinition with null schema round-trips correctly', function () {
    $original = new ResponseDefinition(description: 'No content');

    $restored = ResponseDefinition::fromArray($original->toArray());

    expect($restored->description)->toBe('No content');
    expect($restored->schema)->toBeNull();
});

test('ResponseDefinition fromArray handles missing keys with defaults', function () {
    $response = ResponseDefinition::fromArray([]);

    expect($response->description)->toBe('');
    expect($response->schema)->toBeNull();
});
