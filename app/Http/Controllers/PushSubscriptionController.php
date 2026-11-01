<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function publicKey(): JsonResponse
    {
        $this->abortUnlessEnabled();

        return response()->json(['publicKey' => config('services.web_push.vapid_public_key')]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->abortUnlessEnabled();
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);
        $endpoint = $validated['endpoint'];

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'endpoint' => $endpoint,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $request->string('contentEncoding', 'aes128gcm')->toString(),
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->noContent();
    }

    private function abortUnlessEnabled(): void
    {
        abort_unless(config('services.web_push.enabled') && config('services.web_push.vapid_public_key'), 404);
    }
}
