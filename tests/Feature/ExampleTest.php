<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use App\Services\StatusPageBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Service Status');
        $response->assertSee('Alert Level: High');
        $response->assertSee('Public services');
        $response->assertSee('Example');
        $response->assertSee('123 ms');
        $response->assertSee('example.item');
        $response->assertSee('1.23');
        $response->assertSee('High');
        $response->assertSee('Next pull in');
    }

    public function test_status_page_poll_refreshes_the_cached_snapshot(): void
    {
        Config::set('cache.default', 'array');
        Config::set('zabbix.statuspage_cache_key', 'statuspage.test.snapshot');
        Config::set('zabbix.statuspage_poll_interval', 60);
        Cache::forget('statuspage.test.snapshot');

        $this->mock(StatusPageBuilder::class, function ($mock): void {
            $mock->shouldReceive('build')
                ->once()
                ->andReturn($this->statusPagePayload(withCache: false));
        });

        $this->artisan('statuspage:poll --force')
            ->expectsOutput('Status page snapshot refreshed.')
            ->expectsOutput('Changes:')
            ->expectsOutput(' - Initial snapshot cached with 1 services.')
            ->assertExitCode(0);

        $snapshot = app(CachedStatusPage::class)->current();

        $this->assertSame('Example', $snapshot['sections'][0]['services'][0]['name']);
        $this->assertTrue($snapshot['cache']['next_refresh_at']->greaterThan($snapshot['cache']['refreshed_at']));
    }

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

    public function test_available_items_are_hidden_in_production(): void
    {
        $this->withoutVite();
        Config::set('app.env', 'production');

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('Available items:');
    }

    private function statusPagePayload(bool $withCache = true): array
    {
        $generatedAt = Carbon::parse('2026-06-07 07:32:47');

        $payload = [
            'generated_at' => $generatedAt,
            'summary' => [
                'total' => 1,
                'ok' => 0,
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
            ],
            'services' => [
                $this->servicePayload(),
            ],
            'sections' => [
                [
                    'key' => 'public',
                    'title' => 'Public services',
                    'description' => 'Customer-facing services monitored by Zabbix.',
                    'services' => [
                        [
                            ...$this->servicePayload(),
                            'section' => 'public',
                        ],
                    ],
                ],
            ],
        ];

        if ($withCache) {
            $payload['cache'] = [
                'refreshed_at' => $generatedAt,
                'next_refresh_at' => $generatedAt->copy()->addMinute(),
                'poll_interval' => 60,
                'stale_after' => 120,
                'is_stale' => false,
                'age_seconds' => 0,
            ];
        }

        return $payload;
    }

    private function servicePayload(): array
    {
        return [
            'hostid' => '1',
            'host' => 'public-example',
            'name' => 'Example',
            'description' => '',
            'status' => 'ok',
            'severity' => [
                'label' => 'High',
                'class' => 'high',
            ],
            'triggers' => [
                [
                    'triggerid' => '1',
                    'description' => 'Example trigger',
                    'priority' => '4',
                    'priority_label' => 'High',
                    'priority_class' => 'high',
                    'status' => '0',
                    'value' => '1',
                    'hosts' => [],
                ],
            ],
            'latency' => [
                'milliseconds' => 123,
                'series' => [
                    'points' => '6,66 120,36 234,12',
                    'width' => 240,
                    'height' => 72,
                    'max_ms' => 150,
                    'min_ms' => 75,
                    'samples' => 3,
                    'bands' => [
                        [
                            'class' => 'danger',
                            'y' => 6,
                            'height' => 0,
                        ],
                        [
                            'class' => 'warning',
                            'y' => 6,
                            'height' => 40,
                        ],
                        [
                            'class' => 'ok',
                            'y' => 46,
                            'height' => 20,
                        ],
                    ],
                    'thresholds' => [
                        [
                            'value' => 200,
                            'y' => 6,
                        ],
                        [
                            'value' => 1000,
                            'y' => 6,
                        ],
                    ],
                ],
            ],
            'api_health' => null,
            'public_metrics' => [
                [
                    'itemid' => '1',
                    'name' => 'Example item',
                    'key' => 'example.item',
                    'lastvalue' => '1.23456',
                    'display_value' => '1.23',
                    'lastclock' => Carbon::parse('2026-06-07 07:32:47')->timestamp,
                    'status' => '0',
                    'state' => '0',
                    'value_type' => '0',
                    'units' => '',
                ],
            ],
            'available_items' => [
                [
                    'itemid' => '1',
                    'name' => 'Example item',
                    'key' => 'example.item',
                    'lastvalue' => '1.23456',
                    'display_value' => '1.23',
                    'lastclock' => Carbon::parse('2026-06-07 07:32:47')->timestamp,
                    'status' => '0',
                    'state' => '0',
                    'value_type' => '0',
                    'units' => '',
                ],
            ],
        ];
    }
}
