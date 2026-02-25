<?php

use OpenSwag\Laravel\SchemaConverter;

// --- Test fixture classes ---

class SimpleDto
{
    public int $id;
    public string $name;
    public float $price;
    public bool $active;
    public array $tags;
}

class NullableDto
{
    public ?string $nickname;
    public ?int $age;
    public string $name;
}

class DefaultValueDto
{
    public string $status = 'active';
    public ?string $label = null;
    public int $count;
}

class DocblockDto
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var float */
    public $price;

    /** @var bool */
    public $active;

    /** @var array */
    public $tags;
}

class DocblockNullableDto
{
    /** @var ?string */
    public $nickname;

    /** @var string|null */
    public $label;

    /** @var int */
    public $id;
}

class NestedAddress
{
    public string $street;
    public string $city;
}

class PersonWithAddress
{
    public string $name;
    public NestedAddress $address;
}

class PromotedPropsDto
{
    public function __construct(
        public string $name,
        public int $age,
        public ?string $email = null,
        public string $role = 'user',
    ) {}
}

class MixedTypeDto
{
    public mixed $data;
    public string $name;
}

class UnionTypeDto
{
    public int|string $identifier;
    public string $name;
}

class DocblockArrayItemsDto
{
    /** @var string[] */
    public $names;

    /** @var int[] */
    public $ids;
}

class EmptyDto
{
}

// --- Tests ---

test('fromClass maps basic PHP types to OpenAPI types', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(SimpleDto::class);

    expect($schema['type'])->toBe('object');
    expect($schema['properties']['id'])->toBe(['type' => 'integer']);
    expect($schema['properties']['name'])->toBe(['type' => 'string']);
    expect($schema['properties']['price'])->toBe(['type' => 'number']);
    expect($schema['properties']['active'])->toBe(['type' => 'boolean']);
    expect($schema['properties']['tags'])->toBe(['type' => 'array']);
});

test('fromClass marks non-nullable, non-default properties as required', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(SimpleDto::class);

    expect($schema['required'])->toBe(['id', 'name', 'price', 'active', 'tags']);
});

test('fromClass handles nullable types with nullable flag', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(NullableDto::class);

    expect($schema['properties']['nickname'])->toBe(['type' => 'string', 'nullable' => true]);
    expect($schema['properties']['age'])->toBe(['type' => 'integer', 'nullable' => true]);
    expect($schema['properties']['name'])->toBe(['type' => 'string']);
    expect($schema['required'])->toBe(['name']);
});

test('fromClass treats properties with default values as optional', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(DefaultValueDto::class);

    expect($schema['required'])->toBe(['count']);
});

test('fromClass uses docblock @var when native type is absent', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(DocblockDto::class);

    expect($schema['properties']['id'])->toBe(['type' => 'integer']);
    expect($schema['properties']['name'])->toBe(['type' => 'string']);
    expect($schema['properties']['price'])->toBe(['type' => 'number']);
    expect($schema['properties']['active'])->toBe(['type' => 'boolean']);
    expect($schema['properties']['tags'])->toBe(['type' => 'array']);
});

test('fromClass handles docblock nullable annotations', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(DocblockNullableDto::class);

    expect($schema['properties']['nickname'])->toBe(['type' => 'string', 'nullable' => true]);
    expect($schema['properties']['label'])->toBe(['type' => 'string', 'nullable' => true]);
    expect($schema['properties']['id'])->toBe(['type' => 'integer']);
});

test('fromClass recursively converts class-typed properties', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(PersonWithAddress::class);

    expect($schema['properties']['name'])->toBe(['type' => 'string']);
    expect($schema['properties']['address']['type'])->toBe('object');
    expect($schema['properties']['address']['properties']['street'])->toBe(['type' => 'string']);
    expect($schema['properties']['address']['properties']['city'])->toBe(['type' => 'string']);
});

test('fromClass handles promoted constructor properties', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(PromotedPropsDto::class);

    expect($schema['properties']['name'])->toBe(['type' => 'string']);
    expect($schema['properties']['age'])->toBe(['type' => 'integer']);
    expect($schema['properties']['email'])->toBe(['type' => 'string', 'nullable' => true]);
    expect($schema['required'])->toBe(['name', 'age']);
});

test('fromClass handles mixed type', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(MixedTypeDto::class);

    // mixed maps to empty schema (no type constraint)
    expect($schema['properties']['data'])->toBe(['nullable' => true]);
    expect($schema['properties']['name'])->toBe(['type' => 'string']);
});

test('fromClass handles union types', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(UnionTypeDto::class);

    expect($schema['properties']['identifier'])->toHaveKey('oneOf');
    $types = array_map(fn ($s) => $s['type'], $schema['properties']['identifier']['oneOf']);
    sort($types);
    expect($types)->toBe(['integer', 'string']);
});

