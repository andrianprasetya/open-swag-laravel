<?php

declare(strict_types=1);

namespace OpenSwag\Laravel;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use OpenSwag\Laravel\Models\Endpoint;
use OpenSwag\Laravel\Models\Parameter;
use ReflectionMethod;

/**
 * Introspects the Laravel Router to extract route definitions and produce Endpoint objects.
 */
class RouteDiscoverer
{
    public function __construct(
        private Router $router,
        private AttributeReader $reader,
    ) {
    }

    /**
     * Discover all registered routes and return an array of Endpoint objects.
     *
     * @return Endpoint[]
     */
    public function discover(): array
    {
        $endpoints = [];

        foreach ($this->router->getRoutes() as $route) {
            /** @var Route $route */
            $controllerAction = $this->getControllerAction($route);

            if ($controllerAction === null) {
                continue;
            }

            [$controllerClass, $methodName] = $controllerAction;

            if (!class_exists($controllerClass) || !method_exists($controllerClass, $methodName)) {
                continue;
            }

            $reflectionMethod = new ReflectionMethod($controllerClass, $methodName);
            $metadata = $this->reader->readMethod($reflectionMethod);

            $methods = $this->getHttpMethods($route);
            $uri = '/' . ltrim($route->uri(), '/');

            $pathParameters = $this->extractPathParameters($uri);

            foreach ($methods as $method) {
                // Merge path parameters with attribute-declared parameters, avoiding duplicates
                $mergedParameters = $this->mergeParameters($pathParameters, $metadata->parameters);

                $endpoints[] = new Endpoint(
                    method: strtoupper($method),
                    path: $uri,
                    summary: $metadata->summary,
                    description: $metadata->description,
                    tags: $metadata->tags,
                    parameters: $mergedParameters,
                    requestBody: $metadata->requestBody,
                    responses: $metadata->responses,
                    security: $metadata->security,
                    deprecated: $metadata->deprecated,
                );
            }
        }

        return $endpoints;
    }

    /**
     * Extract controller class and method name from a route action.
     * Returns null for closure-based routes.
     *
     * @return array{0: string, 1: string}|null
     */
    private function getControllerAction(Route $route): ?array
    {
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $controller = $action['controller'];

            // Format: "App\Http\Controllers\UserController@index"
            if (str_contains($controller, '@')) {
                return explode('@', $controller, 2);
            }
        }

        // If 'uses' is a string with @, it's a controller action
        if (isset($action['uses']) && is_string($action['uses']) && str_contains($action['uses'], '@')) {
            return explode('@', $action['uses'], 2);
        }

        return null;
    }

    /**
     * Get HTTP methods for a route, excluding HEAD.
     *
     * @return string[]
     */
    private function getHttpMethods(Route $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            fn (string $method) => strtoupper($method) !== 'HEAD',
        ));
    }

    /**
     * Extract path parameters from URI placeholders like {id} or {user}.
     *
     * @return Parameter[]
     */
    private function extractPathParameters(string $uri): array
    {
        $parameters = [];

        if (preg_match_all('/\{(\w+)\}/', $uri, $matches)) {
            foreach ($matches[1] as $paramName) {
                $parameters[] = new Parameter(
                    name: $paramName,
                    in: 'path',
                    required: true,
                    schema: ['type' => 'string'],
                );
            }
        }

        return $parameters;
    }

    /**
     * Merge auto-detected path parameters with attribute-declared parameters.
     * Attribute-declared parameters take precedence over auto-detected ones.
     *
     * @param Parameter[] $pathParameters
     * @param Parameter[] $attributeParameters
     * @return Parameter[]
     */
    private function mergeParameters(array $pathParameters, array $attributeParameters): array
    {
        $merged = [];
        $attributeNames = [];

        // Index attribute parameters by name+in for quick lookup
        foreach ($attributeParameters as $param) {
            $key = $param->name . ':' . $param->in;
            $attributeNames[$key] = true;
            $merged[] = $param;
        }

        // Add auto-detected path parameters that weren't declared via attributes
        foreach ($pathParameters as $param) {
            $key = $param->name . ':' . $param->in;
            if (!isset($attributeNames[$key])) {
                $merged[] = $param;
            }
        }

        return $merged;
    }
}
