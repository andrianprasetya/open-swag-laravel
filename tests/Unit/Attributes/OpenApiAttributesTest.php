<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Tests\Unit\Attributes;

use OpenSwag\Laravel\Attributes\OpenApiOperation;
use OpenSwag\Laravel\Attributes\OpenApiParameter;
use OpenSwag\Laravel\Attributes\OpenApiRequestBody;
use OpenSwag\Laravel\Attributes\OpenApiResponse;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OpenApiAttributesTest extends TestCase
{
    // --- OpenApiOperation ---

    public function test_open_api_operation_defaults(): void
    {
        $attr = new OpenApiOperation();

        $this->assertSame('', $attr->summary);
        $this->assertSame('', $attr->description);
        $this->assertSame([], $attr->tags);
        $this->assertSame([], $attr->security);
        $this->assertFalse($attr->deprecated);
    }

    public function test_open_api_operation_with_values(): void
    {
        $attr = new OpenApiOperation(
            summary: 'List users',
            description: 'Returns a paginated list of users',
            tags: ['Users', 'Admin'],
            security: ['bearerAuth'],
            deprecated: true,
        );

        $this->assertSame('List users', $attr->summary);
        $this->assertSame('Returns a paginated list of users', $attr->description);
        $this->assertSame(['Users', 'Admin'], $attr->tags);
        $this->assertSame(['bearerAuth'], $attr->security);
        $this->assertTrue($attr->deprecated);
    }

    public function test_open_api_operation_targets_methods_only(): void
    {
        $ref = new ReflectionClass(OpenApiOperation::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_METHOD, $instance->flags);
    }

    // --- OpenApiParameter ---

    public function test_open_api_parameter_defaults(): void
    {
        $attr = new OpenApiParameter(name: 'id');

        $this->assertSame('id', $attr->name);
        $this->assertSame('query', $attr->in);
        $this->assertSame('', $attr->description);
        $this->assertFalse($attr->required);
        $this->assertSame('string', $attr->type);
        $this->assertNull($attr->example);
    }

    public function test_open_api_parameter_with_values(): void
    {
        $attr = new OpenApiParameter(
            name: 'user_id',
            in: 'path',
            description: 'The user ID',
            required: true,
            type: 'integer',
            example: 42,
        );

        $this->assertSame('user_id', $attr->name);
        $this->assertSame('path', $attr->in);
        $this->assertSame('The user ID', $attr->description);
        $this->assertTrue($attr->required);
        $this->assertSame('integer', $attr->type);
        $this->assertSame(42, $attr->example);
    }

    public function test_open_api_parameter_is_repeatable(): void
    {
        $ref = new ReflectionClass(OpenApiParameter::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $instance->flags,
        );
    }

    // --- OpenApiRequestBody ---

    public function test_open_api_request_body_defaults(): void
    {
        $attr = new OpenApiRequestBody();

        $this->assertNull($attr->formRequest);
        $this->assertNull($attr->schema);
        $this->assertSame('application/json', $attr->contentType);
        $this->assertTrue($attr->required);
        $this->assertSame('', $attr->description);
    }

    public function test_open_api_request_body_with_values(): void
    {
        $attr = new OpenApiRequestBody(
            formRequest: 'App\\Http\\Requests\\StoreUserRequest',
            schema: 'App\\Schemas\\UserSchema',
            contentType: 'multipart/form-data',
            required: false,
            description: 'User creation payload',
        );

        $this->assertSame('App\\Http\\Requests\\StoreUserRequest', $attr->formRequest);
        $this->assertSame('App\\Schemas\\UserSchema', $attr->schema);
        $this->assertSame('multipart/form-data', $attr->contentType);
        $this->assertFalse($attr->required);
        $this->assertSame('User creation payload', $attr->description);
    }

    public function test_open_api_request_body_targets_methods_only(): void
    {
        $ref = new ReflectionClass(OpenApiRequestBody::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_METHOD, $instance->flags);
    }

    // --- OpenApiResponse ---

    public function test_open_api_response_defaults(): void
    {
        $attr = new OpenApiResponse();

        $this->assertSame(200, $attr->status);
        $this->assertSame('', $attr->description);
        $this->assertNull($attr->resource);
        $this->assertNull($attr->schema);
        $this->assertFalse($attr->isArray);
    }

    public function test_open_api_response_with_values(): void
    {
        $attr = new OpenApiResponse(
            status: 201,
            description: 'User created successfully',
            resource: 'App\\Http\\Resources\\UserResource',
            schema: 'App\\Schemas\\UserSchema',
            isArray: true,
        );

        $this->assertSame(201, $attr->status);
        $this->assertSame('User created successfully', $attr->description);
        $this->assertSame('App\\Http\\Resources\\UserResource', $attr->resource);
        $this->assertSame('App\\Schemas\\UserSchema', $attr->schema);
        $this->assertTrue($attr->isArray);
    }

    public function test_open_api_response_is_repeatable(): void
    {
        $ref = new ReflectionClass(OpenApiResponse::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $instance->flags,
        );
    }

    // --- Attribute usage on methods via reflection ---

    public function test_attributes_can_be_read_from_method(): void
    {
        $ref = new \ReflectionMethod(SampleController::class, 'store');

        $operations = $ref->getAttributes(OpenApiOperation::class);
        $this->assertCount(1, $operations);
        $op = $operations[0]->newInstance();
        $this->assertSame('Create user', $op->summary);
        $this->assertSame(['Users'], $op->tags);

        $params = $ref->getAttributes(OpenApiParameter::class);
        $this->assertCount(2, $params);
        $p1 = $params[0]->newInstance();
        $this->assertSame('team_id', $p1->name);
        $this->assertSame('path', $p1->in);
        $this->assertTrue($p1->required);
        $p2 = $params[1]->newInstance();
        $this->assertSame('notify', $p2->name);
        $this->assertSame('query', $p2->in);

        $bodies = $ref->getAttributes(OpenApiRequestBody::class);
        $this->assertCount(1, $bodies);
        $body = $bodies[0]->newInstance();
        $this->assertSame('application/json', $body->contentType);

        $responses = $ref->getAttributes(OpenApiResponse::class);
        $this->assertCount(2, $responses);
        $r1 = $responses[0]->newInstance();
        $this->assertSame(201, $r1->status);
        $r2 = $responses[1]->newInstance();
        $this->assertSame(422, $r2->status);
    }
}

/**
 * Sample controller class used for reflection-based attribute tests.
 */
class SampleController
{
    #[OpenApiOperation(summary: 'Create user', tags: ['Users'])]
    #[OpenApiParameter(name: 'team_id', in: 'path', required: true, type: 'integer')]
    #[OpenApiParameter(name: 'notify', in: 'query', type: 'boolean', example: true)]
    #[OpenApiRequestBody(contentType: 'application/json', required: true)]
    #[OpenApiResponse(status: 201, description: 'Created')]
    #[OpenApiResponse(status: 422, description: 'Validation error')]
    public function store(): void
    {
    }
}
