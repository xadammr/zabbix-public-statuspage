<?php

namespace Tests\Feature;

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\BuildsStatusPagePayloads;
use Tests\TestCase;

class AvailableItemsAreHiddenInProductionTest extends TestCase
{
    use BuildsStatusPagePayloads;

    public function test_available_items_are_hidden_in_production(): void
    {
        $this->withoutVite();
        Config::set('app.env', 'production');
        Config::set('zabbix.statuspage_fetch_available_items', false);

        $this->mock(CachedStatusPage::class, function ($mock): void {
            $mock->shouldReceive('current')
                ->once()
                ->andReturn($this->statusPagePayload());
        });

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('Available items:');
    }
}
