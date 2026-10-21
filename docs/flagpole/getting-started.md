# Getting started

Flagpole runs on your machine. There is no hosted demo, so this is how you try it.

## Install

You need PHP 8.5 and Composer. Local development uses SQLite, so there is no database to set up.

```bash
git clone https://github.com/JustSteveKing/flagpole.git
cd flagpole
composer setup
php artisan db:seed
```

`composer setup` installs the dependencies, writes `.env`, generates the app key and migrates a
SQLite database. `db:seed` adds the demo:

- an owner, `demo@flagpole.test`, with the password `password`
- a `checkout` project with `development` and `production` environments
- a flag of each type, with rules in development: `new-checkout` (boolean), `theme` (string),
  `basket-limit` (integer), `discount-rate` (float) and `payment-providers` (object)
- an SDK key for each environment, which it prints

Copy the development key now. Flagpole stores only a hash of it, so it is never shown again.

## Run it

Two processes, in two terminals:

```bash
php artisan serve        # the API, on http://127.0.0.1:8000
php artisan queue:work   # webhook deliveries and emails are queued
```

## Evaluate the flags

This is the request an application makes, through an OpenFeature provider. Every flag in
development, for one user:

```bash
curl -i -X POST http://127.0.0.1:8000/ofrep/v1/evaluate/flags \
  -H "Authorization: Bearer <development SDK key>" \
  -H "Content-Type: application/json" \
  -d '{"context": {"targetingKey": "user-1", "email": "jane@flagpole.test", "country": "GB"}}'
```

```json
{"flags": [
  {"key": "basket-limit", "value": 20, "variant": "standard", "reason": "UNKNOWN"},
  {"key": "discount-rate", "value": 0.0, "variant": "none", "reason": "UNKNOWN"},
  {"key": "new-checkout", "value": true, "variant": "on", "reason": "TARGETING_MATCH"},
  {"key": "payment-providers", "value": {"providers": ["card"]}, "variant": "cards", "reason": "DISABLED"},
  {"key": "theme", "value": "#00aa55", "variant": "green", "reason": "TARGETING_MATCH"}
]}
```

`new-checkout` is on because the demo has a rule for `@flagpole.test` addresses, and `theme` is
green because of a rule for the UK. [Targeting](targeting.md) explains every reason you see here,
`UNKNOWN` included.

Send the response's `ETag` back in `If-None-Match` and you get a `304` with no body until something
changes. That is how clients poll cheaply.

## Change a flag

Sign in as the demo owner. The session token comes back in `meta.token`:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo@flagpole.test", "password": "password", "device_name": "cli"}'
```

Fetch the flag's settings in development, and note the `ETag`:

```bash
curl -i http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/development \
  -H "Authorization: Bearer <token>"
```

Switch it off, sending that `ETag` in `If-Match`:

```bash
curl -X PATCH http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/development \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/merge-patch+json" \
  -H 'If-Match: "<the ETag>"' \
  -d '{"enabled": false}'
```

Run the evaluation again and `new-checkout` is `false`, with the reason `DISABLED`. Leave out
`If-Match` and the change is refused with a `428`; send an `ETag` that is out of date and it is a
`412`. Two people editing the same flag cannot overwrite each other without knowing.

## Explore the API

The full reference, generated from the code and checked against real responses, is at
`http://127.0.0.1:8000/docs/index.html` while the server runs. The OpenAPI document is
`public/docs/openapi.yaml`, if you would rather load it into a client.

## Next

- [Managing flags](managing-flags.md): projects, environments, flags, history, and keys for
  scripts
- [Targeting](targeting.md): rules, percentage rollouts, and what each reason means
- [Evaluating flags](evaluating.md): SDK keys and the OpenFeature providers
- [Webhooks](webhooks.md): hearing about every change
