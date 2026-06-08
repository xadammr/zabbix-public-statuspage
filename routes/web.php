<?php

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

Route::get('/', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) use ($statusPageViewData) {
    return view('status.index', $statusPageViewData($statusPage, $visibility, $request));
});

Route::get('/status-fragment', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) use ($statusPageViewData) {
    return view('status.partials.page-content', $statusPageViewData($statusPage, $visibility, $request));
});
