<?php

use OpenSwag\Laravel\Models\RequestBody;

test('RequestBody has sensible defaults', function () {
    $body = new RequestBody();

    expect($body->description)->toBe('');
    expect($body->required)->toBeFalse();
    expect($body->schema)->toBe([]);
    expect($body->contentType)->toBe('application/json');
});

test('RequestBody toArray serializes all properties', function () {
    $body = new RequestBody(
        description: 'User creation payload',
        required: true,
        schema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        contentType: 'application/json',
    );

    $array = $body->toArray();

    expect($array)->toBe([
        'description' => 'User creation payload',
        'required' => true,
        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        'contentType' => 'application/json',
    ]);
});

test('RequestBody fromArray reconstructs from array', function () {
    $data = [
        'description' => 'File upload',
        'required' => true,
        'schema' => ['type' => 'string', 'format' => 'binary'],
        'contentType' => 'multipart/form-data',
    ];

    $body = RequestBody::fromArray($data);

    expect($body->description)->toBe('File upload');
    expect($body->required)->toBeTrue();
    expect($body->contentType)->toBe('multipart/form-data');
});

test('RequestBody toArray/fromArray round-trip preserves data', function () {
    $original = new RequestBody(
        description: 'JSON payload',
        required: true,
        schema: ['type' => 'object'],
        contentType: 'application/json',
    );

    $restored = RequestBody::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('RequestBody fromArray handles missing keys with defaults', function () {
    $body = RequestBody::fromArray([]);

    expect($body->description)->toBe('');
    expect($body->required)->toBeFalse();
    expect($body->schema)->toBe([]);
    expect($body->contentType)->toBe('application/json');
});
