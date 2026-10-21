# Contributing to Flagpole

Thanks for wanting to help. Flagpole is an example as much as it is an application, so a
contribution is judged on two things: whether it is right, and whether someone reading the code to
learn from it will understand it.

## What helps most

- **Bug reports**, with the request you sent and the response you got. If an OpenFeature provider
  misbehaves against Flagpole, say which provider and version.
- **Fixes**, with a test that fails without them.
- **Documentation**: anything in [`docs/flagpole/`](docs/flagpole/README.md) that was wrong,
  unclear, or missing when you needed it.
- **OFREP conformance**: anywhere Flagpole answers differently from what OFREP specifies.

## Ideas and new features

Open an issue first, and please do not start with a pull request. Flagpole was specified before it
was built, and a new feature starts the same way: as an RFC in [`architecture/`](architecture/INDEX.md),
discussed and accepted before any code is written. An issue is where we work out whether it should
be one.

Some things are left out on purpose: teams, a web interface, event streams, recording evaluations
and a hosted demo. [RFC-0001](architecture/rfc/0001-flagpole-v1.md) gives the reason for each.
Proposing one of them is fine, but it means arguing with that reasoning, in a new RFC.

## Problems that belong elsewhere

Flagpole is built on [Kit](https://github.com/JustSteveKing/kit), and Kit is upstream. If something
would be wrong in every application made from Kit, such as authentication, errors, webhooks or CI,
it is a Kit issue, and fixing it there fixes it here on the next release. The same goes for
[api-toolbox](https://github.com/JustSteveKing/api-toolbox), which provides the HTTP standards.
Not sure which it is? Open the issue here and we will move it.

## Setting up

You need PHP 8.5 and Composer:

```bash
git clone https://github.com/JustSteveKing/flagpole.git
cd flagpole
composer setup
php artisan db:seed
```

[Getting started](docs/flagpole/getting-started.md) has the rest.

## Before you open a pull request

CI runs all of these, and a pull request that fails any of them is not ready:

```bash
composer lint            # Pint
composer rector:check    # Rector
composer stan            # PHPStan, level 10
php artisan test         # Pest, including the contract tests against OFREP's own OpenAPI document
legible check public/docs/openapi.yaml
archdoc lint && archdoc index --check && archdoc glossary --check
```

[legible](https://github.com/JustSteveKing/legible/releases) and [ArchDoc](https://archdoc.dev) are
single binaries. To fix Pint and Rector findings, run `composer rector` and then `composer pint`.

If you changed how evaluation behaves, also run the OpenFeature check in
[`examples/openfeature`](examples/openfeature/README.md) against the seeded demo.

## How changes are made

- **One thing per commit.** The history is meant to be read in order, as a walkthrough, so each
  commit should do one thing and say why in its message.
- **The spec moves with the code.** If you change behaviour, update the page in
  `architecture/spec/` that describes it in the same commit. Accepted RFCs and ADRs are never
  edited; a new document supersedes them.
- **Kit's files change only when they must.** If Flagpole needs a change to a file that came from
  Kit, put it in a commit of its own and say why, so it is easy to tell apart from Flagpole's code.
- **Generated files.** The test suite regenerates `public/docs/` and `.scribe/` with new example
  values every run. Commit them when you changed an endpoint or its documentation, and discard them
  otherwise.

[`AGENTS.md`](AGENTS.md) lists the rules that are easy to break, such as why every write goes
through `FlagWriter` and why `Bucket` must not use string functions. It is written for coding
agents, and it is just as useful for people.

## Conduct

Everyone taking part is expected to follow the [code of conduct](CODE_OF_CONDUCT.md).
