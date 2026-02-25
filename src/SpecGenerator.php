<?php

declare(strict_types=1);

namespace OpenSwag\Laravel;

use OpenSwag\Laravel\Models\Endpoint;
use OpenSwag\Laravel\Models\Parameter;
use OpenSwag\Laravel\RouteDiscoverer;

/**
 * Core class that builds the OpenAPI 3.0 spec from registered endpoints and config.
 */
class SpecGenerator
{
    /** @var Endpoint[] */
    private array $endpoints = [];

    private ?RouteDiscoverer $routeDiscoverer = null;

    private ?GatewayAggregator $gatewayAggregator = null;

    /**
     * Predefined security scheme definitions.
     * Only included in the spec when referenced by at least one endpoint.
     */
    private const SECURITY_SCHEMES = [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
        'basicAuth' => [
            'type' => 'http',
            'scheme' => 'basic',
        ],
        'apiKeyHeader' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
        ],
        'apiKeyQuery' => [
            'type' => 'apiKey',
            'in' => 'query',
            'name' => 'api_key',
        ],
        'cookieAuth' => [
            'type' => 'apiKey',
            'in' => 'cookie',
            'name' => 'session',
        ],
        'oauth2' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorizationUrl' => 'https://example.com/oauth/authorize',
                    'tokenUrl' => 'https://example.com/oauth/token',
                    'scopes' => [],
                ],
            ],
        ],
    ];

    public function __construct(
        private array $config,
    ) {
    }

    public function addEndpoint(Endpoint $endpoint): void
    {
        $this->endpoints[] = $endpoint;
    }

    public function addEndpoints(Endpoint ...$endpoints): void
    {
        foreach ($endpoints as $endpoint) {
            $this->endpoints[] = $endpoint;
        }
    }

    public function setRouteDiscoverer(RouteDiscoverer $discoverer): void
    {
        $this->routeDiscoverer = $discoverer;
    }

    public function setGatewayAggregator(GatewayAggregator $aggregator): void
    {
        $this->gatewayAggregator = $aggregator;
    }

    /**
     * Auto-discover routes via the RouteDiscoverer and add them as endpoints.
     */
    public function discoverRoutes(): void
    {
        if ($this->routeDiscoverer === null) {
            return;
        }

        $this->addEndpoints(...$this->routeDiscoverer->discover());
    }

    /**
     * Build the full OpenAPI 3.0 specification array.
     */
    public function buildSpec(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => $this->buildInfo(),
        ];

        $servers = $this->buildServers();
        if (!empty($servers)) {
            $spec['servers'] = $servers;
        }

        $tags = $this->buildTags();
        if (!empty($tags)) {
            $spec['tags'] = $tags;
        }

        $spec['paths'] = $this->buildPaths();

        $components = $this->buildComponents();
        if (!empty($components)) {
            $spec['components'] = $components;
        }

        // Merge external specs from gateway aggregator if set
        if ($this->gatewayAggregator !== null) {
            $spec = $this->mergeGatewaySpec($spec);
        }

        return $spec;
    }

    /**
     * Serialize the spec to JSON.
     */
    public function specJson(bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->buildSpec(), $flags);
    }

    private function buildInfo(): array
    {
        $infoConfig = $this->config['info'] ?? [];

        $info = [
            'title' => $infoConfig['title'] ?? 'API Documentation',
            'version' => $infoConfig['version'] ?? '1.0.0',
        ];

        if (!empty($infoConfig['description'])) {
            $info['description'] = $infoConfig['description'];
        }

        if (!empty($infoConfig['contact']) && $this->hasNonEmptyValues($infoConfig['contact'])) {
            $info['contact'] = array_filter($infoConfig['contact'], fn ($v) => $v !== '');
        }

        if (!empty($infoConfig['license']) && $this->hasNonEmptyValues($infoConfig['license'])) {
            $info['license'] = array_filter($infoConfig['license'], fn ($v) => $v !== '');
        }

        return $info;
    }

    private function buildServers(): array
    {
        return $this->config['servers'] ?? [];
    }

    private function buildTags(): array
    {
        return $this->config['tags'] ?? [];
    }

    private function buildPaths(): array
    {
        $paths = [];

        foreach ($this->endpoints as $endpoint) {
            $path = $endpoint->path;
            $method = strtolower($endpoint->method);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path][$method] = $this->buildOperation($endpoint);
        }

        return $paths;
    }

    private function buildOperation(Endpoint $endpoint): array
    {
        $operation = [];

        if ($endpoint->summary !== '') {
            $operation['summary'] = $endpoint->summary;
        }

        if ($endpoint->description !== '') {
            $operation['description'] = $endpoint->description;
        }

        if (!empty($endpoint->tags)) {
            $operation['tags'] = $endpoint->tags;
        }

        // Merge auto-detected path parameters with declared parameters
        $parameters = $this->mergePathParameters($endpoint);
        if (!empty($parameters)) {
            $operation['parameters'] = array_map(
                fn (Parameter $p) => $this->buildParameter($p),
                $parameters,
            );
        }

        if ($endpoint->requestBody !== null) {
            $operation['requestBody'] = $this->buildRequestBody($endpoint);
        }

        if (!empty($endpoint->responses)) {
            $operation['responses'] = $this->buildResponses($endpoint);
        }

        if (!empty($endpoint->security)) {
            $operation['security'] = array_map(
                fn (string $scheme) => [$scheme => []],
                $endpoint->security,
            );
        }

        if ($endpoint->deprecated) {
            $operation['deprecated'] = true;
        }

        return $operation;
    }

    /**
     * Auto-detect path parameters from {param} placeholders and merge with declared parameters.
     *
     * @return Parameter[]
     */
    private function mergePathParameters(Endpoint $endpoint): array
    {
        $declaredNames = [];
        foreach ($endpoint->parameters as $param) {
            if ($param->in === 'path') {
                $declaredNames[$param->name] = true;
            }
        }

        $autoDetected = [];
        if (preg_match_all('/\{(\w+)\}/', $endpoint->path, $matches)) {
            foreach ($matches[1] as $paramName) {
                if (!isset($declaredNames[$paramName])) {
                    $autoDetected[] = new Parameter(
                        name: $paramName,
                        in: 'path',
                        required: true,
                        schema: ['type' => 'string'],
                    );
                }
            }
        }

        return array_merge($endpoint->parameters, $autoDetected);
    }

    private function buildParameter(Parameter $param): array
    {
        $result = [
            'name' => $param->name,
            'in' => $param->in,
        ];

        if ($param->description !== '') {
            $result['description'] = $param->description;
        }

        if ($param->required || $param->in === 'path') {
            $result['required'] = true;
        }

        if (!empty($param->schema)) {
            $result['schema'] = $param->schema;
        }

        if ($param->example !== null) {
            $result['example'] = $param->example;
        }

        return $result;
    }

    private function buildRequestBody(Endpoint $endpoint): array
    {
        $body = $endpoint->requestBody;
        $result = [];

        if ($body->description !== '') {
            $result['description'] = $body->description;
        }

        if ($body->required) {
            $result['required'] = true;
        }

        $result['content'] = [
            $body->contentType => [
                'schema' => $body->schema,
            ],
        ];

        return $result;
    }

    private function buildResponses(Endpoint $endpoint): array
    {
        $responses = [];

        foreach ($endpoint->responses as $statusCode => $response) {
            $entry = [
                'description' => $response->description !== '' ? $response->description : 'Response',
            ];

            if ($response->schema !== null) {
                $entry['content'] = [
                    'application/json' => [
                        'schema' => $response->schema,
                    ],
                ];
            }

            $responses[(string) $statusCode] = $entry;
        }

        return $responses;
    }

    private function buildComponents(): array
    {
        $components = [];

        $securitySchemes = $this->buildSecuritySchemes();
        if (!empty($securitySchemes)) {
            $components['securitySchemes'] = $securitySchemes;
        }

        return $components;
    }

    /**
     * Build security schemes — only include schemes that are actually referenced by endpoints.
     */
    private function buildSecuritySchemes(): array
    {
        $referencedSchemes = [];

        foreach ($this->endpoints as $endpoint) {
            foreach ($endpoint->security as $scheme) {
                $referencedSchemes[$scheme] = true;
            }
        }

        $schemes = [];
        foreach ($referencedSchemes as $schemeName => $_) {
            if (isset(self::SECURITY_SCHEMES[$schemeName])) {
                $schemes[$schemeName] = self::SECURITY_SCHEMES[$schemeName];
            }
        }

        return $schemes;
    }

    /**
     * Merge external specs from the gateway aggregator into the local spec.
     */
    private function mergeGatewaySpec(array $spec): array
    {
        $externalSpec = $this->gatewayAggregator->aggregatedSpec();

        // Merge external paths into local paths
        $externalPaths = $externalSpec['paths'] ?? [];
        foreach ($externalPaths as $path => $methods) {
            if (!isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }
            foreach ($methods as $method => $operation) {
                $spec['paths'][$path][$method] = $operation;
            }
        }

        // Merge external tags into local tags
        $externalTags = $externalSpec['tags'] ?? [];
        if (!empty($externalTags)) {
            $existingTagNames = [];
            foreach ($spec['tags'] ?? [] as $tag) {
                $existingTagNames[$tag['name']] = true;
            }

            foreach ($externalTags as $tag) {
                if (!isset($existingTagNames[$tag['name']])) {
                    $spec['tags'] = $spec['tags'] ?? [];
                    $spec['tags'][] = $tag;
                }
            }
        }

        // Merge external components.schemas into local components.schemas
        $externalSchemas = $externalSpec['components']['schemas'] ?? [];
        if (!empty($externalSchemas)) {
            if (!isset($spec['components'])) {
                $spec['components'] = [];
            }
            if (!isset($spec['components']['schemas'])) {
                $spec['components']['schemas'] = [];
            }
            foreach ($externalSchemas as $name => $schema) {
                $spec['components']['schemas'][$name] = $schema;
            }
        }

        return $spec;
    }

    private function hasNonEmptyValues(array $arr): bool
    {
        foreach ($arr as $value) {
            if ($value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
    }
}
