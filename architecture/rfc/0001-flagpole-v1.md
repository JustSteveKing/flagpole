---
id: RFC-0001
title: Flagpole v1
status: draft
created: 2026-10-20
decided:
depends: []
updates: []
obsoletes: []
---

# RFC-0001: Flagpole v1

## Abstract

Flagpole is a feature flag service, built on Kit as its worked example. An owner manages projects,
environments and flags through a JSON API, and applications evaluate those flags with any
OpenFeature SDK over OFREP. This RFC sets the scope of v1, and later RFCs design each area in
detail.

## Motivation

Kit's guides explain each part on its own. What they cannot show is a real application where every
part is there because the domain needs it. That needs an example repository, and an example built
around a contrived domain teaches the wrong lesson: that the standards are decoration.

Feature flags give most of Kit a real job:

- **ETags.** Flag clients poll, and almost every poll finds nothing has changed. A `304` is the
  normal answer.
- **`If-Match`.** Two people changing the same flag's targeting at once is a real way to ship the
  wrong thing to production. A `412` stops it.
- **Merge patch.** Turning a flag off is `{"enabled": false}`.
- **Scoped keys.** An SDK in production should be able to read flags and nothing else. A CI job
  that creates flags should not be able to delete projects.
- **Webhooks.** A change to a flag is something other systems want to hear about, to clear a cache
  or post in a channel.
- **Idempotency.** Flags are created from CI and scripts, which retry.
- **Cursor pagination.** A flag's change history grows forever.
- **Localization.** Validation is most of what a flag owner sees go wrong, and Kit already
  translates it.

Evaluation has a published standard too. OFREP ([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)) is OpenFeature's HTTP protocol for
flag evaluation, so a server that implements it works with OpenFeature's existing providers and
needs no SDK of its own. That matches Kit's premise: build to the published standard, and clients
already know how to talk to you.

## Proposal

### Principles

1. **Every Kit feature earns its place.** A feature appears where the domain needs it. One that
   would only be there to be shown is left out, and the README says so.
2. **Evaluation is OFREP** ([ADR-0001: Flags are evaluated over OFREP](../adr/0001-flags-are-evaluated-over-ofrep.md)). Flagpole ships no SDK.
3. **The management API is a Kit API.** Problem details, idempotency, ETags, merge patch, cursor
   pagination and signed webhooks, all as Kit's guides describe them.
4. **Kit is upstream** ([ADR-0008: Kit is upstream](../adr/0008-kit-is-upstream.md)). Flagpole changes Kit's code only where it has to, and a fix
   that belongs in Kit goes to Kit.
5. **The history is the tutorial.** The first commit is Kit as the installer wrote it, and each
   later commit adds one thing a reader can follow.

### Owners and projects

- **An owner is a Kit user.** Registration, login, verification and API keys are Kit's, unchanged.
- **A project belongs to one owner** ([ADR-0005: A project has one owner and there are no teams](../adr/0005-a-project-has-one-owner-and-there-are-no-teams.md)). There are no teams, invitations or roles.
- **A project has a key and a name.** The key is lowercase letters, digits and hyphens, unique for
  that owner, and cannot change, because it appears in URLs and in webhook payloads.

### Environments

- **An environment belongs to a project**, with a key such as `production` and a name. Keys follow
  the same rules as project keys and are unique within the project.
- **A new project starts with `development` and `production`.** The owner can add, rename and
  delete environments after that.
- **Each environment has its own copy of every flag's settings.** Turning a flag on in development
  leaves production alone.

### Flags

- **A flag belongs to a project**, with a key, a name, an optional description and a type.
- **The key is what applications ask for.** It is unique within the project, and cannot change
  after creation, because changing it would break every application that uses it.
- **The type is one of `boolean`, `string`, `integer`, `float` and `object`**, matching the value
  types OFREP can return. It cannot change after creation either.
