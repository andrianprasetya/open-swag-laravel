<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Tests\Unit;

use OpenSwag\Laravel\AttributeReader;
use OpenSwag\Laravel\Attributes\OpenApiOperation;
use OpenSwag\Laravel\Attributes\OpenApiParameter;
use OpenSwag\Laravel\Attributes\OpenApiRequestBody;
use OpenSwag\Laravel\Attributes\OpenApiResponse;
use OpenSwag\Laravel\Models\EndpointMetadata;
use OpenSwag\Laravel\Models\Parameter;
use OpenSwag\Laravel\Models\RequestBody;
use OpenSwag\Laravel\Models\ResponseDefinition;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new AttributeReader();
    }

    // --- hasAttributes ---

    public function test_has_attributes_returns_true_when_operation_present(): void
    {
        $method = new ReflectionMethod(ControllerWithAllAttributes::class, 'store');
        $this->assertTrue($this->reader->hasAttributes($method));
    }

    public function test_has_attributes_returns_true_with_only_parameter(): void
    {
        $method = new ReflectionMethod(ControllerWithOnlyParameter::class, 'index');
        $this->assertTrue($this->reader->hasAttributes($method));
    }

    public function test_has_attributes_returns_true_with_only_response(): void
    {
        $method = new ReflectionMethod(ControllerWithOnlyResponse::class, 'index');
        $this->assertTrue($this->reader->hasAttributes($method));
    }

    public function test_has_attributes_returns_false_when_no_attributes(): void
    {
        $method = new ReflectionMethod(ControllerWithNoAttributes::class, 'index');
        $this->assertFalse($this->reader->hasAttributes($method));
    }

    // --- readMethod: operation ---

    public function test_read_method_extracts_operation_metadata(): void
    {
        $method = new ReflectionMethod(ControllerWithAllAttributes::class, 'store');
        $metadata = $this->reader->readMethod($method);

        $this->assertInstanceOf(EndpointMetadata::class, $metadata);
        $this->assertSame('Create user', $metadata->summary);
        $this->assertSame('Creates a new user in the system', $metadata->description);
        $this->assertSame(['Users'], $metadata->tags);
        $this->assertSame(['bearerAuth'], $metadata->security);
        $this->assertTrue($metadata->deprecated);
    }

    // --- readMethod: parameters ---

    public function test_read_method_extracts_parameters(): void
    {
        $method = new ReflectionMethod(ControllerWithAllAttributes::class, 'store');
        $metadata = $this->reader->readMethod($method);

        $this->assertCount(2, $metadata->parameters);

        $param1 = $metadata->parameters[0];
        $this->assertInstanceOf(Parameter::class, $param1);
        $this->assertSame('team_id', $param1->name);
        $this->assertSame('path', $param1->in);
        $this->assertTrue($param1->required);
        $this->assertSame(['type' => 'integer'], $param1->schema);
        $this->assertNull($param1->example);

        $param2 = $metadata->parameters[1];
        $this->assertSame('notify', $param2->name);
        $this->assertSame('query', $param2->in);
        $this->assertFalse($param2->required);
        $this->assertSame(['type' => 'boolean'], $param2->schema);
        $this->assertTrue($param2->example);
    }

    // --- readMethod: request body ---

    public function test_read_method_extracts_request_body(): void
    {
        $method = new ReflectionMethod(ControllerWithAllAttributes::class, 'store');
        $metadata = $this->reader->readMethod($method);

        $this->assertNotNull($metadata->requestBody);
        $this->assertInstanceOf(RequestBody::class, $metadata->requestBody);
        $this->assertSame('User creation payload', $metadata->requestBody->description);
        $this->assertTrue($metadata->requestBody->required);
        $this->assertSame('application/json', $metadata->requestBody->contentType);
        $this->assertSame(
            ['$ref' => '#/components/schemas/StoreUserRequest'],
            $metadata->requestBody->schema,
        );
    }

    // --- readMethod: responses ---

    public function test_read_method_extracts_responses(): void
    {
        $method = new ReflectionMethod(ControllerWithAllAttributes::class, 'store');
        $metadata = $this->reader->readMethod($method);

        $this->assertCount(2, $metadata->responses);
        $this->assertArrayHasKey(201, $metadata->responses);
        $this->assertArrayHasKey(422, $metadata->responses);

        $r201 = $metadata->responses[201];
        $this->assertInstanceOf(ResponseDefinition::class, $r201);
        $this->assertSame('User created', $r201->description);
        $this->assertSame(
            ['$ref' => '#/components/schemas/UserResource'],
            $r201->schema,
        );

        $r422 = $metadata->responses[422];
        $this->assertSame('Validation error', $r422->description);
        $this->assertNull($r422->schema);
    }

    // --- readMethod: no attributes returns empty metadata ---

    public function test_read_method_returns_empty_metadata_when_no_attributes(): void
    {
        $method = new ReflectionMethod(ControllerWithNoAttributes::class, 'index');
        $metadata = $this->reader->readMethod($method);

        $this->assertSame('', $metadata->summary);
        $this->assertSame('', $metadata->description);
        $this->assertSame([], $metadata->tags);
        $this->assertSame([], $metadata->security);
        $this->assertFalse($metadata->deprecated);
        $this->assertSame([], $metadata->parameters);
        $this->assertNull($metadata->requestBody);
        $this->assertSame([], $metadata->responses);
    }

    // --- readMethod: response with isArray ---

    public function test_read_method_handles_array_response(): void
    {
        $method = new ReflectionMethod(ControllerWithArrayResponse::class, 'index');
        $metadata = $this->reader->readMethod($method);

        $this->assertCount(1, $metadata->responses);
        $r200 = $metadata->responses[200];
        $this->assertSame('List of users', $r200->description);
        $this->assertSame([
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/UserResource'],
        ], $r200->schema);
    }

    // --- readMethod: request body with schema instead of formRequest ---

    public function test_read_method_handles_request_body_with_schema(): void
    {
        $method = new ReflectionMethod(ControllerWithSchemaBody::class, 'update');
        $metadata = $this->reader->readMethod($method);

        $this->assertNotNull($metadata->requestBody);
        $this->assertSame(
            ['$ref' => '#/components/schemas/UserSchema'],
            $metadata->requestBody->schema,
        );
    }

    // --- readMethod: request body with no schema or formRequest ---

    public function test_read_method_handles_request_body_without_schema(): void
    {
        $method = new ReflectionMethod(ControllerWithEmptyBody::class, 'action');
        $metadata = $this->reader->readMethod($method);

        $this->assertNotNull($metadata->requestBody);
        $this->assertSame([], $metadata->requestBody->schema);
        $this->assertSame('multipart/form-data', $metadata->requestBody->contentType);
    }

    // --- EndpointMetadata toArray/fromArray ---

    public function test_endpoint_metadata_to_array(): void
    {
        $metadata = new EndpointMetadata(
            summary: 'Test',
            description: 'Desc',
            tags: ['Tag1'],
            security: ['bearer'],
            deprecated: true,
            parameters: [
                new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
            ],
            requestBody: new RequestBody(description: 'Body', required: true),
            responses: [
                200 => new ResponseDefinition(description: 'OK'),
            ],
        );

        $array = $metadata->toArray();

        $this->assertSame('Test', $array['summary']);
        $this->assertSame('Desc', $array['description']);
        $this->assertSame(['Tag1'], $array['tags']);
        $this->assertSame(['bearer'], $array['security']);
        $this->assertTrue($array['deprecated']);
        $this->assertCount(1, $array['parameters']);
        $this->assertSame('id', $array['parameters'][0]['name']);
        $this->assertNotNull($array['requestBody']);
        $this->assertCount(1, $array['responses']);
    }

    public function test_endpoint_metadata_from_array(): void
    {
        $data = [
            'summary' => 'Test',
            'description' => 'Desc',
            'tags' => ['Tag1'],
            'security' => ['bearer'],
            'deprecated' => true,
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
            'requestBody' => ['description' => 'Body', 'required' => true],
            'responses' => [
                200 => ['description' => 'OK'],
            ],
        ];

        $metadata = EndpointMetadata::fromArray($data);

        $this->assertSame('Test', $metadata->summary);
        $this->assertSame('Desc', $metadata->description);
        $this->assertSame(['Tag1'], $metadata->tags);
        $this->assertSame(['bearer'], $metadata->security);
        $this->assertTrue($metadata->deprecated);
        $this->assertCount(1, $metadata->parameters);
        $this->assertInstanceOf(Parameter::class, $metadata->parameters[0]);
        $this->assertNotNull($metadata->requestBody);
        $this->assertInstanceOf(RequestBody::class, $metadata->requestBody);
        $this->assertCount(1, $metadata->responses);
        $this->assertArrayHasKey(200, $metadata->responses);
        $this->assertInstanceOf(ResponseDefinition::class, $metadata->responses[200]);
    }

    public function test_endpoint_metadata_from_array_defaults(): void
    {
        $metadata = EndpointMetadata::fromArray([]);

        $this->assertSame('', $metadata->summary);
        $this->assertSame('', $metadata->description);
        $this->assertSame([], $metadata->tags);
        $this->assertSame([], $metadata->security);
        $this->assertFalse($metadata->deprecated);
        $this->assertSame([], $metadata->parameters);
        $this->assertNull($metadata->requestBody);
        $this->assertSame([], $metadata->responses);
    }
}

