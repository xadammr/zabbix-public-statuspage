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
        Config::set('zabbix.wallboard_show_footer', true);

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
            ->assertSee('data-status-fragment-url="/wallboard/status-fragment"', false)
            ->assertSee('data-page-reload', false)
            ->assertSee('Reload wallboard')
            ->assertSee('1 Active Alert')
            ->assertDontSee('Alert Level: High')
            ->assertSee('<details', false)
            ->assertDontSee('<details open', false)
            ->assertSee('Example')
            ->assertSee('data-service-id="1"', false)
            ->assertDontSee('data-service-id="2"', false)
            ->assertDontSee('Internal Services')
            ->assertDontSee('Alerting now')
            ->assertSee('class="section wallboard-alerts severity-high"', false)
            ->assertSee('class="wallboard-alert-problems"', false)
            ->assertSee('data-alert-started-at="', false)
            ->assertSee('data-alert-duration', false)
            ->assertSee('class="problem-row high"', false)
            ->assertDontSee('class="state high"', false)
            ->assertDontSee('class="service-link"', false)
            ->assertSee('Example trigger')
            ->assertDontSee('Pause automatic refresh')
            ->assertDontSee('Last updated:')
            ->assertSee('<footer', false)
            ->assertSee('SPD Ltd. All rights reserved.')
            ->assertSee('aria-label="Request IP address"', false)
            ->assertSee('aria-label="Last poll duration"', false)
            ->assertDontSee('aria-label="Shown sections"', false)
            ->assertDontSee('aria-label="Hidden sections"', false);
    }

    public function test_wallboard_refresh_fragment_has_the_same_access_control(): void
    {
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/wallboard/status-fragment')
            ->assertForbidden();
    }

    public function test_all_clear_wallboard_has_no_alert_cards(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);
        $payload = $this->statusPagePayload();
        $payload['summary'] = [
            'total' => 1,
            'ok' => 1,
            'problem' => 0,
            'highest' => ['label' => 'OK', 'class' => 'ok'],
            'severity_counts' => [],
        ];
        $payload['services'][0]['severity'] = ['label' => 'OK', 'class' => 'ok'];
        $payload['services'][0]['triggers'] = [];
        $payload['sections'][0]['services'][0] = $payload['services'][0];

        $this->mock(CachedStatusPage::class, function ($mock) use ($payload): void {
            $mock->shouldReceive('current')->once()->andReturn($payload);
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/wallboard')
            ->assertOk()
            ->assertSee('Everything is OK.')
            ->assertSee('class="summary ok"', false)
            ->assertSee('src="/images/spd-logo.png"', false)
            ->assertSee('<span class="count">1</span>', false)
            ->assertSee('<span class="label">Host Monitored</span>', false)
            ->assertDontSee('<footer', false)
            ->assertDontSee('wallboard-alerts');
    }

    public function test_stale_wallboard_shows_one_warning_without_service_cards(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_ips', ['203.0.113.0/24']);
        $payload = $this->statusPagePayloadWithPrivateSection();
        $payload['cache']['refreshed_at'] = now()->subMinutes(3);
        $payload['cache']['is_stale'] = true;
        $payload['cache']['age_seconds'] = 180;

        $this->mock(CachedStatusPage::class, function ($mock) use ($payload): void {
            $mock->shouldReceive('current')->once()->andReturn($payload);
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/wallboard')
            ->assertOk()
            ->assertSee('class="stale-cache-warning wallboard-stale"', false)
            ->assertSee('No new data available.')
            ->assertSee('The status page is currently showing stale data')
            ->assertDontSee('class="summary', false)
            ->assertDontSee('<details', false)
            ->assertDontSee('wallboard-alerts');
    }
}
