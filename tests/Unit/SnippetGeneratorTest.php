<?php

use OpenSwag\Laravel\Models\RequestDefinition;
use OpenSwag\Laravel\Snippets\CurlGenerator;
use OpenSwag\Laravel\Snippets\GoGenerator;
use OpenSwag\Laravel\Snippets\JavaScriptGenerator;
use OpenSwag\Laravel\Snippets\PhpGenerator;
use OpenSwag\Laravel\Snippets\PythonGenerator;
use OpenSwag\Laravel\Snippets\SnippetGeneratorInterface;

// --- RequestDefinition ---

it('creates a RequestDefinition with defaults', function () {
    $req = new RequestDefinition();

    expect($req->url)->toBe('');
    expect($req->method)->toBe('GET');
    expect($req->headers)->toBe([]);
    expect($req->query)->toBe([]);
    expect($req->body)->toBeNull();
    expect($req->auth)->toBeNull();
});

it('creates a RequestDefinition from array', function () {
    $req = RequestDefinition::fromArray([
        'url' => 'https://api.example.com/users',
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'query' => ['page' => '1'],
        'body' => '{"name":"John"}',
        'auth' => ['type' => 'bearer', 'value' => 'tok123'],
    ]);

    expect($req->url)->toBe('https://api.example.com/users');
    expect($req->method)->toBe('POST');
    expect($req->headers)->toBe(['Content-Type' => 'application/json']);
    expect($req->query)->toBe(['page' => '1']);
    expect($req->body)->toBe('{"name":"John"}');
    expect($req->auth)->toBe(['type' => 'bearer', 'value' => 'tok123']);
});

it('round-trips RequestDefinition through toArray/fromArray', function () {
    $req = new RequestDefinition(
        url: 'https://api.example.com/items',
        method: 'PUT',
        headers: ['Accept' => 'application/json'],
        query: ['id' => '5'],
        body: '{"title":"Test"}',
        auth: ['type' => 'basic', 'value' => 'dXNlcjpwYXNz'],
    );

    $restored = RequestDefinition::fromArray($req->toArray());

    expect($restored->url)->toBe($req->url);
    expect($restored->method)->toBe($req->method);
    expect($restored->headers)->toBe($req->headers);
    expect($restored->query)->toBe($req->query);
    expect($restored->body)->toBe($req->body);
    expect($restored->auth)->toBe($req->auth);
});

it('builds fullUrl without query params', function () {
    $req = new RequestDefinition(url: 'https://api.example.com/users');
    expect($req->fullUrl())->toBe('https://api.example.com/users');
});

it('builds fullUrl with query params', function () {
    $req = new RequestDefinition(
        url: 'https://api.example.com/users',
        query: ['page' => '1', 'limit' => '10'],
    );
    expect($req->fullUrl())->toBe('https://api.example.com/users?page=1&limit=10');
});

// --- All generators implement the interface ---

it('all generators implement SnippetGeneratorInterface', function () {
    expect(new CurlGenerator())->toBeInstanceOf(SnippetGeneratorInterface::class);
    expect(new JavaScriptGenerator())->toBeInstanceOf(SnippetGeneratorInterface::class);
    expect(new PhpGenerator())->toBeInstanceOf(SnippetGeneratorInterface::class);
    expect(new PythonGenerator())->toBeInstanceOf(SnippetGeneratorInterface::class);
    expect(new GoGenerator())->toBeInstanceOf(SnippetGeneratorInterface::class);
});

it('returns correct language identifiers', function () {
    expect((new CurlGenerator())->language())->toBe('curl');
    expect((new JavaScriptGenerator())->language())->toBe('javascript');
    expect((new PhpGenerator())->language())->toBe('php');
    expect((new PythonGenerator())->language())->toBe('python');
    expect((new GoGenerator())->language())->toBe('go');
});

// --- Helper to build a full request ---

