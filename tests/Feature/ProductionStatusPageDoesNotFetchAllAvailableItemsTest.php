<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionStatusPageDoesNotFetchAllAvailableItemsTest extends TestCase
{
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
                        'tags' => [
                            ['tag' => 'statuspage', 'value' => 'public'],
                        ],
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

        $statusPage = (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $this->assertSame([], $statusPage['services'][0]['available_items']);
        $this->assertSame('https://example.com', $statusPage['services'][0]['public_url']);
        $this->assertSame('Example metric', $statusPage['services'][0]['public_metrics'][0]['name']);
        $this->assertFalse(collect($zabbix->calls)->contains(
            fn (array $call) => $call['method'] === 'item.get' && ! isset($call['params']['filter'])
        ));
    }
}
