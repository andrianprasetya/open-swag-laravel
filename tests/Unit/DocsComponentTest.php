<?php

use OpenSwag\Laravel\View\Components\DocsComponent;

it('renders with default config values when no props are passed', function () {
    $component = new DocsComponent();

    expect($component->resolvedTheme)->toBe('purple');
    expect($component->resolvedDarkMode)->toBeTrue();
    expect($component->resolvedLayout)->toBe('modern');
    expect($component->resolvedSpecUrl)->toContain('/api/docs/json');
});

it('accepts and uses custom theme prop', function () {
    $component = new DocsComponent(theme: 'blue');

    expect($component->resolvedTheme)->toBe('blue');
});

it('accepts and uses custom dark-mode prop', function () {
    $component = new DocsComponent(darkMode: false);

    expect($component->resolvedDarkMode)->toBeFalse();
});

it('accepts and uses custom layout prop', function () {
    $component = new DocsComponent(layout: 'classic');

    expect($component->resolvedLayout)->toBe('classic');
});

it('accepts and uses custom spec-url prop', function () {
    $component = new DocsComponent(specUrl: '/custom/spec.json');

    expect($component->resolvedSpecUrl)->toBe('/custom/spec.json');
});

it('falls back to config values when props are null', function () {
    config()->set('openswag.ui.theme', 'green');
    config()->set('openswag.ui.dark_mode', false);
    config()->set('openswag.ui.layout', 'classic');

    $component = new DocsComponent();

    expect($component->resolvedTheme)->toBe('green');
    expect($component->resolvedDarkMode)->toBeFalse();
    expect($component->resolvedLayout)->toBe('classic');
});

it('overrides config values when props are provided', function () {
    config()->set('openswag.ui.theme', 'green');
    config()->set('openswag.ui.dark_mode', false);
    config()->set('openswag.ui.layout', 'classic');

    $component = new DocsComponent(theme: 'blue', darkMode: true, layout: 'modern');

    expect($component->resolvedTheme)->toBe('blue');
    expect($component->resolvedDarkMode)->toBeTrue();
    expect($component->resolvedLayout)->toBe('modern');
});

it('uses configured route prefix for default spec url', function () {
    config()->set('openswag.route.prefix', 'docs/v2');

    $component = new DocsComponent();

    expect($component->resolvedSpecUrl)->toContain('/docs/v2/json');
});

it('renders the Scalar UI HTML via the view', function () {
    $component = new DocsComponent(specUrl: '/api/docs/json');

    $view = $component->render();
    $html = $view->render();

    expect($html)
        ->toContain('data-url="/api/docs/json"')
        ->toContain('scalar');
});

it('renders with overridden theme in the output HTML', function () {
    config()->set('openswag.ui.theme', 'purple');

    $component = new DocsComponent(theme: 'blue', specUrl: '/api/docs/json');

    $view = $component->render();
    $html = $view->render();

    expect($html)->toContain('&quot;theme&quot;:&quot;blue&quot;');
});
