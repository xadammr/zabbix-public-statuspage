<?php

namespace Tests\Feature\Concerns;

use Illuminate\Support\Carbon;

trait BuildsStatusPagePayloads
{
    protected function statusPagePayload(bool $withCache = true): array
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

    protected function statusPagePayloadWithPrivateSection(): array
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

    protected function servicePayload(): array
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
                    'segments' => [
                        [
                            'class' => 'ok',
                            'points' => '6,66 120,36',
                        ],
                        [
                            'class' => 'ok',
                            'points' => '120,36 234,12',
                        ],
                    ],
                    'width' => 240,
                    'height' => 72,
                    'max_ms' => 150,
                    'min_ms' => 75,
                    'samples' => 3,
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
