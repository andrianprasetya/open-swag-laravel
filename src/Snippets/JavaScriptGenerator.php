<?php

namespace OpenSwag\Laravel\Snippets;

use OpenSwag\Laravel\Models\RequestDefinition;

class JavaScriptGenerator implements SnippetGeneratorInterface
{
    public function generate(RequestDefinition $request): string
    {
        $url = $request->fullUrl();
        $method = strtoupper($request->method);

        $headers = $request->headers;

        // Auth header
        if ($request->auth !== null) {
            $type = $request->auth['type'] ?? '';
            $value = $request->auth['value'] ?? '';

            match ($type) {
                'bearer' => $headers['Authorization'] = 'Bearer ' . $value,
                'basic' => $headers['Authorization'] = 'Basic ' . $value,
                'apikey' => $headers[$request->auth['name'] ?? 'X-API-Key'] = $value,
                default => null,
            };
        }

        $lines = [];
        $lines[] = "fetch('" . $url . "', {";
        $lines[] = "  method: '" . $method . "',";

        if (!empty($headers)) {
            $lines[] = '  headers: {';
            foreach ($headers as $name => $val) {
                $lines[] = "    '" . $name . "': '" . $val . "',";
            }
            $lines[] = '  },';
        }

        if ($request->body !== null) {
            $lines[] = "  body: '" . $request->body . "',";
        }

        $lines[] = '})';
        $lines[] = '  .then(response => response.json())';
        $lines[] = '  .then(data => console.log(data));';

        return implode("\n", $lines);
    }

    public function language(): string
    {
        return 'javascript';
    }
}
