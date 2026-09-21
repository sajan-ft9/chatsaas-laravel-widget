# Changelog

All notable changes to this package are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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
