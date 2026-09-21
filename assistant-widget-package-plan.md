# Yuka Assistant Integration — Diff Review & Self-Service Composer Package Plan

**Branch:** `test/chat-rest-api` vs `master`
**Purpose of this document:** (1) summarize what this branch actually adds, (2) design a Composer
package — `chatsaas/laravel-widget` — that lets **any** Laravel-based client self-install the Yuka
assistant integration into their own app, entirely on their own, with no server access or manual
setup from our side. This is a product-delivery mechanism, not an internal refactor.

---

## 1. Diff summary (branch vs `master`)

35 files changed, ~3,987 insertions. Commits: `b07ea560d`, `c581cfe7b`, `39808d444`, `cf2e10e04`,
`dcec349b7`.

| Area | Files | What changed |
|---|---|---|
| Token-based API auth | `AuthController.php`, `config/sanctum.php`, `create_personal_access_tokens_table.php`, `composer.json`/`composer.lock` | Added Laravel Sanctum; a `POST /api/login` issues a personal-access token, `POST /api/logout` revokes it. Standard token auth for a future API client — separate from the assistant's own auth. |
| Assistant identity handshake | `AssistantIdentity.php`, `AssistantServiceAuth.php`, `app/Http/Kernel.php`, `config/services.php`, `.env.example`, `layouts/app.blade.php`, `routes/web.php` | Server signs a short-lived JWT proving who the logged-in user is; the widget hands it to Yuka; Yuka calls back into m2munity with a shared service key + the verified user id, which `AssistantServiceAuth` uses to impersonate that user for the request. |
| Assistant-facing REST API | `SimController`, `CustomerController`, `DealerController`, `UsageReportController` (all `Api` namespace), `SimResource`, `CustomerResource`, `DealerResource`, `ScopesToAccessibleCustomers`, `routes/api.php` | 17 read (+1 write) endpoints: list/show SIMs, customers, dealers; a SIM's usage history; one write action (`PATCH sims/{sim}/state`, local-only, no carrier call); 13 usage/report aggregates (summary, by-day, by-customer, by-country, by-operator, by-APN, top SIMs, over/near-limit, data pools, inactive/recently-activated SIMs, status breakdown). All scoped to the acting user's accessible customers via one shared trait. |
| Access control (this session's work) | `PermissionsList.php`, `TestCheckPermissionMiddleware.php`, `AddAiAssistantPermissionSeeder.php`, `TestDatabaseSeeder.php`, `routes/api.php`, `routes/web.php`, `layouts/app.blade.php`, `AssistantPermissionTest.php` | New `Use AI Assistant` permission (Admin-only by default, grantable to anyone via the existing `/edit-permissions/{id}` screen); gates the API routes, the token-refresh endpoint, and the widget embed. Along the way, fixed a real bug in a test-only permission-check stub that silently swallowed every permission-gated request in tests. |
| Test/demo fixtures | `RestApiTestUserSeeder.php`, `AssistantDemoUsageSeeder.php`, `DealerCustomerSimDemoSeeder.php`, `AssistantUsageReportTest.php` | Seeders and a feature-test suite for exercising the above locally. |

## 2. Features added — plain list

- Sanctum token login/logout for the REST API (`/api/login`, `/api/logout`)
- Server-signed, short-lived identity token so the in-app assistant widget knows which user it's
  talking to, without ever trusting the browser or the AI model for identity
- A service-key-authenticated REST API the assistant calls, scoped to the acting user's own
  accessible customers/dealers/SIMs (same multi-tenant rule the rest of the portal already follows)
- Read endpoints: SIM list/detail, customer list/detail, dealer list/detail, per-SIM usage history
- One write endpoint: change a SIM's local state (portal-only, does not call any carrier API)
- 13 usage/report endpoints (daily/monthly aggregates, top consumers, over/near-limit SIMs, pooled
  data, inactive/newly-activated SIMs, status breakdown) — the data the assistant uses to actually
  answer questions
- The assistant widget embed itself, wired into the shared page layout
- Admin-only access control on the entire feature, extensible to any other user via the existing
  permission-grant screen with no code change
- Seed data and a feature-test suite covering the above

---

## 3. Can this become a generic Composer package?

**Goal, as clarified:** this isn't an internal reuse question — it's the product. Any client running
a Laravel app should be able to `composer require chatsaas/laravel-widget`, run one install command,
paste two secrets into their Yuka dashboard, and get the assistant working — **without our team ever
touching their server.** That requirement changes the design in a few important ways from a typical
internal extraction, covered in section 4 below. First, the same reusability split still holds:

The integration splits cleanly into two halves with very different reusability:

| Half | Reusable across apps? | Why |
|---|---|---|
| **Identity + auth plumbing**: `AssistantIdentity`, `AssistantServiceAuth`, the permission-gate pattern, the widget embed script, the token-refresh route | **Yes** — this logic doesn't know or care what a "SIM" or "customer" is. It only needs: a way to resolve a user by id, a way to check if that user is allowed to use the assistant, and a signing secret. |
| **The actual REST endpoints** (`SimController`, `CustomerController`, `UsageReportController`, etc.) | **No** — these expose m2munity's own domain model (SIMs, tariffs, customers, dealers, usage tables). A different Laravel app has different tables and business rules; there is nothing to "install" here that would mean anything elsewhere. |

This is the same split every real integration SDK makes: Stripe's package handles auth and the HTTP
client, never your product's checkout logic; Sentry's package handles error capture and transport,
never your app's business exceptions. A `chatsaas/laravel-widget` package should do the same —
ship the protocol/auth layer, and let the host app register its own domain-specific tool endpoints
against a contract the package defines.

### What the package WOULD provide (mirroring `spatie/laravel-permission`'s shape)

Spatie's package is a good model because it solves the exact same kind of problem: something every
Laravel app needs a *slightly different* flavor of (roles/permissions there; assistant identity here),
shipped as installable, configurable, opinionated-but-overridable plumbing.

```
chatsaas/laravel-widget/
├── config/
│   └── chatsaas.php              # publishable config (see below)
├── src/
│   ├── ChatsaasServiceProvider.php
│   ├── AssistantIdentity.php     # generic JWT minting, reads config for TTL/claims
│   ├── Http/
│   │   ├── Middleware/
│   │   │   └── AssistantServiceAuth.php   # service-key check + impersonation, generic
│   │   └── Controllers/
│   │       └── ChatTokenController.php    # the /session/chat-token equivalent
│   ├── Contracts/
│   │   └── ResolvesAssistantUser.php      # host app implements: find-user-by-id
│   └── Facades/
│       └── Chatsaas.php                   # optional convenience facade
├── resources/
│   └── views/
│       └── widget-embed.blade.php         # publishable, so hosts can theme/adjust
├── routes/
│   └── chatsaas.php                       # the token-refresh route, publishable
└── tests/
```

### Config file (`config/chatsaas.php`), the "spatie-style" contract

```php
return [
    'identity_secret' => env('CHATSAAS_IDENTITY_SECRET'),
    'service_key' => env('CHATSAAS_SERVICE_KEY'),
    'widget_key' => env('CHATSAAS_WIDGET_KEY'),
    'api_endpoint' => env('CHATSAAS_API_ENDPOINT'),
    'widget_js' => env('CHATSAAS_WIDGET_JS'),
    'token_ttl' => 600, // seconds

    // The host app tells the package how to find/authorize a user — no assumptions
    // about the host's User model, guard, or permission system baked into the package.
    'user_model' => \App\Models\User::class,
    'gate_ability' => 'use-chat-assistant', // host defines this however it likes
];
```

### Service provider responsibilities

- Merge/publish the config file (`php artisan vendor:publish --tag=chatsaas-config`)
- Register the `assistant.service` middleware alias
- Register the token-refresh route (publishable so a host can move/rename it)
- Register a `Gate::define()` default for `gate_ability` if the host hasn't defined one, so the
  package works out-of-the-box before the host customizes access rules
- Publish the widget-embed Blade partial so a host includes `@include('chatsaas::widget')` in their
  layout, instead of hand-copying the `<script>` block

### What the host app (any client, including m2munity itself) still owns

- Its own REST endpoints exposing whatever data THEIR assistant should answer questions about —
  the package has no opinion on this; it only makes those endpoints easy to protect correctly
- Its own decision for `gate_ability` — who's allowed to use the assistant at all
- Its own `ResolvesAssistantUser` implementation if their `User` model doesn't work with a plain
  `find($id)` (multi-guard apps, UUID keys, etc.) — optional, defaults to `find($id)` on
  `user_model`

---

## 4. Designing for zero-touch, self-service onboarding

Because we will never see a client's server, config, or database, every piece of setup has to be
something the CLIENT does entirely on their own, guided only by an install command and a README —
the same bar `laravel/sanctum`, `laravel/cashier`, and `spatie/laravel-permission` all clear.

### 4.1 One install command does everything

```
composer require chatsaas/laravel-widget
php artisan chatsaas:install
```

`chatsaas:install` should, without any prompts requiring us:
1. Publish `config/chatsaas.php` and the widget-embed Blade partial.
2. Generate `CHATSAAS_IDENTITY_SECRET` and `CHATSAAS_SERVICE_KEY` (`Str::random(32)`) and append
   them to the client's `.env` — mirroring how `php artisan key:generate` already works for
   `APP_KEY`. The client never has to invent or transmit these to us.
3. Print the two generated secrets to the terminal with a short, copy-pasteable instruction:
   *"Paste these into your Chatsaas dashboard under Settings → this app's integration."* This is
   the ONLY manual step, and it happens entirely on the CLIENT's side (their terminal → their
   Chatsaas dashboard) — we never receive, request, or need to see a client's secret, database
   credentials, or server access at any point.
