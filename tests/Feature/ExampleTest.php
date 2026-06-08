<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use App\Services\StatusPageBuilder;
use App\Services\ZabbixClient;
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
        $response->assertSee('href="https://example.com"', false);
        $response->assertSee('123 ms');
        $response->assertSee('example.item');
        $response->assertSee('1.23');
        $response->assertSee('High');
        $response->assertSee('data-has-active-triggers', false);
        $response->assertSee('data-page-refresh-progress', false);
        $response->assertSeeInOrder(['Example trigger', 'Response time']);
        $response->assertDontSee('Next pull in');
        $response->assertDontSee('data-domain=', false);
    }

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

    public function test_the_application_displays_updated_time_in_the_configured_timezone(): void
    {
        $this->withoutVite();
        Config::set('app.timezone', 'Australia/Brisbane');

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['generated_at'] = Carbon::parse('2026-06-07 07:32:47', 'UTC');

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('title="2026-06-07 17:32:47"', false)
            ->assertDontSee('07:32:47');
    }

    public function test_the_application_displays_human_friendly_updated_age(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-06-07 07:37:47', 'UTC'));

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['generated_at'] = Carbon::parse('2026-06-07 07:32:47', 'UTC');

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        try {
            $this->get('/')
                ->assertStatus(200)
                ->assertSee('Last updated')
                ->assertSee('five minutes ago');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_application_keeps_two_digit_updated_ages_numeric(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-06-07 07:42:47', 'UTC'));

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['generated_at'] = Carbon::parse('2026-06-07 07:32:47', 'UTC');

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        try {
            $this->get('/')
                ->assertStatus(200)
                ->assertSee('10 minutes ago')
                ->assertDontSee('ten minutes ago');
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_production_status_page_does_not_fetch_all_available_items(): void
    {
        Config::set('app.env', 'production');
        Config::set('zabbix.statuspage_sections', [
            'public' => [
                'title' => 'Public services',
                'description' => 'Customer-facing services monitored by Zabbix.',
            ],
        ]);

        $zabbix = new class extends ZabbixClient
        {
            public array $calls = [];

            public function request(string $method, array $params = []): array
            {
                $this->calls[] = compact('method', 'params');

                if ($method === 'host.get') {
                    return [[
                        'hostid' => '1',
                        'host' => 'public-example',
                        'name' => 'Example',
                        'description' => '',
                    ]];
                }

                if ($method === 'trigger.get' || $method === 'history.get') {
                    return [];
                }

                if ($method === 'usermacro.get') {
                    return [
                        [
                            'hostid' => '1',
                            'macro' => '{$PUBLIC_METRICS}',
                            'value' => 'example.item',
                        ],
                        [
                            'hostid' => '1',
                            'macro' => '{$PUBLIC_METRIC_MAP}',
                            'value' => 'Example metric',
                        ],
                        [
                            'hostid' => '1',
                            'macro' => '{$PUBLIC_URL}',
                            'value' => 'https://example.com',
                        ],
                    ];
                }

                if ($method === 'item.get' && ($params['filter']['key_'] ?? null) === ['example.item']) {
                    return [[
                        'itemid' => '1',
                        'hostid' => '1',
                        'name' => 'Example item',
                        'key_' => 'example.item',
                        'lastvalue' => '1.23456',
                        'lastclock' => Carbon::parse('2026-06-07 07:32:47')->timestamp,
                        'status' => '0',
                        'state' => '0',
                        'value_type' => '0',
                        'units' => '',
                    ]];
                }

                if ($method === 'item.get') {
                    return [];
                }

                return [];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix))->build();

        $this->assertSame([], $statusPage['services'][0]['available_items']);
        $this->assertSame('https://example.com', $statusPage['services'][0]['public_url']);
        $this->assertSame('Example metric', $statusPage['services'][0]['public_metrics'][0]['name']);
        $this->assertFalse(collect($zabbix->calls)->contains(
            fn (array $call) => $call['method'] === 'item.get' && ! isset($call['params']['filter'])
        ));
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

    private function statusPagePayloadWithPrivateSection(): array
    {
        $payload = $this->statusPagePayload();
        $internalService = [
            ...$this->servicePayload(),
            'hostid' => '2',
            'host' => 'internal-example',
            'name' => 'Internal Example',
            'section' => 'internal',
            'severity' => [
                'label' => 'OK',
                'class' => 'ok',
            ],
            'triggers' => [],
        ];

        $payload['services'][] = $internalService;
        $payload['sections'][] = [
            'key' => 'internal',
            'title' => 'Internal Services',
            'description' => 'Internal services monitored by Zabbix.',
            'services' => [$internalService],
        ];
        $payload['summary']['total'] = 2;
        $payload['summary']['ok'] = 1;

        return $payload;
    }

    private function servicePayload(): array
    {
        return [
            'hostid' => '1',
            'host' => 'public-example',
            'name' => 'Example',
            'description' => '',
            'public_url' => 'https://example.com',
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