// --- Test fixture controllers ---

class ControllerWithAllAttributes
{
    #[OpenApiOperation(
        summary: 'Create user',
        description: 'Creates a new user in the system',
        tags: ['Users'],
        security: ['bearerAuth'],
        deprecated: true,
    )]
    #[OpenApiParameter(name: 'team_id', in: 'path', required: true, type: 'integer')]
    #[OpenApiParameter(name: 'notify', in: 'query', type: 'boolean', example: true)]
    #[OpenApiRequestBody(
        formRequest: 'App\\Http\\Requests\\StoreUserRequest',
        contentType: 'application/json',
        required: true,
        description: 'User creation payload',
    )]
    #[OpenApiResponse(status: 201, description: 'User created', resource: 'App\\Http\\Resources\\UserResource')]
    #[OpenApiResponse(status: 422, description: 'Validation error')]
    public function store(): void
    {
    }
}

class ControllerWithOnlyParameter
{
    #[OpenApiParameter(name: 'page', in: 'query', type: 'integer')]
    public function index(): void
    {
    }
}

class ControllerWithOnlyResponse
{
    #[OpenApiResponse(status: 200, description: 'OK')]
    public function index(): void
    {
    }
}

class ControllerWithNoAttributes
{
    public function index(): void
    {
    }
}

class ControllerWithArrayResponse
{
    #[OpenApiResponse(status: 200, description: 'List of users', resource: 'App\\Http\\Resources\\UserResource', isArray: true)]
    public function index(): void
    {
    }
}

class ControllerWithSchemaBody
{
    #[OpenApiRequestBody(schema: 'App\\Schemas\\UserSchema')]
    public function update(): void
    {
    }
}

class ControllerWithEmptyBody
{
    #[OpenApiRequestBody(contentType: 'multipart/form-data')]
    public function action(): void
    {
    }
}
