<?php

namespace OpenSwag\Laravel\Models;

class BreakingChange
{
    /**
     * @param string $path API endpoint path
     * @param string $method HTTP method
     * @param string $reason Why this is a breaking change
     * @param string $migration Suggested migration step
     */
    public function __construct(
        public string $path = '',
        public string $method = '',
        public string $reason = '',
        public string $migration = '',
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'method' => $this->method,
            'reason' => $this->reason,
            'migration' => $this->migration,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            path: $data['path'] ?? '',
            method: $data['method'] ?? '',
            reason: $data['reason'] ?? '',
            migration: $data['migration'] ?? '',
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
