<?php

namespace Tests\Feature;

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
}
