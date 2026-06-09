<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StatusPageFetchesOnlyProblemTriggersTest extends TestCase
{
    public function test_status_page_fetches_only_problem_triggers(): void
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
                        'name' => 'Public Example',
                        'description' => '',
                        'tags' => [
                            ['tag' => 'statuspage', 'value' => 'public'],
                        ],
                    ]];
                }

                return [];
            }
        };

        (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

        $triggerCall = collect($zabbix->calls)->firstWhere('method', 'trigger.get');

        $this->assertSame(['1'], $triggerCall['params']['hostids']);
        $this->assertFalse($triggerCall['params']['maintenance']);
        $this->assertTrue($triggerCall['params']['only_true']);
        $this->assertTrue($triggerCall['params']['skipDependent']);
    }
}
