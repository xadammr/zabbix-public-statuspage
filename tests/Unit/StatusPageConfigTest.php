<?php

namespace Tests\Unit;

use App\Support\StatusPageConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusPageConfigTest extends TestCase
{
    public function test_sections_are_loaded_from_json_in_their_defined_order(): void
    {
        $sections = StatusPageConfig::sections(json_encode([
            'databases' => ' Databases ',
            'customer-portals' => 'Customer Portals',
        ]), []);

        $this->assertSame([
            'databases' => 'Databases',
            'customer-portals' => 'Customer Portals',
        ], $sections);
    }

    public function test_invalid_sections_json_falls_back_to_defaults(): void
    {
        $defaults = ['public' => 'External Services'];

        $this->assertSame($defaults, StatusPageConfig::sections('not-json', $defaults));
        $this->assertSame($defaults, StatusPageConfig::sections('{"bad key":"Invalid"}', $defaults));
    }

    public function test_section_lists_are_loaded_from_comma_separated_values(): void
    {
        $this->assertSame(
            ['public', 'databases'],
            StatusPageConfig::sectionList(' public, databases,public, '),
        );
    }

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