4. Ask one question interactively (or accept flags for CI use): which Eloquent model is "User," and
   optionally which Gate ability governs access — writing the answer straight into the published
   config.

### 4.2 No assumption about the host's permission system

m2munity uses Spatie's roles/permissions, but a client app might not. The package must not import
or require Spatie at all. Access control has to be expressed as a **plain Laravel Gate ability**,
which every Laravel app has regardless of what permission package (if any) sits behind it:

```php
// config/chatsaas.php
'gate_ability' => 'use-chatsaas',
```

The client defines what that ability means however suits their app:

```php
// a Spatie-based app (like m2munity)
Gate::define('use-chatsaas', fn ($user) => $user->hasDirectPermission('Use AI Assistant'));

// an app with no permission package at all
Gate::define('use-chatsaas', fn ($user) => $user->is_admin);
```

**Security default matters here**: if a client installs the package and never defines this Gate,
the package must default to **deny-all**, not allow-all. `chatsaas:install` should register a
default `Gate::define($ability, fn () => false)` alongside the published config, with a loud
comment telling the client to change it — a package we hand to many different clients must never
ship "wide open" as its out-of-the-box state.

### 4.3 The business API is always the client's own code — document this clearly

Because we can't know a client's schema, the package's docs need to be explicit that step 2 of
integration is: *"protect your own API routes with our `chatsaas.service` middleware — that's the
entire contract."* No tool-registration DSL or endpoint-discovery magic is needed; the pattern is
just "put our middleware in front of whatever you already built," identical to how m2munity's own
`SimController`/`CustomerController`/`UsageReportController` sit behind it today. This keeps the
package small and keeps us out of ever needing to understand a client's domain model.

