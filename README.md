# chatsaas/laravel-widget

Drops the Chatsaas in-app assistant into any Laravel application: a signed identity handshake
so the assistant knows which user it's talking to, a service-auth middleware that lets the
assistant safely call back into your app as that user, and the widget embed itself.

This README is the entire support surface for this package. If something below doesn't cover
your situation, that's a bug in this document — please report it.

## What this package does NOT do

It has no opinion on your data model. It does not ship any REST endpoints exposing your app's
own business data (customers, orders, whatever the assistant should be able to answer questions
about) — you write those yourself, the same way you'd write any other API endpoint, and put the
`chatsaas.service` middleware in front of them. That's the entire contract.

## Install

```bash
composer require chatsaas/laravel-widget
php artisan chatsaas:install
```

`chatsaas:install` will:

1. Publish `config/chatsaas.php` and the widget-embed Blade partial.
2. Generate `CHATSAAS_IDENTITY_SECRET` and `CHATSAAS_SERVICE_KEY` and append them to your `.env`
   (safe to re-run — it never overwrites an existing secret).
3. Print both secrets to your terminal.
4. Ask which Eloquent model is your "User" (or pass `--user-model=App\\Models\\Person`).

### The two secrets — where they go

Both are generated entirely on your side; we never see, request, or need them:

- **`CHATSAAS_IDENTITY_SECRET`** — you sign each user's identity token with this; the assistant
  verifies with the same value.
- **`CHATSAAS_SERVICE_KEY`** — the assistant sends this back on every call into your app; your
  middleware checks it.

Paste both into your Chatsaas dashboard under **Settings → this app's integration**.

## The Gate ability — read this before anything else

The package ships **deny-by-default**. Until you define the Gate ability yourself, every request
is rejected, and the widget effectively does nothing. This is intentional: a package handed to
many different clients must never ship "wide open" out of the box.

Add this to your own `AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

// A Spatie-permission app:
Gate::define('use-chatsaas', fn ($user) => $user->hasDirectPermission('Use AI Assistant'));

// An app with no permission package at all:
Gate::define('use-chatsaas', fn ($user) => $user->is_admin);
```

The ability name is configurable via `chatsaas.gate_ability` (default: `use-chatsaas`).

## Embedding the widget

Include the published partial wherever you want the assistant to appear, guarded by whatever
auth/permission check makes sense for your app — the package doesn't add this guard for you,
since it doesn't know your permission system:

```blade
@auth
    @can('use-chatsaas')
        @include('chatsaas::widget')
    @endcan
@endauth
```

## Protecting your own endpoints

Put the `chatsaas.service` middleware in front of whatever data the assistant should be able to
read or act on:

```php
Route::middleware('chatsaas.service')->get('/api/assistant/orders', [OrderController::class, 'index']);
```

Inside that route, `auth()->user()` resolves to the same user whose identity token the assistant
was handed — every existing controller that already scopes by the logged-in user needs no
changes.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Widget never appears | `chatsaas.widget_key` not set, or your own `@can` guard is false | Check `.env` has `CHATSAAS_WIDGET_KEY`; check the user has the ability you gated the `@include` behind |
| Assistant calls into your app return 403 | You haven't defined `gate_ability` yet (deny-by-default), or the resolved user fails your Gate check | Add `Gate::define(...)` per above; confirm which ability name `config('chatsaas.gate_ability')` actually is |
| Assistant calls return 401 | `CHATSAAS_SERVICE_KEY` mismatch between your `.env` and the Chatsaas dashboard | Re-copy the value from `.env`, don't retype it |
| Assistant calls return 500, "service key not configured" | `CHATSAAS_SERVICE_KEY` missing/empty in `.env` | Re-run `php artisan chatsaas:install`, or set it manually |
| Identity token rejected by the assistant | `CHATSAAS_IDENTITY_SECRET` mismatch between your `.env` and the dashboard | Re-copy the value, don't retype it |
| `user_model` resolution errors | `chatsaas.user_model` points at a class that doesn't exist, or doesn't use an integer/string primary key your assistant sends back | Re-run install with `--user-model=`, or edit `config/chatsaas.php` directly |

## Network-restricted environments (fallback — not the default)

If your environment can't reach our private git host at install/deploy time at all (a
locked-down CI runner with no outbound access, or you'd rather vendor the source directly into
your own repo), use a git submodule plus Composer's `path` repository type instead of the
VCS-repository method above:

```bash
git submodule add git@github.com:diagonal-software/chatsaas-laravel-widget.git packages/chatsaas-laravel-widget
```

```json
{
    "repositories": [
        {"type": "path", "url": "packages/chatsaas-laravel-widget"}
    ],
    "require": {
        "chatsaas/laravel-widget": "*"
    }
}
```

This adds real overhead — you now own updating the submodule pointer yourself
(`git submodule update --remote`) — so only reach for it if the plain `composer require` path
genuinely doesn't work for your environment. For everyone else, add this to your app's
`composer.json` instead:

```json
{
    "repositories": [
        {"type": "vcs", "url": "git@github.com:diagonal-software/chatsaas-laravel-widget.git"}
    ],
    "require": {
        "chatsaas/laravel-widget": "^1.0"
    }
}
```
