<?php

namespace OpenSwag\Laravel\Models;

class ServiceConfig
{
    public function __construct(
        public string $name = '',
        public string $url = '',
        public string $pathPrefix = '',
        public ?string $healthUrl = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'pathPrefix' => $this->pathPrefix,
            'healthUrl' => $this->healthUrl,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            url: $data['url'] ?? '',
            pathPrefix: $data['pathPrefix'] ?? '',
            healthUrl: $data['healthUrl'] ?? null,
        );
    }
}
