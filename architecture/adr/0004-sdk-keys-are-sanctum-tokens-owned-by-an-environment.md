---
id: ADR-0004
title: SDK keys are Sanctum tokens owned by an environment
status: accepted
created: 2026-10-20
decided: 2026-10-20
depends: [RFC-0001, ADR-0008]
updates: []
obsoletes: []
---

# ADR-0004: SDK keys are Sanctum tokens owned by an environment

## Context

An SDK key evaluates one environment's flags and does nothing else ([RFC-0001: Flagpole v1](../rfc/0001-flagpole-v1.md)). OFREP has no
environment in its paths, so the credential has to say which environment it is for.

Kit's API keys are Sanctum tokens that belong to a user and hold a subset of that user's abilities.
A user's token cannot name an environment except through its abilities, and a user's abilities
cover every project they own. Sanctum's `personal_access_tokens` table, though, has a polymorphic
`tokenable`, which Kit creates with `ulidMorphs` ([REF-0002: What Kit gives an application](../ref/0002-what-kit-gives-an-application.md)), so any model with ULID keys can
own tokens.

## Decision

SDK keys are Sanctum tokens owned by the `Environment` model, which uses `HasApiTokens`. Every SDK
key holds exactly one ability, `ofrep:evaluate`, and the OFREP routes accept only a token whose
owner is an environment. The environment comes from the token, never from the request.

## Alternatives

**A user's API key with an ability naming the environment**, such as
`ofrep:evaluate:{environment-id}`. It reuses Kit's API keys unchanged, but abilities become data,
the list in `config/sanctum.php` can no longer be complete, and a key can outlive the environment
it names.

**A separate `sdk_keys` table with its own hashing and middleware.** Full control, and a second
authentication system to write, test and explain beside one that already does the job.

## Consequences

- Sanctum does the hashing, the lookup, `last_used_at` and expiry, as it does for Kit's keys.
- A morph relationship has no foreign key, so nothing in the database removes an environment's
  keys when it is deleted. The model does it, in the same transaction, with a test.
- `$request->user()` on an OFREP route returns an `Environment`. Anything shared with the
  management routes that assumes a `User`, such as Kit's `auth-protected` limiter and the security
  audit log, must not run there, or must check what it has. That is why OFREP has its own limiter
  ([RFC-0002: Application architecture](../rfc/0002-application-architecture.md)).
- SDK keys are created through Flagpole's endpoints, not Kit's `/v1/auth/tokens`, which only issues
  keys for the calling user.
- Kit's `AccessTokens` issues tokens for users only, and always with an expiry. SDK keys get their
  own issuer beside it, which leaves Kit's class untouched ([ADR-0008: Kit is upstream](0008-kit-is-upstream.md)) and allows
  the null `expires_at` that [RFC-0001: Flagpole v1](../rfc/0001-flagpole-v1.md) asks for.
