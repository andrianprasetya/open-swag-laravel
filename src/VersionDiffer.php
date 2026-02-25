<?php

namespace OpenSwag\Laravel;

use InvalidArgumentException;
use OpenSwag\Laravel\Models\BreakingChange;
use OpenSwag\Laravel\Models\Change;
use OpenSwag\Laravel\Models\DiffResult;
use OpenSwag\Laravel\Models\DiffSummary;

class VersionDiffer
{
    /**
     * Compare two spec files and produce a diff result.
     *
     * @throws InvalidArgumentException If either file cannot be read or decoded.
     */
    public function compareFiles(string $oldPath, string $newPath): DiffResult
    {
        $oldSpec = $this->readSpecFile($oldPath);
        $newSpec = $this->readSpecFile($newPath);

        return $this->compare($oldSpec, $newSpec);
    }

    /**
     * Compare two OpenAPI spec arrays and produce a diff result.
     */
    public function compare(array $oldSpec, array $newSpec): DiffResult
    {
        $oldVersion = $oldSpec['info']['version'] ?? '';
        $newVersion = $newSpec['info']['version'] ?? '';

        $oldEndpoints = $this->extractEndpoints($oldSpec);
        $newEndpoints = $this->extractEndpoints($newSpec);

        $changes = [];
        $breaking = [];

        // Detect added endpoints (in new but not old)
        foreach ($newEndpoints as $key => $newOp) {
            if (!isset($oldEndpoints[$key])) {
                $changes[] = new Change(
                    type: 'added',
                    path: $newOp['path'],
                    method: $newOp['method'],
                    description: "Endpoint added: {$newOp['method']} {$newOp['path']}",
                    isBreaking: false,
                );
            }
        }

        // Detect removed endpoints (in old but not new) — BREAKING
        foreach ($oldEndpoints as $key => $oldOp) {
            if (!isset($newEndpoints[$key])) {
                $changes[] = new Change(
                    type: 'removed',
                    path: $oldOp['path'],
                    method: $oldOp['method'],
                    description: "Endpoint removed: {$oldOp['method']} {$oldOp['path']}",
                    isBreaking: true,
                );
                $breaking[] = new BreakingChange(
                    path: $oldOp['path'],
                    method: $oldOp['method'],
                    reason: "Endpoint removed: {$oldOp['method']} {$oldOp['path']}",
                    migration: "Remove all client calls to {$oldOp['method']} {$oldOp['path']} or replace with an alternative endpoint.",
                );
            }
        }

        // Detect modified endpoints (present in both)
        foreach ($oldEndpoints as $key => $oldOp) {
            if (!isset($newEndpoints[$key])) {
                continue;
            }

            $newOp = $newEndpoints[$key];
            $endpointBreaking = $this->detectBreakingChanges($oldOp, $newOp);

            if (!empty($endpointBreaking)) {
                $reasons = array_map(fn (BreakingChange $bc) => $bc->reason, $endpointBreaking);
                $changes[] = new Change(
                    type: 'modified',
                    path: $oldOp['path'],
                    method: $oldOp['method'],
                    description: 'Breaking changes: ' . implode('; ', $reasons),
                    isBreaking: true,
                );
                array_push($breaking, ...$endpointBreaking);
            } elseif ($this->hasNonBreakingChanges($oldOp, $newOp)) {
                $changes[] = new Change(
                    type: 'modified',
                    path: $oldOp['path'],
                    method: $oldOp['method'],
                    description: "Endpoint modified: {$oldOp['method']} {$oldOp['path']}",
                    isBreaking: false,
                );
            }
        }

        $added = count(array_filter($changes, fn (Change $c) => $c->type === 'added'));
        $removed = count(array_filter($changes, fn (Change $c) => $c->type === 'removed'));
        $modified = count(array_filter($changes, fn (Change $c) => $c->type === 'modified'));

        return new DiffResult(
            oldVersion: $oldVersion,
            newVersion: $newVersion,
            changes: $changes,
            breaking: $breaking,
            summary: new DiffSummary(
                addedEndpoints: $added,
                removedEndpoints: $removed,
                modifiedEndpoints: $modified,
                breakingChanges: count($breaking),
            ),
        );
    }

