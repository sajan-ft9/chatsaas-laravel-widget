<?php

return [
    // us -> host: shared HS256 secret we sign each user's identity token with; the assistant
    // verifies with the same value.
    'identity_secret' => env('CHATSAAS_IDENTITY_SECRET', ''),

    // assistant -> host: service key on tool calls (server-to-server). We check it.
    'service_key' => env('CHATSAAS_SERVICE_KEY', ''),

    // Widget embed (browser-facing) — from the Chatsaas dashboard config.
    'widget_key' => env('CHATSAAS_WIDGET_KEY', ''),

    'api_endpoint' => env('CHATSAAS_API_ENDPOINT', 'http://localhost:3002/v1'),
    'widget_js' => env('CHATSAAS_WIDGET_JS', 'http://localhost:3002/widget.js'),

    // Seconds an identity token stays valid for. Keep this short.
    'token_ttl' => env('CHATSAAS_TOKEN_TTL', 600),

    // The host app's Eloquent user model. Used to resolve the user id the assistant injects
    // as `userId` after verifying the service key. Safe as a default string — the package
    // doesn't need the class to exist until this is actually resolved at runtime.
    'user_model' => \App\Models\User::class,

    // The Gate ability that governs whether a user may use the assistant at all. The host
    // app defines what this means (Spatie permission, a plain column, anything). If the host
    // never defines it, the package falls back to `allow_by_default` below — see
    // ChatsaasServiceProvider.
    'gate_ability' => 'use-chatsaas',

    // What the fallback Gate resolves to when the host never defines `gate_ability` themselves.
    // Stays false (deny everyone) unless `chatsaas:install` set this during a deliberate,
    // interactive opt-in (only offered when no permission package was detected) — never flips
    // to true silently. A package handed to many different clients must never ship "wide open"
    // as its out-of-the-box state.
    'allow_by_default' => env('CHATSAAS_ALLOW_BY_DEFAULT', false),
];
