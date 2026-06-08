<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class StatusFragmentRendersPartialContentTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_status_fragment_renders_partial_content(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $this->get('/status-fragment')
            ->assertStatus(200)
            ->assertSee('Service Status')
            ->assertSee('Public services')
            ->assertDontSee('<!DOCTYPE html>', false)
            ->assertDontSee('<html', false);
    }
}
