<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Commands;

use Illuminate\Console\Command;
use OpenSwag\Laravel\SpecGenerator;

class GenerateCommand extends Command
{
    protected $signature = 'openapi:generate
        {--output= : Output file path}
        {--pretty : Pretty-print JSON}';

    protected $description = 'Generate OpenAPI spec from discovered routes and attributes';

    public function handle(): int
    {
        try {
            /** @var SpecGenerator $generator */
            $generator = app(SpecGenerator::class);
            $generator->discoverRoutes();

            $pretty = (bool) $this->option('pretty');
            $json = $generator->specJson($pretty);

            $output = $this->option('output');

            if ($output) {
                $dir = dirname($output);
                if ($dir && !is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($output, $json);
                $this->info("OpenAPI spec written to {$output}");
            } else {
                $this->line($json);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to generate OpenAPI spec: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
