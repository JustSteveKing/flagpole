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
  {"key": "basket-limit", "value": 20, "variant": "standard", "reason": "UNKNOWN"},
  {"key": "discount-rate", "value": 0.0, "variant": "none", "reason": "UNKNOWN"},
  {"key": "new-checkout", "value": true, "variant": "on", "reason": "TARGETING_MATCH"},
  {"key": "payment-providers", "value": {"providers": ["card"]}, "variant": "cards", "reason": "DISABLED"},
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

## Target users and roll out

Rules and rollouts are settings too. This turns `new-checkout` on in production for staff, and for
10% of everyone else:

```bash
curl -X PATCH http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/production \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/merge-patch+json" \
  -H 'If-Match: "<the ETag from a GET of the production settings>"' \
  -d '{
    "enabled": true,
    "rules": [
      {
        "description": "Staff see it first",
        "conditions": [{"attribute": "email", "operator": "ends_with", "values": ["@flagpole.test"]}],
        "serve": {"variant": "on"}
      }
    ],
    "default": {"variant": null, "split": [{"variant": "on", "weight": 1000}, {"variant": "off", "weight": 9000}]}
  }'
```

Rules are tried in order and the first whose conditions all match decides, with the reason
`TARGETING_MATCH`. A condition compares one attribute of the context using `in`, `not_in`,
`starts_with`, `ends_with`, `contains`, `lt`, `lte`, `gt` or `gte`. When no rule matches, the
default serves. A split like this one gives the reason `SPLIT`. A single variant gives `STATIC`
when the flag has no rules, and `UNKNOWN` when it has rules and none matched, which is why
`basket-limit` and `discount-rate` say `UNKNOWN` above: OFREP has no `DEFAULT` reason. A flag that
is switched off serves its off variant, with `DISABLED`.

Split weights are hundredths of a percent and add up to 10,000. The split hashes the flag key
with the context's `targetingKey`, so a user stays in the same variant on every evaluation and on
every server, and a rollout of a different flag picks a different 10%. A context with no
`targetingKey` gets `TARGETING_KEY_MISSING` instead of a guess.

Two things about merge patch are worth knowing here. `rules` is an array, so the patch replaces
the whole list. `default` is an object, so the patch merges into it: going from a single variant
to a split means setting `"variant": null`, or the result has both and is refused with a `422`.

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
