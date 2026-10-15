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
            'public' => 'Public services',
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

        $problemCall = collect($zabbix->calls)->firstWhere('method', 'problem.get');

        $this->assertSame(['1'], $problemCall['params']['hostids']);
        $this->assertFalse($problemCall['params']['recent']);
        $this->assertSame(['objectid', 'suppressed'], $problemCall['params']['output']);
    }

    public function test_suppressed_problems_are_excluded_from_service_status(): void
    {
        Config::set('zabbix.statuspage_sections', [
            'public' => 'Public services',
        ]);

        $zabbix = new class extends ZabbixClient
        {
            public function request(string $method, array $params = []): array
            {
                return match ($method) {
                    'host.get' => [[
                        'hostid' => '1',
                        'host' => 'public-example',
                        'name' => 'Public Example',
                        'description' => '',
                        'tags' => [['tag' => 'statuspage', 'value' => 'public']],
                    ]],
                    'trigger.get' => [
                        $this->trigger('10', 'Suppressed disaster', '5'),
                        $this->trigger('20', 'Visible warning', '2'),
                        $this->trigger('30', 'Mixed suppression', '4'),
                    ],
                    'problem.get' => [
                        ['objectid' => '10', 'suppressed' => '1'],
                        ['objectid' => '20', 'suppressed' => '0'],
                        ['objectid' => '30', 'suppressed' => '1'],
                        ['objectid' => '30', 'suppressed' => '0'],
                    ],
                    default => [],
                };
            }

            private function trigger(string $triggerId, string $description, string $priority): array
            {
                return [
                    'triggerid' => $triggerId,
                    'description' => $description,
                    'lastchange' => '1',
                    'priority' => $priority,
                    'status' => '0',
                    'value' => '1',
                    'hosts' => [['hostid' => '1', 'host' => 'public-example', 'name' => 'Public Example']],
                ];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();
        $service = $statusPage['services'][0];

        $this->assertSame('problem', $service['status']);
        $this->assertSame('High', $service['severity']['label']);
        $this->assertSame(
            ['Visible warning', 'Mixed suppression'],
            collect($service['triggers'])->pluck('description')->all(),
        );
        $this->assertSame(1, $statusPage['summary']['problem']);
    }
}
