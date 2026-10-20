---
id: RFC-0004
title: Flags, variants and settings
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001, RFC-0002, RFC-0003, ADR-0006]
updates: []
obsoletes: []
---

# RFC-0004: Flags, variants and settings

## Abstract

This RFC designs flags and their settings in each environment: the flag's shape and variants,
archiving, the settings document with its rules and splits, and the endpoints that change them.
How the settings turn into a value is [RFC-0005: Evaluation over OFREP](0005-evaluation-over-ofrep.md)'s job; this RFC defines what can be stored.

## Motivation

Flags are the reason Flagpole exists, and settings are where the riskiest changes happen. A
targeting change in production is the edit most likely to be made by two people at once, and the
one where a lost update ships the wrong thing to users. It is also where Kit's `If-Match` and
merge patch earn their place ([RFC-0001: Flagpole v1](0001-flagpole-v1.md)).

## Proposal

### A flag

```json
{
  "key": "new-checkout",
  "name": "New checkout",
  "description": "The redesigned checkout flow.",
  "type": "boolean",
  "variants": [
    {"name": "on", "value": true},
    {"name": "off", "value": false}
  ],
  "archived_at": null,
  "created_at": "2026-10-20T09:00:00+00:00",
  "updated_at": "2026-10-20T09:00:00+00:00"
}
```

- **`key`** follows the key rule in [RFC-0003: Projects and environments](0003-projects-and-environments.md), is unique within the project, and cannot change.
  An archived flag keeps its key, so a new flag cannot reuse it.
- **`type`** is `boolean`, `string`, `integer`, `float` or `object`, and cannot change.
- **`variants`** is an ordered list of names and values. Names follow the key rule and are unique
  within the flag. Every value must be of the flag's type: an `integer` flag refuses `1.5`, and an
  `object` flag takes JSON objects only. A boolean flag always has exactly `on` (`true`) and `off`
  (`false`), which are created for it and cannot be changed. Other flags have between 2 and 20
  variants. An object value is at most 16 KB as JSON.
- **`description`** is optional, up to 1,000 characters.

The order of `variants` matters in one place: a new non-boolean flag starts by serving its first
variant.

### Flag endpoints

| Method | Path | Ability | Kit features |
| --- | --- | --- | --- |
| `GET` | `/v1/projects/{project}/flags` | `flags:read` | Cursor pagination |
| `POST` | `/v1/projects/{project}/flags` | `flags:write` | `idempotent` |
| `GET` | `/v1/projects/{project}/flags/{flag}` | `flags:read` | `conditional` |
| `PATCH` | `/v1/projects/{project}/flags/{flag}` | `flags:write` | `conditional`, `preconditions.required`, `merge-patch` |
| `POST` | `/v1/projects/{project}/flags/{flag}/archive` | `flags:write` | `conditional`, `preconditions.required:POST` |
| `POST` | `/v1/projects/{project}/flags/{flag}/restore` | `flags:write` | `conditional`, `preconditions.required:POST` |

The list is ordered by key, 25 to a page, and leaves out archived flags unless `?archived=true` is
given, which lists only archived ones.

**Creating a flag** takes `key`, `name`, `type`, and `variants` for any type but boolean, with
`description` optional. It answers `201`, and in the same transaction creates the flag's settings
in every environment of the project in their starting state (below).

**Changing a flag** takes a merge patch of `name`, `description` and `variants`. As with projects,
`key` and `type` may be sent only with their current values. Merge patch replaces an array
whole, so `variants` is always sent complete. A variant still used by the settings of any
environment, as the off variant, the default or in a rule, cannot be removed or renamed; the
validation error names the environments that use it. Changing a variant's value is allowed.

**Archiving a flag** takes it out of evaluation ([RFC-0005: Evaluation over OFREP](0005-evaluation-over-ofrep.md)) and makes it read-only: a `PATCH`
to the flag or its settings answers `409` until it is restored. Its settings are kept, and
**restoring** brings it back exactly as it was. Both answer `200` with the flag. They require
`If-Match`, because archiving a flag someone has just changed is the same lost-update risk as
editing it. The middleware names `POST`, since by default `preconditions.required` covers only
`PUT`, `PATCH` and `DELETE`. Neither takes a body, so they need no change to Kit's `consumes:` declaration.

Flags are never deleted on their own. Their history outlives them, and a deleted key that could be
reused would make that history ambiguous. Deleting the project deletes them ([RFC-0003: Projects and environments](0003-projects-and-environments.md)).

### Settings

A flag has one settings document per environment:

```json
{
  "environment": "production",
  "enabled": true,
  "off_variant": "off",
  "default": {"variant": "off"},
  "rules": [
    {
      "description": "Staff see it first",
      "conditions": [
        {"attribute": "email", "operator": "ends_with", "values": ["@example.com"]}
      ],
      "serve": {"variant": "on"}
    },
    {
      "description": "Ten per cent of customers in the UK",
      "conditions": [
        {"attribute": "country", "operator": "in", "values": ["GB"]}
      ],
      "serve": {"split": [{"variant": "on", "weight": 1000}, {"variant": "off", "weight": 9000}]}
    }
  ],
  "updated_at": "2026-10-20T09:00:00+00:00"
}
```

