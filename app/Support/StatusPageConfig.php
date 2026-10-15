<?php

namespace App\Support;

class StatusPageConfig
{
    public static function sections(?string $json, array $defaults): array
    {
        if (! $json) {
            return $defaults;
        }

        $sections = json_decode($json, true);

        if (! is_array($sections) || array_is_list($sections)) {
            return $defaults;
        }

        $sections = collect($sections)
            ->mapWithKeys(function (mixed $title, string|int $key): array {
                if (! is_string($key)
                    || ! preg_match('/^[A-Za-z0-9_-]+$/', $key)
                    || ! is_string($title)
                    || trim($title) === '') {
                    return [];
                }

                return [$key => trim($title)];
            })
            ->all();

        return $sections !== [] ? $sections : $defaults;
    }

    public static function sectionList(?string $sections, array $defaults = []): array
    {
        if ($sections === null) {
            return $defaults;
        }

        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $sections)),
        )));
    }

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
