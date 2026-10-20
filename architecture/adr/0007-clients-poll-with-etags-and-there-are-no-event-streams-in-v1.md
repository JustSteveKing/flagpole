---
id: ADR-0007
title: Clients poll with ETags and there are no event streams in v1
status: accepted
created: 2026-10-20
decided: 2026-10-20
depends: [ADR-0003]
updates: []
obsoletes: []
---

# ADR-0007: Clients poll with ETags and there are no event streams in v1

## Context

OFREP 0.4.0 lets a bulk response advertise event streams: server-sent event connections that tell
clients to fetch again when flags change ([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)). Without them, client-side providers poll
the bulk endpoint on an interval.

An event stream holds a connection open per client. Under Octane on FrankenPHP that is possible,
but it changes how Flagpole is deployed, sized and tested, and Kit has nothing for it.

## Decision

Flagpole v1 advertises no event streams. Clients poll the bulk endpoint, and the ETag
([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)) keeps a poll that finds nothing changed cheap.

## Alternatives

**Event streams in v1.** Changes would reach clients in about a second instead of within a poll
interval. It is the most work in the project, for a difference most flag users never notice.

**Webhooks to the application.** Flagpole's webhooks are for an owner's systems ([RFC-0001: Flagpole v1](../rfc/0001-flagpole-v1.md)),
and OFREP providers do not listen for them.

## Consequences

- A change takes up to one poll interval to reach a client-side provider. Server-side providers
  evaluate on every call, so they see it at once.
- Polling load grows with the number of clients and their poll rate. The `ofrep` rate limiter and
  the cheap `304` are the controls.
- Adding streams later is additive. OFREP providers keep polling when a response has no
  `eventStreams`, and start listening when one does.
