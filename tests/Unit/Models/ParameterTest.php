<?php

use OpenSwag\Laravel\Models\Parameter;

test('Parameter has sensible defaults', function () {
    $param = new Parameter();

    expect($param->name)->toBe('');
    expect($param->in)->toBe('query');
    expect($param->description)->toBe('');
    expect($param->required)->toBeFalse();
    expect($param->schema)->toBe([]);
    expect($param->example)->toBeNull();
});

test('Parameter toArray serializes all properties', function () {
    $param = new Parameter(
        name: 'page',
        in: 'query',
        description: 'Page number',
        required: false,
        schema: ['type' => 'integer', 'minimum' => 1],
        example: 1,
    );

    $array = $param->toArray();

    expect($array)->toBe([
        'name' => 'page',
        'in' => 'query',
        'description' => 'Page number',
        'required' => false,
        'schema' => ['type' => 'integer', 'minimum' => 1],
        'example' => 1,
    ]);
});

test('Parameter fromArray reconstructs from array', function () {
    $data = [
        'name' => 'Authorization',
        'in' => 'header',
        'description' => 'Bearer token',
        'required' => true,
        'schema' => ['type' => 'string'],
        'example' => 'Bearer abc123',
    ];

    $param = Parameter::fromArray($data);

    expect($param->name)->toBe('Authorization');
    expect($param->in)->toBe('header');
    expect($param->required)->toBeTrue();
    expect($param->example)->toBe('Bearer abc123');
});

test('Parameter toArray/fromArray round-trip preserves data', function () {
    $original = new Parameter(
        name: 'filter',
        in: 'query',
        description: 'Filter expression',
        required: false,
        schema: ['type' => 'string'],
        example: 'status:active',
    );

    $restored = Parameter::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('Parameter fromArray handles missing keys with defaults', function () {
    $param = Parameter::fromArray([]);

    expect($param->name)->toBe('');
    expect($param->in)->toBe('query');
    expect($param->required)->toBeFalse();
    expect($param->schema)->toBe([]);
    expect($param->example)->toBeNull();
});
