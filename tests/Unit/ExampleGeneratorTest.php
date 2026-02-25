<?php

use OpenSwag\Laravel\ExampleGenerator;
use OpenSwag\Laravel\Tests\Unit\Fixtures\FactoryModel;
use OpenSwag\Laravel\Tests\Unit\Fixtures\NoFactoryModel;

// --- Constructor and Config ---

it('uses default config when no config provided', function () {
    $gen = new ExampleGenerator();

    // Should work without errors and use defaults
    expect($gen->fromFieldName('email'))->toBe('user@example.com');
});

it('respects use_factories config', function () {
    $gen = new ExampleGenerator(['use_factories' => false]);

    $result = $gen->fromFactory(FactoryModel::class);
    expect($result)->toBe([]);
});

it('loads templates from config', function () {
    $gen = new ExampleGenerator([
        'templates' => ['custom_field' => 'custom_value'],
    ]);

    expect($gen->fromFieldName('custom_field'))->toBe('custom_value');
});

// --- fromFactory ---

it('returns factory definition values for a model with factory', function () {
    $gen = new ExampleGenerator();

    $result = $gen->fromFactory(FactoryModel::class);

    expect($result)->toBe([
        'name' => 'Factory Name',
        'email' => 'factory@example.com',
        'age' => 30,
    ]);
});

it('returns empty array for model without factory', function () {
    $gen = new ExampleGenerator();

    $result = $gen->fromFactory(NoFactoryModel::class);
    expect($result)->toBe([]);
});

it('returns empty array for non-existent class', function () {
    $gen = new ExampleGenerator();

    $result = $gen->fromFactory('NonExistent\\Model\\Class');
    expect($result)->toBe([]);
});

it('returns empty array for non-model class', function () {
    $gen = new ExampleGenerator();

    $result = $gen->fromFactory(\stdClass::class);
    expect($result)->toBe([]);
});

it('returns empty array when use_factories is disabled', function () {
    $gen = new ExampleGenerator(['use_factories' => false]);

    $result = $gen->fromFactory(FactoryModel::class);
    expect($result)->toBe([]);
});

// --- fromFieldName heuristics ---

it('returns correct example for email field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('email'))->toBe('user@example.com');
});

it('returns correct example for name field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('name'))->toBe('John Doe');
});

it('returns correct example for first_name field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('first_name'))->toBe('John Doe');
});

it('returns correct example for phone field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('phone'))->toBe('+1234567890');
});

it('returns correct example for url field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('url'))->toBe('https://example.com');
});

it('returns correct example for website field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('website'))->toBe('https://example.com');
});

it('returns correct example for uuid field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('uuid'))->toBe('550e8400-e29b-41d4-a716-446655440000');
});

it('returns correct example for id field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('id'))->toBe('550e8400-e29b-41d4-a716-446655440000');
});

it('returns correct example for date field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('date'))->toBe('2024-01-15');
});

it('returns correct example for birthday field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('birthday'))->toBe('2024-01-15');
});

it('returns correct example for password field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('password'))->toBe('********');
});

it('returns correct example for description field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('description'))->toStartWith('Lorem ipsum');
});

it('returns correct example for bio field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('bio'))->toStartWith('Lorem ipsum');
});

it('returns correct example for address field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('address'))->toBe('123 Main St');
});

it('returns correct example for city field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('city'))->toBe('New York');
});

it('returns correct example for country field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('country'))->toBe('United States');
});

it('returns correct example for zip field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('zip'))->toBe('10001');
});

it('returns correct example for postal_code field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('postal_code'))->toBe('10001');
});

it('returns correct example for age field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('age'))->toBe(25);
});

it('returns correct example for price field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('price'))->toBe(99.99);
});

it('returns correct example for amount field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('amount'))->toBe(99.99);
});

it('returns correct example for balance field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('balance'))->toBe(99.99);
});

it('returns correct example for status field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('status'))->toBe('active');
});

it('returns correct example for title field', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('title'))->toBe('Sample Title');
});

it('is case-insensitive for field name matching', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('Email'))->toBe('user@example.com');
    expect($gen->fromFieldName('EMAIL'))->toBe('user@example.com');
});

it('matches partial field names containing known patterns', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('user_email'))->toBe('user@example.com');
    expect($gen->fromFieldName('home_address'))->toBe('123 Main St');
});

it('falls back to type-based example for unknown field names', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromFieldName('unknown_field', 'integer'))->toBe(1);
    expect($gen->fromFieldName('random_thing', 'boolean'))->toBe(true);
    expect($gen->fromFieldName('something', 'string'))->toBe('string');
});

// --- fromTypeFormat ---

it('returns correct example for string type', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('string'))->toBe('string');
});

it('returns correct example for string/date-time format', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('string', 'date-time'))->toBe('2024-01-15T10:30:00Z');
});

it('returns correct example for string/date format', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('string', 'date'))->toBe('2024-01-15');
});

it('returns correct example for string/email format', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('string', 'email'))->toBe('user@example.com');
});

it('returns correct example for string/uri format', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('string', 'uri'))->toBe('https://example.com');
});

it('returns correct example for string/uuid format', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('string', 'uuid'))->toBe('550e8400-e29b-41d4-a716-446655440000');
});

it('returns correct example for integer type', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('integer'))->toBe(1);
});

it('returns correct example for number type', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('number'))->toBe(1.5);
});

it('returns correct example for boolean type', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('boolean'))->toBe(true);
});

it('returns correct example for array type', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('array'))->toBe([]);
});

it('falls back to string for unknown type/format', function () {
    $gen = new ExampleGenerator();
    expect($gen->fromTypeFormat('object'))->toBe('string');
    expect($gen->fromTypeFormat('string', 'unknown-format'))->toBe('string');
});

// --- registerTemplate ---

it('registers a custom template that overrides heuristics', function () {
    $gen = new ExampleGenerator();

    // Before registration, uses heuristic
    expect($gen->fromFieldName('email'))->toBe('user@example.com');

    // Register custom template
    $gen->registerTemplate('email', 'custom@test.org');

    // After registration, uses custom template
    expect($gen->fromFieldName('email'))->toBe('custom@test.org');
});

it('registers templates for new field names', function () {
    $gen = new ExampleGenerator();

    $gen->registerTemplate('custom_field', ['key' => 'value']);

    expect($gen->fromFieldName('custom_field'))->toBe(['key' => 'value']);
});

it('allows registering templates with various value types', function () {
    $gen = new ExampleGenerator();

    $gen->registerTemplate('string_field', 'hello');
    $gen->registerTemplate('int_field', 42);
    $gen->registerTemplate('float_field', 3.14);
    $gen->registerTemplate('bool_field', false);
    $gen->registerTemplate('array_field', [1, 2, 3]);
    $gen->registerTemplate('null_field', null);

    expect($gen->fromFieldName('string_field'))->toBe('hello');
    expect($gen->fromFieldName('int_field'))->toBe(42);
    expect($gen->fromFieldName('float_field'))->toBe(3.14);
    expect($gen->fromFieldName('bool_field'))->toBe(false);
    expect($gen->fromFieldName('array_field'))->toBe([1, 2, 3]);
    expect($gen->fromFieldName('null_field'))->toBeNull();
});
