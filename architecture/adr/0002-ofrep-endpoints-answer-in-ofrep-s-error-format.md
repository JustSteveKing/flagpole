---
id: ADR-0002
title: OFREP endpoints answer in OFREP's error format
status: draft
created: 2026-10-20
decided:
depends: [ADR-0001]
updates: []
obsoletes: []
---

# ADR-0002: OFREP endpoints answer in OFREP's error format

## Context

Kit renders every error as RFC 9457 problem details, and its guides tell clients to branch on
`type`. OFREP defines its own error bodies, with `key`, `errorCode` and `errorDetails`, and its
providers read `errorCode` to decide what to do ([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)).

An OFREP provider given a problem details body finds no `errorCode`. Whatever it does next is
outside the protocol, and the application gets a generic failure where it should have got, for
example, a clear "flag not found".

## Decision

Responses from the OFREP routes use OFREP's error bodies and status codes, served as
`application/json`. `errorCode` is OFREP's code and is never translated. `errorDetails` is
translated from `Accept-Language`, as Kit translates `detail`. Everything else in Flagpole answers
with problem details, unchanged.

## Alternatives

**Problem details everywhere.** Consistent with Kit, and wrong for the only clients these routes
have.

**Problem details with OFREP's fields as extension members.** A body with `type`, `title` and
`status`, plus `key`, `errorCode` and `errorDetails`, would satisfy both readers. It would be
served as `application/problem+json`, which OFREP does not define, and it repeats the same message
in `detail` and `errorDetails`. It is clever, and the example should not be clever.

## Consequences

- OFREP providers behave as their authors intended.
- The OFREP routes need their own exception rendering, and Kit's problem details rendering must not
  reach them. That is a deliberate exception to Kit's rule that error JSON is never built by hand,
  and the code says so where it does it.
- OFREP defines no body for `401`, `403` and `429`. Flagpole sends none, so a rate-limited client
  gets `Retry-After` and nothing else.
- Kit's problem details generator for Scribe must skip these routes, or the reference will
  describe bodies the API never sends.