- **A flag has named variants**, each holding a value of the flag's type. A boolean flag has exactly
  two, `on` and `off`. Other types have at least two.
- **Archiving a flag** removes it from evaluation without deleting its history. An archived flag
  evaluates as not found. It can be restored.

### Settings in each environment

Each flag has these settings in each environment:

- **`enabled`.** When it is false, the flag serves its off variant with the reason `DISABLED`.
- **The off variant.** What the flag serves while disabled.
- **Rules**, in order. A rule has conditions on the evaluation context, all of which must match,
  and serves either a variant or a percentage split across variants.
- **The default.** What the flag serves when it is enabled and no rule matches: a variant or a
  split.

A new flag starts disabled in every environment, serving `off` for a boolean flag and the first
variant for the others.

Settings are changed with a merge patch, and the request must carry `If-Match`. A change without
it is refused with `428`, and a change made against settings someone else has since changed is
refused with `412`.

### Evaluation

Applications evaluate flags over OFREP ([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)), against one environment, chosen by the
key they authenticate with:

- `POST /ofrep/v1/evaluate/flags/{key}` evaluates one flag.
- `POST /ofrep/v1/evaluate/flags` evaluates every flag in the environment for one context.

The result follows the settings:

| What decided it | `reason` |
| --- | --- |
| The flag is disabled | `DISABLED` |
| A rule matched and serves a variant | `TARGETING_MATCH` |
| A rule or the default serves a split | `SPLIT` |
| The flag is enabled with no rules, and the default serves a variant | `STATIC` |
| Rules exist, none matched, and the default serves a variant | `UNKNOWN` |

OFREP's `reason` list has no `DEFAULT`, which is the reason OpenFeature itself uses when no rule
matched. `STATIC` would be wrong, because the result depends on the context, so Flagpole sends
`UNKNOWN`. If OFREP adds `DEFAULT`, Flagpole should switch to it.

A split puts a context in a bucket from a hash of the flag key and the `targetingKey`
([ADR-0006: Percentage rollouts bucket on a hash of the flag key and targeting key](../adr/0006-percentage-rollouts-bucket-on-a-hash-of-the-flag-key-and-targeting-key.md)), so the same subject gets the same variant on every evaluation. A split evaluated
without a `targetingKey` fails with `TARGETING_KEY_MISSING`.

The bulk endpoint returns an `ETag`, and a client that sends it back in `If-None-Match` gets `304`
while nothing it would see has changed ([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](../adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)). Clients poll; there are no event streams in
v1 ([ADR-0007: Clients poll with ETags and there are no event streams in v1](../adr/0007-clients-poll-with-etags-and-there-are-no-event-streams-in-v1.md)).

OFREP endpoints answer errors in OFREP's format, not as problem details ([ADR-0002: OFREP endpoints answer in OFREP's error format](../adr/0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md)).
`errorDetails` is translated like any other message a person reads, and `errorCode` never is.

### SDK keys

- **An SDK key belongs to an environment** ([ADR-0004: SDK keys are Sanctum tokens owned by an environment](../adr/0004-sdk-keys-are-sanctum-tokens-owned-by-an-environment.md)), and can do one thing: evaluate that
  environment's flags.
- The owner creates, lists and revokes SDK keys through the management API. A key's secret is shown
  once, when it is created.
- **SDK keys do not expire unless asked to.** A Kit API key expires after 90 days by default, and
  that is right for a person's key. An SDK key that expires on its own turns every flag in
  production back to its code default without anyone changing anything. An owner can still set
  `expires_at`, and rotates a key by creating the new one before revoking the old one.
- An SDK key works from a server or a browser. In a browser it is public, which is acceptable
  because it can only evaluate. Browser origins still have to be allowed through Kit's CORS
  settings.

### Abilities

Kit's API keys hold abilities, and Flagpole adds these:

