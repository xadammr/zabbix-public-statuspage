<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebPushTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_command_reports_when_web_push_is_not_configured(): void
    {
        Config::set('services.web_push.enabled', false);

        $this->artisan('webpush:test')
            ->expectsOutputToContain('Web push is not fully configured.')
            ->assertFailed();
    }

    public function test_test_command_reports_when_there_are_no_subscribers(): void
    {
        Config::set('services.web_push.enabled', true);
        Config::set('services.web_push.vapid_subject', 'https://status.example.com');
        Config::set('services.web_push.vapid_public_key', 'public-key');
        Config::set('services.web_push.vapid_private_key', 'private-key');

        $this->artisan('webpush:test')
            ->expectsOutputToContain('No browser push subscribers found.')
            ->assertFailed();
    }

    public function test_test_command_reports_when_push_subscription_table_is_missing(): void
    {
        Config::set('services.web_push.enabled', true);
        Config::set('services.web_push.vapid_subject', 'https://status.example.com');
        Config::set('services.web_push.vapid_public_key', 'public-key');
        Config::set('services.web_push.vapid_private_key', 'private-key');
        Schema::drop('push_subscriptions');

        $this->artisan('webpush:test')
            ->expectsOutputToContain('Push subscription table is missing.')
            ->assertFailed();
    }

    public function test_clear_command_reports_when_push_subscription_table_is_missing(): void
    {
        Schema::drop('push_subscriptions');

        $this->artisan('webpush:clear')
            ->expectsOutputToContain('Push subscription table is missing.')
            ->assertFailed();
    }

    public function test_clear_command_reports_when_there_are_no_subscribers(): void
    {
        $this->artisan('webpush:clear')
            ->expectsOutputToContain('No browser push subscribers found.')
            ->assertSuccessful();
    }

    public function test_clear_command_keeps_subscribers_when_confirmation_is_declined(): void
    {
        $this->createPushSubscription();

        $this->artisan('webpush:clear')
            ->expectsConfirmation('Remove 1 browser push subscriber(s)?', 'no')
            ->expectsOutputToContain('No subscribers removed.')
            ->assertSuccessful();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_clear_command_removes_all_subscribers_when_forced(): void
    {
        $this->createPushSubscription('https://push.example.com/one');
        $this->createPushSubscription('https://push.example.com/two');

        $this->artisan('webpush:clear --force')
            ->expectsOutputToContain('Removed 2 browser push subscriber(s).')
            ->assertSuccessful();

        $this->assertDatabaseEmpty('push_subscriptions');
    }

    private function createPushSubscription(string $endpoint = 'https://push.example.com/test'): PushSubscription
    {
        return PushSubscription::query()->create([
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
            'user_agent' => 'test-agent',
            'last_seen_at' => now(),
        ]);
    }
}
