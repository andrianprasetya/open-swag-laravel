<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use OpenSwag\Laravel\ScalarRenderer;

class DocsComponent extends Component
{
    public string $resolvedTheme;
    public bool $resolvedDarkMode;
    public string $resolvedLayout;
    public string $resolvedSpecUrl;

    public function __construct(
        public ?string $theme = null,
        public ?bool $darkMode = null,
        public ?string $layout = null,
        public ?string $specUrl = null,
    ) {
        $uiConfig = config('openswag.ui', []);

        $this->resolvedTheme = $this->theme ?? ($uiConfig['theme'] ?? 'purple');
        $this->resolvedDarkMode = $this->darkMode ?? ($uiConfig['dark_mode'] ?? true);
        $this->resolvedLayout = $this->layout ?? ($uiConfig['layout'] ?? 'modern');
        $this->resolvedSpecUrl = $this->specUrl ?? $this->defaultSpecUrl();
    }

    public function render(): View
    {
        $renderer = app(ScalarRenderer::class);

        $overriddenConfig = array_merge(config('openswag.ui', []), [
            'theme' => $this->resolvedTheme,
            'dark_mode' => $this->resolvedDarkMode,
            'layout' => $this->resolvedLayout,
        ]);

        $overriddenRenderer = new ScalarRenderer($overriddenConfig);

        $title = config('openswag.info.title', 'API Documentation');
        $renderedHtml = $overriddenRenderer->render($this->resolvedSpecUrl, $title);

        return view('openswag::components.docs', [
            'renderedHtml' => $renderedHtml,
        ]);
    }

    private function defaultSpecUrl(): string
    {
        $prefix = config('openswag.route.prefix', 'api/docs');

        return url($prefix . '/json');
    }
}
