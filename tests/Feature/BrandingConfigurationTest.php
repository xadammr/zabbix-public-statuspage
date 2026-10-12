<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class BrandingConfigurationTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_public_page_uses_generic_branding_by_default(): void
    {
        config()->set('statuspage.name', 'Service Status');
        config()->set('statuspage.title', 'Service Status');
        config()->set('statuspage.org_name', null);
        config()->set('statuspage.theme_color', '#1a1a1a');
        config()->set('statuspage.favicon_url', null);
        config()->set('statuspage.wallboard_icon_url', null);
        config()->set('statuspage.custom_css_urls', []);

        $this->withoutVite();
        $this->mockStatusPage();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Service Status</title>', false);
        $response->assertSee('<h1>Service Status</h1>', false);
        $response->assertSee(date('Y').'. All rights reserved.');
        $response->assertDontSee('<link rel="icon"', false);
        $response->assertDontSee('<link rel="stylesheet"', false);
        $response->assertDontSee('SPD', false);
        $response->assertDontSee('spd.ltd', false);
    }

    public function test_the_public_page_uses_configured_branding(): void
    {
        config()->set('statuspage.name', 'Example Status');
        config()->set('statuspage.title', 'Example - Service Status');
        config()->set('statuspage.org_name', 'Example Company');
        config()->set('statuspage.theme_color', '#123456');
        config()->set('statuspage.favicon_url', 'https://status.example.com/favicon.svg');
        config()->set('statuspage.custom_css_urls', [
            'https://cdn.example.com/base.css',
            'https://cdn.example.com/brand.css',
        ]);

        $this->withoutVite();
        $this->mockStatusPage();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Example - Service Status</title>', false);
        $response->assertSee('<h1>Example Status</h1>', false);
        $response->assertSee('Example Company. All rights reserved.');
        $response->assertSee('<meta name="theme-color" content="#123456">', false);
        $response->assertSee('<link rel="icon" href="https://status.example.com/favicon.svg">', false);
        $response->assertSee('<link rel="stylesheet" href="https://cdn.example.com/base.css">', false);
        $response->assertSee('<link rel="stylesheet" href="https://cdn.example.com/brand.css">', false);
    }

    public function test_the_manifest_uses_configured_branding(): void
    {
        config()->set('statuspage.name', 'Example Status');
        config()->set('statuspage.short_name', 'Example');
        config()->set('statuspage.theme_color', '#123456');
        config()->set('statuspage.favicon_url', 'https://status.example.com/favicon.svg');

        $this->get('/site.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'Example Status')
            ->assertJsonPath('short_name', 'Example')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('background_color', '#123456')
            ->assertJsonPath('icons.0.src', 'https://status.example.com/favicon.svg')
            ->assertJsonPath('icons.0.sizes', 'any');

        config()->set('statuspage.favicon_url', null);

        $this->get('/site.webmanifest')
            ->assertOk()
            ->assertJsonMissingPath('icons');
    }

    private function mockStatusPage(): void
    {
        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });
    }
}
