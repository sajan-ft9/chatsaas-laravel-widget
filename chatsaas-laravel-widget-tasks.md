# Chatsaas Laravel Widget — Package Build Task List

**Purpose:** a self-contained task list for building the `chatsaas/laravel-widget` package in its
own repository/directory, separate from the m2munity codebase. Copy this file into the new repo
(e.g. as `TASKS.md`) and work through it top to bottom — each task is small and independently
verifiable, same working style used for the rest of this project's phased work.

**Design reference:** `docs/assistant-widget-package-plan.md` in the m2munity repo has the full
rationale, config shape, and decisions (private distribution, docs-only support) behind every task
below. Re-read it before starting if any task here feels under-specified.

**Source material to copy from (in the m2munity repo, read-only reference — do not edit these):**
- `code/app/Support/AssistantIdentity.php`
- `code/app/Http/Middleware/AssistantServiceAuth.php`
- `code/config/services.php` (the `assistant.*` block)
- `code/resources/views/layouts/app.blade.php` (the widget `<script>` block, `@auth`/permission-gated section)
- `code/routes/web.php` (the `/session/chat-token` route)
- `code/app/Constants/PermissionsList.php` (`USE_AI_ASSISTANT` — for reference only; the package
  itself must NOT depend on Spatie or this constant, see Task 2.3)

---

## Phase 0 — New repo setup

- [x] **0.1** Create a new private Git repository. Done as `sajan-ft9/chatsaas-laravel-widget` on
  GitHub (not yet the `diagonal-software` org — revisit if/when this should move under the org).
- [x] **0.2** Scaffold a standard Composer package layout:
  ```
  chatsaas-laravel-widget/
  ├── composer.json
  ├── README.md
  ├── CHANGELOG.md
  ├── LICENSE
  ├── .gitignore
  ├── config/
  │   └── chatsaas.php
  ├── src/
  │   ├── ChatsaasServiceProvider.php
  │   ├── AssistantIdentity.php
  │   ├── Http/
  │   │   ├── Middleware/
  │   │   │   └── AssistantServiceAuth.php
  │   │   └── Controllers/
  │   │       └── ChatTokenController.php
  │   └── Console/
  │       └── InstallCommand.php
  ├── resources/
  │   └── views/
  │       └── widget-embed.blade.php
  ├── routes/
  │   └── chatsaas.php
  └── tests/
      ├── TestCase.php
      └── Feature/
  ```
