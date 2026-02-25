<?php

namespace OpenSwag\Laravel\Snippets;

use OpenSwag\Laravel\Models\RequestDefinition;

class CurlGenerator implements SnippetGeneratorInterface
{
    public function generate(RequestDefinition $request): string
    {
        $parts = ['curl'];

        if (strtoupper($request->method) !== 'GET') {
            $parts[] = '-X ' . strtoupper($request->method);
        }

        $url = $request->fullUrl();
        $parts[] = "'" . $url . "'";

        // Auth header
        if ($request->auth !== null) {
            $type = $request->auth['type'] ?? '';
            $value = $request->auth['value'] ?? '';

            match ($type) {
                'bearer' => $parts[] = "-H 'Authorization: Bearer " . $value . "'",
                'basic' => $parts[] = "-H 'Authorization: Basic " . $value . "'",
                'apikey' => $parts[] = "-H '" . ($request->auth['name'] ?? 'X-API-Key') . ': ' . $value . "'",
                default => null,
            };
        }

        // Headers
        foreach ($request->headers as $name => $val) {
            $parts[] = "-H '" . $name . ': ' . $val . "'";
        }

        // Body
        if ($request->body !== null) {
            $parts[] = "-d '" . $request->body . "'";
        }

        return implode(" \\\n  ", $parts);
    }

    public function language(): string
    {
        return 'curl';
    }
}
