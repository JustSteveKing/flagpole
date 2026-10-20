---
id: ADR-0008
title: Kit is upstream
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0008: Kit is upstream

## Context

Flagpole exists to show Kit in use ([RFC-0001: Flagpole v1](../rfc/0001-flagpole-v1.md)). Every change it makes to Kit's own code is a
change a reader has to tell apart from Flagpole's. Building Flagpole will also find things in Kit
that are wrong or missing.

## Decision

Flagpole treats Kit as upstream. It adds to Kit's code freely, changes it only where Flagpole
cannot work otherwise, and gives each such change its own commit saying why. Anything wrong or
missing in Kit for every application is fixed in Kit first, by pull request, and comes into
Flagpole from there.

## Alternatives

**Change Kit's code in place as needed.** Faster, and the example drifts from Kit until a reader
cannot tell which parts are which.

**Install Kit as a Composer package.** Kit is a starter kit that hands its code to the
application. It is not built to be a dependency, and that is not how readers will use it.

## Consequences

- A diff against the first commit shows exactly what Flagpole is.
- Some Flagpole work waits on a Kit change. Building Flagpole is also Kit's best test, so that is
  a cost worth paying.
- Kit changes made after Flagpole's first commit come in by hand, as they would for any Kit
  application. How that goes is something the example can show too.
