<?php

declare(strict_types=1);

namespace Switon\Pooling;

use Switon\Pooling\Exception\BusyException;
use Switon\Pooling\Exception\FullException;
use Switon\Pooling\Exception\InvalidSizeException;
use Switon\Pooling\Exception\PoolAlreadyExistsException;
use Switon\Pooling\Exception\PoolNotFoundException;

/**
 * Contract for managing reusable resource pools by owner and type.
 *
 * Use when you need:
 * - capacity-limited reuse of expensive objects
 * - explicit acquire()/release() control
 * - automatic return with guard()
 *
 * Owner selects the pool namespace.
 * Type selects a pool variant (for example, <code>default</code>, <code>readonly</code>).
 *
 * Guidance: When one owner uses only one logical pool, omit <code>$type</code> and use the default type; pass explicit type names only when one owner has multiple logical pools.
 *
 * Road-signs:
 * - owner + type identify one pool
 * - single-pool callers can omit type
 * - create()/add() define capacity and seed resources
 * - guard() is the default borrow path
 * - acquire()/release() for explicit lifecycle
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\PoolGuard
 * @see \Switon\Pooling\Exception
 * @see \Switon\Db\Client
 * @see \Switon\Redis\Client
 * @see \Switon\HttpClient\HttpClient Typical consumer
 * @see \Switon\Pooling\Event\ResourceAcquiring
 * @see \Switon\Pooling\Event\ResourceAcquired
 * @see \Switon\Pooling\Event\ResourceReturned
 * @see \Switon\Pooling\Event\ResourceReturnFailed
 * @see \Switon\Pooling\Event\ResourceBusy
 */
interface PoolManagerInterface
{
    /**
     * Remove pool or specific pool type.
     *
     * @param object $owner Pool owner object
     * @param string|null $type Pool type to remove, null removes all types
     */
    public function remove(object $owner, ?string $type = null): void;

    /**
     * Create new connection pool with specified capacity.
     *
     * @param object $owner Pool owner object
     * @param int $capacity Maximum number of connections
     * @param string $type Pool type identifier
     * @throws PoolAlreadyExistsException If pool already exists
     */
    public function create(object $owner, int $capacity, string $type = 'default'): void;

    /**
     * Add connection instances to pool.
     *
     * @param object $owner Pool owner object
     * @param object|array $sample Connection instance or [className, parameters]
     * @param int $size Number of connections to add
     * @param string $type Pool type identifier
     * @throws InvalidSizeException If size is less than 1
     * @throws FullException If pool capacity exceeded
     */
    public function add(object $owner, object|array $sample, int $size = 1, string $type = 'default'): void;

    /**
     * Release (return) resource instance to pool.
     *
     * Release an instance previously obtained from <code>acquire()</code> or <code>guard()</code>.
     * Passing unrelated instances is unsupported.
     *
     * @param object $owner Pool owner object
     * @param object $instance Resource instance to return
     * @param string $type Pool type identifier
     * @param float|null $elapsed Borrowed elapsed time in seconds, null when unavailable
     * @throws PoolNotFoundException If pool doesn't exist
     * @throws FullException If pool is already full
     */
    public function release(object $owner, object $instance, string $type = 'default', ?float $elapsed = null): void;

    /**
     * Acquire resource instance from pool.
     *
     * For most cases, prefer <code>guard()</code> for automatic return.
     *
     * @param object $owner Pool owner object
     * @param float|null $timeout Timeout in seconds, null for blocking
     * @param string $type Pool type identifier
     * @return object Resource instance
     * @throws PoolNotFoundException If pool doesn't exist
     * @throws BusyException If no resources available within timeout
     */
    public function acquire(object $owner, ?float $timeout = null, string $type = 'default'): object;

    /**
     * Guard a pooled resource with automatic return.
     *
     * Acquires a resource and wraps it in <code>PoolGuard</code> for scoped auto-release.
     *
     * @param object $owner Pool owner object
     * @param float|null $timeout Timeout in seconds, null for blocking
     * @param string $type Pool type identifier
     * @return PoolGuard Pool guard with auto-return capability
     * @throws PoolNotFoundException If pool doesn't exist
     * @throws BusyException If no resources available within timeout
     */
    public function guard(object $owner, ?float $timeout = null, string $type = 'default'): PoolGuard;

    /**
     * Check if pool is empty (no available resources).
     *
     * @param object $owner Pool owner object
     * @param string $type Pool type identifier
     * @return bool True if pool has no available resources
     * @throws PoolNotFoundException If pool doesn't exist
     */
    public function isEmpty(object $owner, string $type = 'default'): bool;

    /**
     * Check if pool exists.
     *
     * @param object $owner Pool owner object
     * @param string $type Pool type identifier
     * @return bool True if pool exists
     */
    public function exists(object $owner, string $type = 'default'): bool;

    /**
     * Get pool capacity.
     *
     * @param object $owner Pool owner object
     * @param string $type Pool type identifier
     * @return int Pool capacity
     * @throws PoolNotFoundException If pool doesn't exist
     */
    public function size(object $owner, string $type = 'default'): int;
}
