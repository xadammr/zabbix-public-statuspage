<?php

use App\Models\PushSubscription;
use App\Services\CachedStatusPage;
use App\Services\StatusPageVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$statusPageViewData = function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request): array {
    $snapshot = $statusPage->current();
    $clientIps = $visibility->candidateIps(
        $request->ip(),
        $request->headers->get('X-Real-IP'),
        $request->headers->get('CF-Connecting-IP'),
        $request->headers->get('X-Forwarded-For'),
        $request->server('REMOTE_ADDR'),
    );
    $visibleStatusPage = $visibility->filter($snapshot, $clientIps);

    return [
        'statusPage' => $visibleStatusPage,
        'statusDebug' => $visibility->debug(
            $snapshot,
            $visibleStatusPage,
            $request->ip(),
            $request->headers->get('X-Real-IP'),
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('X-Forwarded-For'),
        ),
    ];
};

$wallboardViewData = function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request): array {
    $clientIps = $visibility->candidateIps(
        $request->ip(),
        $request->headers->get('X-Real-IP'),
        $request->headers->get('CF-Connecting-IP'),
        $request->headers->get('X-Forwarded-For'),
        $request->server('REMOTE_ADDR'),
    );

    abort_unless($visibility->isTrustedVisitor($clientIps), 403);
    $snapshot = $statusPage->current();

    return [
        'statusPage' => $snapshot,
        'statusDebug' => $visibility->debug(
            $snapshot,
            $snapshot,
            $request->ip(),
            $request->headers->get('X-Real-IP'),
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('X-Forwarded-For'),
        ),
        'wallboard' => true,
    ];
};

Route::get('/site.webmanifest', function () {
    $manifest = [
        'name' => config('statuspage.name'),
        'short_name' => config('statuspage.short_name'),
        'theme_color' => config('statuspage.theme_color'),
        'background_color' => config('statuspage.theme_color'),
        'display' => 'standalone',
    ];

    if (config('statuspage.favicon_url')) {
        $manifest['icons'] = [
            [
                'src' => config('statuspage.favicon_url'),
                'sizes' => 'any',
            ],
        ];
    }

    return response()->json($manifest)
        ->header('Content-Type', 'application/manifest+json');
});

Route::get('/', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) use ($statusPageViewData) {
    return view('status.index', $statusPageViewData($statusPage, $visibility, $request));
});

Route::get('/status-fragment', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) use ($statusPageViewData) {
    return view('status.partials.page-content', $statusPageViewData($statusPage, $visibility, $request));
});

Route::get('/wallboard', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) use ($wallboardViewData) {
    return response()
        ->view('status.wallboard', $wallboardViewData($statusPage, $visibility, $request))
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
});

Route::get('/wallboard/status-fragment', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) use ($wallboardViewData) {
    return response()
        ->view('status.partials.page-content', $wallboardViewData($statusPage, $visibility, $request))
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
});

Route::get('/push/vapid-public-key', function () {
    abort_unless(config('services.web_push.enabled') && config('services.web_push.vapid_public_key'), 404);

    return response()->json([
        'publicKey' => config('services.web_push.vapid_public_key'),
    ]);
});

Route::post('/push/subscriptions', function (Request $request) {
    abort_unless(config('services.web_push.enabled') && config('services.web_push.vapid_public_key'), 404);

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
});

Route::delete('/push/subscriptions', function (Request $request) {
    $validated = $request->validate([
        'endpoint' => ['required', 'string'],
    ]);

    PushSubscription::query()
        ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
        ->delete();

    return response()->noContent();
});
