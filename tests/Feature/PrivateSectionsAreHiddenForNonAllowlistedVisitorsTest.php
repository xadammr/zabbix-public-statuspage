<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class PrivateSectionsAreHiddenForNonAllowlistedVisitorsTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_private_sections_are_hidden_for_non_allowlisted_visitors(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_sections', ['internal']);
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.10']);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayloadWithPrivateSection());
        });

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->withHeader('X-Real-IP', '100.64.0.42')
            ->get('/')
            ->assertStatus(200)
            ->assertSee('Public services')
            ->assertSee('aria-label="Request IP address"', false)
            ->assertSee('aria-label="Real IP header"', false)
            ->assertSee('aria-label="Shown sections"', false)
            ->assertSee('aria-label="Hidden sections"', false)
            ->assertSee('198.51.100.25')
            ->assertSee('100.64.0.42')
            ->assertSee('public')
            ->assertSee('internal')
            ->assertDontSee('Internal Services')
            ->assertDontSee('Internal Example');
    }
}
