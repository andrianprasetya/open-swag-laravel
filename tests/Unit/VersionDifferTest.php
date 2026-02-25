<?php

use OpenSwag\Laravel\VersionDiffer;
use OpenSwag\Laravel\Models\DiffResult;

// --- compare() basic detection ---

it('detects added endpoints', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
            ],
            '/orders' => [
                'get' => ['summary' => 'List orders', 'responses' => ['200' => ['description' => 'OK']]],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result)->toBeInstanceOf(DiffResult::class);
    expect($result->oldVersion)->toBe('1.0.0');
    expect($result->newVersion)->toBe('2.0.0');
    expect($result->summary->addedEndpoints)->toBe(1);
    expect($result->summary->removedEndpoints)->toBe(0);

    $added = array_filter($result->changes, fn ($c) => $c->type === 'added');
    expect($added)->toHaveCount(1);
    $addedChange = array_values($added)[0];
    expect($addedChange->path)->toBe('/orders');
    expect($addedChange->method)->toBe('GET');
    expect($addedChange->isBreaking)->toBeFalse();
});

it('detects removed endpoints as breaking changes', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create user', 'responses' => ['201' => ['description' => 'Created']]],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->summary->removedEndpoints)->toBe(1);
    expect($result->hasBreakingChanges())->toBeTrue();
    expect($result->breaking)->toHaveCount(1);
    expect($result->breaking[0]->path)->toBe('/users');
    expect($result->breaking[0]->method)->toBe('POST');
    expect($result->breaking[0]->reason)->toContain('removed');
    expect($result->breaking[0]->migration)->not->toBeEmpty();
});

it('detects new required parameters as breaking changes', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List users',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'required' => false],
                    ],
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List users',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'required' => false],
                        ['name' => 'tenant_id', 'in' => 'query', 'required' => true],
                    ],
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->hasBreakingChanges())->toBeTrue();
    expect($result->breaking)->toHaveCount(1);
    expect($result->breaking[0]->reason)->toContain('tenant_id');
    expect($result->summary->modifiedEndpoints)->toBe(1);
});

it('detects new required request body fields as breaking changes', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'post' => [
                    'summary' => 'Create user',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['name'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => ['201' => ['description' => 'Created']],
                ],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'post' => [
                    'summary' => 'Create user',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['name', 'email'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'email' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => ['201' => ['description' => 'Created']],
                ],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->hasBreakingChanges())->toBeTrue();
    expect($result->breaking)->toHaveCount(1);
    expect($result->breaking[0]->reason)->toContain('email');
    expect($result->summary->modifiedEndpoints)->toBe(1);
});

it('detects removed response codes as breaking changes', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List users',
                    'responses' => [
                        '200' => ['description' => 'OK'],
                        '404' => ['description' => 'Not Found'],
                    ],
                ],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List users',
                    'responses' => [
                        '200' => ['description' => 'OK'],
                    ],
                ],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->hasBreakingChanges())->toBeTrue();
    expect($result->breaking)->toHaveCount(1);
    expect($result->breaking[0]->reason)->toContain('404');
    expect($result->summary->modifiedEndpoints)->toBe(1);
});

it('detects non-breaking modifications', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List users',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List all users',
                    'description' => 'Returns a paginated list of users',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->hasBreakingChanges())->toBeFalse();
    expect($result->summary->modifiedEndpoints)->toBe(1);
    $modified = array_filter($result->changes, fn ($c) => $c->type === 'modified');
    expect($modified)->toHaveCount(1);
    expect(array_values($modified)[0]->isBreaking)->toBeFalse();
});

it('returns empty diff for identical specs', function () {
    $differ = new VersionDiffer();

    $spec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
            ],
        ],
    ];

    $result = $differ->compare($spec, $spec);

    expect($result->changes)->toBeEmpty();
    expect($result->breaking)->toBeEmpty();
    expect($result->summary->addedEndpoints)->toBe(0);
    expect($result->summary->removedEndpoints)->toBe(0);
    expect($result->summary->modifiedEndpoints)->toBe(0);
    expect($result->summary->breakingChanges)->toBe(0);
});

it('handles empty specs', function () {
    $differ = new VersionDiffer();

    $result = $differ->compare([], []);

    expect($result->changes)->toBeEmpty();
    expect($result->breaking)->toBeEmpty();
    expect($result->oldVersion)->toBe('');
    expect($result->newVersion)->toBe('');
});

