---
id: ADR-0003
title: Bulk evaluation answers a matching If-None-Match with 304
status: accepted
created: 2026-10-20
decided: 2026-10-20
depends: [ADR-0001, RFC-0002]
updates: []
obsoletes: []
---

# ADR-0003: Bulk evaluation answers a matching If-None-Match with 304

## Context

OFREP's bulk endpoint is a `POST`, returns an `ETag`, and answers a matching `If-None-Match` with
`304 Not Modified` ([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)). RFC 9110 says a matching `If-None-Match` on any method other
than `GET` or `HEAD` gets `412 Precondition Failed`. OFREP providers are written to expect the
`304`.

api-toolbox follows RFC 9110. Its precondition middleware applies `If-None-Match` to unsafe methods
as the RFC says, and it only adds validators to `GET` and `HEAD` responses. Used on this route, it
would send the `412`.

There is a second problem. OFREP describes the ETag as the state of all flags, but the response
depends on the context in the body too. An ETag built only from the flags would tell a client that
nothing had changed after its context did.

## Decision

The bulk route answers as OFREP specifies, with api-toolbox's conditional request middleware left
off. Its ETag is a hash of the environment's version ([RFC-0002: Application architecture](../rfc/0002-application-architecture.md)) and a canonical form of the
request's context, so it changes when either does. When `If-None-Match` matches, the route answers
`304` with no body. It is the only place in Flagpole that answers a `POST` with `304`, and both the
code and the API reference say it departs from RFC 9110 on purpose.

## Alternatives

**Follow RFC 9110 and answer `412`.** Correct HTTP, and every OFREP provider would read it as an
error and stop caching, which is worse for everyone.

**No ETag on the bulk route.** Within the rules, and clients would download every flag on every
poll. Polling is the normal case ([ADR-0007: Clients poll with ETags and there are no event streams in v1](0007-clients-poll-with-etags-and-there-are-no-event-streams-in-v1.md)), so this throws away the main thing ETags are for.

**An ETag from the environment's version alone**, as OFREP describes it. Cheaper, and it gives a
wrong `304` whenever a client's context changes without the flags changing, such as a user signing
in.

## Consequences

- OFREP providers cache as designed, and a poll that finds nothing changed costs a version lookup
  and a hash.
- The route handles `If-None-Match` itself, so it needs its own tests, including weak tags and
  lists of tags.
- The version must go up on every change that affects evaluation. A change that forgets to bump it
  serves stale results until something else does, so the area RFCs list each change that bumps it,
  each with a test.
- Canonicalising the context means sorting object keys recursively before hashing, so two clients
  sending the same context in a different order share an ETag.
- If OFREP moves to `GET`, or to RFC 9110's semantics, this ADR is the one to replace.
