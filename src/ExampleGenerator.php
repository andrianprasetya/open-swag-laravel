<?php

namespace OpenSwag\Laravel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExampleGenerator
{
    /**
     * Field name heuristics mapping field name patterns to example values.
     */
    private const FIELD_HEURISTICS = [
        'email' => 'user@example.com',
        'name' => 'John Doe',
        'first_name' => 'John Doe',
        'phone' => '+1234567890',
        'url' => 'https://example.com',
        'website' => 'https://example.com',
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'date' => '2024-01-15',
        'birthday' => '2024-01-15',
        'password' => '********',
        'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'bio' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'address' => '123 Main St',
        'city' => 'New York',
        'country' => 'United States',
        'zip' => '10001',
        'postal_code' => '10001',
        'age' => 25,
        'price' => 99.99,
        'amount' => 99.99,
        'balance' => 99.99,
        'status' => 'active',
        'title' => 'Sample Title',
    ];

    /**
     * Type+format to example value mapping.
     */
    private const TYPE_FORMAT_MAP = [
        'string' => 'string',
        'string/date-time' => '2024-01-15T10:30:00Z',
        'string/date' => '2024-01-15',
        'string/email' => 'user@example.com',
        'string/uri' => 'https://example.com',
        'string/uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'integer' => 1,
        'number' => 1.5,
        'boolean' => true,
        'array' => [],
    ];

    /**
     * Custom templates registered at runtime.
     *
     * @var array<string, mixed>
     */
    private array $templates = [];

    /**
     * Whether to use Eloquent model factories for example generation.
     */
    private bool $useFactories;

    /**
     * Create a new ExampleGenerator instance.
     *
     * @param array $config Configuration from the 'examples' section of openswag config
     */
    public function __construct(array $config = [])
    {
        $this->useFactories = $config['use_factories'] ?? true;
        $this->templates = $config['templates'] ?? [];
    }

    /**
     * Generate examples from an Eloquent model's factory definition.
     *
     * If the model has a factory, invokes the factory's definition() method
     * and returns the resulting array of example values.
     *
     * @param string $modelClass Fully qualified model class name
     * @return array Example values keyed by field name
     */
    public function fromFactory(string $modelClass): array
    {
        if (! $this->useFactories) {
            return [];
        }

        if (! class_exists($modelClass)) {
            return [];
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        // Check if the model uses HasFactory trait
        if (! in_array(HasFactory::class, class_uses_recursive($modelClass))) {
            return [];
        }

        try {
            $factory = $modelClass::factory();
            $definition = $factory->definition();

            return is_array($definition) ? $definition : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Generate an example value based on field name heuristics.
     *
     * Custom templates take priority over built-in heuristics.
     *
     * @param string $name The field name
     * @param string $type The OpenAPI type (used for fallback)
     * @return mixed The example value
     */
    public function fromFieldName(string $name, string $type = 'string'): mixed
    {
        // Custom templates override heuristics
        if (array_key_exists($name, $this->templates)) {
            return $this->templates[$name];
        }

        $lowerName = strtolower($name);

        // Check exact match first
        if (isset(self::FIELD_HEURISTICS[$lowerName])) {
            return self::FIELD_HEURISTICS[$lowerName];
        }

        // Check if field name contains a known pattern
        foreach (self::FIELD_HEURISTICS as $pattern => $value) {
            if (str_contains($lowerName, $pattern)) {
                return $value;
            }
        }

        // Fall back to type-based example
        return $this->fromTypeFormat($type);
    }

    /**
     * Generate an example value based on OpenAPI type and format.
     *
     * @param string $type The OpenAPI type (string, integer, number, boolean, array)
     * @param string $format The OpenAPI format (date-time, date, email, uri, uuid)
     * @return mixed The example value
     */
    public function fromTypeFormat(string $type, string $format = ''): mixed
    {
        // Try type/format combination first
        if ($format !== '') {
            $key = "{$type}/{$format}";
            if (isset(self::TYPE_FORMAT_MAP[$key])) {
                return self::TYPE_FORMAT_MAP[$key];
            }
        }

        // Fall back to type-only
        if (isset(self::TYPE_FORMAT_MAP[$type])) {
            return self::TYPE_FORMAT_MAP[$type];
        }

        // Default fallback
        return 'string';
    }

    /**
     * Register a custom example template that overrides heuristics.
     *
     * @param string $name The field name to register a template for
     * @param mixed $value The example value to use
     */
    public function registerTemplate(string $name, mixed $value): void
    {
        $this->templates[$name] = $value;
    }
}
