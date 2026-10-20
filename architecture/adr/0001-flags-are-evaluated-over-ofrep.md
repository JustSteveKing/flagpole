---
id: ADR-0001
title: Flags are evaluated over OFREP
status: accepted
created: 2026-10-20
decided: 2026-10-20
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0001: Flags are evaluated over OFREP

## Context

Applications need to ask Flagpole what a flag is for a given user or request. Every flag service
has an evaluation API, and almost every one ships SDKs in several languages to call it, because
each API is its own shape.

OpenFeature standardises the SDK side: an application codes against the OpenFeature API, and a
provider connects it to a flag service. OFREP ([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)) standardises the wire, so one
community-maintained provider works with any server that implements the protocol.

Flagpole is an example application. Writing and maintaining SDKs is out of the question, and an
evaluation API nobody can call without one would make the example unusable.

## Decision

Flagpole implements OFREP's two core endpoints, single and bulk evaluation, at the paths the
protocol fixes, and has no other evaluation API. Applications use an OpenFeature SDK with its OFREP
provider, configured with Flagpole's URL and an SDK key.

## Alternatives

**A Flagpole evaluation API under `/v1`.** It could follow Kit's conventions all the way, including
`GET` with ETags inside HTTP's rules. It loses because every application would need a client
written for it.

**Both: a Flagpole API and OFREP.** Two ways to do one thing, both to document and test, and the
example would have to explain which to use.

**A ruleset download, evaluated in the client.** Some flag services send the rules to the SDK and
evaluate locally. It is the fastest at runtime, but it publishes the targeting rules to every
client, browsers included, and it needs an SDK that knows Flagpole's rule format.

## Consequences

- Any OpenFeature SDK with an OFREP provider works from the start, and the README can show one.
- OFREP's shapes are fixed by someone else. Where they disagree with Kit, Flagpole follows OFREP on
  the OFREP routes and records each case: errors ([ADR-0002: OFREP endpoints answer in OFREP's error format](0002-ofrep-endpoints-answer-in-ofrep-s-error-format.md)) and caching ([ADR-0003: Bulk evaluation answers a matching If-None-Match with 304](0003-bulk-evaluation-answers-a-matching-if-none-match-with-304.md)).
- OFREP is pre-1.0. A breaking change to it means following it, and the version Flagpole implements
  is pinned in its conformance tests.
- Flag values are limited to the types OFREP can return.
