---
id: RFC-0007
title: History and webhook events
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001, RFC-0002, RFC-0004]
updates: []
obsoletes: []
---

# RFC-0007: History and webhook events

## Abstract

This RFC designs the two records of a change to a flag: the history Flagpole keeps and serves a
page at a time, and the webhook events it sends to the owner's subscriptions. Both are written in
the same transaction as the change ([RFC-0004: Flags, variants and settings](0004-flags-variants-and-settings.md)).

## Motivation

When a flag misbehaves in production, the first question is what changed, when, and through which
key. History answers it inside Flagpole. Webhooks answer it for the systems around Flagpole: a
cache to clear, a channel to post in, an audit trail of the owner's own ([RFC-0001: Flagpole v1](0001-flagpole-v1.md)). History is
also the list in Flagpole that grows without bound, which is what cursor pagination is for.

## Proposal

### What is recorded

One row in `flag_changes` for each change listed in [RFC-0004: Flags, variants and settings](0004-flags-variants-and-settings.md):

| Column | Holds |
| --- | --- |
| `id` | A ULID, so rows sort by time |
| `flag_id` | The flag |
| `environment_id` | The environment, for a settings change; null for a change to the flag |
| `action` | `flag.created`, `flag.updated`, `flag.archived`, `flag.restored` or `flag.settings.updated` |
| `user_id` | The owner who made it |
| `token_id`, `token_name` | The token that made it, and its name at the time |
| `before`, `after` | The flag's representation, or the settings document, before and after. `before` is null on creation |
| `created_at` | When |

`token_name` is copied so the record still says "ci-pipeline" after that key is revoked and its row
is gone. This settles the question left open in [RFC-0002: Application architecture](0002-application-architecture.md).

A write that changes nothing, such as a merge patch setting `enabled` to the value it already has,
is not a change: it answers `200` with the current document and records nothing, bumps no
version and sends no event.

History rows are never edited. They are deleted only with their flag's project, or, for a settings
change, with their environment ([RFC-0003: Projects and environments](0003-projects-and-environments.md)).

### Reading it

`GET /v1/projects/{project}/flags/{flag}/history`, with `flags:read`, newest first, 25 to a page,
with cursor pagination and `Link` headers. `?environment=production` narrows it to one
environment's settings changes.

```json
[
  {
    "id": "01JB8Z6V4T9N3Q2W5E7R8Y0U1I",
    "action": "flag.settings.updated",
    "environment": "production",
    "token": {"id": "12", "name": "ci-pipeline"},
    "before": {"enabled": true, "off_variant": "off", "default": {"variant": "off"}, "rules": []},
    "after": {"enabled": false, "off_variant": "off", "default": {"variant": "off"}, "rules": []},
    "created_at": "2026-10-20T09:30:00+00:00"
  }
]
```

`token.id` is null once that token has been deleted. There is no user field: a project has one
owner ([ADR-0005: A project has one owner and there are no teams](../adr/0005-a-project-has-one-owner-and-there-are-no-teams.md)), so it is always them.

### Webhook events

Each event is a class in `app/Flags/Events/` implementing Kit's `WebhookEvent`, its type listed in
`events` in `config/webhooks.php`, and dispatched for the project's owner inside the change's
transaction, so Kit queues the deliveries only once it commits.

| Type | Subject | `data` |
| --- | --- | --- |
| `flag.created` | `{project}/{flag}` | `project`, `flag`, `name`, `type` |
| `flag.updated` | `{project}/{flag}` | `project`, `flag`, `changed` |
| `flag.archived` | `{project}/{flag}` | `project`, `flag` |
| `flag.restored` | `{project}/{flag}` | `project`, `flag` |
| `flag.settings.updated` | `{project}/{flag}` | `project`, `flag`, `environment`, `enabled`, `changed` |

`project`, `flag` and `environment` are keys. `changed` lists the top-level fields that changed,
such as `["enabled"]` or `["rules", "default"]`. `enabled` is the value after the change, because
"was it switched off" is the most common thing a subscriber wants to know.

The events say what changed and not what it changed to. The full document is one request away for
a subscriber with `flags:read`, and keeping rules out of the payload keeps targeting data, which
often holds email addresses and customer IDs, out of third-party endpoints such as a chat
channel's incoming webhook. Kit's guide treats `data()` as public API to which fields can be added
but never removed, so starting small keeps that option open.

Projects, environments and SDK keys send no events in v1. Nothing in [RFC-0001: Flagpole v1](0001-flagpole-v1.md) needs them, and
each can be added later without breaking a subscriber.

### Retention

History is kept for as long as the flag's project exists. Webhook events and deliveries follow
Kit's retention of 30 days. They are a delivery log, and history is the record.

### Tests

- Every change in [RFC-0004: Flags, variants and settings](0004-flags-variants-and-settings.md) writes exactly one history row and dispatches exactly one event,
  inside the transaction: a change that fails rolls back both.
- A no-op patch writes nothing and sends nothing.
- History pages newest first, `Link` headers included, and the environment filter works.
- A revoked token still shows its name.
- With `Http::fake()` and Kit's `HostResolver` bound to answer without DNS, each event is delivered
  as a signed CloudEvent with the subject and data above.

## Alternatives considered

**History from Kit's webhook events.** They are deleted after 30 days, and exist only for owners
with a subscription.

**The full settings document in `flag.settings.updated`.** Saves subscribers a request, and sends
targeting data to every endpoint subscribed, including ones the owner does not control.

**A history row per field changed.** Easier to filter, and a single change to five fields becomes
five rows that can only be read together.

## Backwards compatibility

Nothing breaks. This is new behaviour with no existing callers.

## Open questions

## Changelog
