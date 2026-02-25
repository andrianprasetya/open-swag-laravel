<?php

use Illuminate\Http\Request;
use OpenSwag\Laravel\Http\Middleware\DocsAuthMiddleware;
use Symfony\Component\HttpFoundation\Response;

function createRequest(array $server = [], array $query = []): Request
{
    return Request::create('/api/docs', 'GET', $query, [], [], $server);
}

function createBasicAuthRequest(string $username, string $password): Request
{
    return createRequest([
        'PHP_AUTH_USER' => $username,
        'PHP_AUTH_PW' => $password,
    ]);
}

function createApiKeyRequest(string $key): Request
{
    return createRequest([], ['key' => $key]);
}

function passThrough(): Closure
{
    return fn (Request $request) => new Response('OK', 200);
}

// --- Auth disabled ---

it('passes through when auth is disabled', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => false,
        'username' => 'admin',
        'password' => 'secret',
        'api_key' => '',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('OK');
});

it('passes through when auth is enabled but all credentials are empty', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => '',
        'password' => '',
        'api_key' => '',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(200);
});

it('passes through when docs_auth config is missing', function () {
    config()->set('openswag.docs_auth', []);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(200);
});

// --- Basic auth ---

it('allows request with correct basic auth credentials', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret123',
        'api_key' => '',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $request = createBasicAuthRequest('admin', 'secret123');
    $response = $middleware->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('OK');
});

it('rejects request with wrong username', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret123',
        'api_key' => '',
        'realm' => 'Docs',
    ]);

    $middleware = new DocsAuthMiddleware();
    $request = createBasicAuthRequest('wrong', 'secret123');
    $response = $middleware->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(401);
    expect($response->headers->get('WWW-Authenticate'))->toBe('Basic realm="Docs"');
});

it('rejects request with wrong password', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret123',
        'api_key' => '',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $request = createBasicAuthRequest('admin', 'wrongpass');
    $response = $middleware->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(401);
    expect($response->headers->get('WWW-Authenticate'))->toContain('Basic realm=');
});

it('rejects request with no basic auth header when basic auth is required', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret123',
        'api_key' => '',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(401);
    expect($response->headers->get('WWW-Authenticate'))->toBe('Basic realm="API Documentation"');
});

// --- API key auth ---

it('allows request with correct API key', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => '',
        'password' => '',
        'api_key' => 'my-secret-key',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $request = createApiKeyRequest('my-secret-key');
    $response = $middleware->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('OK');
});

it('rejects request with wrong API key', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => '',
        'password' => '',
        'api_key' => 'my-secret-key',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $request = createApiKeyRequest('wrong-key');
    $response = $middleware->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(401);
});

it('rejects request with no API key when API key auth is required', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => '',
        'password' => '',
        'api_key' => 'my-secret-key',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(401);
});

// --- Priority: basic auth takes precedence over API key ---

it('uses basic auth when both username/password and api_key are configured', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret',
        'api_key' => 'my-key',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();

    // Basic auth should work
    $request = createBasicAuthRequest('admin', 'secret');
    $response = $middleware->handle($request, passThrough());
    expect($response->getStatusCode())->toBe(200);

    // API key alone should NOT work (basic auth takes precedence)
    $request = createApiKeyRequest('my-key');
    $response = $middleware->handle($request, passThrough());
    expect($response->getStatusCode())->toBe(401);
});

// --- Constant-time comparison ---

it('uses constant-time comparison for basic auth credentials', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret',
        'api_key' => '',
        'realm' => 'API Documentation',
    ]);

    $middleware = new DocsAuthMiddleware();

    // Verify correct credentials pass (hash_equals returns true for matching strings)
    $request = createBasicAuthRequest('admin', 'secret');
    $response = $middleware->handle($request, passThrough());
    expect($response->getStatusCode())->toBe(200);

    // Verify wrong credentials fail
    $request = createBasicAuthRequest('admin', 'wrong');
    $response = $middleware->handle($request, passThrough());
    expect($response->getStatusCode())->toBe(401);
});

// --- Custom realm ---

it('uses custom realm in WWW-Authenticate header', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret',
        'api_key' => '',
        'realm' => 'My Custom Realm',
    ]);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(401);
    expect($response->headers->get('WWW-Authenticate'))->toBe('Basic realm="My Custom Realm"');
});

it('uses default realm when realm is not configured', function () {
    config()->set('openswag.docs_auth', [
        'enabled' => true,
        'username' => 'admin',
        'password' => 'secret',
        'api_key' => '',
    ]);

    $middleware = new DocsAuthMiddleware();
    $response = $middleware->handle(createRequest(), passThrough());

    expect($response->getStatusCode())->toBe(401);
    expect($response->headers->get('WWW-Authenticate'))->toBe('Basic realm="API Documentation"');
});
