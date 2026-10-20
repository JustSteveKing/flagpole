---
id: RFC-0003
title: Projects and environments
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001, RFC-0002, ADR-0005]
updates: []
obsoletes: []
---

# RFC-0003: Projects and environments

## Abstract

This RFC designs the first two resources in the management API: projects and the environments
inside them. It sets out the keys, the endpoints, which Kit features each endpoint uses, and what
happens to everything beneath a project or environment when it is deleted.

## Motivation

Everything else in Flagpole hangs off a project and its environments. Flags belong to a project,
settings and SDK keys belong to an environment, and evaluation is always against one environment
([RFC-0001: Flagpole v1](0001-flagpole-v1.md)). They also make the first endpoints in the build, so they set the pattern every
later endpoint copies: `make:endpoint`, the key bindings ([RFC-0002: Application architecture](0002-application-architecture.md)), ETags, `If-Match` and
merge patch.

## Proposal

### Keys

A project key and an environment key follow the same rule: lowercase letters and digits in runs
separated by single hyphens, at most 64 characters, matching `^[a-z0-9]+(?:-[a-z0-9]+)*$`. A
project key is unique for its owner, and an environment key is unique within its project. Neither
can change after creation.

A name is any text up to 255 characters, and can change.

### Projects

| Method | Path | Ability | Kit features |
| --- | --- | --- | --- |
| `GET` | `/v1/projects` | `projects:read` | Cursor pagination |
| `POST` | `/v1/projects` | `projects:write` | `idempotent` |
| `GET` | `/v1/projects/{project}` | `projects:read` | `conditional` |
| `PATCH` | `/v1/projects/{project}` | `projects:write` | `conditional`, `preconditions.required`, `merge-patch` |
| `DELETE` | `/v1/projects/{project}` | `projects:write` | |

A project's representation:

```json
{
  "key": "checkout",
  "name": "Checkout",
  "created_at": "2026-10-20T09:00:00+00:00",
  "updated_at": "2026-10-20T09:00:00+00:00"
}
```

The key is the identifier, so there is no separate `id` in the body. The list is ordered by key,
25 to a page.

**Creating a project** takes `key` and `name` and answers `201`. In the same transaction it
creates two environments, `development` and `production`, named "Development" and "Production".

**Changing a project** takes a merge patch. Only `name` can change. A patch that sends `key` with
a different value fails validation on `key`, saying it cannot change. Sending the current value is
allowed, so a client can send back a whole document it fetched.

**Deleting a project** answers `204` and deletes everything beneath it: environments, flags,
settings, history and SDK keys. It is logged with Kit's `SecurityAudit`, because it cannot be
undone and it revokes every SDK key in the project at once.

### Environments

| Method | Path | Ability | Kit features |
| --- | --- | --- | --- |
| `GET` | `/v1/projects/{project}/environments` | `projects:read` | Cursor pagination |
| `POST` | `/v1/projects/{project}/environments` | `projects:write` | `idempotent` |
| `GET` | `/v1/projects/{project}/environments/{environment}` | `projects:read` | `conditional` |
| `PATCH` | `/v1/projects/{project}/environments/{environment}` | `projects:write` | `conditional`, `preconditions.required`, `merge-patch` |
| `DELETE` | `/v1/projects/{project}/environments/{environment}` | `projects:write` | |

An environment's representation is a key, a name and the timestamps, like a project's. The
`version` column from [RFC-0002: Application architecture](0002-application-architecture.md) is internal and is not sent.

**Creating an environment** takes `key` and `name`, answers `201`, and creates the settings for
every flag in the project in the same transaction, each in a flag's starting state ([RFC-0004: Flags, variants and settings](0004-flags-variants-and-settings.md)).
A new environment therefore evaluates every flag the moment it exists, with the reason `DISABLED`.

**Changing an environment** works as for projects: only `name` can change.

**Deleting an environment** answers `204` and deletes its settings, the history of those settings
and its SDK keys. It is logged with `SecurityAudit`, because every application using one of those
keys stops getting flags. A project can be left with no environments. Nothing breaks: it simply
has nowhere to evaluate until one is added.

### Bindings and ownership

`{project}` resolves among the authenticated owner's projects by key, and `{environment}` among
that project's environments ([RFC-0002: Application architecture](0002-application-architecture.md)). Both answer `404` for a key that does not resolve,
including another owner's.

### ETags

`Project` and `Environment` implement api-toolbox's `HasValidators` with the `HasModelValidators`
trait. Each has a `revision` integer that every write increments, and `entityTagVersion()` returns
it. api-toolbox's own guidance is to do this for optimistic concurrency: an ETag from `updated_at`
is only as fine as the database's timestamps, and MySQL stores whole seconds by default.

The `revision` is the resource's own counter and is separate from `environments.version`, which
counts changes to what the environment evaluates to.

### Merge patch under Kit's route group

Kit's `v1` group sits behind `consumes:application/json`, which refuses
`application/merge-patch+json` with a `415` before the route's own middleware runs. Each `PATCH`
route lifts the group's declaration and adds its own, the way Kit's guide shows for uploads:

```php
Route::patch('/projects/{project}', UpdateProjectController::class)
    ->withoutMiddleware('consumes:application/json')
    ->middleware(['abilities:projects:write', 'consumes:application/merge-patch+json', 'merge-patch', 'conditional', 'preconditions.required'])
    ->name('v1.projects.update');
```

Both `conditional` and `preconditions.required` are needed on a write. The second only insists
that a precondition is present, answering `428` without one. The first is what compares `If-Match`
with the current ETag and answers `412`.

Kit's HTTP standards guide gets both of these wrong by omission. It recommends merge patch without
saying the group refuses it, and says `preconditions.required` alone gives the `412`. Both are
fixed in Kit's guide under [ADR-0008: Kit is upstream](../adr/0008-kit-is-upstream.md).

### Translations

Every message is a key in `lang/en/api.php` and `lang/es/api.php`, under `projects` and
`environments`, including the "cannot change" message for keys.

### Tests

For each endpoint: success, validation, no token, the missing ability, and another owner's key
answering `404`. Also:

- Creating a project creates both environments, and a replayed `Idempotency-Key` creates nothing
  twice.
- A `PATCH` without `If-Match` is `428`, and with a stale one is `412`.
- A `PATCH` in plain JSON is `415`.
- Deleting a project or an environment removes its SDK keys, which then fail on the OFREP routes.
- Creating an environment gives every existing flag settings in it.

## Alternatives considered

**No starting environments.** A new project would evaluate nothing until the owner made one. Two
is what almost everyone wants, and they can be deleted.

**Refusing to delete the last environment.** It protects a state that is harmless, and it needs a
problem type of its own for little benefit.

**`updated_at` as the ETag version.** Covered above: it depends on the database's timestamp
precision, and a lost update between two writes in the same second is exactly what `If-Match` is
there to stop.

**Soft deletes.** History is kept for flags by archiving them ([RFC-0004: Flags, variants and settings](0004-flags-variants-and-settings.md)). For a whole project,
an owner deleting it means it, and keeping the rows would keep SDK keys that look valid.

## Backwards compatibility

Nothing breaks. This is new behaviour with no existing callers.

## Open questions

## Changelog
