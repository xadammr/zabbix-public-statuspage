<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\StatusPageController;
use App\Http\Controllers\WebManifestController;
use Illuminate\Support\Facades\Route;

Route::get('/site.webmanifest', WebManifestController::class);

Route::get('/', [StatusPageController::class, 'index']);
Route::get('/status-fragment', [StatusPageController::class, 'fragment']);
Route::get('/wallboard', [StatusPageController::class, 'wallboard']);
Route::get('/wallboard/status-fragment', [StatusPageController::class, 'wallboardFragment']);

Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'publicKey']);
Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy']);
