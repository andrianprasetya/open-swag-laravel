<?php

declare(strict_types=1);

namespace OpenSwag\Laravel;

use OpenSwag\Laravel\Attributes\OpenApiOperation;
use OpenSwag\Laravel\Attributes\OpenApiParameter;
use OpenSwag\Laravel\Attributes\OpenApiRequestBody;
use OpenSwag\Laravel\Attributes\OpenApiResponse;
use OpenSwag\Laravel\Models\EndpointMetadata;
use OpenSwag\Laravel\Models\Parameter;
use OpenSwag\Laravel\Models\RequestBody;
use OpenSwag\Laravel\Models\ResponseDefinition;
use ReflectionMethod;

/**
 * Reads PHP 8.1+ attributes from controller methods and returns EndpointMetadata.
 *
 * Attribute values take precedence over docblock values (Req 14.6).
 */
class AttributeReader
{
    /**
     * Read all OpenApi attributes from a ReflectionMethod and return EndpointMetadata.
     */
    public function readMethod(ReflectionMethod $method): EndpointMetadata
    {
        $metadata = new EndpointMetadata();

        $this->readOperation($method, $metadata);
        $this->readParameters($method, $metadata);
        $this->readRequestBody($method, $metadata);
        $this->readResponses($method, $metadata);

        return $metadata;
    }

    /**
     * Check if any OpenApi attributes are present on the method.
     */
    public function hasAttributes(ReflectionMethod $method): bool
    {
        return !empty($method->getAttributes(OpenApiOperation::class))
            || !empty($method->getAttributes(OpenApiParameter::class))
            || !empty($method->getAttributes(OpenApiRequestBody::class))
            || !empty($method->getAttributes(OpenApiResponse::class));
    }

    private function readOperation(ReflectionMethod $method, EndpointMetadata $metadata): void
    {
        $attributes = $method->getAttributes(OpenApiOperation::class);

        if (empty($attributes)) {
            return;
        }

        /** @var OpenApiOperation $operation */
        $operation = $attributes[0]->newInstance();

        $metadata->summary = $operation->summary;
        $metadata->description = $operation->description;
        $metadata->tags = $operation->tags;
        $metadata->security = $operation->security;
        $metadata->deprecated = $operation->deprecated;
    }

    private function readParameters(ReflectionMethod $method, EndpointMetadata $metadata): void
    {
        $attributes = $method->getAttributes(OpenApiParameter::class);

        foreach ($attributes as $attribute) {
            /** @var OpenApiParameter $param */
            $param = $attribute->newInstance();

            $metadata->parameters[] = new Parameter(
                name: $param->name,
                in: $param->in,
                description: $param->description,
                required: $param->required,
                schema: ['type' => $param->type],
                example: $param->example,
            );
        }
    }

    private function readRequestBody(ReflectionMethod $method, EndpointMetadata $metadata): void
    {
        $attributes = $method->getAttributes(OpenApiRequestBody::class);

        if (empty($attributes)) {
            return;
        }

        /** @var OpenApiRequestBody $body */
        $body = $attributes[0]->newInstance();

        $schema = [];
        if ($body->formRequest !== null) {
            $schema = ['$ref' => '#/components/schemas/' . class_basename($body->formRequest)];
        } elseif ($body->schema !== null) {
            $schema = ['$ref' => '#/components/schemas/' . class_basename($body->schema)];
        }

        $metadata->requestBody = new RequestBody(
            description: $body->description,
            required: $body->required,
            schema: $schema,
            contentType: $body->contentType,
        );
    }

    private function readResponses(ReflectionMethod $method, EndpointMetadata $metadata): void
    {
        $attributes = $method->getAttributes(OpenApiResponse::class);

        foreach ($attributes as $attribute) {
            /** @var OpenApiResponse $response */
            $response = $attribute->newInstance();

            $schema = null;
            if ($response->resource !== null) {
                $ref = ['$ref' => '#/components/schemas/' . class_basename($response->resource)];
                $schema = $response->isArray
                    ? ['type' => 'array', 'items' => $ref]
                    : $ref;
            } elseif ($response->schema !== null) {
                $ref = ['$ref' => '#/components/schemas/' . class_basename($response->schema)];
                $schema = $response->isArray
                    ? ['type' => 'array', 'items' => $ref]
                    : $ref;
            }

            $metadata->responses[$response->status] = new ResponseDefinition(
                description: $response->description,
                schema: $schema,
            );
        }
    }
}