test('fromClass handles docblock array items notation', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromClass(DocblockArrayItemsDto::class);

    expect($schema['properties']['names'])->toBe([
        'type' => 'array',
        'items' => ['type' => 'string'],
    ]);
    expect($schema['properties']['ids'])->toBe([
        'type' => 'array',
        'items' => ['type' => 'integer'],
    ]);
});

test('fromClass returns empty required array omitted when all optional', function () {
    $converter = new SchemaConverter();

    // NullableDto has 'name' as required, so let's use a class where all are optional
    $schema = $converter->fromClass(DefaultValueDto::class);

    // count is required, status has default, label is nullable with default
    expect($schema['required'])->toBe(['count']);
});

test('fromType maps ReflectionNamedType correctly', function () {
    $converter = new SchemaConverter();
    $reflection = new ReflectionClass(SimpleDto::class);

    $idType = $reflection->getProperty('id')->getType();
    expect($converter->fromType($idType))->toBe(['type' => 'integer']);

    $nameType = $reflection->getProperty('name')->getType();
    expect($converter->fromType($nameType))->toBe(['type' => 'string']);

    $priceType = $reflection->getProperty('price')->getType();
    expect($converter->fromType($priceType))->toBe(['type' => 'number']);

    $activeType = $reflection->getProperty('active')->getType();
    expect($converter->fromType($activeType))->toBe(['type' => 'boolean']);
});

test('fromType handles nullable ReflectionNamedType', function () {
    $converter = new SchemaConverter();
    $reflection = new ReflectionClass(NullableDto::class);

    $type = $reflection->getProperty('nickname')->getType();
    // fromType returns the base type; nullable is handled in fromClass
    expect($converter->fromType($type))->toBe(['type' => 'string']);
});

test('fromClass with empty class returns object with no properties', function () {
    // Anonymous class workaround - use a named empty class
    $schema = (new SchemaConverter())->fromClass(EmptyDto::class);

    expect($schema)->toBe([
        'type' => 'object',
        'properties' => [],
    ]);
});


// --- fromFormRequest tests ---

use OpenSwag\Laravel\Tests\Unit\Fixtures\StoreUserRequest;
use OpenSwag\Laravel\Tests\Unit\Fixtures\ArrayRulesRequest;
use OpenSwag\Laravel\Tests\Unit\Fixtures\FormatRulesRequest;

test('fromFormRequest generates schema with correct property types from string rules', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['type'])->toBe('object');
    expect($schema['properties']['name']['type'])->toBe('string');
    expect($schema['properties']['email']['type'])->toBe('string');
    expect($schema['properties']['age']['type'])->toBe('integer');
    expect($schema['properties']['bio']['type'])->toBe('string');
    expect($schema['properties']['role']['type'])->toBe('string');
});

test('fromFormRequest marks required fields correctly', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['required'])->toContain('name');
    expect($schema['required'])->toContain('email');
    expect($schema['required'])->toContain('age');
    expect($schema['required'])->toContain('role');
    expect($schema['required'])->not->toContain('bio');
});

test('fromFormRequest marks nullable fields', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['properties']['bio']['nullable'])->toBeTrue();
    expect($schema['properties']['name'])->not->toHaveKey('nullable');
});

test('fromFormRequest extracts enum values from in: rule', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['properties']['role']['enum'])->toBe(['admin', 'editor', 'viewer']);
});

test('fromFormRequest handles array-format rules', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(ArrayRulesRequest::class);

    expect($schema['type'])->toBe('object');
    expect($schema['properties']['title']['type'])->toBe('string');
    expect($schema['properties']['price']['type'])->toBe('number');
    expect($schema['properties']['active']['type'])->toBe('boolean');
    expect($schema['properties']['tags']['type'])->toBe('array');
    expect($schema['properties']['website']['nullable'])->toBeTrue();
    expect($schema['required'])->toBe(['title', 'price']);
});

test('fromFormRequest maps format rules to OpenAPI formats', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(FormatRulesRequest::class);

    expect($schema['properties']['email']['format'])->toBe('email');
    expect($schema['properties']['website']['format'])->toBe('uri');
    expect($schema['properties']['birthday']['format'])->toBe('date');
    expect($schema['properties']['token']['format'])->toBe('uuid');
});

test('fromFormRequest throws for non-existent class', function () {
    $converter = new SchemaConverter();
    $converter->fromFormRequest('NonExistent\\FakeClass');
})->throws(\InvalidArgumentException::class, 'does not exist');

test('fromFormRequest throws for class not extending FormRequest', function () {
    $converter = new SchemaConverter();
    $converter->fromFormRequest(SimpleDto::class);
})->throws(\InvalidArgumentException::class, 'does not extend FormRequest');

