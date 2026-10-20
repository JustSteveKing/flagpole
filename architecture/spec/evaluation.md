---
title: Evaluation
includes: [RFC-0005, ADR-0001, ADR-0002, ADR-0003, ADR-0006, ADR-0007]
---

# Evaluation

Applications evaluate flags over OFREP 0.4.0, against the environment of the SDK key they send.

## The evaluator

`App\Flags\Evaluator` takes a flag, its settings in one environment, and a context:

1. When the flag is disabled, it serves the off variant with `DISABLED`.
2. Otherwise the first rule whose conditions all match decides: a variant with `TARGETING_MATCH`,
   a split with `SPLIT`.
3. Otherwise the default: a split with `SPLIT`; a variant with `STATIC` when the flag has no rules
   and `UNKNOWN` when it has rules and none matched.

A condition reads one top-level property of the context. A missing or `null` attribute never
matches, for any operator. `in` and `not_in` compare with type, numbers by value. String operators
match strings only and are case-sensitive. Number operators match numbers only; a numeric string
does not match. An attribute of another type does not match.

A split puts a subject in bucket `N mod 10000`, where `N` is the first four bytes of
`sha256(flagKey + "." + targetingKey)` read as an unsigned big-endian integer, and serves the
variant whose share of buckets 0 to 9,999, in listed order, contains it. A split reached without a
string `targetingKey` fails with `TARGETING_KEY_MISSING`.

## Endpoints

| Method | Path |
| --- | --- |
| `POST` | `/ofrep/v1/evaluate/flags/{key}` |
| `POST` | `/ofrep/v1/evaluate/flags` |

Both take `{"context": {...}}` as `application/json` and an SDK key as a bearer token. Archived
flags are not evaluated.

| Single evaluation | Status | Body |
| --- | --- | --- |
| Evaluated | `200` | `key`, `value`, `variant`, `reason` |
| No such flag, or archived | `404` | `key`, `errorCode: FLAG_NOT_FOUND`, `errorDetails` |
| A split without a targeting key | `400` | `key`, `errorCode: TARGETING_KEY_MISSING`, `errorDetails` |
| No context, a context that is not an object, or a targeting key that is not a string | `400` | `key`, `errorCode: INVALID_CONTEXT`, `errorDetails` |
| A body that is not JSON | `400` | `key`, `errorCode: PARSE_ERROR`, `errorDetails` |
| An unexpected failure | `500` | `errorDetails` |

The bulk endpoint answers `200` with `{"flags": [...]}`, every flag in the environment by key, each
an evaluation or its own error. A bad body fails the whole request with `400`, `errorCode` and
`errorDetails`. No `eventStreams` are sent.

Values keep their type: a float is encoded with a decimal point, and an empty object as `{}`.
`errorDetails` is translated from `Accept-Language`; `errorCode` never is. Authentication,
abilities, content type and rate limits answer with Kit's problem details.

## The bulk ETag

The bulk response's ETag is strong: `xxh128` of the environment ID, its `version`, and the context
as canonical JSON, with object keys sorted at every depth. A request whose `If-None-Match` matches
it, compared weakly, gets `304` with the ETag and no body. `*` never matches.

## Limits and access

- `throttle:ofrep` allows 600 requests a minute per SDK key.
- A token that is not an SDK key gets `403`.
- `ofrep/*` is covered by CORS.
- The API catalog at `/.well-known/api-catalog` lists `/ofrep/v1`.
