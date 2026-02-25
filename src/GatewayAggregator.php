<?php

namespace OpenSwag\Laravel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenSwag\Laravel\Models\ServiceConfig;
use Psr\SimpleCache\CacheInterface;

class GatewayAggregator
{
    /** @var ServiceConfig[] */
    private array $services;

    private ?CacheInterface $cache;

    private int $cacheTtl;

    private int $healthCheckTimeout;

    /**
     * @param array $servicesConfig Array of ServiceConfig objects or raw arrays
     */
    public function __construct(
        array $servicesConfig,
        ?CacheInterface $cache = null,
        int $cacheTtl = 300,
        int $healthCheckTimeout = 5,
    ) {
        $this->services = array_map(
            fn ($svc) => $svc instanceof ServiceConfig ? $svc : ServiceConfig::fromArray($svc),
            $servicesConfig,
        );
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->healthCheckTimeout = $healthCheckTimeout;
    }

    /**
     * Fetch OpenAPI specs from all registered services.
     * Performs health check before fetch, uses cache fallback on failure.
     *
     * @return array<string, array> Map of service name => spec array
     */
    public function fetchAll(): array
    {
        $specs = [];

        foreach ($this->services as $service) {
            $spec = $this->fetchServiceSpec($service);

            if ($spec !== null) {
                $specs[$service->name] = $spec;
            }
        }

        return $specs;
    }

