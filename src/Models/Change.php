<?php

namespace OpenSwag\Laravel\Models;

class Change
{
    /**
     * @param string $type Change type: added, removed, modified
     * @param string $path API endpoint path
     * @param string $method HTTP method
     * @param string $description Description of the change
     * @param bool $isBreaking Whether this is a breaking change
     */
    public function __construct(
        public string $type = '',
        public string $path = '',
        public string $method = '',
        public string $description = '',
        public bool $isBreaking = false,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'path' => $this->path,
            'method' => $this->method,
            'description' => $this->description,
            'isBreaking' => $this->isBreaking,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? '',
            path: $data['path'] ?? '',
            method: $data['method'] ?? '',
            description: $data['description'] ?? '',
            isBreaking: $data['isBreaking'] ?? false,
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
