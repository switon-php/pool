# Design Rationale {#design-rationale}
`switon/pool` standardizes one borrow/return boundary for reusable resources.

## See also {#see-also}

- [Pool Package](README.md) — usage, concepts, and boundaries
- [FAQ](faq.md#pool-faq) — short answers and troubleshooting

## Core Problem {#core-problem}

Reuse is easy to say and easy to get wrong.

- ownership drifts into global names
- borrow and return code leaks across exception paths
- application code repeats lifecycle rules
- observability ends up mixed into business logic

## Owner And Type {#owner-and-type}

Pools are keyed by `(owner, type)`.

That keeps one owner isolated from another and lets one owner keep separate pool variants when needed. Storing pools in a `WeakMap` ties pool lifetime to owner lifetime, so retired owners do not leak pools.

## Guard-First Lifecycle {#guard-first-lifecycle}

`guard()` is the normal borrow path. `acquire()` and `release()` stay available for explicit lifecycle control.

That trade-off keeps the common path short while still supporting manual handoff when a borrowed resource must cross a longer lifecycle boundary.

## Runtime Seeding {#runtime-seeding}

`add()` can create the pool when it does not exist yet. That keeps bootstrap code simple for applications that seed pools from a service constructor or setup path.

## Observability {#observability}

Borrow, return, busy, and return-failure events keep lifecycle visibility outside application logic.
