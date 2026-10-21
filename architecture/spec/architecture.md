---
title: Architecture
includes: [RFC-0002, ADR-0005, ADR-0008]
---

# Architecture

Flagpole is a Kit application. Its first commit is Kit as `laravel new --using=juststeveking/kit`
wrote it, and Kit's guides are in `docs/`. Changes to Kit's own files are in commits of their own.

## Code

| What | Where |
| --- | --- |
| Management controllers | `app/Http/Controllers/Api/V1/{Projects,Environments,Flags,FlagSettings,SdkKeys}/` |
| OFREP controllers | `app/Http/Controllers/Ofrep/` |
| Form Requests, payloads, resources | Kit's directories under `app/Http/` |
| Models | `app/Models/`: `Project`, `Environment`, `Flag`, `FlagSetting`, `FlagChange` |
| Evaluation, writes, keys and events | `app/Flags/` |

Every write to a flag or its settings goes through `App\Flags\FlagWriter`.

## Tables

| Table | Holds |
| --- | --- |
| `projects` | `user_id`, `key`, `name`, `revision`. Unique on `user_id` and `key` |
| `environments` | `project_id`, `key`, `name`, `revision`, `version`. Unique on `project_id` and `key` |
| `flags` | `project_id`, `key`, `name`, `description`, `type`, `variants`, `archived_at`, `revision`. Unique on `project_id` and `key` |
| `flag_settings` | `flag_id`, `environment_id`, `enabled`, `off_variant`, `default`, `rules`, `revision`. Unique on `flag_id` and `environment_id` |
| `flag_changes` | `flag_id`, `environment_id`, `action`, `user_id`, `token_id`, `token_name`, `before`, `after`, `created_at` |
| `personal_access_tokens` | Kit's. SDK keys are rows whose `tokenable` is an environment |

Every ID is a ULID. `revision` goes up on every write to its row and is what the row's ETag is built
from. `environments.version` goes up whenever what the environment's flags evaluate to changes.

## Routes

| | Management | OFREP |
| --- | --- | --- |
| Paths | `/v1/projects/...` | `/ofrep/v1/evaluate/flags[/{key}]` |
| Middleware | `auth:sanctum`, `throttle:auth-protected`, one ability per route | `consumes:application/json`, `auth:sanctum`, `abilities:ofrep:evaluate`, `sdk-key`, `throttle:ofrep` |
| Errors | Problem details | OFREP's bodies for `400`, `404` and `500`; problem details otherwise |

Management resources are addressed by key. `{project}`, `{environment}`, `{flag}` and `{settings}`
are bound in `AppServiceProvider` within the authenticated owner and the parameter before them, so
a key that does not resolve, including another owner's, is a `404`. `{settings}` is an environment
key.

## Abilities

Session tokens hold `projects:read`, `projects:write`, `flags:read`, `flags:write`,
`sdk-keys:read` and `sdk-keys:write`, beside Kit's own. `ofrep:evaluate` is held only by SDK keys.

The ability is checked before route parameters are bound and before idempotency, so a token
without it gets `403` whether or not the resource exists, and the refusal is not stored for a retry
with the same `Idempotency-Key`.
