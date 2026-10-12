<?php

$name = env('STATUSPAGE_NAME', 'Service Status');
$normaliseAssetUrl = function (?string $url): ?string {
    if (! $url) {
        return null;
    }

    $isRootRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
    $isHttpUrl = filter_var($url, FILTER_VALIDATE_URL)
        && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);

    return $isRootRelative || $isHttpUrl ? $url : null;
};

return [
    'name' => $name,
    'short_name' => env('STATUSPAGE_SHORT_NAME', $name),
    'title' => env('STATUSPAGE_TITLE', $name),
    'wallboard_title' => env('STATUSPAGE_WALLBOARD_TITLE', $name.' - Operations Wallboard'),
    'footer_text' => env('STATUSPAGE_FOOTER_TEXT', $name),
    'theme_color' => env('STATUSPAGE_THEME_COLOR', '#1a1a1a'),
    'favicon_url' => $normaliseAssetUrl(env('STATUSPAGE_FAVICON_URL')),
    'wallboard_icon_url' => $normaliseAssetUrl(env('STATUSPAGE_WALLBOARD_ICON_URL')),
];
