<?php

use OpenSwag\Laravel\Facades\OpenSwag;
use OpenSwag\Laravel\Models\Endpoint;
use OpenSwag\Laravel\Models\ResponseDefinition;
use OpenSwag\Laravel\SpecGenerator;

it('resolves to SpecGenerator instance', function () {
    expect(OpenSwag::getFacadeRoot())->toBeInstanceOf(SpecGenerator::class);
});

it('proxies addEndpoint to SpecGenerator', function () {
    $endpoint = new Endpoint(
        method: 'GET',
        path: '/api/users',
        summary: 'List users',
        responses: [
            200 => new ResponseDefinition(description: 'Success'),
        ],
    );

    OpenSwag::addEndpoint($endpoint);

    $spec = OpenSwag::buildSpec();

    expect($spec['paths'])->toHaveKey('/api/users')
        ->and($spec['paths']['/api/users'])->toHaveKey('get')
        ->and($spec['paths']['/api/users']['get']['summary'])->toBe('List users');
});

it('proxies buildSpec to SpecGenerator', function () {
    $spec = OpenSwag::buildSpec();

    expect($spec)
        ->toBeArray()
        ->toHaveKeys(['openapi', 'info', 'paths'])
        ->and($spec['openapi'])->toBe('3.0.0');
});

it('proxies specJson to SpecGenerator', function () {
    $json = OpenSwag::specJson();

    expect($json)->toBeString();

    $decoded = json_decode($json, true);

    expect($decoded)
        ->toBeArray()
        ->toHaveKeys(['openapi', 'info', 'paths'])
        ->and($decoded['openapi'])->toBe('3.0.0');
});
