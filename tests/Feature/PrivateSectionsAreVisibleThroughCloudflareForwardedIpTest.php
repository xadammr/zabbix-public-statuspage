<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class PrivateSectionsAreVisibleThroughCloudflareForwardedIpTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_private_sections_are_visible_when_allowlisted_ip_is_forwarded_by_cloudflare(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_sections', ['internal']);
        Config::set('zabbix.statuspage_private_ips', ['100.64.0.0/10']);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayloadWithPrivateSection());
        });

        $this->withServerVariables(['REMOTE_ADDR' => '172.68.84.175'])
            ->withHeader('CF-Connecting-IP', '100.64.0.42')
            ->get('/')
            ->assertStatus(200)
            ->assertSee('Public services')
            ->assertSee('Internal Services')
            ->assertSee('Internal Example')
            ->assertSee('172.68.84.175')
            ->assertSee('100.64.0.42')
            ->assertSee('Hidden sections')
            ->assertSee('none');
    }
}
