<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use OpenSwag\Laravel\SpecGenerator;

class CacheCommand extends Command
{
    public const CACHE_KEY = 'openswag_spec_cache';

    protected $signature = 'openapi:cache
        {--clear : Clear the cached spec}';

    protected $description = 'Build and cache the OpenAPI spec for faster serving, or clear the cached spec';

    public function handle(): int
    {
        try {
            if ($this->option('clear')) {
                Cache::forget(self::CACHE_KEY);
                $this->info('OpenAPI spec cache cleared.');

                return self::SUCCESS;
            }

            /** @var SpecGenerator $generator */
            $generator = app(SpecGenerator::class);
            $generator->discoverRoutes();
            $spec = $generator->buildSpec();

            Cache::forever(self::CACHE_KEY, $spec);
            $this->info('OpenAPI spec cached successfully [' . self::CACHE_KEY . '].');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to cache OpenAPI spec: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
