<?php

return [
    'info' => [
        'title' => env('OPENSWAG_TITLE', 'API Documentation'),
        'version' => env('OPENSWAG_VERSION', '1.0.0'),
        'description' => '',
        'contact' => ['name' => '', 'url' => '', 'email' => ''],
        'license' => ['name' => '', 'url' => ''],
    ],
    'servers' => [],
    'tags' => [],
    'route' => [
        'prefix' => 'api/docs',
        'middleware' => [],
    ],
    'ui' => [
        'theme' => 'purple',
        'dark_mode' => true,
        'layout' => 'modern',
        'show_sidebar' => true,
        'sidebar_search' => true,
        'tag_grouping' => true,
        'collapsible_schemas' => true,
        'custom_css' => '',
    ],
    'docs_auth' => [
        'enabled' => false,
        'username' => '',
        'password' => '',
        'api_key' => '',
        'realm' => 'API Documentation',
    ],
    'gateway' => [
        'enabled' => false,
        'services' => [],
        'cache_ttl' => 300,
        'health_check_timeout' => 5,
    ],
    'examples' => [
        'use_factories' => true,
        'templates' => [],
    ],
];
