<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PushSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vapid_public_key_endpoint_is_hidden_until_web_push_is_enabled(): void
    {
        Config::set('services.web_push.enabled', false);
        Config::set('services.web_push.vapid_public_key', 'public-key');

        $this->getJson('/push/vapid-public-key')
            ->assertNotFound();
    }

    public function test_vapid_public_key_endpoint_returns_configured_key(): void
    {
        Config::set('services.web_push.enabled', true);
        Config::set('services.web_push.vapid_public_key', 'public-key');

        $this->getJson('/push/vapid-public-key')
            ->assertOk()
            ->assertExactJson(['publicKey' => 'public-key']);
    }

    public function test_push_subscription_can_be_saved_and_removed(): void
    {
        Config::set('services.web_push.enabled', true);
        Config::set('services.web_push.vapid_public_key', 'public-key');

        $payload = [
            'endpoint' => 'https://push.example/subscription/123',
            'keys' => [
                'p256dh' => 'browser-public-key',
                'auth' => 'browser-auth-token',
            ],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->postJson('/push/subscriptions', $payload)
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint_hash' => hash('sha256', $payload['endpoint']),
            'endpoint' => $payload['endpoint'],
            'public_key' => 'browser-public-key',
            'auth_token' => 'browser-auth-token',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->deleteJson('/push/subscriptions', ['endpoint' => $payload['endpoint']])
            ->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint_hash' => hash('sha256', $payload['endpoint']),
        ]);
    }
}
