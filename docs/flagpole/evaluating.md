# Evaluating flags

Applications evaluate flags with an [OpenFeature](https://openfeature.dev) SDK and its OFREP
provider. OFREP, the Remote Evaluation Protocol, is OpenFeature's standard way for a provider to ask
a flag service what a flag is, so Flagpole needs no SDK of its own: any language with an OFREP
provider works. Flagpole implements OFREP 0.4.0.

## SDK keys

An application authenticates with an SDK key. A key belongs to one environment, sees only that
environment's flags, and can do nothing else: no reading settings, no changes, no other
environment. That is what makes it safe to put one in a browser.

```bash
curl -X POST http://127.0.0.1:8000/v1/projects/checkout/environments/production/sdk-keys \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name": "storefront web"}'
```

The key comes back in `meta.token`, once. Unlike an API key, it never expires unless you give it an
`expires_at`. A key that expired on its own would switch every flag in production back to its code
default, with no change anyone made. To rotate one, create the new key, deploy it, then revoke the
old one:

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/environments/{environment}/sdk-keys` | `sdk-keys:read` |
| `POST` | `/v1/projects/{project}/environments/{environment}/sdk-keys` | `sdk-keys:write` |
| `DELETE` | `/v1/projects/{project}/environments/{environment}/sdk-keys/{key_id}` | `sdk-keys:write` |

The list shows each key's `last_used_at`, so you can tell which ones are still in use before you
revoke them. A revoked or expired key gets `401`.

## In a browser

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

The web provider evaluates every flag at once for the current context, then polls. Reading a flag
is synchronous after that, which is what a page rendering needs.

## On a server

```js
import { OpenFeature } from '@openfeature/server-sdk';
import { OFREPProvider } from '@openfeature/ofrep-provider';

await OpenFeature.setProviderAndWait(new OFREPProvider({
  baseUrl: 'http://127.0.0.1:8000',
  headers: [['Authorization', 'Bearer <SDK key>']],
}));

const client = OpenFeature.getClient();
const limit = await client.getNumberValue('basket-limit', 20, { targetingKey: user.id, plan: user.plan });
```

The server provider evaluates one flag per call, with the context of that request.

[`examples/openfeature`](../../examples/openfeature/README.md) runs both providers against the
demo and checks every answer, so you can see this work before you wire it into anything.

## The context

The context is whatever your rules need to know, as a JSON object: an email, a country, a plan, a
count of orders. Rules read its top-level properties. `targetingKey` is the one with a fixed
meaning: who the evaluation is for, which percentage rollouts need. Use something stable, such as a
user ID, so a user does not move between variants.

Flagpole does not store the context or record evaluations. It does not know which flags were
evaluated, or for whom.

## Polling and ETags

The bulk response carries an `ETag`, built from the environment's version and your context. Each
environment has a version that goes up whenever a change could alter what its flags evaluate to.
A client that sends the `ETag` back in `If-None-Match` gets a `304` with no body until either one
changes. The web provider does this for you.

So a change to production's settings reaches production clients on their next poll, and clients
of every other environment carry on getting `304`. Renaming a flag or editing its description
changes no environment's version, because no evaluation changes.

HTTP does not allow a `304` in answer to a `POST`, and the bulk endpoint is a `POST`, because that
is what OFREP specifies. Flagpole follows OFREP here, since its providers expect it.
[ADR-0003](../../architecture/adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)
records why.

## The endpoints

If you are writing a provider, or just want to see the raw protocol:

| Method | Path | Answers |
| --- | --- | --- |
| `POST` | `/ofrep/v1/evaluate/flags/{key}` | One flag |
| `POST` | `/ofrep/v1/evaluate/flags` | Every flag in the environment, each an evaluation or its own error |

Both take `{"context": {...}}` as `application/json` and the SDK key as a bearer token. An
evaluation is `key`, `value`, `variant` and `reason`, and values keep their type: a float always
has a decimal point, and an empty object is `{}`.

Errors are in OFREP's format, which is what the providers read:

| Status | `errorCode` | When |
| --- | --- | --- |
| `404` | `FLAG_NOT_FOUND` | No such flag in this environment, or it is archived |
| `400` | `TARGETING_KEY_MISSING` | The flag serves a split and the context has no `targetingKey` |
| `400` | `INVALID_CONTEXT` | No context, a context that is not an object, or a `targetingKey` that is not a string |
| `400` | `PARSE_ERROR` | The body is not JSON |
| `500` | | Something unexpected, with only `errorDetails` |

`errorDetails` is in English or Spanish, following `Accept-Language`; `errorCode` never changes.
Anything that happens before evaluation, such as a missing key, the wrong content type or the rate
limit, answers with Kit's problem details, like the rest of the API.

## Limits

- 600 requests a minute per SDK key. Over that is a `429` with `Retry-After`.
- Browsers can call the API from the origins in `CORS_ALLOWED_ORIGINS`, a comma-separated list in
  `.env` that defaults to `http://localhost:3000`. Add the origin your front end is served from.