    /**
     * Merge multiple service specs into a single OpenAPI document.
     * Applies path prefixes, tags operations by service, prefixes schema names.
     *
     * @param array<string, array> $specs Map of service name => spec array
     * @return array Merged OpenAPI spec
     */
    public function merge(array $specs): array
    {
        $merged = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'API Gateway',
                'version' => '1.0.0',
            ],
            'paths' => [],
            'tags' => [],
            'components' => [
                'schemas' => [],
            ],
        ];

        $tagNames = [];

        foreach ($specs as $serviceName => $spec) {
            $serviceConfig = $this->findServiceConfig($serviceName);
            $pathPrefix = $serviceConfig ? rtrim($serviceConfig->pathPrefix, '/') : '';

            // Add service tag
            if (!in_array($serviceName, $tagNames, true)) {
                $merged['tags'][] = [
                    'name' => $serviceName,
                    'description' => "Operations from {$serviceName} service",
                ];
                $tagNames[] = $serviceName;
            }

            // Merge paths with prefix
            $paths = $spec['paths'] ?? [];
            foreach ($paths as $path => $methods) {
                $prefixedPath = $pathPrefix . '/' . ltrim($path, '/');
                // Normalize double slashes but preserve leading slash
                $prefixedPath = '/' . ltrim(preg_replace('#/+#', '/', $prefixedPath), '/');

                foreach ($methods as $method => $operation) {
                    // Skip non-HTTP method keys
                    if (!in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'], true)) {
                        continue;
                    }

                    // Tag operation with service name
                    $existingTags = $operation['tags'] ?? [];
                    if (!in_array($serviceName, $existingTags, true)) {
                        $operation['tags'] = array_merge([$serviceName], $existingTags);
                    }

                    // Update $ref references in the operation to use prefixed schema names
                    $operation = $this->prefixSchemaRefs($operation, $serviceName);

                    if (!isset($merged['paths'][$prefixedPath])) {
                        $merged['paths'][$prefixedPath] = [];
                    }
                    $merged['paths'][$prefixedPath][$method] = $operation;
                }
            }

            // Merge schemas with service name prefix
            $schemas = $spec['components']['schemas'] ?? [];
            foreach ($schemas as $schemaName => $schema) {
                $prefixedName = $serviceName . '_' . $schemaName;
                // Also prefix any $ref within the schema itself
                $schema = $this->prefixSchemaRefs($schema, $serviceName);
                $merged['components']['schemas'][$prefixedName] = $schema;
            }
        }

        // Clean up empty components
        if (empty($merged['components']['schemas'])) {
            unset($merged['components']);
        }

        if (empty($merged['tags'])) {
            unset($merged['tags']);
        }

        return $merged;
    }

    /**
     * Fetch all specs and merge them into a single document.
     */
    public function aggregatedSpec(): array
    {
        $specs = $this->fetchAll();

        return $this->merge($specs);
    }

    /**
     * Fetch, merge, and serialize to JSON.
     */
    public function aggregatedSpecJson(bool $pretty = true): string
    {
        $spec = $this->aggregatedSpec();
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($spec, $flags);
    }

    /**
     * Reconstruct a GatewayAggregator from serialized config data.
     */
    public static function fromArray(array $data): self
    {
        $services = array_map(
            fn ($svc) => ServiceConfig::fromArray($svc),
            $data['services'] ?? [],
        );

        return new self(
            servicesConfig: $services,
            cache: null,
            cacheTtl: $data['cache_ttl'] ?? 300,
            healthCheckTimeout: $data['health_check_timeout'] ?? 5,
        );
    }

    /**
     * Fetch a single service's spec with health check and cache support.
     */
    private function fetchServiceSpec(ServiceConfig $service): ?array
    {
        $cacheKey = 'openswag_gateway_' . $service->name;

        // Health check
        if (!$this->isServiceHealthy($service)) {
            Log::warning("OpenSwag: Service '{$service->name}' health check failed, attempting cache fallback.");

            return $this->getCachedSpec($cacheKey);
        }

        // Fetch spec
        try {
            $response = Http::timeout($this->healthCheckTimeout)->get($service->url);

            if (!$response->successful()) {
                Log::error("OpenSwag: Failed to fetch spec from '{$service->name}' (HTTP {$response->status()}).");

                return $this->getCachedSpec($cacheKey);
            }

            $spec = $response->json();

            if (!is_array($spec)) {
                Log::error("OpenSwag: Invalid JSON response from '{$service->name}'.");

                return $this->getCachedSpec($cacheKey);
            }

            // Cache the successful response
            $this->cacheSpec($cacheKey, $spec);

            return $spec;
        } catch (\Throwable $e) {
            Log::error("OpenSwag: Error fetching spec from '{$service->name}': {$e->getMessage()}");

            return $this->getCachedSpec($cacheKey);
        }
    }

    /**
     * Check if a service is healthy.
     */
    private function isServiceHealthy(ServiceConfig $service): bool
    {
        $healthUrl = $service->healthUrl ?? $service->url;

        try {
            $response = Http::timeout($this->healthCheckTimeout)->get($healthUrl);

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get a cached spec, returning null if not available.
     */
    private function getCachedSpec(string $cacheKey): ?array
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $cached = $this->cache->get($cacheKey);

            return is_array($cached) ? $cached : null;
        } catch (\Throwable $e) {
            Log::warning("OpenSwag: Cache read failure for '{$cacheKey}': {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Cache a spec with the configured TTL.
     */
    private function cacheSpec(string $cacheKey, array $spec): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $this->cache->set($cacheKey, $spec, $this->cacheTtl);
        } catch (\Throwable $e) {
            Log::warning("OpenSwag: Cache write failure for '{$cacheKey}': {$e->getMessage()}");
        }
    }

    /**
     * Find a ServiceConfig by name.
     */
    private function findServiceConfig(string $name): ?ServiceConfig
    {
        foreach ($this->services as $service) {
            if ($service->name === $name) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Recursively prefix $ref schema references with the service name.
     */
    private function prefixSchemaRefs(array $data, string $serviceName): array
    {
        foreach ($data as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/components/schemas/')) {
                $schemaName = substr($value, strlen('#/components/schemas/'));
                $data[$key] = '#/components/schemas/' . $serviceName . '_' . $schemaName;
            } elseif (is_array($value)) {
                $data[$key] = $this->prefixSchemaRefs($value, $serviceName);
            }
        }

        return $data;
    }
}