### 4.4 Versioning, support, and docs — now load-bearing, not optional

Once other companies depend on this, the normal internal-tool slack (skip the README, break a
config key without a changelog) isn't available:

- **Semver + a real CHANGELOG.md**, since a breaking config-key rename now breaks someone else's
  production app, not just ours.
- **A README that assumes zero context** — install command, the two secrets, the Gate example, a
  minimal "protect one endpoint" example, and a troubleshooting table (mirroring the failure-mode
  table already in `docs/telenor/yuka-assistant-integration.md`, generalized).
- **Automated tests that run against a bare Laravel skeleton app** (Orchestra Testbench is the
  standard tool for this), not against m2munity's own database/fixtures — the package must prove it
  works in an app that looks nothing like ours.
- **A support channel** for client developers who get stuck installing it, since — by design — we
  can't just log into their server and look.

### Recommendation

Given the actual goal (a self-service capability for any client, not an internal reuse concern),
**this is worth building properly now** — it's the delivery mechanism for the product, not a nice-to-have
refactor. The plumbing is already small and clean enough that extraction itself is low-risk; the real
work is in 4.1–4.4: the install command, the Gate-based (not Spatie-based) access contract, a safe
default-deny posture, and genuine documentation/tests for an audience that isn't us. I'd scope a
first version narrowly — identity handshake + service-auth middleware + install command + docs —
and deliberately leave any "richer tool registration" ideas out of v1 until a real client's
integration surfaces a need for one.

**Decisions made (2026-09):** private distribution for now (revisit public Packagist later);
docs-only support for now (a thorough README/troubleshooting page, no ticket system or health-check
ping yet — revisit if support load ever justifies it).

---

## 5. Package creation & private-distribution plan

### 5.1 Is private Composer distribution possible without a submodule? Yes.

Composer supports installing a private package straight from a private Git repository — no
Packagist account, no private registry server (Satis/Private Packagist), no submodule needed. This
already works today with plain Composer:

