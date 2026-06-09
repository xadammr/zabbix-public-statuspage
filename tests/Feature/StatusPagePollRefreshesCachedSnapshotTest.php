<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use App\Services\StatusPageBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class StatusPagePollRefreshesCachedSnapshotTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_status_page_poll_refreshes_the_cached_snapshot(): void
    {
        Config::set('cache.default', 'array');
        Config::set('zabbix.statuspage_cache_key', 'statuspage.test.snapshot');
        Config::set('zabbix.statuspage_poll_interval', 60);
        Cache::forget('statuspage.test.snapshot');

        $this->mock(StatusPageBuilder::class, function ($mock): void {
            $mock->shouldReceive('build')
                ->once()
                ->andReturn($this->statusPagePayload(withCache: false));
        });

        $this->artisan('statuspage:poll --force')
            ->expectsOutput('Status page snapshot refreshed.')
            ->expectsOutput('Changes:')
            ->expectsOutput(' - Initial snapshot cached with 1 services.')
            ->assertExitCode(0);

        $snapshot = app(CachedStatusPage::class)->current();

        $this->assertSame('Example', $snapshot['sections'][0]['services'][0]['name']);
        $this->assertTrue($snapshot['cache']['next_refresh_at']->greaterThan($snapshot['cache']['refreshed_at']));
        $this->assertIsFloat($snapshot['cache']['duration_ms']);
        $this->assertNotEmpty($snapshot['cache']['duration']);
    }
}
