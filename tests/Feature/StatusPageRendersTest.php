<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class StatusPageRendersTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Service Status');
        $response->assertSee('Alert Level: High');
        $response->assertSee('Public services');
        $response->assertSee('Example');
        $response->assertSee('href="https://example.com"', false);
        $response->assertSee('123 ms');
        $response->assertSee('example.item');
        $response->assertSee('1.23');
        $response->assertSee('High');
        $response->assertSee('data-has-active-triggers', false);
        $response->assertSee('data-page-refresh-progress', false);
        $response->assertSeeInOrder(['Example trigger', 'Response time']);
        $response->assertDontSee('Next pull in');
        $response->assertDontSee('data-domain=', false);
    }
}
