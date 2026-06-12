<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class StaleCacheWarningRendersTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_application_warns_when_cached_data_is_stale(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['cache']['refreshed_at'] = now()->subMinutes(3);
            $payload['cache']['is_stale'] = true;
            $payload['cache']['age_seconds'] = 180;

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('No new data available.')
            ->assertSee('The status page is currently showing stale data');
    }

    public function test_stale_status_page_renders_all_service_cards_as_disaster(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayloadWithPrivateSection();
            $payload['sections'][0]['services'][0]['severity'] = [
                'label' => 'High',
                'class' => 'high',
            ];
            $payload['sections'][1]['services'][0]['severity'] = [
                'label' => 'OK',
                'class' => 'ok',
            ];
            $payload['cache']['refreshed_at'] = now()->subMinutes(3);
            $payload['cache']['is_stale'] = true;
            $payload['cache']['age_seconds'] = 180;

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $response = $this->get('/')
            ->assertStatus(200)
            ->assertSee('The status page is currently showing stale data')
            ->assertSee('severity-disaster', false)
            ->assertSee('state disaster', false)
            ->assertSee('Disaster');

        $this->assertSame(2, substr_count($response->getContent(), 'severity-disaster'));
        $this->assertStringNotContainsString('severity-ok', $response->getContent());
        $this->assertStringNotContainsString('state ok', $response->getContent());
    }

    public function test_stale_status_page_renders_summary_as_disaster(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayloadWithPrivateSection();
            $payload['summary'] = [
                'total' => 2,
                'ok' => 1,
                'problem' => 1,
                'highest' => [
                    'label' => 'High',
                    'class' => 'high',
                ],
                'severity_counts' => [
                    [
                        'label' => 'High',
                        'class' => 'high',
                        'count' => 1,
                    ],
                ],
            ];
            $payload['cache']['refreshed_at'] = now()->subMinutes(3);
            $payload['cache']['is_stale'] = true;
            $payload['cache']['age_seconds'] = 180;

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $content = $this->get('/')
            ->assertStatus(200)
            ->assertSee('Alert Level: Disaster')
            ->assertSee('<section class="summary disaster">', false)
            ->assertSee('<div class="badge disaster">', false)
            ->assertSee('<span class="count">2</span>', false)
            ->getContent();

        $this->assertStringNotContainsString('Alert Level: High', $content);
        $this->assertStringNotContainsString('<div class="badge normal">', $content);
        $this->assertStringNotContainsString('<div class="badge high">', $content);
    }

    public function test_stale_status_page_can_keep_last_known_statuses_when_disaster_override_is_disabled(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_stale_forces_disaster', false);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayloadWithPrivateSection();
            $payload['cache']['refreshed_at'] = now()->subMinutes(3);
            $payload['cache']['is_stale'] = true;
            $payload['cache']['age_seconds'] = 180;

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $content = $this->get('/')
            ->assertStatus(200)
            ->assertSee('The status page is currently showing stale data')
            ->assertSee('Alert Level: High')
            ->assertSee('<section class="summary high">', false)
            ->assertSee('<div class="badge normal">', false)
            ->assertSee('severity-high', false)
            ->assertSee('severity-ok', false)
            ->getContent();

        $this->assertStringNotContainsString('Alert Level: Disaster', $content);
        $this->assertStringNotContainsString('<section class="summary disaster">', $content);
    }
}
