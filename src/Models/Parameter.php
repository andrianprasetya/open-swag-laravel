<?php

namespace OpenSwag\Laravel\Models;

class Parameter
{
    public function __construct(
        public string $name = '',
        public string $in = 'query',
        public string $description = '',
        public bool $required = false,
        public array $schema = [],
        public mixed $example = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'in' => $this->in,
            'description' => $this->description,
            'required' => $this->required,
            'schema' => $this->schema,
            'example' => $this->example,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            in: $data['in'] ?? 'query',
            description: $data['description'] ?? '',
            required: $data['required'] ?? false,
            schema: $data['schema'] ?? [],
            example: $data['example'] ?? null,
        );
    }
}