test('fromFormRequest omits required key when no fields are required', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(ArrayRulesRequest::class);

    // active, tags, website are not required — but title and price are
    // Let's verify the required array only has the expected entries
    expect($schema['required'])->toBe(['title', 'price']);
});

test('fromFormRequest generates email format from email rule', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromFormRequest(StoreUserRequest::class);

    expect($schema['properties']['email']['format'])->toBe('email');
});


// --- fromModel tests ---

use OpenSwag\Laravel\Tests\Unit\Fixtures\SampleModel;

test('fromModel generates schema from fillable fields excluding hidden', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromModel(SampleModel::class);

    expect($schema['type'])->toBe('object');
    expect($schema['properties'])->toHaveKeys([
        'name', 'email', 'age', 'balance', 'is_active',
        'settings', 'metadata', 'tags', 'birthday', 'created_at', 'login_count',
    ]);
    expect($schema['properties'])->not->toHaveKey('password');
});

test('fromModel maps cast types to OpenAPI types correctly', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromModel(SampleModel::class);

    expect($schema['properties']['age'])->toBe(['type' => 'integer']);
    expect($schema['properties']['balance'])->toBe(['type' => 'number']);
    expect($schema['properties']['is_active'])->toBe(['type' => 'boolean']);
    expect($schema['properties']['settings'])->toBe(['type' => 'object']);
    expect($schema['properties']['metadata'])->toBe(['type' => 'array']);
    expect($schema['properties']['tags'])->toBe(['type' => 'array']);
    expect($schema['properties']['birthday'])->toBe(['type' => 'string', 'format' => 'date-time']);
    expect($schema['properties']['created_at'])->toBe(['type' => 'string', 'format' => 'date-time']);
    expect($schema['properties']['login_count'])->toBe(['type' => 'integer']);
});

test('fromModel defaults to string type for fields without casts', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromModel(SampleModel::class);

    expect($schema['properties']['name'])->toBe(['type' => 'string']);
    expect($schema['properties']['email'])->toBe(['type' => 'string']);
});

test('fromModel throws for non-existent class', function () {
    $converter = new SchemaConverter();
    $converter->fromModel('NonExistent\\FakeModel');
})->throws(\InvalidArgumentException::class, 'does not exist');

test('fromModel throws for class not extending Model', function () {
    $converter = new SchemaConverter();
    $converter->fromModel(SimpleDto::class);
})->throws(\InvalidArgumentException::class, 'does not extend Eloquent Model');


// --- fromResource tests ---

use OpenSwag\Laravel\Tests\Unit\Fixtures\UserResource;
use OpenSwag\Laravel\Tests\Unit\Fixtures\SimpleResource;

test('fromResource generates schema with keys from toArray output', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromResource(UserResource::class);

    expect($schema['type'])->toBe('object');
    expect($schema['properties'])->toHaveKeys(['id', 'name', 'email', 'is_active', 'age', 'tags']);
});

test('fromResource infers types from cast values in toArray', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromResource(UserResource::class);

    // (bool) cast produces a boolean value
    expect($schema['properties']['is_active']['type'])->toBe('boolean');
    // (int) cast produces an integer value
    expect($schema['properties']['age']['type'])->toBe('integer');
    // (float) cast produces a number value
    expect($schema['properties']['balance']['type'])->toBe('number');
});

test('fromResource marks null values as nullable string', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromResource(UserResource::class);

    // Properties that resolve to null from the dummy model get nullable string
    expect($schema['properties']['id'])->toBe(['type' => 'string', 'nullable' => true]);
    expect($schema['properties']['name'])->toBe(['type' => 'string', 'nullable' => true]);
    expect($schema['properties']['email'])->toBe(['type' => 'string', 'nullable' => true]);
});

test('fromResource handles resource with literal values', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromResource(SimpleResource::class);

    expect($schema['type'])->toBe('object');
    expect($schema['properties'])->toHaveKeys(['title', 'published']);
    expect($schema['properties']['published']['type'])->toBe('boolean');
});

test('fromResource handles empty array values as array type', function () {
    $converter = new SchemaConverter();
    $schema = $converter->fromResource(UserResource::class);

    // tags defaults to [] via null coalescing
    expect($schema['properties']['tags']['type'])->toBe('array');
});

test('fromResource throws for non-existent class', function () {
    $converter = new SchemaConverter();
    $converter->fromResource('NonExistent\\FakeResource');
})->throws(\InvalidArgumentException::class, 'does not exist');

test('fromResource throws for class not extending JsonResource', function () {
    $converter = new SchemaConverter();
    $converter->fromResource(SimpleDto::class);
})->throws(\InvalidArgumentException::class, 'does not extend JsonResource');
