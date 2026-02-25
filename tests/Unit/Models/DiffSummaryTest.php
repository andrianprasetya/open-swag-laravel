<?php

use OpenSwag\Laravel\Models\DiffSummary;

test('DiffSummary has sensible defaults', function () {
    $summary = new DiffSummary();

    expect($summary->addedEndpoints)->toBe(0);
    expect($summary->removedEndpoints)->toBe(0);
    expect($summary->modifiedEndpoints)->toBe(0);
    expect($summary->breakingChanges)->toBe(0);
});

test('DiffSummary toArray serializes all properties', function () {
    $summary = new DiffSummary(
        addedEndpoints: 3,
        removedEndpoints: 1,
        modifiedEndpoints: 2,
        breakingChanges: 1,
    );

    $array = $summary->toArray();

    expect($array)->toBe([
        'addedEndpoints' => 3,
        'removedEndpoints' => 1,
        'modifiedEndpoints' => 2,
        'breakingChanges' => 1,
    ]);
});

test('DiffSummary fromArray reconstructs from array', function () {
    $data = [
        'addedEndpoints' => 5,
        'removedEndpoints' => 2,
        'modifiedEndpoints' => 4,
        'breakingChanges' => 3,
    ];

    $summary = DiffSummary::fromArray($data);

    expect($summary->addedEndpoints)->toBe(5);
    expect($summary->removedEndpoints)->toBe(2);
    expect($summary->modifiedEndpoints)->toBe(4);
    expect($summary->breakingChanges)->toBe(3);
});

test('DiffSummary toArray/fromArray round-trip preserves data', function () {
    $original = new DiffSummary(
        addedEndpoints: 10,
        removedEndpoints: 3,
        modifiedEndpoints: 7,
        breakingChanges: 2,
    );

    $restored = DiffSummary::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray());
});

test('DiffSummary fromArray handles missing keys with defaults', function () {
    $summary = DiffSummary::fromArray([]);

    expect($summary->addedEndpoints)->toBe(0);
    expect($summary->removedEndpoints)->toBe(0);
    expect($summary->modifiedEndpoints)->toBe(0);
    expect($summary->breakingChanges)->toBe(0);
});

test('DiffSummary toJson/fromJson round-trip preserves data', function () {
    $original = new DiffSummary(
        addedEndpoints: 4,
        removedEndpoints: 1,
        modifiedEndpoints: 6,
        breakingChanges: 2,
    );

    $restored = DiffSummary::fromJson($original->toJson());

    expect($restored->toArray())->toBe($original->toArray());
});
