# Flagpole

A feature flag service, built on [Kit](https://github.com/JustSteveKing/kit) as its worked example.
You manage projects, environments and flags through a JSON API, and your applications evaluate
those flags with any [OpenFeature](https://openfeature.dev) SDK, over OpenFeature's Remote
Evaluation Protocol (OFREP). Flagpole has no SDK of its own; it doesn't need one.

It exists to show Kit in a real application, where each part is there because the domain needs it.
Flag clients poll, so ETags and `304` matter. Two people editing the same targeting rules is a real
way to ship the wrong thing, so writes need `If-Match`. An SDK key in a browser should be able to
read flags and nothing else, so keys are scoped. The rest of this page says where each of those
lives.

## Run it

```bash
git clone https://github.com/JustSteveKing/flagpole.git
cd flagpole
composer setup
php artisan db:seed
```

`composer setup` installs, writes `.env`, and migrates a SQLite database. `db:seed` adds a demo
owner (`demo@flagpole.test`, password `password`), a `checkout` project with development and
production, a flag of each type with rules in development, and an SDK key for each environment,
which it prints. Copy the development key.

Then run the API and a worker, which sends webhooks and emails:

```bash
php artisan serve
php artisan queue:work
```

## Evaluate a flag

Every flag in development, for one user:

```bash
curl -i -X POST http://127.0.0.1:8000/ofrep/v1/evaluate/flags \
  -H "Authorization: Bearer <development SDK key>" \
  -H "Content-Type: application/json" \
  -d '{"context": {"targetingKey": "user-1", "email": "jane@flagpole.test", "country": "GB"}}'
```

```json
{"flags": [
  {"key": "new-checkout", "value": true, "variant": "on", "reason": "TARGETING_MATCH"},
  {"key": "theme", "value": "#00aa55", "variant": "green", "reason": "TARGETING_MATCH"}
]}
```

The response has an `ETag`. Send it back in `If-None-Match` and, until a flag or your context
changes, the answer is a `304` with no body.

In an application, OpenFeature's OFREP provider does all of that for you. In a browser:

```js
import { OpenFeature } from '@openfeature/web-sdk';
import { OFREPWebProvider } from '@openfeature/ofrep-web-provider';

await OpenFeature.setContext({ targetingKey: 'user-1', email: 'jane@flagpole.test' });
await OpenFeature.setProviderAndWait(new OFREPWebProvider({
  baseUrl: 'http://127.0.0.1:8000',
  headers: [['Authorization', 'Bearer <SDK key>']],
}));

const newCheckout = OpenFeature.getClient().getBooleanValue('new-checkout', false);
```

On a server it is `@openfeature/server-sdk` with `@openfeature/ofrep-provider`, and the context goes
with each evaluation. Both have been run against Flagpole: every flag type evaluates, the browser
provider polls with `If-None-Match` and gets `304`s, and it notices a flag switched off through the
API on its next poll.

## Change a flag

Sign in for a token, then switch the flag off in development:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo@flagpole.test", "password": "password", "device_name": "cli"}'

curl -i http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/development \
  -H "Authorization: Bearer <token>"

curl -X PATCH http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/development \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/merge-patch+json" \
  -H 'If-Match: "<the ETag from the GET>"' \
  -d '{"enabled": false}'
```

Leave out `If-Match` and you get a `428`. Send one someone else has already changed and you get a
`412`, which is the point: two people editing the same rules cannot overwrite each other.

The full reference is at `http://127.0.0.1:8000/docs/index.html`.

## Where Kit is used

| Kit gives you | Flagpole uses it for |
| --- | --- |
| ETags and `304` | Every `GET`, and the bulk evaluation that clients poll |
| `If-Match` with `preconditions.required` | Every change to a project, environment, flag or its settings |
| JSON Merge Patch | Changes: `{"enabled": false}` turns a flag off |
| Scoped API keys | A CI key that can create flags and nothing more; SDK keys that can only evaluate |
| `Idempotency-Key` | Creating projects, flags and SDK keys from scripts that retry |
| Cursor pagination with `Link` headers | Every list, and a flag's history, which only grows |
| Signed CloudEvents webhooks | `flag.created`, `flag.updated`, `flag.archived`, `flag.restored`, `flag.settings.updated` |
| Problem details in English and Spanish | Every error in the management API |
| Scribe, contract tests and legible | The reference, checked against real responses, at 100 for agent readiness |

Two things are not Kit's way, on purpose. The OFREP routes answer errors in OFREP's format,
because OpenFeature's providers read nothing else. And the bulk route answers a `POST` with `304`,
which HTTP does not allow but OFREP specifies. Both are written up in the ADRs below.

Kit is upstream. Flagpole changes Kit's own code only where it has to, in commits of their own,
and anything wrong in Kit for every application gets fixed in Kit first. Building Flagpole has
already sent several fixes there, and to [api-toolbox](https://github.com/JustSteveKing/api-toolbox).

## How it was designed

Flagpole was specified before it was built, with [ArchDoc](https://archdoc.dev). The record is in
[`architecture/`](architecture/INDEX.md): an RFC per area, an ADR per decision with the
alternatives it beat, and refs on OFREP and on what Kit provides. Start with RFC-0001.

`docs/` holds Kit's own guides, which came with the installer and apply here unchanged.

The commit history is meant to be read in order. The first commit is Kit as the installer wrote
it, and each one after it adds one thing.

## What it leaves out

There is no web interface; the API reference is how you explore it. Projects have one owner and
there are no teams. Clients poll and there are no event streams. Flagpole does not record which
flags were evaluated or for whom. Each of those is a decision in the RFCs, with the reasons.

## License

MIT. See [LICENSE](LICENSE).