- [x] **0.3** Write `composer.json`: package name `chatsaas/laravel-widget`, `type: library`,
  `autoload.psr-4` for `Chatsaas\LaravelWidget\` → `src/`, `autoload-dev.psr-4` for tests,
  `require` on `illuminate/support` + `firebase/php-jwt` (or whatever JWT lib `AssistantIdentity`
  currently uses — check the source file), `require-dev` on `orchestra/testbench` + `phpunit/phpunit`,
  and a `extra.laravel.providers`/`aliases` block so Laravel's package auto-discovery registers
  `ChatsaasServiceProvider` without the client editing `config/app.php`.
- [x] **0.4** Confirm `composer validate` passes and `composer install` succeeds in the new repo
  before writing any real code.

## Phase 1 — Extract & generalize the plumbing

- [x] **1.1** Copy `AssistantIdentity.php` into `src/`, namespace to `Chatsaas\LaravelWidget`.
  Replace every `config('services.assistant.*')` read with `config('chatsaas.*')`. No other logic
  changes — this class should not know anything about m2munity.
- [x] **1.2** Copy `AssistantServiceAuth.php` into `src/Http/Middleware/`, same config-key swap.
  Replace the hardcoded `User::find($request->input('userId'))` with:
  ```php
  $model = config('chatsaas.user_model');
  $user = $model::find($request->input('userId'));
  ```
  (Keep it this simple for v1 — no separate `ResolvesAssistantUser` interface unless a real need
  shows up; a config-driven model class is enough per the design doc's "don't overbuild v1" call.)
- [x] **1.3** After resolving `$user`, add the Gate check before impersonating:
  ```php
  if (!$user || !\Illuminate\Support\Facades\Gate::forUser($user)->allows(config('chatsaas.gate_ability'))) {
      return response()->json(['error' => 'Forbidden'], 403);
  }
  ```
- [x] **1.4** Write `config/chatsaas.php` with exactly the keys from the design doc: `identity_secret`,
  `service_key`, `widget_key`, `api_endpoint`, `widget_js`, `token_ttl` (default 600),
  `user_model` (default `\App\Models\User::class` — this is safe as a *default string*, the package
  doesn't need the class to exist until runtime), `gate_ability` (default `'use-chatsaas'`).
- [x] **1.5** Extract the widget `<script>` block from m2munity's `layouts/app.blade.php` into
  `resources/views/widget-embed.blade.php`, parameterized entirely from `config('chatsaas.*')` —
  no m2munity-specific text/branding left in it.
- [x] **1.6** Extract the `/session/chat-token` route + its closure into `routes/chatsaas.php` and a
  proper `ChatTokenController@refresh` method (cleaner than an inline closure for a package).

## Phase 2 — Service provider

- [x] **2.1** Write `ChatsaasServiceProvider::register()`: merge `config/chatsaas.php`.
- [x] **2.2** Write `ChatsaasServiceProvider::boot()`:
  - Publish config (`vendor:publish --tag=chatsaas-config`)
  - Publish the widget-embed view (`--tag=chatsaas-views`)
  - Load `routes/chatsaas.php`
  - Register the `chatsaas.service` middleware alias pointing at `AssistantServiceAuth`
  - Register `InstallCommand` when running in console
- [x] **2.3** **Default-deny safety net**: in `boot()`, register a fallback Gate definition ONLY if
  the host app hasn't already defined one for `config('chatsaas.gate_ability')`:
  ```php
  if (!Gate::has(config('chatsaas.gate_ability'))) {
      Gate::define(config('chatsaas.gate_ability'), fn () => false);
  }
  ```
  Verify this actually blocks access before a host defines their own Gate — write a test for it
  specifically (Phase 4), since this is the single most important security property of the package.

## Phase 3 — Install command

- [x] **3.1** Write `chatsaas:install` (`InstallCommand`):
  - Call `$this->call('vendor:publish', ['--tag' => 'chatsaas-config'])` and `chatsaas-views`.
  - Check if `CHATSAAS_IDENTITY_SECRET` and `CHATSAAS_SERVICE_KEY` already exist in `.env`; if not,
    generate each via `Str::random(32)` and append to `.env` (idempotent — re-running the command
    must never overwrite an existing secret).
  - Print both secrets to the console with the exact copy-paste instruction: *"Paste these into your
    Chatsaas dashboard under Settings → this app's integration."*
  - Prompt (or accept a `--user-model=` option for non-interactive/CI use) for the Eloquent user
    class, defaulting to `App\Models\User`, and write it into the published config file (a simple
    string replace on the published `config/chatsaas.php` is fine for v1).
  - Print a reminder that `gate_ability` defaults to deny-all and MUST be defined in the host's own
    `AppServiceProvider` before the assistant will work for anyone — include the exact
    `Gate::define(...)` snippet in the command's own output, not just the README.
- [x] **3.2** Manually run `php artisan chatsaas:install` twice in a row in a scratch Laravel app to
  confirm idempotency (second run doesn't duplicate `.env` entries or crash).

## Phase 4 — Tests (Orchestra Testbench)

- [x] **4.1** Set up `tests/TestCase.php` extending `Orchestra\Testbench\TestCase`, registering
  `ChatsaasServiceProvider` and a minimal in-memory `User` model/migration for the test app —
  deliberately NOT reusing anything from m2munity.
- [x] **4.2** Test: `AssistantServiceAuth` rejects a request with a missing/wrong service key (401).
- [x] **4.3** Test: rejects an unknown `userId` (403).
- [x] **4.4** Test: **default-deny** — a valid user, valid service key, but no `Gate::define()` set
  up at all → still rejected (403). This is the test that proves Task 2.3 actually works.
- [x] **4.5** Test: once the test app defines `Gate::define('use-chatsaas', fn () => true)`, the same
  request succeeds and the request is impersonated as that user (`auth()->id()` matches).
- [x] **4.6** Test: `AssistantIdentity::tokenFor()` produces a JWT that decodes with the configured
  secret and expires per `token_ttl`.
- [x] **4.7** Test: `chatsaas:install` run against a scratch Testbench app writes both secrets into
  `.env` and is a no-op (doesn't duplicate) on a second run.

## Phase 5 — Documentation

- [x] **5.1** Write `README.md`: what this package does (one paragraph), install command, the two
  secrets and exactly where they go (client's `.env` → Chatsaas dashboard), a minimal
  `Gate::define()` example, a minimal "protect one of your own endpoints" example
  (`Route::middleware('chatsaas.service')->get(...)`), and a troubleshooting table covering at least:
  wrong/missing service key, Gate not defined (still blocked after install), secret mismatch between
  `.env` and the dashboard, and widget not rendering (check `gate_ability` on the current user).
  This README **is the entire support surface** per the docs-only decision — write it assuming the
  reader has never seen this package before and has no one to ask.
- [x] **5.2** Add a clearly separate "Network-restricted environments" section documenting the git
  submodule + Composer `path`-repository fallback from the design doc, explicitly labeled as the
  fallback, not the default install path.
- [x] **5.3** Write `CHANGELOG.md` with a `v0.1.0` entry once Phase 4 is green.

## Phase 6 — Dogfood in m2munity

- [ ] **6.0** Resolve the `companyId`/`role` JWT claims gap: the package's `AssistantIdentity`
  dropped these (Spatie/`customer()`-specific, package must stay generic — see 1.1). Before
  reusing them, confirm with the Yuka/widget side whether they're purely display context for the
  assistant (nothing in the REST API path uses them — auth is done via
  `AssistantServiceAuth` re-resolving + impersonating the user, not via JWT claims) or load-bearing
  somewhere non-obvious. If still needed, add an `extra_claims` callable to `config/chatsaas.php`
  (`callable(Authenticatable $user): array`, merged into the token payload in
  `AssistantIdentity::tokenFor()`) and have m2munity's own config supply the Spatie-specific
  closure — do not put `customer()`/`getRoleNames()` back into the package itself.
- [x] **6.1** Tag `v0.1.0` in the new repo. Done ahead of the rest of Phase 6, to unblock
  installing via a real `composer require` (not a local path repo) while dogfooding is pending.
- [ ] **6.2** In the m2munity repo, add the VCS-repository entry to `code/composer.json` pointing at
  the new private repo, and `composer require chatsaas/laravel-widget:^0.1`.
- [ ] **6.3** Run `php artisan chatsaas:install` inside m2munity's own container. Confirm it doesn't
  clash with the existing hand-written `ASSISTANT_*` env vars (either reuse them via `.env` edits or
  confirm the install command's generated `CHATSAAS_*` names are picked up cleanly).
- [ ] **6.4** Add `Gate::define('use-chatsaas', fn ($user) => $user->hasDirectPermission('Use AI Assistant'))`
  to m2munity's `AppServiceProvider` — this replaces the direct `permission:` middleware check in
  `routes/api.php` with the package's Gate-based one.
- [ ] **6.5** Swap m2munity's own `app/Support/AssistantIdentity.php` and
  `app/Http/Middleware/AssistantServiceAuth.php` usages for the package's versions; delete the local
  copies once nothing references them.
- [ ] **6.6** Re-run the existing `AssistantPermissionTest`/`AssistantUsageReportTest` suite against
  the swapped-in package versions — same pass/fail bar as before the swap, zero regressions.
- [ ] **6.7** Manually re-verify the three checks from the earlier live verification (admin allowed,
  a real dealer blocked, granting the Gate/permission to that dealer unblocks them) still hold true
  end-to-end through the package.

## Phase 7 — Release

- [ ] **7.1** Tag `v1.0.0` once Phase 6 is fully green in m2munity.
- [ ] **7.2** Hand the repo's read access (SSH deploy key or scoped token) + the README to the first
  external client.
- [ ] **7.3** Revisit the parked items (public Packagist, install health-check, richer support model)
  only if/when actual usage surfaces a real need — do not build them speculatively (see "Out of
  scope for v1" in the design doc).
