---
title: SDK keys
includes: [RFC-0006, ADR-0004]
---

# SDK keys

An SDK key is a Sanctum token owned by an environment, holding the single ability
`ofrep:evaluate`. `App\Flags\SdkKeys` issues them.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/environments/{environment}/sdk-keys` | `sdk-keys:read` |
| `POST` | `/v1/projects/{project}/environments/{environment}/sdk-keys` | `sdk-keys:write` |
| `DELETE` | `/v1/projects/{project}/environments/{environment}/sdk-keys/{key_id}` | `sdk-keys:write` |

- A key has an `id`, `name`, `last_used_at`, `expires_at` and `created_at`.
- Creating one takes a `name` and an optional `expires_at` in the future, with no maximum, and an
  `Idempotency-Key`. It answers `201` with the secret in `meta.token`, once.
- A key without `expires_at` never expires. A revoked or expired key gets `401`.
- Creating and revoking are written to Kit's security audit log.
- SDK keys are not listed or revoked by Kit's `/v1/auth/tokens` endpoints, and outlive the owner's
  password changes.
- Deleting an environment or its project deletes its keys.
- Every request with a key updates its `last_used_at`.
