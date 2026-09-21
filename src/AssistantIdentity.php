<?php

namespace Chatsaas\LaravelWidget;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Mints a short-lived HS256 identity token for the in-app assistant widget. Signed with
 * the shared secret (chatsaas.identity_secret); the assistant verifies with the same value.
 * HS256 is an HMAC, so no JWT library is needed.
 *
 * `sub` is the host app's user id — it must match what AssistantServiceAuth impersonates
 * (via chatsaas.user_model::find($userId)) so the injected identity resolves to the same user.
 */
class AssistantIdentity
{
    public static function tokenFor(Authenticatable $user, ?int $ttl = null): string
    {
        $now = time();
        $ttl = $ttl ?? (int) config('chatsaas.token_ttl', 600);

        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub'  => (string) $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'iat'  => $now,
            'exp'  => $now + $ttl,
        ];

        $b64   = fn ($data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $input = $b64($header) . '.' . $b64($payload);
        $sig   = hash_hmac('sha256', $input, (string) config('chatsaas.identity_secret'), true);

        return $input . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }
}
