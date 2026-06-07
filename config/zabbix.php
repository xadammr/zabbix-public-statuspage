<?php

return [
    'url' => env('ZABBIX_API_URL'),
    'token' => env('ZABBIX_API_TOKEN'),

    'statuspage_sections' => [
        'public' => [
            'title' => 'External Services',
            'description' => 'Customer-facing services monitored by Zabbix.',
        ],
        'internal' => [
            'title' => 'Internal Services',
            'description' => 'Internal services monitored by Zabbix.',
        ],
        'infrastructure' => [
            'title' => 'Infrastructure',
            'description' => 'Supporting services and infrastructure dependencies.',
        ],
    ],

    'latency_item_key' => env('ZABBIX_LATENCY_ITEM_KEY', 'statuspage.web.latency'),
    'latency_sections' => ['public', 'internal'],
    'api_health_item_key' => env('ZABBIX_API_HEALTH_ITEM_KEY', 'api.health.status'),
    'api_health_success_value' => env('ZABBIX_API_HEALTH_SUCCESS_VALUE', '1'),
    'statuspage_cache_key' => env('STATUSPAGE_CACHE_KEY', 'statuspage.snapshot'),
    'statuspage_poll_interval' => env('STATUSPAGE_POLL_INTERVAL', 60),
    'statuspage_stale_after' => env('STATUSPAGE_STALE_AFTER', 120),
];
