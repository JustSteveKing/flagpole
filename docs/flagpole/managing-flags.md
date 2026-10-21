# Managing flags

Everything here goes through the management API under `/v1`, with a token from signing in or an
API key. Each section names the ability a token needs. A session token from `/v1/auth/login` holds
all of them; an [API key](#keys-for-scripts-and-ci) holds only what you give it.

Examples assume the server is on `http://127.0.0.1:8000` and your token is in `$TOKEN`.

## Projects

A project is one application whose flags you manage. It belongs to one owner, and it starts with
two environments, `development` and `production`.

```bash
curl -X POST http://127.0.0.1:8000/v1/projects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"key": "storefront", "name": "Storefront"}'
```

Keys are lowercase letters and digits, in runs separated by single hyphens, up to 64 characters.
They appear in every URL and cannot change. Names are for people and can.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects` | `projects:read` |
| `POST` | `/v1/projects` | `projects:write` |
| `GET` | `/v1/projects/{project}` | `projects:read` |
| `PATCH` | `/v1/projects/{project}` | `projects:write` |
| `DELETE` | `/v1/projects/{project}` | `projects:write` |

Another owner's project is a `404`, exactly like one that does not exist. Deleting a project
deletes everything in it: environments, flags, history and SDK keys.

## Environments

Add one for each deployment that needs its own flag settings, such as staging:

```bash
curl -X POST http://127.0.0.1:8000/v1/projects/storefront/environments \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"key": "staging", "name": "Staging"}'
```

Every flag in the project gets settings in the new environment straight away, switched off.

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/environments` | `projects:read` |
| `POST` | `/v1/projects/{project}/environments` | `projects:write` |
| `GET` | `/v1/projects/{project}/environments/{environment}` | `projects:read` |
| `PATCH` | `/v1/projects/{project}/environments/{environment}` | `projects:write` |
| `DELETE` | `/v1/projects/{project}/environments/{environment}` | `projects:write` |

Deleting an environment deletes its settings, their history and its SDK keys, so applications
using those keys stop getting flags.

## Flags

A flag has a key, a type and a list of variants: the values it can serve, each with a name.

| Type | Variants |
| --- | --- |
| `boolean` | Always `on` (`true`) and `off` (`false`). You do not send them |
| `string`, `integer`, `float` | 2 to 20, with values of that type |
| `object` | 2 to 20 JSON objects, each up to 16 KB |

```bash
curl -X POST http://127.0.0.1:8000/v1/projects/storefront/flags \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "key": "search-engine",
    "name": "Search engine",
    "description": "Which search backend the storefront calls.",
    "type": "string",
    "variants": [
      {"name": "legacy", "value": "solr"},
      {"name": "new", "value": "meilisearch"}
    ]
  }'
```

A new flag is switched off in every environment, serving its first variant (`off` for a boolean).
Turning it on, and deciding who gets what, is [targeting](targeting.md).

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/v1/projects/{project}/flags` | `flags:read` |
| `POST` | `/v1/projects/{project}/flags` | `flags:write` |
| `GET` | `/v1/projects/{project}/flags/{flag}` | `flags:read` |
| `PATCH` | `/v1/projects/{project}/flags/{flag}` | `flags:write` |
| `POST` | `/v1/projects/{project}/flags/{flag}/archive` | `flags:write` |
| `POST` | `/v1/projects/{project}/flags/{flag}/restore` | `flags:write` |

A `PATCH` can change the name, the description and the variants, never the key or the type.
`variants` is an array, so a patch replaces the whole list. A variant that any environment is
serving cannot be removed or renamed, and the error names the environments that use it, so you
know where to change the settings first.

### Archiving

Flags are not deleted on their own. Their history outlives them, and a key that could be reused
would make that history ambiguous. When the code that reads a flag is gone, archive it: it stops
being evaluated, its settings and history are kept, and it is read-only until you restore it.
Deleting the project is the only thing that removes its flags. Archive and restore take the flag's `ETag` in `If-Match`:

```bash
curl -X POST http://127.0.0.1:8000/v1/projects/storefront/flags/search-engine/archive \
  -H "Authorization: Bearer $TOKEN" \
  -H 'If-Match: "<the flag ETag>"'
```

The flag list leaves archived flags out. `?archived=true` lists only them.

## Every change needs If-Match

Every `PATCH`, archive and restore requires the `ETag` from your last `GET` of that resource, in
`If-Match`. Without it the change is a `428`. If someone changed the resource after your `GET`, it
is a `412`: fetch it again, look at what changed, and decide. That is the whole point. Two people
editing the same rules at once is a real way to ship the wrong thing, and this makes the second
one find out instead of silently winning.

Changes are JSON Merge Patch, sent as `application/merge-patch+json`. Send only what you are
changing, `null` removes a field, and an array is replaced whole.

## Retrying safely

Creating a project, an environment, a flag or an SDK key takes an `Idempotency-Key`. Send a new
key for each thing you mean to create, and the same key when retrying it. A retry with the same key
and body gets the original response, so a script that times out and tries again does not create
two flags. The same key with a different body is a `422`.

## History

Every change to a flag or its settings is recorded: who made it, with which token, and the
document before and after.

```bash
curl http://127.0.0.1:8000/v1/projects/storefront/flags/search-engine/history \
  -H "Authorization: Bearer $TOKEN"
```

Changes come newest first, 25 to a page, with the next page in the `Link` header.
`?environment=production` shows only changes to the production settings. The token's name is kept
as it was at the time, so a change made by a key you have since revoked still says which key it
was.

## Keys for scripts and CI

Your session token can do everything you can. A deploy script that creates flags for new code
should not be able to delete a project, so give it an API key with only what it needs:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/tokens \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name": "deploy-bot", "abilities": ["flags:read", "flags:write"]}'
```

The key comes back in `meta.token`, once. It expires after 90 days unless you set `expires_at`,
and never more than a year ahead. A key can only be given abilities its creator holds.

| Ability | Lets a token |
| --- | --- |
| `projects:read`, `projects:write` | Read, or create, change and delete, projects and environments |
| `flags:read`, `flags:write` | Read, or create, change, archive and restore, flags and their settings |
| `sdk-keys:read`, `sdk-keys:write` | List, or create and revoke, [SDK keys](evaluating.md#sdk-keys) |

These sit beside Kit's own abilities for tokens and webhooks, which
[Kit's authentication guide](../authentication.md) covers.
