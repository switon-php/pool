# Pool Package {#pool-package}
`switon/pool` manages owner-scoped pools of reusable objects with guard-based auto-return. Choose it when several callers need capacity-limited reuse of the same expensive resource (database client, Redis client, mailer, HTTP engine) and want lifecycle observability without business code repeating borrow/return rules.

**Key features:**

- **Owner-scoped pools:** one owner object owns one pool group; pools live in a `WeakMap` keyed by owner.
- **Type variants:** one owner can keep `default`, `readonly`, or other variants separate.
- **Guard-first borrowing:** `guard()` is the normal borrow path; the returned `Switon\Pooling\PoolGuard` releases automatically.
- **Manual lifecycle:** `acquire()` and `release()` stay available when one borrow must cross a longer boundary.
- **Runtime seeding:** `add()` can create the pool when it is missing; capacity comes from that first seed.
- **Lifecycle events:** borrow, return, busy, and return-failure paths emit events for observability.

## Installation {#installation}

**Requirements:** PHP 8.3+, `switon/core`, `switon/event`, `switon/sync`, `psr/event-dispatcher`

```bash
composer require switon/pool
```

## Quick Start {#quick-start}

Inject `Switon\Pooling\PoolManagerInterface`, seed the pool from the owner, and borrow with `guard()`.

```php
namespace App\Service;

use App\Resource\Connection;
use Switon\Core\Attribute\Autowired;
use Switon\Pooling\PoolManagerInterface;

final class DatabaseClient
{
    #[Autowired] protected PoolManagerInterface $poolManager;

    public function __construct(private readonly string $uri)
    {
        $this->poolManager->add($this, [Connection::class, ['uri' => $this->uri]], 4);
    }

    public function query(string $sql): mixed
    {
        $conn = $this->poolManager->guard($this, timeout: 1.0);

        return $conn->query($sql);
    }
}
```

When the seed is `[ClassName, $parameters]`, `Switon\Core\MakerInterface` builds the first instance and the rest are `clone`d from it.

## Core Concepts {#core-concepts}

### Owner And Type {#owner-and-type}

Pools are keyed by `(owner, type)`.

- `owner` selects the pool group; pass the object that owns the resource lifecycle (a service, a client, the call site).
- `type` selects a variant under one owner. Use `default` unless one owner needs more than one logical pool (for example `default` plus `readonly`).

Pools are stored in a `WeakMap`, so an owner that is garbage-collected takes its pools with it.

### Capacity And Seeding {#capacity}

- `create($owner, $capacity, $type)` creates an empty pool with a fixed capacity.
- `add($owner, $sample, $size, $type)` seeds the pool. When the pool does not exist yet, it is created with capacity equal to that first seed `$size`.
- `add()` rejects sizes that would exceed the existing capacity (`FullException`).

### Borrowing With `guard()` {#guard}

`guard()` is the default borrow path.

```php
$conn   = $this->poolManager->guard($this, timeout: 1.0);
$result = $conn->query($sql);
```

- `Switon\Pooling\PoolGuard` forwards method calls to the borrowed resource through `__call()`.
- `close()` and `release()` belong to the guard itself, so they do not reach the wrapped resource. Call either to return early; otherwise destruction returns it automatically.
- If the resource defines its own `close()` or `release()`, call them on `$guard->resource` directly.

### Manual Borrowing {#manual-borrowing}

Use `acquire()` and `release()` only when one borrowed resource must cross a longer lifecycle boundary that auto-return cannot follow.

Timeout rules apply to `acquire()` and `guard()`:

- `null` blocks until a resource is available
- `0.0` fails immediately when none is free
- a positive value waits up to that many seconds, then raises `BusyException`

### Lifecycle Events {#lifecycle-events}

The pool manager emits:

- `Switon\Pooling\Event\ResourceAcquiring` — before a resource is taken
- `Switon\Pooling\Event\ResourceAcquired` — after a successful borrow
- `Switon\Pooling\Event\ResourceReturned` — on successful return
- `Switon\Pooling\Event\ResourceBusy` — when timeout exhausts the pool
- `Switon\Pooling\Event\ResourceReturnFailed` — when return fails

## Configuration {#configuration}

The pool itself is configuration-light. Owners create and seed their own pools in code; per-owner sizes typically live in the owner's own config (for example `pool_size` on `Switon\Mail\MailerInterface`).

## Keep in Mind {#keep-in-mind}

- `size()` reports configured capacity, not idle count.
- `isEmpty()` means nothing is immediately borrowable, not that the pool has zero objects.
- `isEmpty()` and `size()` only work after the pool exists; check `exists()` first.
- `add()` grows an existing pool until it reaches capacity. Use `remove()` first if you need a fresh pool.
- Do not remove a pool while a guard still holds a resource; explicit `close()` after removal can fail. Destructor cleanup suppresses return-time errors.

## Next {#next}

- **To read short answers:** [FAQ](faq.md#pool-faq)
- **To understand the trade-offs:** [Design Rationale](design.md#design-rationale)
- **To pool database clients:** [DB Package](../../db/docs/README.md)
- **To pool Redis clients:** [Redis Package](../../redis/docs/README.md)
- **To pool outbound HTTP engines:** [HTTP Client Package](../../http-client/docs/README.md)
