<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StatusPageFetchesHostsOnceTest extends TestCase
{
    public function test_status_page_fetches_hosts_once_and_assigns_sections_from_tags(): void
    {
        Config::set('zabbix.statuspage_sections', [
            'public' => [
                'title' => 'Public services',
                'description' => 'Customer-facing services monitored by Zabbix.',
            ],
            'internal' => [
                'title' => 'Internal services',
                'description' => 'Internal services monitored by Zabbix.',
            ],
        ]);

        $zabbix = new class extends ZabbixClient
        {
            public array $calls = [];

            public function request(string $method, array $params = []): array
            {
                $this->calls[] = compact('method', 'params');

                if ($method === 'host.get') {
                    return [
                        [
                            'hostid' => '1',
                            'host' => 'public-example',
                            'name' => 'Public Example',
                            'description' => '',
                            'tags' => [
                                ['tag' => 'statuspage', 'value' => 'public'],
                            ],
                        ],
                        [
                            'hostid' => '2',
                            'host' => 'multi-example',
                            'name' => 'Multi Example',
                            'description' => '',
                            'tags' => [
                                ['tag' => 'statuspage', 'value' => 'internal'],
                                ['tag' => 'statuspage', 'value' => 'public'],
                            ],
                        ],
                        [
                            'hostid' => '3',
                            'host' => 'ignored-example',
                            'name' => 'Ignored Example',
                            'description' => '',
                            'tags' => [
                                ['tag' => 'statuspage', 'value' => 'unknown'],
                            ],
                        ],
                    ];
                }

                return [];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();
        $hostCalls = collect($zabbix->calls)->where('method', 'host.get')->values();

        $this->assertCount(1, $hostCalls);
        $this->assertSame('statuspage', $hostCalls[0]['params']['tags'][0]['tag']);
        $this->assertSame(4, $hostCalls[0]['params']['tags'][0]['operator']);
        $this->assertArrayNotHasKey('value', $hostCalls[0]['params']['tags'][0]);
        $this->assertSame(['public', 'public'], collect($statusPage['services'])->pluck('section')->all());
        $this->assertSame(['1', '2'], collect($statusPage['services'])->pluck('hostid')->all());
    }
}
