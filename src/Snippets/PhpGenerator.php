<?php

namespace OpenSwag\Laravel\Snippets;

use OpenSwag\Laravel\Models\RequestDefinition;

class PhpGenerator implements SnippetGeneratorInterface
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
        $lines[] = '$client = new \\GuzzleHttp\\Client();';
        $lines[] = '';
        $lines[] = '$response = $client->request(\'' . $method . '\', \'' . $url . '\', [';

        if (!empty($headers)) {
            $lines[] = '    \'headers\' => [';
            foreach ($headers as $name => $val) {
                $lines[] = '        \'' . $name . '\' => \'' . $val . '\',';
            }
            $lines[] = '    ],';
        }

        if ($request->body !== null) {
            $lines[] = '    \'body\' => ' . var_export($request->body, true) . ',';
        }

        $lines[] = ']);';
        $lines[] = '';
        $lines[] = '$body = $response->getBody();';
        $lines[] = 'echo $body;';

        return implode("\n", $lines);
    }

    public function language(): string
    {
        return 'php';
    }
}
