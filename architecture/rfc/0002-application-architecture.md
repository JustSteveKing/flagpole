---
id: RFC-0002
title: Application architecture
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001, ADR-0008]
updates: []
obsoletes: []
---

# RFC-0002: Application architecture

## Abstract

Flagpole is a Kit application, and this RFC sets out how it sits on Kit: where the code goes, the
tables, the two route groups and how they differ, and where evaluation lives. Kit's own conventions
apply everywhere this RFC is silent.

## Motivation

[RFC-0001: Flagpole v1](0001-flagpole-v1.md) says what Flagpole does. Before the area RFCs design each part, they need a shared
answer to where things go. The answer matters more here than in most projects, because readers
will copy it into their own Kit applications. Flagpole should look like what Kit's `AGENTS.md`
describes, and where it cannot, the reason should be written down.

## Proposal

### Starting point

The repository was created with `laravel new flagpole --using=juststeveking/kit`. Its first commit
is that output unchanged, so a reader can diff any later commit against plain Kit ([REF-0002: What Kit gives an application](../ref/0002-what-kit-gives-an-application.md)).
Kit's guides stay in `docs/`, and this specification lives in `architecture/`.

### Where code goes

Flagpole follows Kit's layout:

| What | Where |
| --- | --- |
| Management endpoints | `app/Http/Controllers/Api/V1/{Projects,Environments,Flags,SdkKeys}/`, with their Form Requests, payloads and resources in Kit's matching directories |
| OFREP endpoints | `app/Http/Controllers/Ofrep/` |
| Models | `app/Models/`, with ULID keys like Kit's `User` |
| Evaluation | `app/Flags/`, beside Kit's `app/Webhooks/` |
| Webhook events | `app/Flags/Events/`, each implementing Kit's `WebhookEvent` |

Every management endpoint starts from `php artisan make:endpoint`. That keeps them identical in
shape to Kit's own, and makes each endpoint's commit easy to read.

There is no `src/` and no domain layer beyond `app/Flags/`. Kit has neither, and an example that
adds architecture Kit does not ask for would teach that it is needed.

### Evaluation is plain PHP

`app/Flags/` holds the evaluator: given a flag's definition, its settings in one environment and a
context, it returns a resolution, which is a value, a variant and a reason, or an error code. It
does not touch the database, the request or the container. That makes it Octane-safe by
construction, and its behaviour can be tested exhaustively with datasets, without HTTP.

The OFREP controllers load an environment's flags and settings, hand each to the evaluator, and
turn the resolutions into OFREP responses. They are the only place that knows OFREP's shapes.

### Tables

| Table | Holds |
| --- | --- |
| `projects` | `id`, `user_id`, `key`, `name`, timestamps. Unique on `user_id` and `key` |
| `environments` | `id`, `project_id`, `key`, `name`, `version`, timestamps. Unique on `project_id` and `key` |
| `flags` | `id`, `project_id`, `key`, `name`, `description`, `type`, `variants` (JSON), `archived_at`, timestamps. Unique on `project_id` and `key` |
| `flag_settings` | `id`, `flag_id`, `environment_id`, `enabled`, `off_variant`, `default` (JSON), `rules` (JSON), timestamps. Unique on `flag_id` and `environment_id` |
| `flag_changes` | `id`, `flag_id`, `environment_id` (null for a change to the flag itself), `action`, `user_id`, `token_id`, `token_name`, `before` and `after` (JSON), `created_at`. See [RFC-0007: History and webhook events](0007-history-and-webhook-events.md) |
| `personal_access_tokens` | Kit's table, unchanged. SDK keys are rows whose `tokenable` is an environment ([ADR-0004: SDK keys are Sanctum tokens owned by an environment](../adr/0004-sdk-keys-are-sanctum-tokens-owned-by-an-environment.md)) |

`environments.version` goes up by one in the same transaction as any change that alters what the
environment's flags evaluate to: settings, variants, archiving and restoring. It is what the bulk
evaluation ETag is built from ([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](../adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)).

Rules are stored as JSON on the settings row because they are always read and written together, in
order, as part of one document. A table per rule would turn every settings change into a delete
and reinsert, and the `If-Match` check already protects the document as a whole.

### Two kinds of route