function fullRequest(): RequestDefinition
{
    return new RequestDefinition(
        url: 'https://api.example.com/users',
        method: 'POST',
        headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        query: ['page' => '1'],
        body: '{"name":"John"}',
        auth: ['type' => 'bearer', 'value' => 'my-token'],
    );
}

function simpleGetRequest(): RequestDefinition
{
    return new RequestDefinition(
        url: 'https://api.example.com/users',
        method: 'GET',
    );
}

// --- CurlGenerator ---

it('generates curl for a simple GET', function () {
    $snippet = (new CurlGenerator())->generate(simpleGetRequest());

    expect($snippet)->toContain('curl');
    expect($snippet)->toContain('https://api.example.com/users');
    expect($snippet)->not->toContain('-X');
});

it('generates curl with method, headers, body, query, and auth', function () {
    $snippet = (new CurlGenerator())->generate(fullRequest());

    expect($snippet)->toContain('curl');
    expect($snippet)->toContain('-X POST');
    expect($snippet)->toContain('https://api.example.com/users?page=1');
    expect($snippet)->toContain("Authorization: Bearer my-token");
    expect($snippet)->toContain("Content-Type: application/json");
    expect($snippet)->toContain("Accept: application/json");
    expect($snippet)->toContain('{"name":"John"}');
});

it('generates curl with basic auth', function () {
    $req = new RequestDefinition(
        url: 'https://api.example.com/users',
        method: 'GET',
        auth: ['type' => 'basic', 'value' => 'dXNlcjpwYXNz'],
    );

    $snippet = (new CurlGenerator())->generate($req);
    expect($snippet)->toContain('Authorization: Basic dXNlcjpwYXNz');
});

it('generates curl with apikey auth', function () {
    $req = new RequestDefinition(
        url: 'https://api.example.com/users',
        method: 'GET',
        auth: ['type' => 'apikey', 'name' => 'X-API-Key', 'value' => 'key123'],
    );

    $snippet = (new CurlGenerator())->generate($req);
    expect($snippet)->toContain('X-API-Key: key123');
});

// --- JavaScriptGenerator ---

it('generates JavaScript fetch for a simple GET', function () {
    $snippet = (new JavaScriptGenerator())->generate(simpleGetRequest());

    expect($snippet)->toContain('fetch(');
    expect($snippet)->toContain('https://api.example.com/users');
    expect($snippet)->toContain("method: 'GET'");
});

it('generates JavaScript fetch with method, headers, body, query, and auth', function () {
    $snippet = (new JavaScriptGenerator())->generate(fullRequest());

    expect($snippet)->toContain('fetch(');
    expect($snippet)->toContain('https://api.example.com/users?page=1');
    expect($snippet)->toContain("method: 'POST'");
    expect($snippet)->toContain("'Authorization': 'Bearer my-token'");
    expect($snippet)->toContain("'Content-Type': 'application/json'");
    expect($snippet)->toContain('{"name":"John"}');
    expect($snippet)->toContain('.then(response => response.json())');
});

// --- PhpGenerator ---

it('generates PHP Guzzle for a simple GET', function () {
    $snippet = (new PhpGenerator())->generate(simpleGetRequest());

    expect($snippet)->toContain('GuzzleHttp\\Client');
    expect($snippet)->toContain('https://api.example.com/users');
    expect($snippet)->toContain("'GET'");
});

it('generates PHP Guzzle with method, headers, body, query, and auth', function () {
    $snippet = (new PhpGenerator())->generate(fullRequest());

    expect($snippet)->toContain('GuzzleHttp\\Client');
    expect($snippet)->toContain("'POST'");
    expect($snippet)->toContain('https://api.example.com/users?page=1');
    expect($snippet)->toContain("'Authorization' => 'Bearer my-token'");
    expect($snippet)->toContain("'Content-Type' => 'application/json'");
    expect($snippet)->toContain('{"name":"John"}');
    expect($snippet)->toContain('$response->getBody()');
});

// --- PythonGenerator ---

