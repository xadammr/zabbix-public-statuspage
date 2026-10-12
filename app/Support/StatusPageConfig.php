<?php

namespace App\Support;

class StatusPageConfig
{
    public static function assetUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $isRootRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $isHttpUrl = filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);

        return $isRootRelative || $isHttpUrl ? $url : null;
    }

    public static function stylesheetUrls(?string $urls): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $urls ?? '')),
            fn (string $url): bool => (bool) filter_var($url, FILTER_VALIDATE_URL)
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true),
        )));
    }
}
