# Pool FAQ {#pool-faq}
Short answers for setup, borrowing, and pool state.

> **Prerequisites:** [Pool Package](README.md)

## Getting Started {#getting-started}

### Do I need a config file for Pool? {#config-file}

No. Pool is configuration-light; owners call `create()` or `add()` in code.

### What do `owner` and `type` mean? {#owner-type}

`owner` selects the pool group. `type` selects a variant under that owner. See [Owner And Type](README.md#owner-and-type).

### What does `guard()` return? {#guard-return}

`Switon\Pooling\PoolGuard`. It forwards method calls to the borrowed resource and releases it back to the pool when destroyed.

### When should I use `guard()` instead of `acquire()` and `release()`? {#guard-vs-manual}

Use `guard()` by default. Use manual `acquire()` and `release()` only when the borrowed object must cross a longer lifecycle boundary that auto-return cannot follow.

## State {#state}

### What does `size()` return? {#size-meaning}

Configured capacity, not idle count.

### What happens if I call `add()` before `create()`? {#add-before-create}

The pool is created automatically and uses that first `add()` size as capacity.

### What happens if I remove a pool while a guard still exists? {#remove-while-borrowed}

Explicit `close()` can raise `PoolNotFoundException` after removal. Destructor cleanup suppresses return-time errors, so the pool may simply disappear when the owner is collected.

## Troubleshooting {#troubleshooting}

### Why did borrowing fail with `BusyException`? {#busy-exception}

Usually the pool is undersized, guards are held too long, or the timeout is too short.

### Why does `isEmpty()` throw? {#isempty-throws}

It throws `PoolNotFoundException` when the pool does not exist yet. Use `exists()` first.

### Why does a pool disappear on its own? {#weakmap-owner}

Pools are owned through a `WeakMap`, so when the owner object is garbage-collected the pool group goes with it.
