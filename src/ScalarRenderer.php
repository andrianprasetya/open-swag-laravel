<?php

namespace OpenSwag\Laravel;

use Illuminate\Support\Facades\View;

class ScalarRenderer
{
    private const VALID_THEMES = ['purple', 'blue', 'green', 'light'];
    private const VALID_LAYOUTS = ['modern', 'classic'];
    private const DEFAULT_THEME = 'purple';
    private const DEFAULT_LAYOUT = 'modern';

    public function __construct(
        private readonly array $uiConfig,
    ) {
    }

    public function render(string $specUrl, string $title): string
    {
        $theme = $this->resolveTheme();
        $layout = $this->resolveLayout();
        $darkMode = (bool) ($this->uiConfig['dark_mode'] ?? true);
        $showSidebar = (bool) ($this->uiConfig['show_sidebar'] ?? true);
        $sidebarSearch = (bool) ($this->uiConfig['sidebar_search'] ?? true);
        $tagGrouping = (bool) ($this->uiConfig['tag_grouping'] ?? true);
        $collapsibleSchemas = (bool) ($this->uiConfig['collapsible_schemas'] ?? true);
        $customCss = (string) ($this->uiConfig['custom_css'] ?? '');

        $configuration = [
            'theme' => $theme,
            'darkMode' => $darkMode,
            'layout' => $layout,
            'showSidebar' => $showSidebar,
            'searchHotKey' => $sidebarSearch ? 'k' : '',
            'hiddenClients' => [],
            'defaultOpenAllTags' => $tagGrouping,
            'withDefaultFonts' => true,
        ];

        return View::make('openswag::scalar', [
            'title' => $title,
            'specUrl' => $specUrl,
            'configuration' => $configuration,
            'customCss' => $customCss,
        ])->render();
    }

    private function resolveTheme(): string
    {
        $theme = $this->uiConfig['theme'] ?? self::DEFAULT_THEME;

        if (!in_array($theme, self::VALID_THEMES, true)) {
            return self::DEFAULT_THEME;
        }

        return $theme;
    }

    private function resolveLayout(): string
    {
        $layout = $this->uiConfig['layout'] ?? self::DEFAULT_LAYOUT;

        if (!in_array($layout, self::VALID_LAYOUTS, true)) {
            return self::DEFAULT_LAYOUT;
        }

        return $layout;
    }
}
