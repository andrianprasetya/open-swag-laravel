<?php

namespace OpenSwag\Laravel\Models;

class Endpoint
{
    /**
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path URI path
     * @param string $summary Short summary
     * @param string $description Detailed description
     * @param string[] $tags
     * @param Parameter[] $parameters
     * @param RequestBody|null $requestBody
     * @param array<int, ResponseDefinition> $responses Keyed by HTTP status code
     * @param string[] $security
     * @param bool $deprecated
     */
    public function __construct(
        public string $method = 'GET',
        public string $path = '/',
        public string $summary = '',
        public string $description = '',
        public array $tags = [],
        public array $parameters = [],
        public ?RequestBody $requestBody = null,
        public array $responses = [],
        public array $security = [],
        public bool $deprecated = false,
    ) {}

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'summary' => $this->summary,
            'description' => $this->description,
            'tags' => $this->tags,
            'parameters' => array_map(
                fn (Parameter $p) => $p->toArray(),
                $this->parameters,
            ),
            'requestBody' => $this->requestBody?->toArray(),
            'responses' => array_map(
                fn (ResponseDefinition $r) => $r->toArray(),
                $this->responses,
            ),
            'security' => $this->security,
            'deprecated' => $this->deprecated,
        ];
    }

    public static function fromArray(array $data): self
    {
        $parameters = array_map(
            fn (array $p) => Parameter::fromArray($p),
            $data['parameters'] ?? [],
        );

        $requestBody = isset($data['requestBody'])
            ? RequestBody::fromArray($data['requestBody'])
            : null;

        $responses = [];
        foreach ($data['responses'] ?? [] as $statusCode => $response) {
            $responses[$statusCode] = ResponseDefinition::fromArray($response);
        }

        return new self(
            method: $data['method'] ?? 'GET',
            path: $data['path'] ?? '/',
            summary: $data['summary'] ?? '',
            description: $data['description'] ?? '',
            tags: $data['tags'] ?? [],
            parameters: $parameters,
            requestBody: $requestBody,
            responses: $responses,
            security: $data['security'] ?? [],
            deprecated: $data['deprecated'] ?? false,
        );
    }
}
