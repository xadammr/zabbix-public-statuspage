<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class FooterDebugFallsBackToForwardedIpsTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_footer_debug_uses_cloudflare_or_forwarded_ip_when_real_ip_header_is_missing(): void
    {
        $this->withoutVite();
        Config::set('zabbix.statuspage_private_sections', []);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $this->withServerVariables(['REMOTE_ADDR' => '172.68.84.175'])
            ->withHeader('CF-Connecting-IP', '100.64.0.42')
            ->withHeader('X-Forwarded-For', '100.64.0.43, 172.68.84.175')
            ->get('/')
            ->assertStatus(200)
            ->assertSee('172.68.84.175')
            ->assertSee('100.64.0.42')
            ->assertDontSee('100.64.0.43');
    }
}
