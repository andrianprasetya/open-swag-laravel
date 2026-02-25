<?php

use OpenSwag\Laravel\ExampleGenerator;
use OpenSwag\Laravel\SchemaConverter;
use OpenSwag\Laravel\Tests\Unit\Fixtures\SampleModel;
use OpenSwag\Laravel\Tests\Unit\Fixtures\StoreUserRequest;

// --- Test fixture for fromClass ---

class ExampleTestDto
{
    public string $name;
    public int $age;
    public string $email;
}

// --- Without ExampleGenerator (existing behavior preserved) ---

test('fromClass without ExampleGenerator does not include examples', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(ExampleTestDto::class);

    expect($schema['properties']['name'])->not->toHaveKey('example');
    expect($schema['properties']['age'])->not->toHaveKey('example');
    expect($schema['properties']['email'])->not->toHaveKey('example');
});

test('fromFormRequest without ExampleGenerator does not include examples', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['properties']['name'])->not->toHaveKey('example');
    expect($schema['properties']['email'])->not->toHaveKey('example');
    expect($schema['properties']['age'])->not->toHaveKey('example');
});

test('fromModel without ExampleGenerator does not include examples', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromModel(SampleModel::class);

    expect($schema['properties']['name'])->not->toHaveKey('example');
    expect($schema['properties']['email'])->not->toHaveKey('example');
});

// --- With ExampleGenerator ---

test('fromClass with ExampleGenerator includes example values for each property', function () {
    $converter = new SchemaConverter();
    $converter->setExampleGenerator(new ExampleGenerator());

    $schema = $converter->fromClass(ExampleTestDto::class);

    expect($schema['properties']['name'])->toHaveKey('example');
    expect($schema['properties']['age'])->toHaveKey('example');
    expect($schema['properties']['email'])->toHaveKey('example');

    // Verify heuristic-based examples
    expect($schema['properties']['name']['example'])->toBe('John Doe');
    expect($schema['properties']['email']['example'])->toBe('user@example.com');
});

test('fromFormRequest with ExampleGenerator includes example values for each property', function () {
    $converter = new SchemaConverter();
    $converter->setExampleGenerator(new ExampleGenerator());

    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['properties']['name'])->toHaveKey('example');
    expect($schema['properties']['email'])->toHaveKey('example');
    expect($schema['properties']['age'])->toHaveKey('example');
    expect($schema['properties']['bio'])->toHaveKey('example');
    expect($schema['properties']['role'])->toHaveKey('example');

    // Verify heuristic-based examples
    expect($schema['properties']['email']['example'])->toBe('user@example.com');
    expect($schema['properties']['name']['example'])->toBe('John Doe');
});

test('fromModel with ExampleGenerator includes example values for each property', function () {
    $converter = new SchemaConverter();
    $converter->setExampleGenerator(new ExampleGenerator());

    $schema = $converter->fromModel(SampleModel::class);

    expect($schema['properties']['name'])->toHaveKey('example');
    expect($schema['properties']['email'])->toHaveKey('example');
    expect($schema['properties']['age'])->toHaveKey('example');

    // Verify heuristic-based examples
    expect($schema['properties']['name']['example'])->toBe('John Doe');
    expect($schema['properties']['email']['example'])->toBe('user@example.com');
});

test('fromClass with ExampleGenerator uses type-appropriate examples', function () {
    $converter = new SchemaConverter();
    $converter->setExampleGenerator(new ExampleGenerator());

    $schema = $converter->fromClass(ExampleTestDto::class);

    // age is an integer type, should get an integer example
    expect($schema['properties']['age']['example'])->toBeInt();
});

test('fromModel with ExampleGenerator uses field name heuristics over type fallback', function () {
    $converter = new SchemaConverter();
    $converter->setExampleGenerator(new ExampleGenerator());

    $schema = $converter->fromModel(SampleModel::class);

    // 'balance' matches the heuristic for price/amount/balance → 99.99
    expect($schema['properties']['balance']['example'])->toBe(99.99);
});

// --- Service Provider wiring ---

test('service provider wires ExampleGenerator into SchemaConverter', function () {
    $converter = app(SchemaConverter::class);

    $reflection = new \ReflectionClass($converter);
    $prop = $reflection->getProperty('exampleGenerator');
    $prop->setAccessible(true);

    expect($prop->getValue($converter))->toBeInstanceOf(ExampleGenerator::class);
});

test('service provider registers ExampleGenerator as a singleton', function () {
    $instance1 = app(ExampleGenerator::class);
    $instance2 = app(ExampleGenerator::class);

    expect($instance1)
        ->toBeInstanceOf(ExampleGenerator::class)
        ->toBe($instance2);
});
