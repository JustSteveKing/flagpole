# Flagpole

[![CI](https://github.com/JustSteveKing/flagpole/actions/workflows/ci-tests.yml/badge.svg)](https://github.com/JustSteveKing/flagpole/actions/workflows/ci-tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A feature flag service you can read from end to end. You manage projects, environments and flags
through a JSON API, and your applications evaluate them with any [OpenFeature](https://openfeature.dev)
SDK, over OpenFeature's Remote Evaluation Protocol (OFREP). Flagpole has no SDK of its own, and
doesn't need one.

It is the worked example for [Kit](https://github.com/JustSteveKing/kit), a Laravel starter kit
for APIs built on the published HTTP standards. A starter kit's README can tell you it has ETags,
idempotency keys and scoped tokens. Flagpole shows them in an application that actually needs
them.

## Why a flag service

Most example apps are a to-do list with the interesting parts bolted on. A flag service needs them
for real:

- **Clients poll.** Every application asks again every few seconds, so a cheap "nothing changed"
  matters. Flagpole answers with a `304` until something in the caller's environment changes.
- **Two people editing the same rules can ship the wrong thing.** Every change carries the `ETag`
  it was based on, and a stale one is refused instead of silently winning.
- **Keys end up in browsers.** An SDK key can evaluate one environment's flags and do nothing
  else: no reading rules, no changes, no other environment.
- **Scripts retry.** A deploy that creates a flag and times out can try again with the same
  `Idempotency-Key` and get the first answer back, not a second flag.
- **Changes need an audit trail.** Every change is kept, with who made it and with which key, and
  sent as a signed webhook.

## Try it

```bash
git clone https://github.com/JustSteveKing/flagpole.git
cd flagpole
composer setup
php artisan db:seed
php artisan serve
```

The seeder creates a demo project with a flag of each type and prints an SDK key. From there,
[Getting started](docs/flagpole/getting-started.md) evaluates the flags, changes one, and watches
the answer change, in a few minutes.

## What's inside

| Kit gives you | Flagpole uses it for |
| --- | --- |
| ETags and `304` | Every `GET`, and the bulk evaluation that clients poll |
| `If-Match`, required | Every change to a project, environment, flag or its settings |
| JSON Merge Patch | Changes: `{"enabled": false}` turns a flag off |
| Scoped API keys | A CI key that can create flags and nothing more; SDK keys that can only evaluate |
| `Idempotency-Key` | Creating projects, environments, flags and SDK keys from scripts that retry |
| Cursor pagination with `Link` headers | Every list, and a flag's history, which only grows |
| Signed CloudEvents webhooks | An event for every change to a flag or its settings |
| Problem details in English and Spanish | Every error in the management API |
| Scribe, contract tests and [legible](https://github.com/JustSteveKing/legible) | An API reference checked against real responses, scoring 100 for agent readiness |

On top of that, Flagpole adds its own:

- **Targeting rules and percentage rollouts.** Rules match on anything in the context, and a
  rollout puts each user in the same variant on every evaluation and every server.
- **OFREP, checked against OpenFeature's own document.** The contract tests validate Flagpole's
  answers, errors included, against OFREP's published OpenAPI document, and
  [`examples/openfeature`](examples/openfeature/README.md) runs OpenFeature's real providers
  against the demo.
- **A design record.** Every area was specified in an RFC before it was built, and every decision
  is an ADR with the alternatives it beat.

Two things are not Kit's way, on purpose. The OFREP routes answer errors in OFREP's format, because
that is all OpenFeature's providers read. And bulk evaluation answers a `POST` with `304`, which
HTTP does not allow but OFREP specifies. Both are ADRs, with the reasoning.

## How it was built

Flagpole was specified before a line of it was written, with [ArchDoc](https://archdoc.dev). The
record is in [`architecture/`](architecture/INDEX.md): start with RFC-0001, which sets the scope,
and RFC-0002, which says how it sits on Kit.

The commit history is meant to be read in order. The first commit is Kit exactly as
`laravel new --using=juststeveking/kit` wrote it, and every commit after it adds one thing, so you
can diff any point against plain Kit and see what an application on top of it really involves.

Kit is upstream. Flagpole changes Kit's own files only where it has to, in commits of their own.
Anything wrong in Kit for every application gets fixed in Kit, released, and brought back. Building
Flagpole found real problems that way: browsers could not send conditional requests through Kit's
CORS settings, a token without the right ability got a `404` instead of a `403` on bound routes,
and a new application failed its secrets scan on its very first push. Each is fixed in Kit now, as
are the problems it found in [api-toolbox](https://github.com/JustSteveKing/api-toolbox).

It was built with coding agents, and [`AGENTS.md`](AGENTS.md) is what they read: Flagpole's own
rules first, then Kit's. It is worth reading even if you never use an agent, because it is the
shortest list of what not to break.

## Documentation

- **[Flagpole's guides](docs/flagpole/README.md)**: getting started, managing flags, targeting,
  evaluating with OpenFeature, and webhooks
- **The API reference**: at `/docs/index.html` while Flagpole runs, and in
  [`public/docs/openapi.yaml`](public/docs/openapi.yaml)
- **[Kit's guides](docs/README.md)**: authentication, errors, the HTTP standards, deployment and CI
- **[The design record](architecture/INDEX.md)**: why it works the way it does

## What it leaves out

There is no web interface; the API reference is how you explore it. Projects have one owner, and
there are no teams. Clients poll, and there are no event streams. Flagpole does not record which
flags were evaluated, or for whom. It runs locally, and there is no hosted demo. Each of those is
a decision in RFC-0001, with the reason.

## Contributing

Bug reports, fixes and documentation are welcome, and so are ideas, as long as they start as an
issue rather than a pull request: a new feature here means a new RFC first.
[CONTRIBUTING.md](CONTRIBUTING.md) explains how, including how to run every check locally.

Everyone taking part is expected to follow the [code of conduct](CODE_OF_CONDUCT.md). To report a
security problem, see [SECURITY.md](SECURITY.md) and please do not open a public issue.

## Credits

Built by [Steve McDougall](https://github.com/JustSteveKing) on
[Kit](https://github.com/JustSteveKing/kit), [Laravel](https://laravel.com) and
[api-toolbox](https://github.com/JustSteveKing/api-toolbox). OFREP and its OpenAPI document are
OpenFeature's; the copy in [`tests/Fixtures/ofrep`](tests/Fixtures/ofrep/README.md) is used under
the Apache License 2.0.

## License

MIT. See [LICENSE](LICENSE).
