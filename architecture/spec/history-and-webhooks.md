---
title: History and webhooks
includes: [RFC-0007]
---

# History and webhooks

## History

Every change to a flag or its settings writes one row to `flag_changes`, with the action, the
environment for a settings change, the owner, the token's ID and its name at the time, and the
document before and after.

| Action | Recorded |
| --- | --- |
| `flag.created` | The flag, with no `before` |
| `flag.updated` | The flag before and after |
| `flag.archived`, `flag.restored` | The flag before and after |
| `flag.settings.updated` | The settings in one environment, before and after |

A change made outside the API, such as by the demo seeder, records the owner with no token.

`GET /v1/projects/{project}/flags/{flag}/history`, with `flags:read`, lists changes newest first,
25 to a page. `?environment=` narrows it to one environment. Each has `id`, `action`,
`environment`, `token` (`id`, null once the token is deleted, and `name`), `before`, `after` and
`created_at`.

History is deleted with its project, and a settings change with its environment.

## Webhooks

Each change dispatches one event to the project owner's subscriptions, through Kit's webhooks.

| Type | `data` |
| --- | --- |
| `flag.created` | `project`, `flag`, `name`, `type` |
| `flag.updated` | `project`, `flag`, `changed` |
| `flag.archived` | `project`, `flag` |
| `flag.restored` | `project`, `flag` |
| `flag.settings.updated` | `project`, `flag`, `environment`, `enabled`, `changed` |

The subject is `{project}/{flag}`. `changed` lists the top-level fields that changed. No event
carries rules or variant values.
