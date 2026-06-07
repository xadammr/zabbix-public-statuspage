<?php

use App\Services\CachedStatusPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function (CachedStatusPage $statusPage) {
    return view('status.index', [
        'statusPage' => $statusPage->current(),
    ]);
});
