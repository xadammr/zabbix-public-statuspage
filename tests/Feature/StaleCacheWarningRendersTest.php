<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class StaleCacheWarningRendersTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_application_warns_when_cached_data_is_stale(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['cache']['refreshed_at'] = now()->subMinutes(3);
            $payload['cache']['is_stale'] = true;
            $payload['cache']['age_seconds'] = 180;

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('No new data available.')
            ->assertSee('The status page is currently showing stale data');
    }
}
