<?php

namespace OpenSwag\Laravel\Models;

class ResponseDefinition
{
    public function __construct(
        public string $description = '',
        public ?array $schema = null,
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'schema' => $this->schema,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? '',
            schema: $data['schema'] ?? null,
        );
    }
}
