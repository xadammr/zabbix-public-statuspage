<?php

namespace Tests\Feature;

use App\Services\StatusPageBuilder;
use App\Services\ZabbixClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicUrlOverrideTakesPrecedenceTest extends TestCase
{
    public function test_public_url_override_takes_precedence(): void
    {
        Config::set('zabbix.statuspage_sections', [
            'public' => [
                'title' => 'Public services',
                'description' => 'Customer-facing services monitored by Zabbix.',
            ],
        ]);

        $zabbix = new class extends ZabbixClient
        {
            public function request(string $method, array $params = []): array
            {
                if ($method === 'host.get') {
                    return [[
                        'hostid' => '1',
                        'host' => 'public-example',
                        'name' => 'Example',
                        'description' => '',
                    ]];
                }

                if ($method === 'usermacro.get') {
                    return [
                        [
                            'hostid' => '1',
                            'macro' => '{$PUBLIC_URL}',
                            'value' => 'https://example.com',
                        ],
                        [
                            'hostid' => '1',
                            'macro' => '{$PUBLIC_URL_OVERRIDE}',
                            'value' => 'https://override.example.com',
                        ],
                    ];
                }

                return [];
            }
        };

        $statusPage = (new StatusPageBuilder($zabbix))->build();

        $this->assertSame('https://override.example.com', $statusPage['services'][0]['public_url']);
    }
}
