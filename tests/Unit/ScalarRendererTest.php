<?php

use OpenSwag\Laravel\ScalarRenderer;

it('renders HTML with default config values', function () {
    $renderer = new ScalarRenderer([]);

    $html = $renderer->render('/api/docs/json', 'My API Docs');

    expect($html)
        ->toContain('<title>My API Docs</title>')
        ->toContain('data-url="/api/docs/json"')
        ->toContain('https://cdn.jsdelivr.net/npm/@scalar/api-reference');
});

it('includes the configured theme in the Scalar configuration', function () {
    $renderer = new ScalarRenderer(['theme' => 'blue']);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['theme'])->toBe('blue');
});

it('falls back to purple for invalid theme', function () {
    $renderer = new ScalarRenderer(['theme' => 'invalid-theme']);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['theme'])->toBe('purple');
});

it('applies dark mode setting', function () {
    $renderer = new ScalarRenderer(['dark_mode' => false]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['darkMode'])->toBeFalse();
});

it('defaults dark mode to true', function () {
    $renderer = new ScalarRenderer([]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['darkMode'])->toBeTrue();
});

it('applies layout setting', function () {
    $renderer = new ScalarRenderer(['layout' => 'classic']);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['layout'])->toBe('classic');
});

it('falls back to modern for invalid layout', function () {
    $renderer = new ScalarRenderer(['layout' => 'futuristic']);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['layout'])->toBe('modern');
});

it('applies show_sidebar setting', function () {
    $renderer = new ScalarRenderer(['show_sidebar' => false]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['showSidebar'])->toBeFalse();
});

it('applies sidebar_search setting', function () {
    $renderer = new ScalarRenderer(['sidebar_search' => false]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['searchHotKey'])->toBe('');
});

it('enables search hotkey when sidebar_search is true', function () {
    $renderer = new ScalarRenderer(['sidebar_search' => true]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['searchHotKey'])->toBe('k');
});

it('applies tag_grouping setting', function () {
    $renderer = new ScalarRenderer(['tag_grouping' => false]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['defaultOpenAllTags'])->toBeFalse();
});

it('injects custom CSS into a style tag', function () {
    $renderer = new ScalarRenderer(['custom_css' => 'body { background: red; }']);

    $html = $renderer->render('/api/docs/json', 'API');

    expect($html)->toContain('<style>')
        ->toContain('body { background: red; }')
        ->toContain('</style>');
});

it('does not include style tag when custom_css is empty', function () {
    $renderer = new ScalarRenderer(['custom_css' => '']);

    $html = $renderer->render('/api/docs/json', 'API');

    expect($html)->not->toContain('<style>');
});

it('renders valid HTML structure', function () {
    $renderer = new ScalarRenderer([
        'theme' => 'green',
        'dark_mode' => true,
        'layout' => 'modern',
        'show_sidebar' => true,
        'custom_css' => '.test { color: blue; }',
    ]);

    $html = $renderer->render('/api/spec.json', 'Test API');

    expect($html)
        ->toContain('<!DOCTYPE html>')
        ->toContain('<html lang="en">')
        ->toContain('<meta charset="UTF-8">')
        ->toContain('<title>Test API</title>')
        ->toContain('data-url="/api/spec.json"')
        ->toContain('id="api-reference"')
        ->toContain('.test { color: blue; }');
});

it('supports all valid themes', function (string $theme) {
    $renderer = new ScalarRenderer(['theme' => $theme]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['theme'])->toBe($theme);
})->with(['purple', 'blue', 'green', 'light']);

it('supports all valid layouts', function (string $layout) {
    $renderer = new ScalarRenderer(['layout' => $layout]);

    $html = $renderer->render('/api/docs/json', 'API');

    $config = extractScalarConfig($html);
    expect($config['layout'])->toBe($layout);
})->with(['modern', 'classic']);

/**
 * Extract the Scalar configuration object from the rendered HTML.
 */
function extractScalarConfig(string $html): array
{
    preg_match('/data-configuration="([^"]*)"/', $html, $matches);

    expect($matches)->toHaveCount(2);

    return json_decode(html_entity_decode($matches[1]), true);
}
