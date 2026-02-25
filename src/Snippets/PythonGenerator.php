<?php

namespace OpenSwag\Laravel\Snippets;

use OpenSwag\Laravel\Models\RequestDefinition;

class PythonGenerator implements SnippetGeneratorInterface
{
    public function generate(RequestDefinition $request): string
    {
        $method = strtolower($request->method);

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
        $lines[] = 'import requests';
        $lines[] = '';
        $lines[] = "url = '" . $request->url . "'";

        if (!empty($request->query)) {
            $lines[] = 'params = {';
            foreach ($request->query as $key => $val) {
                $lines[] = "    '" . $key . "': '" . $val . "',";
            }
            $lines[] = '}';
        }

        if (!empty($headers)) {
            $lines[] = 'headers = {';
            foreach ($headers as $name => $val) {
                $lines[] = "    '" . $name . "': '" . $val . "',";
            }
            $lines[] = '}';
        }

        $args = ["'" . $request->url . "'"];
        if (!empty($request->query)) {
            $args[] = 'params=params';
        }
        if (!empty($headers)) {
            $args[] = 'headers=headers';
        }
        if ($request->body !== null) {
            $lines[] = "data = '" . $request->body . "'";
            $args[] = 'data=data';
        }

        $lines[] = '';
        $lines[] = 'response = requests.' . $method . '(' . implode(', ', $args) . ')';
        $lines[] = 'print(response.json())';

        return implode("\n", $lines);
    }

    public function language(): string
    {
        return 'python';
    }
}
