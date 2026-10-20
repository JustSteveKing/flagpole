---
id: ADR-0002
title: OFREP endpoints answer in OFREP's error format
status: accepted
created: 2026-10-20
decided: 2026-10-20
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

Where OFREP defines an error body, the OFREP routes send it, as `application/json`: `400` and
`404` with `key`, `errorCode` and `errorDetails`, the bulk endpoint's `400` without `key`, and
`500` with `errorDetails` alone. `errorCode` is OFREP's code and is never translated.
`errorDetails` is translated from `Accept-Language`, as Kit translates `detail`. Where OFREP
defines no body, for `401`, `403`, `415` and `429`, Kit's problem details are sent unchanged.
Everything outside the OFREP routes answers with problem details as before.

## Alternatives

**Problem details everywhere.** Consistent with Kit, and wrong for the only clients these routes
have.

**Problem details with OFREP's fields as extension members.** A body with `type`, `title` and
`status`, plus `key`, `errorCode` and `errorDetails`, would satisfy both readers. It would be
served as `application/problem+json`, which OFREP does not define, and it repeats the same message
in `detail` and `errorDetails`. It is clever, and the example should not be clever.

## Consequences

- OFREP providers get the bodies their authors wrote them to read.
- `400` and `404` are results the controllers produce, so they are built there and never thrown.
- A `500` is thrown from anywhere, so the OFREP routes need one exception render callback of their
  own. Kit's rule is that render callbacks in `bootstrap/app.php` only write the audit log; this is
  the one deliberate exception, and the callback says so.
- Authentication, abilities, media types and rate limits stay Kit's, with Kit's problem bodies.
  Providers act on those statuses alone, and `429` still carries `Retry-After`.
- Scribe must document the OFREP routes with OFREP's bodies for `400`, `404` and `500`, so Kit's
  problem details generator has to skip those statuses on these routes.
