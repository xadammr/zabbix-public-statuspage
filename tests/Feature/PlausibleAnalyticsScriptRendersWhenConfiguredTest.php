<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class PlausibleAnalyticsScriptRendersWhenConfiguredTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_plausible_analytics_script_renders_when_configured(): void
    {
        $this->withoutVite();
        Config::set('services.plausible.domain', 'status.example.com');
        Config::set('services.plausible.script_url', 'https://plausible.example.com/js/script.js');

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('data-domain="status.example.com"', false)
            ->assertSee('src="https://plausible.example.com/js/script.js"', false);
    }
}
