<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Commands;

use Illuminate\Console\Command;
use OpenSwag\Laravel\VersionDiffer;

class DiffCommand extends Command
{
    protected $signature = 'openapi:diff
        {old : Path to old spec file}
        {new : Path to new spec file}
        {--format=text : Output format (text or json)}';

    protected $description = 'Compare two OpenAPI spec files and output diff summary, breaking changes, and migration guide';

    public function handle(): int
    {
        try {
            $oldPath = $this->argument('old');
            $newPath = $this->argument('new');

            $differ = new VersionDiffer();
            $result = $differ->compareFiles($oldPath, $newPath);

            if ($this->option('format') === 'json') {
                $this->line($result->toJson());

                return self::SUCCESS;
            }

            $summary = $result->summary;
            $this->info('Diff Summary:');
            $this->line("  Added endpoints:    {$summary->addedEndpoints}");
            $this->line("  Removed endpoints:  {$summary->removedEndpoints}");
            $this->line("  Modified endpoints: {$summary->modifiedEndpoints}");
            $this->line("  Breaking changes:   {$summary->breakingChanges}");

            if ($result->hasBreakingChanges()) {
                $this->newLine();
                $this->warn('Breaking Changes:');
                foreach ($result->breaking as $bc) {
                    $this->line("  - [{$bc->method} {$bc->path}] {$bc->reason}");
                }

                $this->newLine();
                $this->info('Migration Guide:');
                $this->line($result->toMarkdown());
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
