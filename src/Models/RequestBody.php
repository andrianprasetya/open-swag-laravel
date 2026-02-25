<?php

namespace OpenSwag\Laravel\Models;

class RequestBody
{
    public function __construct(
        public string $description = '',
        public bool $required = false,
        public array $schema = [],
        public string $contentType = 'application/json',
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'required' => $this->required,
            'schema' => $this->schema,
            'contentType' => $this->contentType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? '',
            required: $data['required'] ?? false,
            schema: $data['schema'] ?? [],
            contentType: $data['contentType'] ?? 'application/json',
        );
    }
}
