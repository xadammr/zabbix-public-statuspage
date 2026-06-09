<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LatencyHistoryFetchIsScopedTest extends TestCase
{
    public function test_latency_history_is_only_fetched_for_latency_sections_with_sixty_samples(): void
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

                return [];
            }
        };

        (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $historyCalls = collect($zabbix->calls)->where('method', 'history.get')->values();

        $this->assertCount(1, $historyCalls);
        $this->assertSame(['1'], $historyCalls[0]['params']['hostids']);
        $this->assertSame(60, $historyCalls[0]['params']['limit']);
    }
}
