<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class PrivateSectionsAreVisibleForAllowlistedVisitorsTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_private_sections_are_visible_for_allowlisted_visitors(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_sections', ['internal']);
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayloadWithPrivateSection());
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/')
            ->assertStatus(200)
            ->assertSee('Public services')
            ->assertSee('aria-label="Request IP address"', false)
            ->assertSee('aria-label="Real IP header"', false)
            ->assertSee('aria-label="Shown sections"', false)
            ->assertSee('aria-label="Hidden sections"', false)
            ->assertSee('203.0.113.10')
            ->assertSee('unknown')
            ->assertSee('public, internal')
            ->assertSee('none')
            ->assertSee('Internal Services')
            ->assertSee('Internal Example');
    }
}
