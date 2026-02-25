<?php

namespace OpenSwag\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

class SchemaConverter
{
    /**
     * Optional example generator for adding example values to schemas.
     */
    private ?ExampleGenerator $exampleGenerator = null;

    /**
     * Set the example generator to add example values to generated schemas.
     */
    public function setExampleGenerator(ExampleGenerator $generator): void
    {
        $this->exampleGenerator = $generator;
    }

    /**
     * PHP type to OpenAPI type mapping.
     */
    private const TYPE_MAP = [
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
    ];

    /**
     * Convert a PHP class to an OpenAPI schema array using reflection.
     *
     * @param string $className Fully qualified class name
     * @return array OpenAPI schema array
     */
    public function fromClass(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $type = $property->getType();

            if ($type !== null) {
                $schema = $this->fromType($type);

                if ($type->allowsNull()) {
                    $schema['nullable'] = true;
                }
            } else {
                $schema = $this->fromDocblock($property);
            }

            $properties[$name] = $schema;

            if (! $this->isOptionalProperty($property)) {
                $required[] = $name;
            }
        }

        if ($this->exampleGenerator !== null) {
            foreach ($properties as $fieldName => &$propSchema) {
                $type = $propSchema['type'] ?? 'string';
                $propSchema['example'] = $this->exampleGenerator->fromFieldName($fieldName, $type);
            }
            unset($propSchema);
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * Validation rule to OpenAPI type mapping.
     */
    private const RULE_TYPE_MAP = [
        'integer' => 'integer',
        'int' => 'integer',
        'string' => 'string',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'numeric' => 'number',
        'array' => 'array',
    ];

    /**
     * Validation rule to OpenAPI format mapping.
     */
    private const RULE_FORMAT_MAP = [
        'email' => 'email',
        'url' => 'uri',
        'date' => 'date',
        'uuid' => 'uuid',
    ];

    /**
     * Convert a Laravel Form Request class to an OpenAPI schema array.
     *
     * @param string $formRequestClass Fully qualified Form Request class name
     * @return array OpenAPI schema array
     *
     * @throws InvalidArgumentException If the class does not extend FormRequest or has no rules() method
     */
    public function fromFormRequest(string $formRequestClass): array
    {
        if (! class_exists($formRequestClass)) {
            throw new InvalidArgumentException("Class [{$formRequestClass}] does not exist.");
        }

        if (! is_subclass_of($formRequestClass, FormRequest::class)) {
            throw new InvalidArgumentException("Class [{$formRequestClass}] does not extend FormRequest.");
        }

        $reflection = new ReflectionClass($formRequestClass);

        if (! $reflection->hasMethod('rules')) {
            throw new InvalidArgumentException("Class [{$formRequestClass}] does not have a rules() method.");
        }

        $instance = $reflection->newInstanceWithoutConstructor();
        $rules = $instance->rules();

        $properties = [];
        $required = [];

        foreach ($rules as $field => $fieldRules) {
            $parsedRules = $this->normalizeRules($fieldRules);
            $propertySchema = $this->buildPropertySchema($parsedRules);

            $properties[$field] = $propertySchema;

            if (in_array('required', $parsedRules, true)) {
                $required[] = $field;
            }
        }

        if ($this->exampleGenerator !== null) {
            foreach ($properties as $fieldName => &$propSchema) {
                $type = $propSchema['type'] ?? 'string';
                $propSchema['example'] = $this->exampleGenerator->fromFieldName($fieldName, $type);
            }
            unset($propSchema);
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * Cast type to OpenAPI type mapping.
     */
    private const CAST_TYPE_MAP = [
        'integer' => ['type' => 'integer'],
        'int' => ['type' => 'integer'],
        'float' => ['type' => 'number'],
        'double' => ['type' => 'number'],
        'real' => ['type' => 'number'],
        'decimal' => ['type' => 'number'],
        'string' => ['type' => 'string'],
        'boolean' => ['type' => 'boolean'],
        'bool' => ['type' => 'boolean'],
        'array' => ['type' => 'array'],
        'json' => ['type' => 'object'],
        'collection' => ['type' => 'array'],
        'date' => ['type' => 'string', 'format' => 'date-time'],
        'datetime' => ['type' => 'string', 'format' => 'date-time'],
        'immutable_date' => ['type' => 'string', 'format' => 'date-time'],
        'immutable_datetime' => ['type' => 'string', 'format' => 'date-time'],
        'timestamp' => ['type' => 'integer'],
    ];

    /**
     * Convert an Eloquent Model class to an OpenAPI schema array.
     *
     * Introspects $fillable, $hidden, and $casts to generate properties,
     * excluding any fields listed in $hidden.
     *
     * @param string $modelClass Fully qualified Eloquent Model class name
     * @return array OpenAPI schema array
     *
     * @throws InvalidArgumentException If the class does not extend Model
     */
    public function fromModel(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException("Class [{$modelClass}] does not exist.");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("Class [{$modelClass}] does not extend Eloquent Model.");
        }

        $instance = new $modelClass();

        $fillable = $instance->getFillable();
        $hidden = $instance->getHidden();
        $casts = $instance->getCasts();

        $properties = [];

        foreach ($fillable as $field) {
            if (in_array($field, $hidden, true)) {
                continue;
            }

            $properties[$field] = $this->resolveModelFieldSchema($field, $casts);
        }

        if ($this->exampleGenerator !== null) {
            $factoryExamples = $this->exampleGenerator->fromFactory($modelClass);

            foreach ($properties as $fieldName => &$propSchema) {
                if (isset($factoryExamples[$fieldName])) {
                    $propSchema['example'] = $factoryExamples[$fieldName];
                } else {
                    $type = $propSchema['type'] ?? 'string';
                    $propSchema['example'] = $this->exampleGenerator->fromFieldName($fieldName, $type);
                }
            }
            unset($propSchema);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Resolve the OpenAPI schema for a single model field based on its cast.
     *
     * @param string $field The field name
     * @param array $casts The model's casts array
     * @return array OpenAPI property schema
     */
    private function resolveModelFieldSchema(string $field, array $casts): array
    {
        if (! isset($casts[$field])) {
            return ['type' => 'string'];
        }

        $cast = $casts[$field];

        // Handle parameterized casts like "decimal:2"
        $baseCast = strtolower(explode(':', $cast)[0]);

        if (isset(self::CAST_TYPE_MAP[$baseCast])) {
            return self::CAST_TYPE_MAP[$baseCast];
        }

        return ['type' => 'string'];
    }

    /**
     * Convert a Laravel API Resource class to an OpenAPI response schema array.
     *
     * Instantiates the resource with a dummy model, calls toArray() with a
     * mock request, and inspects the returned array keys and value types
     * to build the schema.
     *
     * @param string $resourceClass Fully qualified API Resource class name
     * @return array OpenAPI schema array
     *
     * @throws InvalidArgumentException If the class does not extend JsonResource
     */
    public function fromResource(string $resourceClass): array
    {
        if (! class_exists($resourceClass)) {
            throw new InvalidArgumentException("Class [{$resourceClass}] does not exist.");
        }

        if (! is_subclass_of($resourceClass, JsonResource::class)) {
            throw new InvalidArgumentException("Class [{$resourceClass}] does not extend JsonResource.");
        }

        $dummy = $this->createDummyModel();
        $resource = new $resourceClass($dummy);
        $request = Request::create('/', 'GET');

        try {
            $data = $resource->toArray($request);
        } catch (\Throwable) {
            // If toArray() fails with the dummy, return a generic object schema
            return ['type' => 'object'];
        }

        $properties = [];

        foreach ($data as $key => $value) {
            $properties[$key] = $this->inferSchemaFromValue($value);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Create a dummy model instance that returns default values for any attribute access.
     *
     * @return object
     */
    private function createDummyModel(): object
    {
        return new class extends Model
        {
            protected $guarded = [];

            public function getAttribute($key): mixed
            {
                return null;
            }

            public function getRelationValue($key): mixed
            {
                return null;
            }

            public function toArray(): array
            {
                return [];
            }
        };
    }

    /**
     * Infer an OpenAPI schema from a PHP value's type.
     *
     * @param mixed $value
     * @return array OpenAPI property schema
     */
    private function inferSchemaFromValue(mixed $value): array
    {
        if (is_int($value)) {
            return ['type' => 'integer'];
        }

        if (is_float($value)) {
            return ['type' => 'number'];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if (is_string($value)) {
            return ['type' => 'string'];
        }

        if (is_array($value)) {
            if (empty($value)) {
                return ['type' => 'array'];
            }

            // Check if it's an associative array (object)
            if (array_keys($value) !== range(0, count($value) - 1)) {
                $nestedProperties = [];
                foreach ($value as $k => $v) {
                    $nestedProperties[$k] = $this->inferSchemaFromValue($v);
                }

                return [
                    'type' => 'object',
                    'properties' => $nestedProperties,
                ];
            }

            // Sequential array — infer items from first element
            return [
                'type' => 'array',
                'items' => $this->inferSchemaFromValue($value[0]),
            ];
        }

        if (is_null($value)) {
            return ['type' => 'string', 'nullable' => true];
        }

        if (is_object($value)) {
            return ['type' => 'object'];
        }

        return ['type' => 'string'];
    }

    /**
     * Normalize rules to a flat array of rule strings.
     * Handles both pipe-delimited strings and arrays.
     *
     * @param string|array $rules
     * @return string[]
     */
    private function normalizeRules(string|array $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        $normalized = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                // A single array entry could still be pipe-delimited
                foreach (explode('|', $rule) as $r) {
                    $normalized[] = $r;
                }
            }
            // Skip non-string rules (e.g., Rule objects) for now
        }

        return $normalized;
    }

    /**
     * Build an OpenAPI property schema from parsed validation rules.
     *
     * @param string[] $rules
     * @return array
     */
    private function buildPropertySchema(array $rules): array
    {
        $schema = [];

        // Determine type
        $type = $this->inferTypeFromRules($rules);
        if ($type !== null) {
            $schema['type'] = $type;
        } else {
            $schema['type'] = 'string'; // default
        }

        // Determine format
        $format = $this->inferFormatFromRules($rules);
        if ($format !== null) {
            $schema['format'] = $format;
        }

        // Nullable
        if (in_array('nullable', $rules, true)) {
            $schema['nullable'] = true;
        }

        // Enum (in:a,b,c)
        $enum = $this->extractEnum($rules);
        if ($enum !== null) {
            $schema['enum'] = $enum;
        }

        return $schema;
    }

    /**
     * Infer the OpenAPI type from validation rules.
     *
     * @param string[] $rules
     * @return string|null
     */
    private function inferTypeFromRules(array $rules): ?string
    {
        foreach ($rules as $rule) {
            $ruleName = strtolower(trim($rule));

            if (isset(self::RULE_TYPE_MAP[$ruleName])) {
                return self::RULE_TYPE_MAP[$ruleName];
            }
        }

        return null;
    }

    /**
     * Infer the OpenAPI format from validation rules.
     *
     * @param string[] $rules
     * @return string|null
     */
    private function inferFormatFromRules(array $rules): ?string
    {
        foreach ($rules as $rule) {
            $ruleName = strtolower(trim($rule));

            if (isset(self::RULE_FORMAT_MAP[$ruleName])) {
                return self::RULE_FORMAT_MAP[$ruleName];
            }
        }

        return null;
    }

    /**
     * Extract enum values from an `in:a,b,c` rule.
     *
     * @param string[] $rules
     * @return string[]|null
     */
    private function extractEnum(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (preg_match('/^in:(.+)$/i', trim($rule), $matches)) {
                return explode(',', $matches[1]);
            }
        }

        return null;
    }


    /**
     * Convert a ReflectionType to an OpenAPI type array.
     *
     * @param ReflectionType $type
     * @return array OpenAPI type array
     */
    public function fromType(ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType) {
            return $this->fromUnionType($type);
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->fromNamedType($type);
        }

        return ['type' => 'string'];
    }

    /**
     * Convert a ReflectionNamedType to an OpenAPI type array.
     */
    private function fromNamedType(ReflectionNamedType $type): array
    {
        $typeName = $type->getName();

        if (isset(self::TYPE_MAP[$typeName])) {
            return ['type' => self::TYPE_MAP[$typeName]];
        }

        if ($typeName === 'mixed') {
            return [];
        }

        // Class type — recursively convert
        if (class_exists($typeName)) {
            return $this->fromClass($typeName);
        }

        return ['type' => 'string'];
    }

    /**
     * Convert a ReflectionUnionType to an OpenAPI oneOf schema.
     */
    private function fromUnionType(ReflectionUnionType $type): array
    {
        $schemas = [];

        foreach ($type->getTypes() as $innerType) {
            if ($innerType instanceof ReflectionNamedType && $innerType->getName() === 'null') {
                continue;
            }

            if ($innerType instanceof ReflectionNamedType) {
                $schemas[] = $this->fromNamedType($innerType);
            }
        }

        if (count($schemas) === 1) {
            return $schemas[0];
        }

        if (count($schemas) > 1) {
            return ['oneOf' => $schemas];
        }

        return [];
    }

    /**
     * Parse @var docblock annotation to determine the OpenAPI type.
     */
    private function fromDocblock(ReflectionProperty $property): array
    {
        $docComment = $property->getDocComment();

        if ($docComment === false) {
            return ['type' => 'string'];
        }

        if (preg_match('/@var\s+(\S+)/', $docComment, $matches)) {
            $docType = $matches[1];

            // Strip nullable prefix
            $nullable = false;
            if (str_starts_with($docType, '?')) {
                $nullable = true;
                $docType = substr($docType, 1);
            }

            // Handle union types with null (e.g., "string|null")
            $parts = explode('|', $docType);
            $nonNullParts = array_filter($parts, fn (string $p) => strtolower($p) !== 'null');

            if (count($nonNullParts) < count($parts)) {
                $nullable = true;
            }

            $schema = $this->resolveDocblockType(array_values($nonNullParts));

            if ($nullable) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

        return ['type' => 'string'];
    }

    /**
     * Resolve docblock type strings to OpenAPI schema.
     *
     * @param string[] $types
     */
    private function resolveDocblockType(array $types): array
    {
        if (count($types) === 0) {
            return ['type' => 'string'];
        }

        if (count($types) === 1) {
            return $this->mapDocblockSingleType($types[0]);
        }

        $schemas = array_map(fn (string $t) => $this->mapDocblockSingleType($t), $types);

        return ['oneOf' => $schemas];
    }

    /**
     * Map a single docblock type string to an OpenAPI type array.
     */
    private function mapDocblockSingleType(string $type): array
    {
        // Handle array notation like "string[]" or "int[]"
        if (str_ends_with($type, '[]')) {
            $itemType = substr($type, 0, -2);

            return [
                'type' => 'array',
                'items' => $this->mapDocblockSingleType($itemType),
            ];
        }

        $lower = strtolower($type);

        if (isset(self::TYPE_MAP[$lower])) {
            return ['type' => self::TYPE_MAP[$lower]];
        }

        if ($lower === 'mixed') {
            return [];
        }

        // Fully qualified class name
        if (class_exists($type)) {
            return $this->fromClass($type);
        }

        return ['type' => 'string'];
    }

    /**
     * Determine if a property is optional (nullable or has a default value).
     */
    private function isOptionalProperty(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        // Nullable typed properties are optional
        if ($type !== null && $type->allowsNull()) {
            return true;
        }

        // Properties with default values are optional
        if ($property->hasDefaultValue()) {
            return true;
        }

        // Promoted constructor params with defaults are optional
        if ($property->isPromoted()) {
            $constructor = $property->getDeclaringClass()->getConstructor();
            if ($constructor !== null) {
                foreach ($constructor->getParameters() as $param) {
                    if ($param->getName() === $property->getName() && $param->isDefaultValueAvailable()) {
                        return true;
                    }
                }
            }
        }

        // Untyped properties without docblock nullable are required
        if ($type === null) {
            $docComment = $property->getDocComment();
            if ($docComment !== false && preg_match('/@var\s+(\S+)/', $docComment, $matches)) {
                $docType = $matches[1];
                if (str_starts_with($docType, '?') || stripos($docType, '|null') !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
