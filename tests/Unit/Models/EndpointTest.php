<?php

use OpenSwag\Laravel\Models\Endpoint;
use OpenSwag\Laravel\Models\Parameter;
use OpenSwag\Laravel\Models\RequestBody;
use OpenSwag\Laravel\Models\ResponseDefinition;

test('Endpoint has sensible defaults', function () {
    $endpoint = new Endpoint();

    expect($endpoint->method)->toBe('GET');
    expect($endpoint->path)->toBe('/');
    expect($endpoint->summary)->toBe('');
    expect($endpoint->description)->toBe('');
    expect($endpoint->tags)->toBe([]);
    expect($endpoint->parameters)->toBe([]);
    expect($endpoint->requestBody)->toBeNull();
    expect($endpoint->responses)->toBe([]);
    expect($endpoint->security)->toBe([]);
    expect($endpoint->deprecated)->toBeFalse();
});

test('Endpoint toArray serializes all properties', function () {
    $endpoint = new Endpoint(
        method: 'POST',
        path: '/api/users',
        summary: 'Create user',
        description: 'Creates a new user',
        tags: ['users'],
        parameters: [
            new Parameter(name: 'X-Request-Id', in: 'header', description: 'Request ID', required: true),
        ],
        requestBody: new RequestBody(description: 'User data', required: true, schema: ['type' => 'object']),
        responses: [
            201 => new ResponseDefinition(description: 'Created', schema: ['type' => 'object']),
            422 => new ResponseDefinition(description: 'Validation error'),
        ],
        security: ['bearerAuth'],
        deprecated: false,
    );

    $array = $endpoint->toArray();

    expect($array['method'])->toBe('POST');
    expect($array['path'])->toBe('/api/users');
    expect($array['summary'])->toBe('Create user');
    expect($array['description'])->toBe('Creates a new user');
    expect($array['tags'])->toBe(['users']);
    expect($array['parameters'])->toHaveCount(1);
    expect($array['parameters'][0]['name'])->toBe('X-Request-Id');
    expect($array['requestBody']['description'])->toBe('User data');
    expect($array['responses'])->toHaveCount(2);
    expect($array['responses'][201]['description'])->toBe('Created');
    expect($array['security'])->toBe(['bearerAuth']);
    expect($array['deprecated'])->toBeFalse();
});

test('Endpoint fromArray reconstructs from array', function () {
    $data = [
        'method' => 'PUT',
        'path' => '/api/users/{id}',
        'summary' => 'Update user',
        'description' => 'Updates an existing user',
        'tags' => ['users', 'admin'],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'description' => 'User ID', 'required' => true, 'schema' => ['type' => 'integer'], 'example' => 42],
        ],
        'requestBody' => ['description' => 'Updated data', 'required' => true, 'schema' => ['type' => 'object'], 'contentType' => 'application/json'],
        'responses' => [
            200 => ['description' => 'OK', 'schema' => ['type' => 'object']],
            404 => ['description' => 'Not found', 'schema' => null],
        ],
        'security' => ['bearerAuth'],
        'deprecated' => true,
    ];

    $endpoint = Endpoint::fromArray($data);

    expect($endpoint->method)->toBe('PUT');
    expect($endpoint->path)->toBe('/api/users/{id}');
    expect($endpoint->summary)->toBe('Update user');
    expect($endpoint->tags)->toBe(['users', 'admin']);
    expect($endpoint->parameters)->toHaveCount(1);
    expect($endpoint->parameters[0])->toBeInstanceOf(Parameter::class);
    expect($endpoint->parameters[0]->name)->toBe('id');
    expect($endpoint->requestBody)->toBeInstanceOf(RequestBody::class);
    expect($endpoint->requestBody->description)->toBe('Updated data');
    expect($endpoint->responses)->toHaveCount(2);
    expect($endpoint->responses[200])->toBeInstanceOf(ResponseDefinition::class);
    expect($endpoint->responses[200]->description)->toBe('OK');
    expect($endpoint->deprecated)->toBeTrue();
});

test('Endpoint toArray/fromArray round-trip preserves data', function () {
    $original = new Endpoint(
        method: 'DELETE',
        path: '/api/users/{id}',
        summary: 'Delete user',
        description: 'Deletes a user by ID',
        tags: ['users'],
        parameters: [
            new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
        ],
        requestBody: null,
        responses: [
            204 => new ResponseDefinition(description: 'No content'),
        ],
        security: ['bearerAuth'],
        deprecated: false,
    );

    $restored = Endpoint::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('Endpoint fromArray handles missing keys with defaults', function () {
    $endpoint = Endpoint::fromArray([]);

    expect($endpoint->method)->toBe('GET');
    expect($endpoint->path)->toBe('/');
    expect($endpoint->summary)->toBe('');
    expect($endpoint->tags)->toBe([]);
    expect($endpoint->parameters)->toBe([]);
    expect($endpoint->requestBody)->toBeNull();
    expect($endpoint->responses)->toBe([]);
    expect($endpoint->security)->toBe([]);
    expect($endpoint->deprecated)->toBeFalse();
});
