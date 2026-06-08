<?php

use App\Services\CachedStatusPage;
use App\Services\StatusPageVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) {
    $snapshot = $statusPage->current();
    $visibleStatusPage = $visibility->filter($snapshot, $request->ip());

    return view('status.index', [
        'statusPage' => $visibleStatusPage,
        'statusDebug' => $visibility->debug($snapshot, $visibleStatusPage, $request->ip()),
    ]);
});
