---
id: ADR-0006
title: Percentage rollouts bucket on a hash of the flag key and targeting key
status: draft
created: 2026-10-20
decided:
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0006: Percentage rollouts bucket on a hash of the flag key and targeting key

## Context

A split serves variants by percentage, such as 10% `on` and 90% `off`. The same subject must get
the same variant on every evaluation and on every server, or a user sees a feature flicker on and
off between requests. OFREP gives the server the context, and its `targetingKey` names the subject
([REF-0001: OpenFeature Remote Evaluation Protocol](../ref/0001-openfeature-remote-evaluation-protocol.md)).

## Decision

A split puts a subject in one of 10,000 buckets: the first four bytes of the SHA-256 of the flag
key, a full stop and the `targetingKey`, read as an unsigned big-endian 32-bit integer, modulo
10,000. Variant weights
are in hundredths of a percent and fill the buckets in the order the split lists them. A split
evaluated without a `targetingKey` fails with `TARGETING_KEY_MISSING`.

## Alternatives

**Random on each evaluation.** Fails the requirement.

**Bucket on the `targetingKey` alone.** Stable, but a subject in the first 10% of one flag is in
the first 10% of every flag, so the same users get every rollout first.

**Store an assignment per subject.** Exact, and it needs a write on the first evaluation of every
subject. Without it, evaluation stays read-only.

**A faster non-cryptographic hash**, such as MurmurHash. The distribution would be as good, but
PHP has no built-in one, and SHA-256 is fast enough for this.

## Consequences

- Results are stable across servers and restarts, with no stored state.
- Raising a rollout from 10% to 20% keeps the first 10% where they were, because buckets do not
  move. Reordering the variants in a split does move people, and the settings documentation must
  say so.
- Renaming a flag would move every subject, which is one more reason flag keys cannot change.
- Four bytes fit in a PHP integer on every platform. Eight would overflow PHP's signed 64-bit
  integers and need GMP or BCMath. The modulo bias from 2^32 not dividing evenly by 10,000 is
  a few in a million, which no rollout will notice.
- The hash is part of the contract. Changing it reshuffles every running rollout, so it changes
  only through a new ADR.
