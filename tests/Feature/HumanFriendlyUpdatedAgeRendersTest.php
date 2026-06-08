<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class HumanFriendlyUpdatedAgeRendersTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_application_displays_human_friendly_updated_age(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-06-07 07:37:47', 'UTC'));

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['generated_at'] = Carbon::parse('2026-06-07 07:32:47', 'UTC');

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        try {
            $this->get('/')
                ->assertStatus(200)
                ->assertSee('Last updated')
                ->assertSee('five minutes ago');
        } finally {
            Carbon::setTestNow();
        }
    }
}
