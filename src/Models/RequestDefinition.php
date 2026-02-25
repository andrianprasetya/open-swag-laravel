<?php

namespace OpenSwag\Laravel\Models;

class RequestDefinition
{
    /**
     * @param string $url Full request URL
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param array<string, string> $headers Request headers
     * @param array<string, string> $query Query parameters
     * @param string|null $body Request body content
     * @param array{type: string, value: string}|null $auth Authentication config (e.g. ['type' => 'bearer', 'value' => 'token'])
     */
    public function __construct(
        public string $url = '',
        public string $method = 'GET',
        public array $headers = [],
        public array $query = [],
        public ?string $body = null,
        public ?array $auth = null,
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'query' => $this->query,
            'body' => $this->body,
            'auth' => $this->auth,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            method: $data['method'] ?? 'GET',
            headers: $data['headers'] ?? [],
            query: $data['query'] ?? [],
            body: $data['body'] ?? null,
            auth: $data['auth'] ?? null,
        );
    }

    /**
     * Build the full URL with query parameters appended.
     */
    public function fullUrl(): string
    {
        if (empty($this->query)) {
            return $this->url;
        }

        $separator = str_contains($this->url, '?') ? '&' : '?';

        return $this->url . $separator . http_build_query($this->query);
    }
}
