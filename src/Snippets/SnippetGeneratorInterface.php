<?php

namespace OpenSwag\Laravel\Snippets;

use OpenSwag\Laravel\Models\RequestDefinition;

interface SnippetGeneratorInterface
{
    /**
     * Generate a code snippet for the given request definition.
     */
    public function generate(RequestDefinition $request): string;

    /**
     * Return the language identifier for this generator.
     */
    public function language(): string;
}