| | Management API | OFREP |
| --- | --- | --- |
| Paths | `/v1/projects/...` | `/ofrep/v1/evaluate/flags[/{key}]`, fixed by the protocol |
| Caller | An owner's session token or API key | An SDK key |
| Errors | Problem details | OFREP's format ([ADR-0002: OFREP endpoints answer in OFREP's error format](../adr/0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md)) |
| Conditional requests | Kit's ETags on `GET`, `If-Match` required on writes | Flagpole's own `If-None-Match` handling on the bulk `POST` ([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](../adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)) |
| Rate limit | `auth-protected`, Kit's per-user limiter | `ofrep`, a new limiter keyed by SDK key |

The OFREP routes sit outside the `v1` group, so they do not inherit its middleware. They take
`auth:sanctum`, the `ofrep:evaluate` ability, a check that the token belongs to an environment, and
their own rate limiter. Each piece of Kit's middleware that would get in the way is named in the
evaluation RFC, with the reason it is left off.

A management token can never evaluate, because `ofrep:evaluate` is never granted to a user's token.
An SDK key can never manage, because the management routes check for a user and the SDK key's
owner is an environment.

### Management URLs

Resources are addressed by their keys, nested under the project:

```
/v1/projects/{project}
/v1/projects/{project}/environments/{environment}
/v1/projects/{project}/environments/{environment}/sdk-keys
/v1/projects/{project}/flags/{flag}
/v1/projects/{project}/flags/{flag}/settings/{settings}
/v1/projects/{project}/flags/{flag}/history
```

Keys make URLs a person can type and read in a log, and they never change, so they are safe to
build URLs from. `{settings}` is an environment key: the settings of that flag in that environment.

### Binding route parameters

Kit's controllers do not use route model binding. They read the parameter and look the row up
through the user's relationship, as in `$user->webhookSubscriptions()->findOrFail(...)`. That works
for Kit because its routes are flat.

Flagpole binds its parameters, because api-toolbox needs it to. The `conditional` and
`preconditions.required` middleware find a route's validators in its bound parameters, so a
resource that is never bound can have no ETag and no `If-Match` check. Each parameter has an
explicit binding in `AppServiceProvider` that resolves it within the authenticated owner and the
parameter before it: `{project}` among the owner's projects, `{flag}` and `{environment}` within
that project, and `{settings}` to the row for that flag in that environment. A key that does not
resolve is a `404`, so another owner's project looks exactly like one that does not exist.

### A change needed in api-toolbox

api-toolbox takes a route's validators from the **first** bound parameter that implements
`HasValidators`. On a nested route the first one is the parent, so
`GET /v1/projects/{project}/flags/{flag}` would send the project's ETag, and a `PATCH` to a flag
would check `If-Match` against the project.

Flagpole needs the last one, the resource the URL names. That is the right default for every
nested route, not only Flagpole's, so under [ADR-0008: Kit is upstream](../adr/0008-kit-is-upstream.md) it is changed in api-toolbox first, by
pull request, and Kit and Flagpole pick it up from a release. Until then the management routes
that need ETags cannot be finished.

### Documentation and checks

- Scribe documents both route groups. The OFREP endpoints are documented with OFREP's error bodies,
  which means Kit's problem details generator must leave them alone.
- Kit's contract tests cover the management API, as they cover Kit's own routes.
- The OFREP endpoints are also tested against OFREP's own OpenAPI document, kept in the repository
  at a pinned version. That is the test that Flagpole really speaks OFREP.
- legible scores the whole spec, at Kit's threshold.

### Running it

Kit's image, unchanged: Octane on FrankenPHP, with a queue worker for emails and webhooks.
Evaluation reads an environment's flags in one query per request, and v1 caches nothing. A cache
keyed by environment version is the obvious next step if measurement says it is needed, and the
version column makes it simple to add.

## Alternatives considered

**A domain layer in `src/`**, as tail -f has. It suits an application with many contexts and rules
that cross them. Flagpole has one real piece of logic, evaluation, and Kit's own code puts a
comparable piece, webhooks, in `app/Webhooks/`. Following that is the more useful example.

**Rules in their own table.** Covered above: the settings are one document, edited as one.

**Numeric or ULID IDs in management URLs.** They are what Kit's webhook routes use. Keys read
better, and are what an owner already knows, because the key is what their code asks for.

**One route group for both APIs.** The two differ in caller, error format and conditional request
handling, so a shared group would mean lifting most of its middleware route by route.

## Backwards compatibility

Nothing breaks. This is a new application with no existing callers.

## Open questions

## Changelog
