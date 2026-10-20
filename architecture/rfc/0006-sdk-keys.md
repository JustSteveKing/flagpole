---
id: RFC-0006
title: SDK keys
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001, RFC-0003, ADR-0004, ADR-0008]
updates: []
obsoletes: []
---

# RFC-0006: SDK keys

## Abstract

This RFC designs the endpoints that create, list and revoke SDK keys, and how a key is issued.
An SDK key is a Sanctum token owned by an environment with the single ability `ofrep:evaluate`
([ADR-0004: SDK keys are Sanctum tokens owned by an environment](../adr/0004-sdk-keys-are-sanctum-tokens-owned-by-an-environment.md)). It is the credential an application's OpenFeature provider sends to Flagpole.

## Motivation

Without SDK keys nothing can evaluate a flag. They are also the clearest case in Flagpole of
Kit's least-privilege rule: a key that sits in an application's configuration, and in a browser's
JavaScript, must be able to do one thing and nothing else ([RFC-0001: Flagpole v1](0001-flagpole-v1.md)).

## Proposal

### Endpoints

| Method | Path | Ability | Kit features |
| --- | --- | --- | --- |
| `GET` | `/v1/projects/{project}/environments/{environment}/sdk-keys` | `sdk-keys:read` | Cursor pagination |
| `POST` | `/v1/projects/{project}/environments/{environment}/sdk-keys` | `sdk-keys:write` | `idempotent` |
| `DELETE` | `/v1/projects/{project}/environments/{environment}/sdk-keys/{key_id}` | `sdk-keys:write` | |

`{key_id}` is the token's numeric ID, constrained with `whereNumber`, as Kit's
`DELETE /v1/auth/tokens/{token_id}` is. It resolves only among that environment's keys.

### A key

```json
{
  "id": "41",
  "name": "checkout-web production",
  "last_used_at": "2026-10-20T09:12:00+00:00",
  "expires_at": null,
  "created_at": "2026-10-20T09:00:00+00:00"
}
```

It follows Kit's `PersonalAccessTokenResource` without `abilities`, which are always the same, and
without `is_current`, which can never be true from a management token.

**Creating a key** takes a `name` up to 255 characters and an optional `expires_at` in the future.
It answers `201` with the key and its secret in `meta.token`, shown this once, the way Kit returns
a new API key. With no `expires_at` the key never expires ([RFC-0001: Flagpole v1](0001-flagpole-v1.md)). There is no maximum, which
is the one place Flagpole's keys differ from Kit's.

**Revoking a key** answers `204`. The next request that uses it gets `401`.

There is no rotate endpoint. Rotation is creating the new key, deploying it, and revoking the old
one, and an endpoint that did both at once would revoke a key that is still deployed.

### Issuing

`App\Flags\SdkKeys` issues them, beside Kit's `AccessTokens` and without changing it
([ADR-0004: SDK keys are Sanctum tokens owned by an environment](../adr/0004-sdk-keys-are-sanctum-tokens-owned-by-an-environment.md), [ADR-0008: Kit is upstream](../adr/0008-kit-is-upstream.md)):

```php
$environment->createToken($name, ['ofrep:evaluate'], $expiresAt);
```

`ofrep:evaluate` is added to neither `sanctum.abilities.available` nor `sanctum.abilities.default`.
Those lists are what Kit lets a user's token hold, and a user's token must never evaluate.

### Audit

Creating and revoking a key are logged with `SecurityAudit`, as `sdk_keys.created` and
`sdk_keys.revoked`, with the user, project, environment and token IDs. The secret is never logged.

### How SDK keys sit beside Kit's own tokens

- **They are not the owner's tokens.** `GET /v1/auth/tokens` does not list them, and Kit's
  "sign out everywhere", `DELETE /v1/auth/tokens`, does not revoke them. Signing out should not
  turn off flags in production.
- **They outlive the owner's password.** Changing or resetting a password leaves them working, for
  the same reason. An owner who thinks their account was used by someone else revokes them here.
- **They die with their environment** and with the project ([RFC-0003: Projects and environments](0003-projects-and-environments.md)).

### Two things worth knowing

**Every evaluation writes.** Sanctum sets `last_used_at` on the token for every authenticated
request, so each OFREP call is one `UPDATE`. Under polling that is the bulk of Flagpole's writes.
It is acceptable at the scale of a local demo, and `last_used_at` is useful: it is how an owner
tells which key is still deployed before revoking one. If it ever matters, the fix is to stop
Sanctum's update for SDK keys and record use at a coarser grain.

**A replay returns the secret.** Creating a key is `idempotent`, so a replayed request within a day
gets the original `201` again, secret included. That is what a client that lost the first response
needs. Since api-toolbox 0.3.2 the stored response is encrypted with a key derived from `APP_KEY` and
the client's `Idempotency-Key`, so the idempotency store cannot be read without the key the client
sent. It was raised and fixed there under [ADR-0008: Kit is upstream](../adr/0008-kit-is-upstream.md).

### Tests

- A key can only reach its own environment. It has no way to name another, so the test is that
  keys for two environments get different results for the same flag.
- A key gets `403` on every management route, and a user's session token gets `403` on the OFREP
  routes.
- A revoked key and an expired key get `401`.
- `GET /v1/auth/tokens` does not list SDK keys, and `DELETE /v1/auth/tokens` leaves them working.
- Deleting the environment deletes its keys.

## Alternatives considered

**A maximum lifetime, as Kit's API keys have.** Covered in [RFC-0001: Flagpole v1](0001-flagpole-v1.md): an SDK key that expires on
its own switches production flags back to their code defaults with no change anyone made.

**A rotate endpoint.** Covered above.

**Showing the secret again.** Sanctum stores only a hash, which is the point.

## Backwards compatibility

Nothing breaks. This is new behaviour with no existing callers.

## Open questions

## Changelog
