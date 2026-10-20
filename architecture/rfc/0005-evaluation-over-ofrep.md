---
id: RFC-0005
title: Evaluation over OFREP
status: draft
created: 2026-10-20
decided:
depends: [RFC-0002, RFC-0004, ADR-0001, ADR-0002, ADR-0003, ADR-0004, ADR-0006, ADR-0007]
updates: []
obsoletes: []
---

# RFC-0005: Evaluation over OFREP

## Abstract

This RFC designs evaluation: the rules the evaluator follows to turn a flag's settings and a
context into a value, and the two OFREP endpoints that expose it. It covers the middleware the
routes take and leave off, the bulk ETag, the error bodies, and how conformance with OFREP is
tested.

## Motivation

Evaluation is the only part of Flagpole that applications call, and the part with the least room
for surprise: a wrong answer here is a feature shown to the wrong users. It is also where Flagpole
departs from Kit, deliberately, in three places ([ADR-0002: OFREP endpoints answer in OFREP's error format](../adr/0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md), [ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](../adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md), [ADR-0004: SDK keys are Sanctum tokens owned by an environment](../adr/0004-sdk-keys-are-sanctum-tokens-owned-by-an-environment.md)). Those
departures need to be exact, so a reader can see which parts of Kit still apply and which do not.

## Proposal

### The evaluator

`App\Flags\Evaluator` takes a flag, its settings in one environment, and a context, and returns a
resolution ([RFC-0002: Application architecture](0002-application-architecture.md)). It reads nothing else. In order:

1. **Disabled.** If `enabled` is false, serve `off_variant` with the reason `DISABLED`.
2. **Rules.** Try each rule in order. The first whose conditions all match decides: a variant is
   served with `TARGETING_MATCH`, and a split with `SPLIT`.
3. **Default.** If no rule matched, serve `default`. A split is `SPLIT`. A variant is `STATIC` when
   the flag has no rules, and `UNKNOWN` when it has rules and none matched ([RFC-0001: Flagpole v1](0001-flagpole-v1.md)).

A split needs a `targetingKey`. Without one, evaluation fails with `TARGETING_KEY_MISSING`, and only
when a split is actually reached: a flag whose split sits behind a rule that does not match
evaluates fine without one. The bucket is computed as [ADR-0006: Percentage rollouts bucket on a hash of the flag key and targeting key](../adr/0006-percentage-rollouts-bucket-on-a-hash-of-the-flag-key-and-targeting-key.md) says, and the split's variants
fill buckets 0 to 9,999 in the order listed.

### Matching a condition

A condition reads one top-level property of the context ([RFC-0004: Flags, variants and settings](0004-flags-variants-and-settings.md)):

- **A missing or `null` attribute never matches**, for every operator, `not_in` included. "Not in
  the beta list" should not quietly include everyone who sent no list membership at all.
- **`in` and `not_in`** compare with type: the string `"1"` does not equal the number `1`. Numbers
  compare by value, so `1` equals `1.0`.
- **`starts_with`, `ends_with` and `contains`** match only a string attribute, and are
  case-sensitive.
- **`lt`, `lte`, `gt` and `gte`** match only a number. A numeric string such as `"42"` does not
  match.
- **An attribute of the wrong type does not match.** It is not an error. One badly typed property
  should not stop every flag evaluating, and the owner can see why a rule did not match from the
  context they sent.

### The routes

Both routes sit outside the `v1` group, at the paths OFREP fixes, in `routes/api/routes.php`:

```php
Route::prefix('ofrep/v1')
    ->middleware(['consumes:application/json', 'auth:sanctum', 'abilities:ofrep:evaluate', 'sdk-key', 'throttle:ofrep'])
    ->group(base_path('routes/api/ofrep.php'));
```

As routes in Laravel's `api` group they still get what Kit adds to it: the request locale, the
transport security checks, the security headers, and the request ID and trace context. They do
not get the `v1` group, and they never take `conditional`, `preconditions` or `idempotent`.

- **`sdk-key`** is a small middleware that refuses, with `403`, any token whose owner is not an
  environment ([ADR-0004: SDK keys are Sanctum tokens owned by an environment](../adr/0004-sdk-keys-are-sanctum-tokens-owned-by-an-environment.md)). A user's token cannot hold `ofrep:evaluate` anyway, since it is in
  neither of Kit's ability lists. This is the second lock.
- **`ofrep`** is a new rate limiter keyed by the SDK key's token ID, at 600 requests a minute. That
  allows a busy server-side application one evaluation every tenth of a second per key, and owners
  who need more can use more keys. Kit's `auth-protected` limiter is not used, because it expects
  a `User`.

### Single evaluation

`POST /ofrep/v1/evaluate/flags/{key}`, with a body of `{"context": {...}}`.

| Outcome | Status | Body |
| --- | --- | --- |
| Evaluated | `200` | `key`, `value`, `variant`, `reason` |
| No such flag in the project, or archived | `404` | `key`, `errorCode: FLAG_NOT_FOUND`, `errorDetails` |
| A split with no `targetingKey` | `400` | `key`, `errorCode: TARGETING_KEY_MISSING`, `errorDetails` |
| No `context`, a `context` that is not an object, or a `targetingKey` that is not a string | `400` | `key`, `errorCode: INVALID_CONTEXT`, `errorDetails` |
| A body that is not JSON | `400` | `key`, `errorCode: PARSE_ERROR`, `errorDetails` |

`value` has the flag's type. A `float` flag's whole-number value is encoded with a decimal point,
`2.0` and not `2`, with `JSON_PRESERVE_ZERO_FRACTION`, so a typed client does not read it as an
integer. Flagpole sends no `metadata`.

These bodies are built by the controller ([ADR-0002: OFREP endpoints answer in OFREP's error format](../adr/0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md)). They never go through Kit's validation or
problem rendering, so the controller reads the body itself and does not use a Form Request.

### Bulk evaluation

`POST /ofrep/v1/evaluate/flags`, with the same body. It evaluates every flag in the environment's
project that is not archived, ordered by key, and answers `200` with `{"flags": [...]}`. A flag
whose evaluation fails, such as a split with no `targetingKey`, appears in the list with its
`errorCode` and `errorDetails`, and the others are unaffected. A request whose context is invalid
fails as a whole, with `400` and `errorCode` and `errorDetails` but no `key`.

No `eventStreams` are sent ([ADR-0007: Clients poll with ETags and there are no event streams in v1](../adr/0007-clients-poll-with-etags-and-there-are-no-event-streams-in-v1.md)), and the `flagConfigEtag` and `flagConfigLastModified`
parameters, which only accompany event streams, are ignored.

### The bulk ETag

The ETag is strong, the `xxh128` hash of the environment's ID, its `version`, and the canonical
JSON of the context ([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](../adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)). Canonical means object keys sorted at every depth, lists left
in order, and no escaping of slashes or Unicode.

When `If-None-Match` is present, it is compared before anything is evaluated:

- It may hold one tag or a comma-separated list. Any match answers `304` with the ETag and no body.
- Comparison is weak, as RFC 9110 says for `If-None-Match`, so `W/"abc"` matches `"abc"`.
- `*` is treated as no match. On this route it would mean "only if no flags exist", which no
  provider asks.

A matching request still costs a version lookup and a hash, and no evaluation.

### Errors that are not OFREP's

Authentication, the ability check, `sdk-key`, `consumes:` and the rate limiter answer as Kit
always does, with problem details ([ADR-0002: OFREP endpoints answer in OFREP's error format](../adr/0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md)). An unexpected exception on these routes is
rendered as `500` with `{"errorDetails": ...}` by a render callback that checks the route name
prefix `ofrep.`. `errorDetails` comes from `lang/{locale}/ofrep.php`.

### Documentation

Both routes are documented in Scribe like any other, with OFREP's bodies for `400`, `404` and
`500`, and a note on the bulk route that its `304` to a `POST` departs from RFC 9110 on purpose.
The API catalog at `/.well-known/api-catalog` gains an entry pointing at OFREP's specification.

### Testing

- **The evaluator** has unit tests driven by datasets: one row per step, per operator, per
  missing or wrongly typed attribute, and for the reasons in every combination of rules and
  default.
- **Bucketing** is pinned by known answers: a fixed list of flag keys and targeting keys with the
  bucket each must get, so a change to the hash fails loudly. A second test checks that 100,000
  generated keys spread across a 10% split to within half a percentage point.
- **The routes** have feature tests for every row in the tables above, for the ETag rules, and for
  each lock: a user's token, an SDK key for another environment, a revoked key, an archived flag.
- **Conformance.** OFREP's `openapi.yaml` is kept in the repository at version 0.4.0 with its
  licence, and every response the feature tests produce is checked against the schema for its
  status. If OFREP changes, updating that file is how Flagpole finds out what broke.
- **One real provider.** The README shows the OpenFeature JavaScript web SDK with its OFREP
  provider against a local Flagpole. Whether that becomes an automated test is decided when it is
  built.

### Demo data

`php artisan db:seed` creates an owner, a project with both environments, a flag of each type with
rules and a split in development, and an SDK key for each environment, and prints the keys. This
is the run-locally demo from [RFC-0001: Flagpole v1](0001-flagpole-v1.md). The seeder refuses to run in
production.

## Alternatives considered

**Errors for a wrongly typed attribute.** Stricter, and a single typo in one application's context
would turn every flag into its code default. A non-match is visible and contained.

**`not_in` matching a missing attribute.** It is how some flag services work, and it makes "not in
the beta group" include anonymous traffic that sent nothing. That surprises people more often than
it helps them.

**A Form Request for the OFREP body.** It is Kit's convention, but validation failures would be
rendered as problem details, which OFREP providers cannot read ([ADR-0002: OFREP endpoints answer in OFREP's error format](../adr/0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md)).

**Kit's `auth-protected` limiter.** It keys by user and would fail on an environment.

## Backwards compatibility

Nothing breaks. This is new behaviour with no existing callers.

## Open questions

1. **Schema validation in the conformance tests.** Kit's contract tests check documented schemas
   with helpers written in the test file, and have no JSON Schema package. Those helpers handle
   `oneOf` but not `allOf`, which OFREP's evaluation schemas are built from. The options are
   extending the helpers to cover `allOf`, which Kit would want too, or adding a JSON Schema
   validator as a dev dependency, which needs your approval and a Vet review.

## Changelog
