<?php

use Chatsaas\LaravelWidget\Http\Controllers\ChatTokenController;
use Illuminate\Support\Facades\Route;

// The host app is responsible for gating who reaches this route (e.g. wrap the
// @include('chatsaas::widget') in an @auth/@can check, and put an equivalent guard here via
// the 'auth' middleware or a custom gate). The package only mints the token.
Route::middleware(['auth'])
    ->get('/session/chat-token', [ChatTokenController::class, 'refresh'])
    ->name('chatsaas.chat-token');
