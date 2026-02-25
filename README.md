# open-swag-laravel

A Laravel package that automatically generates **OpenAPI 3.0** documentation from your routes and serves it with a beautiful **Scalar UI**.

## Features

- **Automatic Route Discovery** — scans your Laravel routes and builds the OpenAPI spec
- **PHP 8.1+ Attributes** — annotate controllers with `#[OpenApiOperation]`, `#[OpenApiParameter]`, `#[OpenApiRequestBody]`, `#[OpenApiResponse]`
- **Schema Conversion** — converts Form Requests, Eloquent Models, API Resources, and plain PHP classes to OpenAPI schemas
- **Scalar UI** — interactive API docs with themes, dark mode, sidebar search, and tag grouping
- **Example Generation** — auto-generates examples from model factories, field name heuristics, and custom templates
- **Code Snippets** — generates request snippets in cURL, JavaScript, PHP, Python, and Go
- **Version Diffing** — compares two specs and detects breaking changes with migration guides
- **Gateway Aggregation** — merges specs from multiple microservices with path prefixing and health checks
- **Docs Authentication** — protect your docs with basic auth or API key
- **Artisan Commands** — `openapi:generate`, `openapi:diff`, `openapi:export`, `openapi:cache`

## Requirements

- PHP 8.1+
- Laravel 9, 10, 11, or 12

## Installation

```bash
composer require openswag/laravel
```

The service provider is auto-discovered. To publish the config file:

```bash
php artisan vendor:publish --tag=openswag-config
```

## Quick Start

Once installed, your API docs are available at `/api/docs` by default. The JSON spec is served at `/api/docs/json`.

### Annotate Your Controllers

```php
use OpenSwag\Laravel\Attributes\OpenApiOperation;
use OpenSwag\Laravel\Attributes\OpenApiParameter;
use OpenSwag\Laravel\Attributes\OpenApiRequestBody;
use OpenSwag\Laravel\Attributes\OpenApiResponse;

class UserController extends Controller
{
    #[OpenApiOperation(
        summary: 'List all users',
        tags: ['Users'],
        security: ['bearerAuth']
    )]
    #[OpenApiParameter(name: 'page', in: 'query', type: 'integer')]
    #[OpenApiResponse(status: 200, description: 'User list', resource: UserResource::class, isArray: true)]
    public function index()
    {
        return UserResource::collection(User::paginate());
    }

    #[OpenApiOperation(summary: 'Create a user', tags: ['Users'])]
    #[OpenApiRequestBody(formRequest: StoreUserRequest::class)]
    #[OpenApiResponse(status: 201, description: 'User created', resource: UserResource::class)]
    public function store(StoreUserRequest $request)
    {
        return new UserResource(User::create($request->validated()));
    }
}
```

### Generate the Spec

```bash
php artisan openapi:generate --output=openapi.json --pretty
```

## Configuration

Publish and edit `config/openswag.php`:

```php
return [
    'info' => [
        'title' => env('OPENSWAG_TITLE', 'API Documentation'),
        'version' => env('OPENSWAG_VERSION', '1.0.0'),
    ],
    'route' => [
        'prefix' => 'api/docs',
        'middleware' => [],
    ],
    'ui' => [
        'theme' => 'purple',    // purple, blue, green, light
        'dark_mode' => true,
        'layout' => 'modern',   // modern, classic
    ],
    'docs_auth' => [
        'enabled' => false,
        'username' => '',
        'password' => '',
    ],
    'gateway' => [
        'enabled' => false,
        'services' => [],
    ],
    'examples' => [
        'use_factories' => true,
        'templates' => [],
    ],
];
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `openapi:generate` | Generate the OpenAPI spec from routes and attributes |
| `openapi:export` | Export the spec to a file |
| `openapi:diff` | Compare two spec files and detect breaking changes |
| `openapi:cache` | Cache the spec for faster serving |

```bash
# Generate with pretty output
php artisan openapi:generate --output=openapi.json --pretty

# Compare versions
php artisan openapi:diff old-spec.json new-spec.json

# Export
php artisan openapi:export --output=public/openapi.json

# Cache / clear cache
php artisan openapi:cache
php artisan openapi:cache --clear
```

## Schema Conversion

OpenSwag automatically converts your PHP classes to OpenAPI schemas:

```php
use OpenSwag\Laravel\SchemaConverter;

$converter = app(SchemaConverter::class);

// From a Form Request (validation rules → schema)
$schema = $converter->fromFormRequest(StoreUserRequest::class);

// From an Eloquent Model ($fillable, $hidden, $casts)
$schema = $converter->fromModel(User::class);

// From an API Resource
$schema = $converter->fromResource(UserResource::class);

// From any PHP class (public properties via reflection)
$schema = $converter->fromClass(UserDto::class);
```

## Security Schemes

Apply security schemes via attributes:

```php
#[OpenApiOperation(security: ['bearerAuth'])]    // JWT Bearer
#[OpenApiOperation(security: ['basicAuth'])]      // HTTP Basic
#[OpenApiOperation(security: ['apiKeyHeader'])]   // X-API-Key header
#[OpenApiOperation(security: ['oauth2'])]         // OAuth2
```

Supported schemes: `bearerAuth`, `basicAuth`, `apiKeyHeader`, `apiKeyQuery`, `cookieAuth`, `oauth2`.

## Gateway Aggregation

Merge specs from multiple microservices:

```php
// config/openswag.php
'gateway' => [
    'enabled' => true,
    'services' => [
        [
            'name' => 'users-service',
            'url' => 'https://users.internal/openapi.json',
            'path_prefix' => '/users',
        ],
        [
            'name' => 'orders-service',
            'url' => 'https://orders.internal/openapi.json',
            'path_prefix' => '/orders',
        ],
    ],
    'cache_ttl' => 300,
    'health_check_timeout' => 5,
],
```

## Blade Component

Embed docs in any Blade view:

```blade
<x-openswag-docs />
```

## Facade

```php
use OpenSwag\Laravel\Facades\OpenSwag;

$spec = OpenSwag::buildSpec();
$json = OpenSwag::specJson(pretty: true);
```

## Testing

```bash
composer test
```

## License

MIT
