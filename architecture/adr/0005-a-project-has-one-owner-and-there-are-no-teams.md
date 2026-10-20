---
id: ADR-0005
title: A project has one owner and there are no teams
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0005: A project has one owner and there are no teams

## Context

Real flag services have organisations, teams and roles. Kit has users and nothing above them, and
its webhook subscriptions belong to a user ([REF-0002: What Kit gives an application](../ref/0002-what-kit-gives-an-application.md)).

Teams would be the biggest single piece of Flagpole: invitations, membership, roles, a permission
check on every route, and webhooks owned by a team. None of it uses Kit any more than the rest of
the application does, and all of it would be in the way of a reader looking for how Kit is used.

## Decision

A project belongs to exactly one user, its owner. Only the owner can see or change it, and webhooks
about it go to the owner's subscriptions. Machines act for the owner through API keys and SDK keys.

## Alternatives

**Teams in v1.** Covered above: a lot of code, little of it about Kit.

**Sharing a project with other users** through a membership table with no roles. It looks small,
and it still brings invitations, removal, and the question of whose webhooks fire.

## Consequences

- Every query is scoped by the owner, and route model binding does it in one place.
- Another owner's project answers `404`, the same as a missing one.
- A team that wants Flagpole shares an account or builds teams itself. The README says so.
- Adding teams later means a new owner for projects and webhooks, and a new ADR that replaces this
  one.
