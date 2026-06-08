<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class UpdatedTimeUsesConfiguredTimezoneTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_application_displays_updated_time_in_the_configured_timezone(): void
    {
        $this->withoutVite();
        Config::set('app.timezone', 'Australia/Brisbane');

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $payload = $this->statusPagePayload();
            $payload['generated_at'] = Carbon::parse('2026-06-07 07:32:47', 'UTC');

            $mock->shouldReceive('current')
                ->once()
                ->andReturn($payload);
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('title="2026-06-07 17:32:47"', false)
            ->assertDontSee('07:32:47');
    }
}
