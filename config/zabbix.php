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
    'statuspage_profile_log' => env('STATUSPAGE_PROFILE_LOG', false),
    'statuspage_private_sections' => array_filter(array_map('trim', explode(',', env('STATUSPAGE_PRIVATE_SECTIONS', 'internal,infrastructure')))),
    'statuspage_private_ips' => array_filter(array_map('trim', explode(',', env('STATUSPAGE_PRIVATE_IPS', '')))),
    'trusted_proxies' => array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '')))),
    'cloudflare_ip_ranges_url' => env('CLOUDFLARE_IP_RANGES_URL', 'https://api.cloudflare.com/client/v4/ips'),
    'cloudflare_ip_ranges_cache_key' => env('CLOUDFLARE_IP_RANGES_CACHE_KEY', 'statuspage.cloudflare_ip_ranges'),
    'cloudflare_ip_ranges_cache_ttl' => env('CLOUDFLARE_IP_RANGES_CACHE_TTL', 86400),
];
