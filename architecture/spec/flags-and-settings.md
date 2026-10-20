---
title: Flags and settings
includes: [RFC-0004, ADR-0006]
---

# Flags and settings

## Flags

A flag has a key, a name, an optional description of up to 1,000 characters, a type, and an
ordered list of variants. Its key and type cannot change.

| Type | Variant values |
| --- | --- |
| `boolean` | Always `on` (`true`) and `off` (`false`), which cannot change |
| `string` | Strings |
| `integer` | Integers |
| `float` | Numbers; whole numbers are stored as floats |
| `object` | JSON objects of at most 16,384 bytes |

Non-boolean flags have between 2 and 20 variants, with unique names in the form of a key.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/flags` | `flags:read` |
| `POST` | `/v1/projects/{project}/flags` | `flags:write` |
| `GET` | `/v1/projects/{project}/flags/{flag}` | `flags:read` |
| `PATCH` | `/v1/projects/{project}/flags/{flag}` | `flags:write` |
| `POST` | `/v1/projects/{project}/flags/{flag}/archive` | `flags:write` |
| `POST` | `/v1/projects/{project}/flags/{flag}/restore` | `flags:write` |

- The list leaves out archived flags, or shows only them with `?archived=true`.
- A `PATCH` can change the name, description and variants. A variant that the settings of any
  environment use cannot be removed or renamed; the error names the environments.
- Archive and restore require `If-Match`. An archived flag is read-only: a `PATCH` to it or its
  settings answers `409`. Restoring keeps its settings as they were.
- Flags are never deleted on their own.

## Settings

Each flag has a settings document in each environment:

| Field | Holds |
| --- | --- |
| `enabled` | When false, the off variant is served |
| `off_variant` | A variant name |
| `default` | A serve, used when enabled and no rule matches |
| `rules` | Up to 50, in order: each has an optional description, 1 to 10 conditions, and a serve |

A serve is `{"variant": name}` or `{"split": [{"variant", "weight"}]}`. Weights are whole numbers in
hundredths of a percent and add up to 10,000; a variant appears in a split once. A condition has an
`attribute`, an `operator`, and 1 to 100 `values`:

| Operator | Values |
| --- | --- |
| `in`, `not_in` | Strings, numbers or booleans |
| `starts_with`, `ends_with`, `contains` | Strings |
| `lt`, `lte`, `gt`, `gte` | Exactly one number |

The starting state is disabled, with the off variant and default set to `off` for a boolean flag
and to the first variant otherwise, and no rules.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/flags/{flag}/settings` | `flags:read` |
| `GET` | `/v1/projects/{project}/flags/{flag}/settings/{settings}` | `flags:read` |
| `PATCH` | `/v1/projects/{project}/flags/{flag}/settings/{settings}` | `flags:write` |

The `PATCH` is a merge patch applied to the current document, and the merged result is validated.
`rules` is replaced whole when sent. `environment` and `updated_at` may be sent back unchanged and
are otherwise refused.

## What a write does

Every write through `FlagWriter` saves the change, increments `version` on the environments whose
results it can change, records it in the history, and dispatches its webhook event, in one
transaction. A write that changes nothing does none of these.

| Change | Environments whose version goes up |
| --- | --- |
| Creating a flag | All in the project |
| Changing variants | All in the project |
| Changing a name or description | None |
| Archiving or restoring | All in the project |
| Changing settings | That environment |
