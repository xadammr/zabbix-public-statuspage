<?php

use App\Support\StatusPageConfig;

$defaultSections = [
    'public' => 'External Services',
    'internal' => 'Internal Services',
    'infrastructure' => 'Infrastructure',
];

return [
    'url' => env('ZABBIX_API_URL'),
    'token' => env('ZABBIX_API_TOKEN'),

    'statuspage_sections' => StatusPageConfig::sections(env('STATUSPAGE_SECTIONS'), $defaultSections),

    'latency_sections' => StatusPageConfig::sectionList(env('STATUSPAGE_LATENCY_SECTIONS'), ['public', 'internal']),
    'api_health_item_key' => env('ZABBIX_API_HEALTH_ITEM_KEY', 'api.health.status'),
    'api_health_success_value' => env('ZABBIX_API_HEALTH_SUCCESS_VALUE', '1'),
    'statuspage_cache_key' => env('STATUSPAGE_CACHE_KEY', 'statuspage.snapshot'),
    'statuspage_poll_interval' => env('STATUSPAGE_POLL_INTERVAL', 60),
    'statuspage_stale_after' => env('STATUSPAGE_STALE_AFTER', 120),
    'statuspage_stale_forces_disaster' => env('STATUSPAGE_STALE_FORCES_DISASTER', true),
    'statuspage_profile_log' => env('STATUSPAGE_PROFILE_LOG', false),
    'statuspage_fetch_available_items' => env('STATUSPAGE_FETCH_AVAILABLE_ITEMS', env('APP_ENV', 'production') !== 'production'),
    'statuspage_private_sections' => array_filter(array_map('trim', explode(',', env('STATUSPAGE_PRIVATE_SECTIONS', 'internal,infrastructure')))),
    'statuspage_private_ips' => array_filter(array_map('trim', explode(',', env('STATUSPAGE_PRIVATE_IPS', '')))),
    'wallboard_show_footer' => env('WALLBOARD_SHOW_FOOTER', false),
    'trusted_proxies' => array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '')))),
    'cloudflare_ip_ranges_url' => env('CLOUDFLARE_IP_RANGES_URL', 'https://api.cloudflare.com/client/v4/ips'),
    'cloudflare_ip_ranges_cache_key' => env('CLOUDFLARE_IP_RANGES_CACHE_KEY', 'statuspage.cloudflare_ip_ranges'),
    'cloudflare_ip_ranges_cache_ttl' => env('CLOUDFLARE_IP_RANGES_CACHE_TTL', 86400),
];
