# Changelog

All notable changes to this package are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

- `chatsaas:install` now asks for the Eloquent user model's fully-qualified class name
  explicitly (rather than an ambiguous "model name"), so the exact `::class` reference written
  into config is unambiguous. Strips a leading `\` if the answer includes one.
- Added an interactive, opt-in `CHATSAAS_ALLOW_BY_DEFAULT` fallback for hosts with no permission
  package installed: `chatsaas:install` detects the absence of `spatie/laravel-permission` and
  asks whether to allow every authenticated user until real access control is configured
  (`--allow-by-default` for non-interactive use). The package still denies by default unless this
  is explicitly opted into — see `config/chatsaas.php`'s `allow_by_default` key and
  `ChatsaasServiceProvider`'s fallback Gate.
- README: added a Laravel version compatibility table (10.x-13.x) documenting where to register
  the service provider manually if package auto-discovery is disabled, and clarified that the
  `chatsaas.service` middleware alias never depends on `app/Http/Kernel.php` on any version.

## [0.1.0]

Initial extraction from the m2munity Yuka assistant integration.

- `AssistantIdentity`: mints a short-lived HS256 identity token for the assistant widget.
- `AssistantServiceAuth` middleware (`chatsaas.service`): verifies the shared service key,
  checks the host app's `gate_ability`, then impersonates the resolved user.
- `chatsaas:install` Artisan command: publishes config/views, generates and appends
  `CHATSAAS_IDENTITY_SECRET` / `CHATSAAS_SERVICE_KEY` to `.env` (idempotent), and prompts for
  the host's Eloquent user model.
- Default-deny `Gate::define()` fallback registered by `ChatsaasServiceProvider` when the host
  hasn't defined `chatsaas.gate_ability` themselves.
- Publishable `chatsaas::widget` Blade partial for the widget embed script.
- Orchestra Testbench test suite covering the auth middleware, default-deny behavior, identity
  token minting, and install-command idempotency.
