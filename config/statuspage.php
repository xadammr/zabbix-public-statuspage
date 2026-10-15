<?php

use App\Support\StatusPageConfig;

$name = env('STATUSPAGE_NAME', 'Service Status');

return [
    'name' => $name,
    'short_name' => env('STATUSPAGE_SHORT_NAME', $name),
    'title' => env('STATUSPAGE_TITLE', $name),
    'wallboard_title' => env('STATUSPAGE_WALLBOARD_TITLE', $name.' - Operations Wallboard'),
    'org_name' => env('STATUSPAGE_ORG_NAME'),
    'theme_color' => env('STATUSPAGE_THEME_COLOR', '#1a1a1a'),
    'favicon_url' => StatusPageConfig::assetUrl(env('STATUSPAGE_FAVICON_URL')),
    'wallboard_icon_url' => StatusPageConfig::assetUrl(env('STATUSPAGE_WALLBOARD_ICON_URL')),
    'custom_css_urls' => StatusPageConfig::stylesheetUrls(env('STATUSPAGE_CUSTOM_CSS_URLS')),
    'use_custom_css_in_wallboard' => env('STATUSPAGE_USE_CUSTOM_CSS_IN_WALLBOARD', false),
];