it('generates Python requests for a simple GET', function () {
    $snippet = (new PythonGenerator())->generate(simpleGetRequest());

    expect($snippet)->toContain('import requests');
    expect($snippet)->toContain('https://api.example.com/users');
    expect($snippet)->toContain('requests.get(');
});

it('generates Python requests with method, headers, body, query, and auth', function () {
    $snippet = (new PythonGenerator())->generate(fullRequest());

    expect($snippet)->toContain('import requests');
    expect($snippet)->toContain("url = 'https://api.example.com/users'");
    expect($snippet)->toContain('requests.post(');
    expect($snippet)->toContain("'Authorization': 'Bearer my-token'");
    expect($snippet)->toContain("'Content-Type': 'application/json'");
    expect($snippet)->toContain("'page': '1'");
    expect($snippet)->toContain('params=params');
    expect($snippet)->toContain('headers=headers');
    expect($snippet)->toContain('data=data');
    expect($snippet)->toContain('print(response.json())');
});

// --- GoGenerator ---

it('generates Go net/http for a simple GET', function () {
    $snippet = (new GoGenerator())->generate(simpleGetRequest());

    expect($snippet)->toContain('package main');
    expect($snippet)->toContain('"net/http"');
    expect($snippet)->toContain('https://api.example.com/users');
    expect($snippet)->toContain('"GET"');
    expect($snippet)->toContain('http.NewRequest');
    expect($snippet)->not->toContain('"strings"');
});

it('generates Go net/http with method, headers, body, query, and auth', function () {
    $snippet = (new GoGenerator())->generate(fullRequest());

    expect($snippet)->toContain('package main');
    expect($snippet)->toContain('"net/http"');
    expect($snippet)->toContain('"strings"');
    expect($snippet)->toContain('"POST"');
    expect($snippet)->toContain('https://api.example.com/users?page=1');
    expect($snippet)->toContain('req.Header.Set("Authorization", "Bearer my-token")');
    expect($snippet)->toContain('req.Header.Set("Content-Type", "application/json")');
    expect($snippet)->toContain('strings.NewReader');
    expect($snippet)->toContain('{"name":"John"}');
    expect($snippet)->toContain('http.DefaultClient.Do(req)');
});

// --- All generators produce non-empty output for minimal request ---

it('all generators produce non-empty output containing the URL', function () {
    $req = new RequestDefinition(url: 'https://api.example.com', method: 'GET');

    $generators = [
        new CurlGenerator(),
        new JavaScriptGenerator(),
        new PhpGenerator(),
        new PythonGenerator(),
        new GoGenerator(),
    ];

    foreach ($generators as $gen) {
        $snippet = $gen->generate($req);
        expect($snippet)->not->toBeEmpty();
        expect($snippet)->toContain('https://api.example.com');
    }
});

// --- All generators include body when present ---

it('all generators include body content when present', function () {
    $req = new RequestDefinition(
        url: 'https://api.example.com/data',
        method: 'POST',
        body: '{"key":"value"}',
    );

    $generators = [
        new CurlGenerator(),
        new JavaScriptGenerator(),
        new PhpGenerator(),
        new PythonGenerator(),
        new GoGenerator(),
    ];

    foreach ($generators as $gen) {
        $snippet = $gen->generate($req);
        expect($snippet)->toContain('{"key":"value"}');
    }
});

// --- All generators include auth when present ---

it('all generators include bearer auth when present', function () {
    $req = new RequestDefinition(
        url: 'https://api.example.com/secure',
        method: 'GET',
        auth: ['type' => 'bearer', 'value' => 'secret-token'],
    );

    $generators = [
        new CurlGenerator(),
        new JavaScriptGenerator(),
        new PhpGenerator(),
        new PythonGenerator(),
        new GoGenerator(),
    ];

    foreach ($generators as $gen) {
        $snippet = $gen->generate($req);
        expect($snippet)->toContain('Bearer secret-token');
    }
});
