# Targeting

A flag's settings decide what it serves, separately in each environment. You can switch a flag
on in development, target staff in production, and roll it out to 10% of everyone else, all
without touching the flag itself.

## The settings document

```json
{
  "enabled": true,
  "off_variant": "off",
  "default": {"variant": "off"},
  "rules": []
}
```

| Field | What it does |
| --- | --- |
| `enabled` | When `false`, the flag serves `off_variant` and nothing else is looked at |
| `off_variant` | The variant served while the flag is switched off |
| `rules` | Up to 50, tried in order. The first that matches decides |
| `default` | What is served when the flag is on and no rule matches |

A new flag starts switched off, with `off_variant` and `default` set to `off` for a boolean flag,
or its first variant otherwise, and no rules.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/flags/{flag}/settings` | `flags:read` |
| `GET` | `/v1/projects/{project}/flags/{flag}/settings/{environment}` | `flags:read` |
| `PATCH` | `/v1/projects/{project}/flags/{flag}/settings/{environment}` | `flags:write` |

The last segment is an environment key. The API reference calls it `{settings}`.

## Rules

A rule has an optional description, 1 to 10 conditions, and what it serves. Every condition has
to match for the rule to match.

```json
{
  "description": "Staff see it first",
  "conditions": [{"attribute": "email", "operator": "ends_with", "values": ["@flagpole.test"]}],
  "serve": {"variant": "on"}
}
```

A condition reads one top-level property of the evaluation context, the object your application
sends with each evaluation, and compares it with 1 to 100 values:

| Operator | Matches when the attribute | Values |
| --- | --- | --- |
| `in` | equals one of the values | Strings, numbers or booleans |
| `not_in` | equals none of the values | Strings, numbers or booleans |
| `starts_with`, `ends_with`, `contains` | is a string that starts with, ends with or contains one of them | Strings |
| `lt`, `lte`, `gt`, `gte` | is a number less than, at most, greater than or at least the value | Exactly one number |

The comparisons are strict, so a rule never matches by accident:

- An attribute that is missing or `null` matches nothing, `not_in` included.
- String operators are case-sensitive and only match strings.
- Number operators only match numbers. `"12"` is not `12`.
- `in` and `not_in` compare with type, so `true` is not `"true"`, but `10` matches `10.0`.

## Serving a variant, or a split

A rule's `serve`, and the `default`, is one of two things:

```json
{"variant": "on"}
```

```json
{"split": [{"variant": "on", "weight": 1000}, {"variant": "off", "weight": 9000}]}
```

A split is a percentage rollout. Weights are hundredths of a percent and add up to 10,000, so the
one above serves `on` to 10%.

Who lands in that 10% comes from a hash of the flag's key and the context's `targetingKey`. The
same user gets the same variant on every evaluation and on every server. Variants take their
share of the buckets in the order they are listed, so widening the rollout from 10% to 20%, with
`on` still listed first, keeps the first 10% where they were. Because the flag key is part of the hash, a
rollout of a different flag picks a different 10%, so the same users are not first in line for
everything.

A split needs a `targetingKey`. Without one, the evaluation fails with `TARGETING_KEY_MISSING`
and your application gets its own default value, rather than Flagpole guessing.

## Putting it together

This turns `new-checkout` on in production for staff, and for 10% of everyone else:

```bash
curl -X PATCH http://127.0.0.1:8000/v1/projects/checkout/flags/new-checkout/settings/production \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/merge-patch+json" \
  -H 'If-Match: "<the ETag from a GET of the production settings>"' \
  -d '{
    "enabled": true,
    "rules": [
      {
        "description": "Staff see it first",
        "conditions": [{"attribute": "email", "operator": "ends_with", "values": ["@flagpole.test"]}],
        "serve": {"variant": "on"}
      }
    ],
    "default": {"variant": null, "split": [{"variant": "on", "weight": 1000}, {"variant": "off", "weight": 9000}]}
  }'
```

Note the `"variant": null`. The change is a JSON Merge Patch, which merges objects and replaces
arrays. `rules` is an array, so the patch replaces the whole list. `default` is an object, so the
patch merges into it, and going from `{"variant": "off"}` to a split means removing `variant` as
well, or the result has both and is refused with a `422`.

The merged document is validated as a whole: variant names have to exist on the flag, and a split
names each variant once.

## Reasons

Every evaluation says why it came out as it did, in OFREP's terms:

| Reason | When |
| --- | --- |
| `DISABLED` | The flag is switched off in this environment, so it served `off_variant` |
| `TARGETING_MATCH` | A rule matched and served a variant |
| `SPLIT` | A split decided, from a rule or from the default |
| `STATIC` | The flag has no rules, and the default served a variant |
| `UNKNOWN` | The flag has rules, none matched, and the default served a variant |

`UNKNOWN` is the odd one. OpenFeature would call that case `DEFAULT`, but OFREP has no such reason,
and `UNKNOWN` is the honest answer in the codes it does have. In the demo, `basket-limit` and
`discount-rate` say `UNKNOWN` for a user their rules do not cover.

## Which clients see a change

A change to the settings in one environment only affects that environment. Clients polling the
others keep getting `304`, and clients polling this one get the new values on their next poll.
[Evaluating flags](evaluating.md#polling-and-etags) explains how that works.
