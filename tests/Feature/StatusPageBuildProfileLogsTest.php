<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\StatusPageSummary;
use App\Services\ZabbixClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class StatusPageBuildProfileLogsTest extends TestCase
{
    public function test_status_page_build_profile_can_be_logged(): void
    {
        Config::set('zabbix.statuspage_profile_log', true);
        Config::set('zabbix.statuspage_sections', [
            'public' => [
                'title' => 'Public services',
                'description' => 'Customer-facing services monitored by Zabbix.',
            ],
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('statuspage:build_profile', Mockery::on(fn (array $context): bool => isset(
                $context['total_ms'],
                $context['services'],
                $context['events'],
                $context['slowest'],
            )
                && $context['services'] === 1
                && collect($context['events'])->contains(fn (array $event) => $event['label'] === 'zabbix.host.get')));

        $zabbix = new class extends ZabbixClient
        {
            public function request(string $method, array $params = []): array
            {
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

        (new StatusPageBuilder($zabbix, new StatusPageSummary))->build();

    }
}
