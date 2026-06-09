<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicMetricChangesAreFormattedTest extends TestCase
{
    public function test_public_metric_changes_are_formatted_from_previous_item_value(): void
    {
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

                if ($method === 'usermacro.get') {
                    return [[
                        'hostid' => '1',
                        'macro' => '{$PUBLIC_METRICS}',
                        'value' => 'example.item',
                    ]];
                }

                if ($method === 'item.get' && ($params['filter']['key_'] ?? null) === ['example.item']) {
                    return [[
                        'itemid' => 'metric-1',
                        'hostid' => '1',
                        'name' => 'Example item',
                        'key_' => 'example.item',
                        'lastvalue' => '2.5',
                        'prevvalue' => '1.5',
                        'lastclock' => Carbon::parse('2026-06-07 07:32:47')->timestamp,
                        'status' => '0',
                        'state' => '0',
                        'value_type' => '0',
                        'units' => '',
                    ]];
                }

                return [];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $change = $statusPage['services'][0]['public_metrics'][0]['change'];

        $this->assertSame('up', $change['direction']);
        $this->assertSame('1.5', $change['previous_value']);
        $this->assertSame(1.0, $change['delta']);
        $this->assertFalse(collect($zabbix->calls)->contains(
            fn (array $call) => $call['method'] === 'history.get'
        ));
    }

    public function test_public_metric_changes_are_omitted_without_previous_item_value(): void
    {
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

                if ($method === 'usermacro.get') {
                    return [[
                        'hostid' => '1',
                        'macro' => '{$PUBLIC_METRICS}',
                        'value' => 'example.item',
                    ]];
                }

                if ($method === 'item.get' && ($params['filter']['key_'] ?? null) === ['example.item']) {
                    return [[
                        'itemid' => 'metric-1',
                        'hostid' => '1',
                        'name' => 'Example item',
                        'key_' => 'example.item',
                        'lastvalue' => '2.5',
                        'prevvalue' => '',
                        'lastclock' => Carbon::parse('2026-06-07 07:32:47')->timestamp,
                        'status' => '0',
                        'state' => '0',
                        'value_type' => '0',
                        'units' => '',
                    ]];
                }

                return [];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $this->assertArrayNotHasKey('change', array_filter(
            $statusPage['services'][0]['public_metrics'][0],
            fn ($value) => $value !== null,
        ));
        $this->assertSame('2.50', $statusPage['services'][0]['public_metrics'][0]['display_value']);
        $this->assertFalse(collect($zabbix->calls)->contains(
            fn (array $call) => $call['method'] === 'history.get'
        ));
    }
}