it('handles multiple breaking changes on same endpoint', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'post' => [
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'required' => ['name'],
                                    'properties' => ['name' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Created'],
                        '422' => ['description' => 'Validation Error'],
                    ],
                ],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'post' => [
                    'parameters' => [
                        ['name' => 'tenant_id', 'in' => 'header', 'required' => true],
                    ],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'required' => ['name', 'email'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'email' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Created'],
                    ],
                ],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->hasBreakingChanges())->toBeTrue();
    // 3 breaking: new required param, new required body field, removed response code
    expect($result->breaking)->toHaveCount(3);
    expect($result->summary->breakingChanges)->toBe(3);
    expect($result->summary->modifiedEndpoints)->toBe(1);
});

// --- compareFiles() ---

it('compares two spec files successfully', function () {
    $differ = new VersionDiffer();

    $oldPath = tempnam(sys_get_temp_dir(), 'old_spec_');
    $newPath = tempnam(sys_get_temp_dir(), 'new_spec_');

    file_put_contents($oldPath, json_encode([
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
        ],
    ]));

    file_put_contents($newPath, json_encode([
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => ['get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]]],
            '/orders' => ['post' => ['summary' => 'Create', 'responses' => ['201' => ['description' => 'Created']]]],
        ],
    ]));

    $result = $differ->compareFiles($oldPath, $newPath);

    expect($result->summary->addedEndpoints)->toBe(1);
    expect($result->oldVersion)->toBe('1.0.0');
    expect($result->newVersion)->toBe('2.0.0');

    unlink($oldPath);
    unlink($newPath);
});

it('throws InvalidArgumentException for non-existent file', function () {
    $differ = new VersionDiffer();
    $differ->compareFiles('/nonexistent/old.json', '/nonexistent/new.json');
})->throws(InvalidArgumentException::class, 'Spec file not found');

it('throws InvalidArgumentException for invalid JSON file', function () {
    $differ = new VersionDiffer();

    $path = tempnam(sys_get_temp_dir(), 'bad_spec_');
    file_put_contents($path, 'not valid json {{{');

    try {
        $differ->compareFiles($path, $path);
    } finally {
        unlink($path);
    }
})->throws(InvalidArgumentException::class, 'Invalid JSON');

// --- Summary counts ---

it('produces correct summary counts for mixed changes', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List users', 'responses' => ['200' => ['description' => 'OK']]],
                'delete' => ['summary' => 'Delete user', 'responses' => ['204' => ['description' => 'Deleted']]],
            ],
            '/legacy' => [
                'get' => ['summary' => 'Legacy', 'responses' => ['200' => ['description' => 'OK']]],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List all users', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create user', 'responses' => ['201' => ['description' => 'Created']]],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    // Added: POST /users
    // Removed: DELETE /users, GET /legacy
    // Modified: GET /users (summary changed)
    expect($result->summary->addedEndpoints)->toBe(1);
    expect($result->summary->removedEndpoints)->toBe(2);
    expect($result->summary->modifiedEndpoints)->toBe(1);
    expect($result->summary->breakingChanges)->toBe(2); // 2 removed endpoints
});

// --- Edge cases ---

it('skips non-HTTP-method keys in paths', function () {
    $differ = new VersionDiffer();

    $oldSpec = [
        'info' => ['version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]],
                'parameters' => [['name' => 'shared', 'in' => 'query']],
            ],
        ],
    ];

    $newSpec = [
        'info' => ['version' => '2.0.0'],
        'paths' => [
            '/users' => [
                'get' => ['summary' => 'List', 'responses' => ['200' => ['description' => 'OK']]],
                'parameters' => [['name' => 'shared', 'in' => 'query']],
            ],
        ],
    ];

    $result = $differ->compare($oldSpec, $newSpec);

    expect($result->changes)->toBeEmpty();
});

it('handles specs with no paths key', function () {
    $differ = new VersionDiffer();

    $result = $differ->compare(
        ['info' => ['version' => '1.0.0']],
        ['info' => ['version' => '2.0.0']],
    );

    expect($result->changes)->toBeEmpty();
    expect($result->oldVersion)->toBe('1.0.0');
    expect($result->newVersion)->toBe('2.0.0');
});
