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
4. Ask for the fully-qualified class name of your Eloquent User model (e.g. `App\Models\User`),
   so it can write the exact `use`/`::class` reference into the published config — pass
   `--user-model=App\Models\Person` to skip the prompt.
5. If no permission package is detected, ask whether to allow every authenticated user by
   default until you set up real access control (see below) — pass `--allow-by-default` to opt
   in non-interactively, or just answer the prompt.

### The two secrets — where they go

Both are generated entirely on your side; we never see, request, or need them:

- **`CHATSAAS_IDENTITY_SECRET`** — you sign each user's identity token with this; the assistant
  verifies with the same value.
- **`CHATSAAS_SERVICE_KEY`** — the assistant sends this back on every call into your app; your
  middleware checks it.

Paste both into your Chatsaas dashboard under **Settings → this app's integration**.

## Laravel version compatibility

| Your Laravel version | Package version tested against | If package auto-discovery is disabled, register the provider in… |
|---|---|---|
| 10.x | `orchestra/testbench` ^8.0 | `config/app.php` → `providers` array: add `Chatsaas\LaravelWidget\ChatsaasServiceProvider::class` |
| 11.x | `orchestra/testbench` ^9.0 | `bootstrap/providers.php` → add `Chatsaas\LaravelWidget\ChatsaasServiceProvider::class` to the returned array (Laravel 11 moved provider registration out of `config/app.php`) |
| 12.x | `orchestra/testbench` ^10.0 | Same as 11.x — `bootstrap/providers.php` |
| 13.x | `orchestra/testbench` ^11.0 | Same as 11.x — `bootstrap/providers.php` |

Almost everyone can ignore this table: `composer require` triggers Laravel's package
auto-discovery automatically, which registers `ChatsaasServiceProvider` for you regardless of
version. It only matters if your app has auto-discovery turned off (an
`extra.laravel.dont-discover` entry in your own `composer.json` listing this package, or
`--no-scripts` on install) — the table tells you which file to edit by hand for the Laravel
version you're on.

One thing that does **not** change across versions: the `chatsaas.service` middleware alias is
registered directly against the router from inside the service provider, not through
`app/Http/Kernel.php`. Laravel 11+ removed that file from the default skeleton; this package
never needed it in the first place, on any version, so there's nothing to add there.

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

### No permission package at all?

If `chatsaas:install` doesn't detect `spatie/laravel-permission` (or you're running it
interactively with no other permission system in place), it asks — once, and only
interactively — whether to allow every authenticated user to use the assistant until you set up
something stricter:

```
No permission package (e.g. spatie/laravel-permission) detected.
Allow every authenticated user to use the assistant by default, until you configure something stricter? (yes/no)
```

Answering yes sets `CHATSAAS_ALLOW_BY_DEFAULT=true` in your `.env`. You can also set this
non-interactively (CI, scripted installs) with `--allow-by-default`. If you skip the prompt, run
`chatsaas:install --no-interaction`, or say no, the package stays deny-by-default — nothing is
ever allowed silently. Defining your own `Gate::define(...)` always takes priority over this
setting, so it's safe to leave `CHATSAAS_ALLOW_BY_DEFAULT=true` around even after you add real
access control later; the Gate you define wins.

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
| Assistant calls into your app return 403 | You haven't defined `gate_ability` yet and `CHATSAAS_ALLOW_BY_DEFAULT` isn't set (deny-by-default), or the resolved user fails your Gate check | Add `Gate::define(...)` per above, or set `CHATSAAS_ALLOW_BY_DEFAULT=true` if you genuinely want every authenticated user allowed for now; confirm which ability name `config('chatsaas.gate_ability')` actually is |
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
