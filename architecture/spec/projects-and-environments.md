---
title: Projects and environments
includes: [RFC-0003]
---

# Projects and environments

## Keys

Project and environment keys are lowercase letters and digits in runs separated by single
hyphens, at most 64 characters. A project key is unique for its owner and an environment key
within its project. Keys cannot change. Names are up to 255 characters.

## Endpoints

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects` | `projects:read` |
| `POST` | `/v1/projects` | `projects:write` |
| `GET` | `/v1/projects/{project}` | `projects:read` |
| `PATCH` | `/v1/projects/{project}` | `projects:write` |
| `DELETE` | `/v1/projects/{project}` | `projects:write` |
| `GET` | `/v1/projects/{project}/environments` | `projects:read` |
| `POST` | `/v1/projects/{project}/environments` | `projects:write` |
| `GET` | `/v1/projects/{project}/environments/{environment}` | `projects:read` |
| `PATCH` | `/v1/projects/{project}/environments/{environment}` | `projects:write` |
| `DELETE` | `/v1/projects/{project}/environments/{environment}` | `projects:write` |

- Lists are ordered by key, 25 to a page, with `Link` headers.
- `POST` takes an `Idempotency-Key` and answers `201`.
- `GET` of one resource sends an ETag and answers `304` to a matching `If-None-Match`.
- `PATCH` takes `application/merge-patch+json` and requires `If-Match`: `428` without it, `412`
  when it is stale, `415` for any other content type. Only `name` can change; `key` may be sent
  with its current value.
- `DELETE` answers `204`.

## Behaviour

- Creating a project creates `development` and `production` in the same transaction.
- Creating an environment creates the settings of every flag in the project there, archived flags
  included, in their starting state.
- Deleting a project deletes its environments, flags, settings, history and SDK keys. Deleting an
  environment deletes its settings, their history and its SDK keys. Both are written to Kit's
  security audit log.
