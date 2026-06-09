<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LatencyHistoryFetchIsScopedTest extends TestCase
{
    public function test_latency_history_is_only_fetched_for_macro_configured_latency_items_with_sixty_samples(): void
    {
        Config::set('zabbix.latency_sections', ['public']);
        Config::set('zabbix.statuspage_sections', [
            'public' => [
                'title' => 'Public services',
                'description' => 'Customer-facing services monitored by Zabbix.',
            ],
            'infrastructure' => [
                'title' => 'Infrastructure',
                'description' => 'Supporting services and infrastructure dependencies.',
            ],
        ]);

        $zabbix = new class extends ZabbixClient
        {
            public array $calls = [];

            public function request(string $method, array $params = []): array
            {
                $this->calls[] = compact('method', 'params');

                if ($method === 'host.get') {
                    return match ($params['tags'][0]['value'] ?? null) {
                        'public' => [[
                            'hostid' => '1',
                            'host' => 'public-example',
                            'name' => 'Public Example',
                            'description' => '',
                        ]],
                        'infrastructure' => [[
                            'hostid' => '2',
                            'host' => 'infra-example',
                            'name' => 'Infrastructure Example',
                            'description' => '',
                        ]],
                        default => [],
                    };
                }

                if ($method === 'usermacro.get') {
                    return [
                        [
                            'hostid' => '1',
                            'macro' => '{$PUBLIC_LATENCY_ITEM_KEY}',
                            'value' => 'web.test.time[Public HTTP Check,Access Website,resp]',
                        ],
                    ];
                }

                if ($method === 'item.get' && ($params['filter']['key_'] ?? null) === ['web.test.time[Public HTTP Check,Access Website,resp]']) {
                    return [[
                        'itemid' => 'latency-public',
                        'hostid' => '1',
                        'name' => 'Response time for step "Access Website" of scenario "Public HTTP Check".',
                        'key_' => 'web.test.time[Public HTTP Check,Access Website,resp]',
                        'lastvalue' => '0.123',
                        'lastclock' => now()->timestamp,
                        'status' => '0',
                        'state' => '0',
                    ]];
                }

                return [];
            }
        };

        (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $historyCalls = collect($zabbix->calls)->where('method', 'history.get')->values();
        $latencyItemCall = collect($zabbix->calls)
            ->where('method', 'item.get')
            ->first(fn (array $call) => $call['params']['webitems'] ?? false);

        $this->assertCount(1, $historyCalls);
        $this->assertSame(['1'], $latencyItemCall['params']['hostids']);
        $this->assertSame(['web.test.time[Public HTTP Check,Access Website,resp]'], $latencyItemCall['params']['filter']['key_']);
        $this->assertTrue($latencyItemCall['params']['webitems']);
        $this->assertSame(['latency-public'], $historyCalls[0]['params']['itemids']);
        $this->assertArrayNotHasKey('hostids', $historyCalls[0]['params']);
        $this->assertSame(60, $historyCalls[0]['params']['limit']);
    }

    public function test_latency_is_not_fetched_without_latency_item_key_macro(): void
    {
        Config::set('zabbix.latency_sections', ['public']);
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
                        'name' => 'Public Example',
                        'description' => '',
                    ]];
                }

                return [];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $this->assertNull($statusPage['services'][0]['latency']);
        $this->assertFalse(collect($zabbix->calls)->contains(
            fn (array $call) => $call['method'] === 'item.get' && ($call['params']['webitems'] ?? false)
        ));
        $this->assertFalse(collect($zabbix->calls)->contains(
            fn (array $call) => $call['method'] === 'history.get'
        ));
    }
}
