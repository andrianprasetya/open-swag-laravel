<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Commands;

use Illuminate\Console\Command;
use OpenSwag\Laravel\SpecGenerator;

class ExportCommand extends Command
{
    protected $signature = 'openapi:export
        {--output=openapi.json : Output file path}
        {--format=json : Output format (json)}';

    protected $description = 'Export the current OpenAPI spec to a file';

    public function handle(): int
    {
        try {
            /** @var SpecGenerator $generator */
            $generator = app(SpecGenerator::class);
            $generator->discoverRoutes();

            $format = $this->option('format');

            if ($format !== 'json') {
                $this->error("Unsupported format: {$format}. Only 'json' is supported.");

                return self::FAILURE;
            }

            $json = $generator->specJson(true);

            $output = $this->option('output');

            $dir = dirname($output);
            if ($dir && $dir !== '.' && !is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($output, $json);
            $this->info("OpenAPI spec exported to {$output}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to export OpenAPI spec: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
