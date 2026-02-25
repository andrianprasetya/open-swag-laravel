<?php

namespace OpenSwag\Laravel\Models;

class DiffResult
{
    /**
     * @param string $oldVersion Previous spec version
     * @param string $newVersion New spec version
     * @param Change[] $changes List of all changes
     * @param BreakingChange[] $breaking List of breaking changes
     * @param DiffSummary $summary Summary counts
     */
    public function __construct(
        public string $oldVersion = '',
        public string $newVersion = '',
        public array $changes = [],
        public array $breaking = [],
        public ?DiffSummary $summary = null,
    ) {
        $this->summary ??= new DiffSummary();
    }

    public function hasBreakingChanges(): bool
    {
        return count($this->breaking) > 0;
    }

    /**
     * Generate a Markdown changelog from the diff.
     * Placeholder — full implementation in Task 11.2.
     */
    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = "# Changelog: {$this->oldVersion} → {$this->newVersion}";
        $lines[] = '';

        if ($this->summary->addedEndpoints > 0) {
            $lines[] = "## Added ({$this->summary->addedEndpoints})";
            foreach ($this->changes as $change) {
                if ($change->type === 'added') {
                    $lines[] = "- `{$change->method} {$change->path}` — {$change->description}";
                }
            }
            $lines[] = '';
        }

        if ($this->summary->removedEndpoints > 0) {
            $lines[] = "## Removed ({$this->summary->removedEndpoints})";
            foreach ($this->changes as $change) {
                if ($change->type === 'removed') {
                    $lines[] = "- `{$change->method} {$change->path}` — {$change->description}";
                }
            }
            $lines[] = '';
        }

        if ($this->summary->modifiedEndpoints > 0) {
            $lines[] = "## Modified ({$this->summary->modifiedEndpoints})";
            foreach ($this->changes as $change) {
                if ($change->type === 'modified') {
                    $lines[] = "- `{$change->method} {$change->path}` — {$change->description}";
                }
            }
            $lines[] = '';
        }

        if ($this->hasBreakingChanges()) {
            $lines[] = "## Breaking Changes ({$this->summary->breakingChanges})";
            foreach ($this->breaking as $bc) {
                $lines[] = "- `{$bc->method} {$bc->path}` — {$bc->reason}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function toArray(): array
    {
        return [
            'oldVersion' => $this->oldVersion,
            'newVersion' => $this->newVersion,
            'changes' => array_map(fn (Change $c) => $c->toArray(), $this->changes),
            'breaking' => array_map(fn (BreakingChange $b) => $b->toArray(), $this->breaking),
            'summary' => $this->summary->toArray(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $data): self
    {
        $changes = array_map(
            fn (array $c) => Change::fromArray($c),
            $data['changes'] ?? [],
        );

        $breaking = array_map(
            fn (array $b) => BreakingChange::fromArray($b),
            $data['breaking'] ?? [],
        );

        $summary = isset($data['summary'])
            ? DiffSummary::fromArray($data['summary'])
            : new DiffSummary();

        return new self(
            oldVersion: $data['oldVersion'] ?? '',
            newVersion: $data['newVersion'] ?? '',
            changes: $changes,
            breaking: $breaking,
            summary: $summary,
        );
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
