<?php

use OpenSwag\Laravel\Models\ServiceConfig;

test('ServiceConfig has sensible defaults', function () {
    $config = new ServiceConfig();

    expect($config->name)->toBe('');
    expect($config->url)->toBe('');
    expect($config->pathPrefix)->toBe('');
    expect($config->healthUrl)->toBeNull();
});

test('ServiceConfig toArray serializes all properties', function () {
    $config = new ServiceConfig(
        name: 'user-service',
        url: 'http://user-service:8080/api/docs/json',
        pathPrefix: '/api/users',
        healthUrl: 'http://user-service:8080/health',
    );

    $array = $config->toArray();

    expect($array)->toBe([
        'name' => 'user-service',
        'url' => 'http://user-service:8080/api/docs/json',
        'pathPrefix' => '/api/users',
        'healthUrl' => 'http://user-service:8080/health',
    ]);
});

test('ServiceConfig fromArray reconstructs from array', function () {
    $data = [
        'name' => 'order-service',
        'url' => 'http://order-service:8080/api/docs/json',
        'pathPrefix' => '/api/orders',
        'healthUrl' => null,
    ];

    $config = ServiceConfig::fromArray($data);

    expect($config->name)->toBe('order-service');
    expect($config->url)->toBe('http://order-service:8080/api/docs/json');
    expect($config->pathPrefix)->toBe('/api/orders');
    expect($config->healthUrl)->toBeNull();
});

test('ServiceConfig toArray/fromArray round-trip preserves data', function () {
    $original = new ServiceConfig(
        name: 'product-service',
        url: 'http://product-service:8080/api/docs/json',
        pathPrefix: '/api/products',
        healthUrl: 'http://product-service:8080/health',
    );

    $restored = ServiceConfig::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('ServiceConfig with null healthUrl round-trips correctly', function () {
    $original = new ServiceConfig(
        name: 'auth-service',
        url: 'http://auth-service:8080/api/docs/json',
        pathPrefix: '/api/auth',
    );

    $restored = ServiceConfig::fromArray($original->toArray());

    expect($restored->healthUrl)->toBeNull();
});

test('ServiceConfig fromArray handles missing keys with defaults', function () {
    $config = ServiceConfig::fromArray([]);

    expect($config->name)->toBe('');
    expect($config->url)->toBe('');
    expect($config->pathPrefix)->toBe('');
    expect($config->healthUrl)->toBeNull();
});
