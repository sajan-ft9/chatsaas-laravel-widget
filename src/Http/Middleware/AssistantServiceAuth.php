<?php

namespace Chatsaas\LaravelWidget\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Auth for the in-app assistant REST integration. Verifies a shared service key, checks the
 * host app's Gate ability for the resolved user, then impersonates them so downstream
 * controllers that already scope by auth()->user() need no per-endpoint changes.
 */
class AssistantServiceAuth
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        $expected = config('chatsaas.service_key');
        // Accept the key as an Authorization bearer, a raw Authorization value, or the
        // X-Chatsaas-Key header — whichever the caller sends.
        $provided = $request->bearerToken()
            ?? $request->header('X-Chatsaas-Key')
            ?? $request->header('Authorization');
        if (!$expected) {
            return response()->json(['error' => 'Chatsaas service key not configured'], 500);
        }
        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // The assistant injects the verified user server-side; the model can never set it.
        $model = config('chatsaas.user_model');
        $user  = $model::find($request->input('userId'));
        if (!$user || !Gate::forUser($user)->allows(config('chatsaas.gate_ability'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
