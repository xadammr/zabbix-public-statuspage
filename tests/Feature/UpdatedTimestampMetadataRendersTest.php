<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class UpdatedTimestampMetadataRendersTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_application_renders_timestamp_metadata_for_javascript_formatting(): void
    {
        $this->withoutVite();
        Config::set('app.timezone', 'UTC');
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
                ->assertSee('Last updated:')
                ->assertSee('data-last-updated-at="2026-06-07T07:32:47+00:00"', false)
                ->assertSee('datetime="2026-06-07T07:32:47+00:00"', false)
                ->assertSee('title="2026-06-07 07:32:47"', false)
                ->assertSee('2026-06-07T07:32:47+00:00')
                ->assertDontSee('five minutes ago');
        } finally {
            Carbon::setTestNow();
        }
    }
}
