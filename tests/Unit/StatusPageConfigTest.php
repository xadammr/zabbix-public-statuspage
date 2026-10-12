<?php

namespace Tests\Unit;

use App\Support\StatusPageConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusPageConfigTest extends TestCase
{
    public function test_stylesheet_urls_are_trimmed_deduplicated_and_limited_to_http_schemes(): void
    {
        $urls = StatusPageConfig::stylesheetUrls(implode(',', [
            ' https://cdn.example.com/base.css ',
            'http://cdn.example.com/brand.css',
            'https://cdn.example.com/base.css',
            'javascript:alert(1)',
            '/local.css',
            '',
        ]));

        $this->assertSame([
            'https://cdn.example.com/base.css',
            'http://cdn.example.com/brand.css',
        ], $urls);
    }

    #[DataProvider('assetUrls')]
    public function test_asset_urls_are_limited_to_safe_absolute_or_root_relative_urls(?string $url, ?string $expected): void
    {
        $this->assertSame($expected, StatusPageConfig::assetUrl($url));
    }

    public static function assetUrls(): array
    {
        return [
            'empty' => [null, null],
            'root relative' => ['/vendor/statuspage/icon.png', '/vendor/statuspage/icon.png'],
            'https' => ['https://cdn.example.com/icon.png', 'https://cdn.example.com/icon.png'],
            'http' => ['http://cdn.example.com/icon.png', 'http://cdn.example.com/icon.png'],
            'protocol relative' => ['//cdn.example.com/icon.png', null],
            'javascript' => ['javascript:alert(1)', null],
        ];
    }
}