- **`enabled`**: when false, the flag serves `off_variant` and nothing else is consulted.
- **`off_variant`**: a variant name.
- **`default`**: a serve, used when the flag is enabled and no rule matches.
- **`rules`**: up to 50, tried in order. The first rule whose conditions all match decides.
- **A serve** is either `{"variant": name}` or `{"split": [...]}`, never both.
- **A split** is a list of variants and weights. Weights are whole numbers in hundredths of a
  percent ([ADR-0006: Percentage rollouts bucket on a hash of the flag key and targeting key](../adr/0006-percentage-rollouts-bucket-on-a-hash-of-the-flag-key-and-targeting-key.md)) and must add up to 10,000. A variant appears in a split at most once.
- **A rule** has an optional `description` up to 255 characters, between 1 and 10 conditions, and
  a serve. A rule with no conditions would always match, which is what `default` is for.
- **A condition** has an `attribute`, an `operator` and between 1 and 100 `values`. The attribute
  is the name of a top-level property of the evaluation context, `targetingKey` included.

The operators:

| Operator | Matches when the attribute | Values |
| --- | --- | --- |
| `in` | equals any of the values | strings, numbers or booleans |
| `not_in` | is present and equals none of the values | strings, numbers or booleans |
| `starts_with` | is a string starting with any of the values | strings |
| `ends_with` | is a string ending with any of the values | strings |
| `contains` | is a string containing any of the values | strings |
| `lt`, `lte`, `gt`, `gte` | is a number less than, at most, greater than, or at least the value | exactly one number |

What happens when an attribute is missing or of another type is evaluation, and [RFC-0005: Evaluation over OFREP](0005-evaluation-over-ofrep.md)
defines it.

**The starting state**, for a new flag or a new environment, is disabled, with the off variant and
the default both set to `off` for a boolean flag and to the first variant otherwise, and no rules.

### Settings endpoints

| Method | Path | Ability | Kit features |
| --- | --- | --- | --- |
| `GET` | `/v1/projects/{project}/flags/{flag}/settings` | `flags:read` | Cursor pagination |
| `GET` | `/v1/projects/{project}/flags/{flag}/settings/{settings}` | `flags:read` | `conditional` |
| `PATCH` | `/v1/projects/{project}/flags/{flag}/settings/{settings}` | `flags:write` | `conditional`, `preconditions.required`, `merge-patch` |

`{settings}` is an environment key ([RFC-0002: Application architecture](0002-application-architecture.md)). The list holds one document per environment,
ordered by environment key.

Turning a flag off in production is the smallest change there is:

```bash
curl -X PATCH http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/production \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/merge-patch+json" \
  -H 'If-Match: "<etag>"' \
  -d '{"enabled": false}'
```

The patch is applied to the current document with api-toolbox's `MergePatch`, and the Form
Request validates the **result**. A patch that leaves `rules` alone is checked against the rules
already stored, so a variant renamed elsewhere cannot slip through. As with variants, `rules` is
an array and is replaced whole.

Every variant a settings document names must exist on the flag. `environment` and `updated_at`
are read-only: they may be sent back with their current values and are otherwise refused.

### What a change does

Every write in this RFC that changes what a flag evaluates to does four things in one transaction:

1. Saves the change and increments the resource's `revision`.
2. Increments `version` on every environment whose results it can change ([RFC-0002: Application architecture](0002-application-architecture.md)).
3. Records the change in the flag's history ([RFC-0007: History and webhook events](0007-history-and-webhook-events.md)).
4. Dispatches its webhook event ([RFC-0007: History and webhook events](0007-history-and-webhook-events.md)).

| Change | Environments whose `version` goes up |
| --- | --- |
| Creating a flag | All in the project |
| Changing a variant's value | All in the project |
| Changing a flag's name or description | None |
| Archiving or restoring a flag | All in the project |
| Changing settings | That environment |

Name and description are never part of an evaluation, so changing them leaves every ETag alone.

A write that changes nothing does none of the four ([RFC-0007: History and webhook events](0007-history-and-webhook-events.md)).

### ETags

`Flag` and `FlagSetting` implement `HasValidators` with a `revision` column, as projects and
environments do ([RFC-0003: Projects and environments](0003-projects-and-environments.md)). Both `PATCH` routes lift Kit's `consumes:application/json` in the
same way.

### Tests

Beyond the usual cases for each endpoint:

- Each validation rule above, with a failing example: wrong value types, a split not adding up to
  10,000, a duplicate variant in a split, an unknown variant, too many rules or conditions, and an
  operator given values of the wrong kind.
- A patch that sends only `{"enabled": false}` keeps the stored rules, and one that sends `rules`
  replaces them all.
- Removing a variant that production's settings use fails and names production.
- An archived flag's `PATCH` answers `409`, and restoring it brings its settings back unchanged.
- Each row of the version table, including that changing a name bumps nothing.
- Two clients fetch the same settings, both send a patch with the same ETag, and the second gets
  `412`.

## Alternatives considered

**Variants as a JSON object of names to values.** Easier to read, but JSON objects have no
guaranteed order, and the starting state depends on the first variant.

**Rules as their own resource**, with endpoints to add, move and delete one rule. Finer-grained
changes and smaller requests, but each rule edit becomes a separate write with its own ETag, and
reordering needs an endpoint of its own. A settings document edited whole keeps one `If-Match`
check over everything that decides the outcome.

**Deleting flags.** Covered above: a reusable key makes history ambiguous.

**Weights as percentages.** Simpler to read, and too coarse: a rollout to 0.5% of a large user
base is common.

**Nested attributes**, such as `user.plan`. Useful, but OFREP contexts are usually flat, and it
adds a path syntax to explain. It can be added later without breaking anything.

## Backwards compatibility

Nothing breaks. This is new behaviour with no existing callers.

## Open questions

## Changelog