    /**
     * Extract a flat map of "METHOD /path" => operation data from an OpenAPI spec.
     *
     * @return array<string, array{path: string, method: string, operation: array}>
     */
    private function extractEndpoints(array $spec): array
    {
        $endpoints = [];
        $paths = $spec['paths'] ?? [];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                // Skip non-HTTP-method keys like "parameters", "summary", etc.
                if (!in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'])) {
                    continue;
                }

                $key = strtoupper($method) . ' ' . $path;
                $endpoints[$key] = [
                    'path' => $path,
                    'method' => strtoupper($method),
                    'operation' => is_array($operation) ? $operation : [],
                ];
            }
        }

        return $endpoints;
    }

    /**
     * Detect breaking changes between old and new versions of the same endpoint.
     *
     * @return BreakingChange[]
     */
    private function detectBreakingChanges(array $oldOp, array $newOp): array
    {
        $breaking = [];
        $path = $oldOp['path'];
        $method = $oldOp['method'];
        $oldOperation = $oldOp['operation'];
        $newOperation = $newOp['operation'];

        // Check for new required parameters
        $oldRequiredParams = $this->getRequiredParameters($oldOperation);
        $newRequiredParams = $this->getRequiredParameters($newOperation);

        $addedRequiredParams = array_diff($newRequiredParams, $oldRequiredParams);
        foreach ($addedRequiredParams as $param) {
            $breaking[] = new BreakingChange(
                path: $path,
                method: $method,
                reason: "New required parameter added: {$param}",
                migration: "Add the required parameter '{$param}' to all requests to {$method} {$path}.",
            );
        }

        // Check for new required request body fields
        $oldRequiredFields = $this->getRequiredBodyFields($oldOperation);
        $newRequiredFields = $this->getRequiredBodyFields($newOperation);

        $addedRequiredFields = array_diff($newRequiredFields, $oldRequiredFields);
        foreach ($addedRequiredFields as $field) {
            $breaking[] = new BreakingChange(
                path: $path,
                method: $method,
                reason: "New required request body field added: {$field}",
                migration: "Add the required field '{$field}' to the request body for {$method} {$path}.",
            );
        }

        // Check for removed response codes
        $oldResponseCodes = array_keys($oldOperation['responses'] ?? []);
        $newResponseCodes = array_keys($newOperation['responses'] ?? []);

        // Normalize to strings for comparison
        $oldResponseCodes = array_map('strval', $oldResponseCodes);
        $newResponseCodes = array_map('strval', $newResponseCodes);

        $removedCodes = array_diff($oldResponseCodes, $newResponseCodes);
        foreach ($removedCodes as $code) {
            $breaking[] = new BreakingChange(
                path: $path,
                method: $method,
                reason: "Response code removed: {$code}",
                migration: "Update client code that handles response code {$code} from {$method} {$path}.",
            );
        }

        return $breaking;
    }

    /**
     * Get names of required parameters from an operation.
     *
     * @return string[]
     */
    private function getRequiredParameters(array $operation): array
    {
        $required = [];
        foreach ($operation['parameters'] ?? [] as $param) {
            if (!empty($param['required'])) {
                $required[] = $param['name'] ?? '';
            }
        }
        return $required;
    }

    /**
     * Get names of required request body fields from an operation.
     *
     * @return string[]
     */
    private function getRequiredBodyFields(array $operation): array
    {
        $requestBody = $operation['requestBody'] ?? [];
        $content = $requestBody['content'] ?? [];

        foreach ($content as $mediaType => $mediaData) {
            $schema = $mediaData['schema'] ?? [];
            return $schema['required'] ?? [];
        }

        return [];
    }

    /**
     * Check if there are any non-breaking changes between two operations.
     */
    private function hasNonBreakingChanges(array $oldOp, array $newOp): bool
    {
        return $oldOp['operation'] !== $newOp['operation'];
    }

    /**
     * Read and decode a JSON spec file.
     *
     * @throws InvalidArgumentException
     */
    private function readSpecFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Spec file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new InvalidArgumentException("Unable to read spec file: {$path}");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException("Invalid JSON in spec file: {$path}");
        }

        return $data;
    }
}
