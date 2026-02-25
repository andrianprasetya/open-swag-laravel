<?php

namespace OpenSwag\Laravel\Snippets;

use OpenSwag\Laravel\Models\RequestDefinition;

class GoGenerator implements SnippetGeneratorInterface
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
        $lines[] = 'package main';
        $lines[] = '';
        $lines[] = 'import (';
        $lines[] = '    "fmt"';
        $lines[] = '    "io"';
        $lines[] = '    "net/http"';
        if ($request->body !== null) {
            $lines[] = '    "strings"';
        }
        $lines[] = ')';
        $lines[] = '';
        $lines[] = 'func main() {';

        if ($request->body !== null) {
            $lines[] = '    body := strings.NewReader(`' . $request->body . '`)';
            $lines[] = '    req, err := http.NewRequest("' . $method . '", "' . $url . '", body)';
        } else {
            $lines[] = '    req, err := http.NewRequest("' . $method . '", "' . $url . '", nil)';
        }

        $lines[] = '    if err != nil {';
        $lines[] = '        panic(err)';
        $lines[] = '    }';

        foreach ($headers as $name => $val) {
            $lines[] = '    req.Header.Set("' . $name . '", "' . $val . '")';
        }

        $lines[] = '';
        $lines[] = '    resp, err := http.DefaultClient.Do(req)';
        $lines[] = '    if err != nil {';
        $lines[] = '        panic(err)';
        $lines[] = '    }';
        $lines[] = '    defer resp.Body.Close()';
        $lines[] = '';
        $lines[] = '    respBody, _ := io.ReadAll(resp.Body)';
        $lines[] = '    fmt.Println(string(respBody))';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function language(): string
    {
        return 'go';
    }
}
