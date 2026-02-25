<?php

namespace OpenSwag\Laravel\Models;

class DiffSummary
{
    public function __construct(
        public int $addedEndpoints = 0,
        public int $removedEndpoints = 0,
        public int $modifiedEndpoints = 0,
        public int $breakingChanges = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'addedEndpoints' => $this->addedEndpoints,
            'removedEndpoints' => $this->removedEndpoints,
            'modifiedEndpoints' => $this->modifiedEndpoints,
            'breakingChanges' => $this->breakingChanges,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            addedEndpoints: $data['addedEndpoints'] ?? 0,
            removedEndpoints: $data['removedEndpoints'] ?? 0,
            modifiedEndpoints: $data['modifiedEndpoints'] ?? 0,
            breakingChanges: $data['breakingChanges'] ?? 0,
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