| Ability | Allows |
| --- | --- |
| `projects:read` | Reading projects and environments |
| `projects:write` | Creating, changing and deleting projects and environments |
| `flags:read` | Reading flags, their settings and their history |
| `flags:write` | Creating, changing, archiving and restoring flags, and changing their settings |
| `sdk-keys:read` | Listing an environment's SDK keys |
| `sdk-keys:write` | Creating and revoking SDK keys |

Session tokens get all of them. An API key for a CI job that creates flags needs only
`flags:write`.

### History

Every change to a flag or its settings is recorded: who made it, with which token, when, and the
value before and after. The history is read newest first, a page at a time, with cursor
pagination.

### Webhooks

Flagpole adds these event types to Kit's webhooks, dispatched for the project's owner:

| Type | When |
| --- | --- |
| `flag.created` | A flag is created |
| `flag.updated` | A flag's name, description or variants change |
| `flag.archived` | A flag is archived |
| `flag.restored` | An archived flag is restored |
| `flag.settings.updated` | A flag's settings change in an environment |

Each carries the project and flag keys, and the environment key where there is one, so a subscriber
can act without fetching anything first.

### What v1 leaves out

- **A web interface.** Flagpole is an API, and the Scribe reference is how a person explores it.
- **Teams and shared projects** ([ADR-0005: A project has one owner and there are no teams](../adr/0005-a-project-has-one-owner-and-there-are-no-teams.md)).
- **Event streams for evaluation** ([ADR-0007: Clients poll with ETags and there are no event streams in v1](../adr/0007-clients-poll-with-etags-and-there-are-no-event-streams-in-v1.md)).
- **Reusable segments.** Each rule lists its own conditions.
- **Flags that depend on other flags**, scheduled changes, approvals, and experiment analysis.
- **A hosted demo**, with the registration limits and data retention it would need.
- **Recording evaluations.** Flagpole does not know which flags were evaluated, or for whom.

### Running it, and publishing it

- **Flagpole runs locally.** There is no hosted demo. A reader clones it, runs it with Kit's
  usual `serve` and `queue:work`, and seeds an owner, a project and some flags to try against.
- **The repository is private until v1 is finished**, then public under JustSteveKing with its full
  history, so the commits still read as the walkthrough (principle 5).

### Later RFCs

Each area gets its own RFC, written and accepted one at a time:

1. Projects and environments
2. Flags, variants and settings, including the rule conditions and their operators
3. Evaluation over OFREP, including the ETag and conformance testing
4. SDK keys
5. History and webhook events

[RFC-0002: Application architecture](0002-application-architecture.md) sets out how all of this sits on Kit.

## Alternatives considered

**Another domain.** Event RSVPs, invoicing, an uptime monitor and a reading list were considered.
RSVPs make the best case for idempotency but have no natural need for polling or scoped keys.
Invoicing is dominated by money and tax, so the example would read as an invoicing tutorial. An
uptime monitor exercises the scheduler and queue well and has little use for `If-Match`. A reading
list is the simplest, and almost every standard would be bolted on.

**Flagpole's own evaluation API.** A `GET` endpoint returning an environment's flags would sit
inside HTTP's semantics, where OFREP does not ([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](../adr/0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)), but every application would need a
Flagpole SDK. See [ADR-0001: Flags are evaluated over OFREP](../adr/0001-flags-are-evaluated-over-ofrep.md).

**Building on Laravel Pennant.** Pennant decides flags inside one Laravel application. Flagpole is
the service that other applications ask, so Pennant does not fit the job, though a Pennant driver
backed by Flagpole would be a reasonable thing for someone to build on top of it.

## Backwards compatibility

Nothing breaks. This is a new application with no existing callers.

## Open questions

1. **Deprecation.** A new application has nothing to deprecate, and principle 1 rules out faking
   it. The suggestion is to leave it out of v1, and to show it for real the first time a released
   endpoint changes. The alternative is a deliberate v1 change that a later commit makes.

## Changelog
