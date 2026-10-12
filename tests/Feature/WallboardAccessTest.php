<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class WallboardAccessTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_wallboard_is_forbidden_outside_the_trusted_subnets(): void
    {
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/wallboard')
            ->assertForbidden();
    }

    public function test_trusted_visitors_can_see_the_dense_wallboard_and_private_services(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayloadWithPrivateSection());
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/wallboard')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertDontSee('Operations Wallboard')
            ->assertSee('Internal Example')
            ->assertSee('data-status-fragment-url="/wallboard/status-fragment"', false)
            ->assertSee('data-page-reload', false)
            ->assertSee('Reload wallboard')
            ->assertSee('Services need attention')
            ->assertSee('class="summary-severity-pill high"', false)
            ->assertDontSee('Alert Level: High')
            ->assertSee('<details', false)
            ->assertDontSee('<details open', false)
            ->assertSeeInOrder(['Example', 'Internal Services', 'Internal Example'])
            ->assertDontSee('Alerting now')
            ->assertSee('class="section wallboard-alerts severity-high"', false)
            ->assertSee('class="wallboard-alert-problems"', false)
            ->assertSee('class="problem-row high"', false)
            ->assertDontSee('class="state high"', false)
            ->assertDontSee('class="service-link"', false)
            ->assertSee('Example trigger')
            ->assertDontSee('Pause automatic refresh')
            ->assertDontSee('Last updated:')
            ->assertDontSee('<footer', false);
    }

    public function test_wallboard_refresh_fragment_has_the_same_access_control(): void
    {
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/wallboard/status-fragment')
            ->assertForbidden();
    }
}