```json
// in the CLIENT app's composer.json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:diagonal-software/chatsaas-laravel-widget.git"
        }
    ],
    "require": {
        "chatsaas/laravel-widget": "^1.0"
    }
}
```

`composer require chatsaas/laravel-widget` then clones the repo directly (over SSH or an HTTPS
token), reads its `composer.json`, and resolves the version from **git tags** (`v1.0.0`, `v1.1.0`,
...) exactly like a public package. The client needs read access to that one repo (an SSH deploy key
or a scoped access token we hand them once) — nothing else, and no submodule involved. This is the
standard, well-supported way private Laravel packages are distributed (it's literally what
Spatie/Laravel-shop teams use before a package ever goes public).

### 5.2 The submodule idea — when it's actually needed, and how it would work

A git submodule only becomes necessary if a client's environment **can't reach our private git host
at install/deploy time at all** (e.g. a locked-down CI runner with no outbound access, or a client
who wants the package's source vendored directly into their own repo rather than fetched at build
time). For that case:

```bash
# inside the client's own app repo
git submodule add git@github.com:diagonal-software/chatsaas-laravel-widget.git packages/chatsaas-laravel-widget
```

Then Composer's **path** repository type treats that submodule folder as a local package — no
network fetch at all during `composer install`, since the code is already sitting in their repo:

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

This is documented as **the fallback**, not the default — it adds real overhead for the client
(they now own updating the submodule pointer themselves, `git submodule update --remote`, and it's
one more concept a client's developer has to understand). The plain VCS-repository method in 5.1
should be the one in the main README; the submodule/path method goes in a "network-restricted
environments" section for the rare case it's needed.

### 5.3 Steps to actually create the package

1. **Create a new private repo** — `diagonal-software/chatsaas-laravel-widget` (or similar), empty
   except for a standard package skeleton (`composer.json`, `src/`, `config/`, `README.md`,
   `LICENSE`, a `.gitignore`).
2. **Extract the generic plumbing** from m2munity into `src/`, generalizing only what currently
   hardcodes m2munity specifics:
   - `AssistantIdentity.php` → drop in mostly as-is; swap `config('services.assistant.*')` reads for
     `config('chatsaas.*')`.
   - `AssistantServiceAuth.php` → same swap, plus replace the m2munity-specific "just impersonate
     the found user" step with the `user_model` + optional `ResolvesAssistantUser` contract from
     section 3.
   - The widget-embed `<script>` block from `layouts/app.blade.php` → its own publishable Blade
     partial.
   - The `/session/chat-token` route → a package route, publishable so a client can move/rename it.
3. **Write `ChatsaasServiceProvider`**: registers the middleware alias, merges config, registers the
   default-deny `Gate::define()` fallback (section 4.2), registers the package route, makes the
   config/views/routes publishable via `vendor:publish` tags.
4. **Write `chatsaas:install`** (an Artisan command inside the package): publishes config, generates
   and appends the two secrets to `.env` if not already present (idempotent — safe to re-run), and
   prints the copy-paste instructions for the client's Chatsaas dashboard.
5. **Set up Orchestra Testbench** so the package's tests run against a minimal, bare Laravel app
   (not m2munity) — proves it works for someone else's schema/setup, not just ours.
6. **Write the README** — install command (5.1's snippet), the two secrets and where they go, one
   `Gate::define()` example, one "protect an endpoint" example, and the submodule fallback (5.2) in
   its own clearly-labeled section. This README **is** the entire support surface for now, per the
   docs-only decision — it needs to actually anticipate the mistakes a first-time integrator will
   make (wrong Gate ability name, forgetting to define the Gate at all, secret mismatch between
   `.env` and the dashboard), not just describe the happy path.
7. **Tag `v0.1.0`**, point m2munity's own `composer.json` at it via the VCS-repository method (so
   m2munity becomes the first real "client" of its own package — the best possible test), and swap
   m2munity's local `AssistantIdentity`/`AssistantServiceAuth` for the package's versions.
8. **Tag `v1.0.0`** once that swap is verified working end-to-end in m2munity, and hand the repo
   access + README to the first external client.

### 5.4 What stays out of scope for this first version

- Public Packagist listing (revisit later per the decision above)
- Any install health-check / "phone home" status ping (revisit if docs-only support proves
  insufficient)
- A hosted Chatsaas-side dashboard integration beyond what already exists
- Tool-registration DSL / endpoint auto-discovery (section 4.3 — client always writes their own
  protected endpoints; the package never tries to infer their schema)
