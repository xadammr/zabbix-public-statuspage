<?php

use App\Services\CachedStatusPage;
use App\Services\StatusPageVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (CachedStatusPage $statusPage, StatusPageVisibility $visibility, Request $request) {
    return view('status.index', [
        'statusPage' => $visibility->filter($statusPage->current(), $request->ip()),
    ]);
});
