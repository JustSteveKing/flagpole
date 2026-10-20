---
id: REF-0002
title: What Kit gives an application
verified: 2026-10-20
---

# REF-0002: What Kit gives an application

Flagpole was created with `laravel new flagpole --using=juststeveking/kit`. This is what that
command produced on the verified date, as far as Flagpole's design depends on it. Kit's own guides
came with it, in `docs/`.

## Accounts and tokens

- Users register, log in and verify their email over the API. IDs are ULIDs.
- Register and login return a session token that lasts two hours by default.
- `POST /v1/auth/tokens` makes an API key: a Sanctum token whose abilities are a subset of the
  creating token's. API keys last 90 days unless given `expires_at`, at most a year.
- Abilities are listed in `config/sanctum.php`. `available` is everything a token can hold, and
  `default` is what a session token gets.
- Sanctum's `personal_access_tokens` table has a polymorphic `tokenable`, created with
  `ulidMorphs`. Users are the only tokenable model Kit ships.

## Building endpoints

- Routes live under `/v1`, with no `/api` prefix.
- An endpoint is an invokable controller, a Form Request, a readonly payload, a JSON resource, a
  route and a Pest test. `php artisan make:endpoint` writes all of them, behind
  `throttle:auth-protected` and an ability.
- Every error is `application/problem+json`, typed from the apiguide.dev catalogue, with `detail`
  translated from `Accept-Language`. English and Spanish ship.

## HTTP standards, from api-toolbox

- `Idempotency-Key` on creating requests, with a replay window of a day.
- Cursor pagination with `Link` headers.
- ETags and `Last-Modified` on `GET` and `HEAD` only, from a model's validators.
  `preconditions.required` answers `428` when a write has no `If-Match` and `412` when it is stale.
- JSON merge patch.
- `Deprecation` and `Sunset` from route attributes, with Kit answering `410` after the sunset date.
- Request IDs and W3C trace context, and an API catalog at `/.well-known/api-catalog`.
- `consumes:` media type checks, answering `415`.

## Webhooks

- Subscriptions belong to a user, and webhook events are dispatched for a user.
- Events are CloudEvents in structured mode, signed with RFC 9421 and an HMAC.
- An event is a class implementing `App\Webhooks\WebhookEvent`, and its type must be listed in
  `events` in `config/webhooks.php`. The list ships empty.
- Kit retries on a published schedule, keeps every attempt, and disables a subscription after ten
  failures in a row. Outbound addresses are checked against private networks.

## Documentation and checks

- Scribe builds the reference and the OpenAPI spec from attributes on the controllers.
- Contract tests compare documented response schemas with real responses.
- legible scores the spec, and CI fails below 95.
- PHPStan at level 10, Pint with the PER preset, Rector, and Laravel Vet on dependencies.

## Running it

- A Dockerfile for Octane on FrankenPHP, which CI builds and boots on every push.
- Emails and webhook deliveries are queued, so a worker runs beside the API.

## Sources

- The scaffolded repository, read on the verified date: `config/sanctum.php`, `config/webhooks.php`,
  `routes/`, `app/Webhooks/`, the `personal_access_tokens` migration, and `php artisan route:list`.
- Kit's guides, copied into `docs/` by the installer.
- https://github.com/JustSteveKing/kit
