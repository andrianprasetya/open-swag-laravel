<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Models;

/**
 * Data class holding metadata extracted from PHP attributes on a controller method.
 */
class EndpointMetadata
{
    /**
     * @param string $summary Short summary of the operation
     * @param string $description Detailed description of the operation
     * @param string[] $tags List of tags for grouping
     * @param string[] $security List of security scheme names
     * @param bool $deprecated Whether the operation is deprecated
     * @param Parameter[] $parameters List of parameters
     * @param RequestBody|null $requestBody Request body definition
     * @param array<int, ResponseDefinition> $responses Keyed by HTTP status code
     */
    public function __construct(
        public string $summary = '',
        public string $description = '',
        public array $tags = [],
        public array $security = [],
        public bool $deprecated = false,
        public array $parameters = [],
        public ?RequestBody $requestBody = null,
        public array $responses = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'description' => $this->description,
            'tags' => $this->tags,
            'security' => $this->security,
            'deprecated' => $this->deprecated,
            'parameters' => array_map(
                fn(Parameter $p) => $p->toArray(),
                $this->parameters,
            ),
            'requestBody' => $this->requestBody?->toArray(),
            'responses' => array_map(
                fn(ResponseDefinition $r) => $r->toArray(),
                $this->responses,
            ),
        ];
    }

    public static function fromArray(array $data): self
    {
        $parameters = array_map(
            fn(array $p) => Parameter::fromArray($p),
            $data['parameters'] ?? [],
        );

        $requestBody = isset($data['requestBody'])
            ? RequestBody::fromArray($data['requestBody'])
            : null;

        $responses = [];
        foreach ($data['responses'] ?? [] as $statusCode => $response) {
            $responses[(int) $statusCode] = ResponseDefinition::fromArray($response);
        }

        return new self(
            summary: $data['summary'] ?? '',
            description: $data['description'] ?? '',
            tags: $data['tags'] ?? [],
            security: $data['security'] ?? [],
            deprecated: $data['deprecated'] ?? false,
            parameters: $parameters,
            requestBody: $requestBody,
            responses: $responses,
        );
    }
}
