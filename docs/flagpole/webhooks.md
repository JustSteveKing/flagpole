# Webhooks

Every change to a flag or its settings sends a webhook to the project owner's subscriptions. Use
it to post to a chat channel when production changes, to keep an audit trail somewhere else, or to
warm a cache instead of waiting for clients to poll.

The machinery is Kit's: signed CloudEvents, retries, delivery history, redelivery. This page covers
what Flagpole sends. [Kit's webhooks guide](../webhooks.md) covers everything else, from verifying
a signature to what happens when an endpoint keeps failing.

## Subscribing

```bash
curl -X POST http://127.0.0.1:8000/v1/webhooks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"url": "https://example.com/hooks/flagpole", "events": ["flag.settings.updated"], "description": "Production changes to Slack"}'
```

`events` lists the types you want, or `["*"]` for all of them. The signing secret comes back in
`meta.secret`, and no other response ever contains it.

Deliveries are queued, so `php artisan queue:work` has to be running. Locally, `.env` already
allows plain `http` and private addresses, so a receiver on your own machine works. Kit's
defaults refuse both, and anywhere real should keep them.

## Events

| Type | When | `data` |
| --- | --- | --- |
| `flag.created` | A flag is created | `project`, `flag`, `name`, `type` |
| `flag.updated` | Its name, description or variants change | `project`, `flag`, `changed` |
| `flag.archived` | It is archived | `project`, `flag` |
| `flag.restored` | It is restored | `project`, `flag` |
| `flag.settings.updated` | Its settings change in one environment | `project`, `flag`, `environment`, `enabled`, `changed` |

`changed` lists the top-level fields that changed. A change that changes nothing sends nothing.

Turning `new-checkout` on in production, with a new rule, sends:

```json
{
  "specversion": "1.0",
  "id": "01jb8x3a1b2c3d4e5f6g7h8j9k",
  "source": "http://localhost/v1",
  "type": "flag.settings.updated",
  "subject": "checkout/new-checkout",
  "time": "2026-10-21T09:00:00+00:00",
  "datacontenttype": "application/json",
  "data": {
    "project": "checkout",
    "flag": "new-checkout",
    "environment": "production",
    "enabled": true,
    "changed": ["enabled", "rules"]
  }
}
```

The subject is always `{project}/{flag}`.

## What the events leave out

No event carries rules or variant values. An event says what changed, not what it changed to. Rules
are full of the attributes you target on, such as email domains, countries and plans, and a
webhook endpoint is often someone else's service, so that data stays in Flagpole. A subscriber that
needs the new settings fetches them, with a token holding `flags:read`.

Fields can be added to an event's `data` and are never removed, so a subscriber should ignore
fields it does not know.

## History

Webhooks are for reacting to changes. To look back at them, every flag keeps its own history,
which [Managing flags](managing-flags.md#history) covers: each change with who made it, with which
token, and the document before and after.
