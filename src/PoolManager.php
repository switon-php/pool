<?php

declare(strict_types=1);

namespace Switon\Pooling;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\MakerInterface;
use Switon\Pooling\Event\ResourceAcquired;
use Switon\Pooling\Event\ResourceAcquiring;
use Switon\Pooling\Event\ResourceBusy;
use Switon\Pooling\Event\ResourceReturned;
use Switon\Pooling\Event\ResourceReturnFailed;
use Switon\Pooling\Exception\BusyException;
use Switon\Pooling\Exception\FullException;
use Switon\Pooling\Exception\InvalidSizeException;
use Switon\Pooling\Exception\PoolAlreadyExistsException;
use Switon\Pooling\Exception\PoolNotFoundException;
use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;
use Switon\Sync\Stack;
use WeakMap;
use function is_array;
use function microtime;

/**
 * Default implementation of <code>PoolManagerInterface</code>.
 *
 * Use when you need framework-managed pools with acquire/release lifecycle events.
 *
 * Quick start:
 * <code>
 * $poolManager->add($owner, $sampleConnection, 8, 'default');
 * $conn = $poolManager->guard($owner, 1.0, 'default');
 * $conn->query('SELECT 1');
 * </code>
 *
 * Pools are keyed by owner and type.
 * <code>guard()</code> returns <code>PoolGuard</code> for automatic release.
 *
 * @see \Switon\Pooling\PoolManagerInterface
 * @see \Switon\Pooling\PoolGuard
 * @see \Switon\Sync\Stack
 * @see \Switon\Core\MakerInterface
 * @see \Switon\Pooling\Event\ResourceAcquired
 * @see \Switon\Pooling\Event\ResourceReturned
 * @see \Switon\Pooling\Event\ResourceReturnFailed
 * @see \Switon\Pooling\Event\ResourceBusy
 */
class PoolManager implements PoolManagerInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected MakerInterface $maker;

    /** @var WeakMap<object, array<string, Stack>> Pools grouped by owner and type. */
    protected WeakMap $pools;

    public function __construct()
    {
        $this->pools = new WeakMap();
    }

    /**
     * Remove pool or specific pool type.
     *
     * If type is null, removes all pools for the owner.
     *
     * @param object $owner Pool owner object
     * @param string|null $type Pool type to remove, null removes all types
     */
    public function remove(object $owner, ?string $type = null): void
    {
        if ($type === null) {
            unset($this->pools[$owner]);
        } else {
            // WeakMap doesn't support nested array unset, need to reassign
            if (isset($this->pools[$owner])) {
                $pools = $this->pools[$owner];
                unset($pools[$type]);
                $this->pools[$owner] = $pools;
            }
        }
    }

    /**
     * Create new connection pool with specified capacity.
     *
     * Pool must not already exist for the owner/type combination.
     *
     * @param object $owner Pool owner object
     * @param int $capacity Maximum number of connections
     * @param string $type Pool type identifier
     * @throws \Switon\Pooling\Exception\PoolAlreadyExistsException If pool already exists
     */
    public function create(object $owner, int $capacity, string $type = 'default'): void
    {
        if (isset($this->pools[$owner][$type])) {
            PoolAlreadyExistsException::raise('Pool "{type}" already exists for {owner}, use remove() first to recreate', ['type' => $type, 'owner' => $owner::class]);
        }

        $this->pools[$owner] ??= [];
        $this->pools[$owner][$type] = new Stack($capacity);
    }

    /**
     * Add connection instances to pool.
     *
     * Creates the pool when missing.
     * If sample is an array, <code>MakerInterface</code> creates the first instance.
     * Remaining instances are cloned from the first one.
     *
     * @param object $owner Pool owner object
     * @param object|array $sample Connection instance or [className, parameters]
     * @param int $size Number of connections to add
     * @param string $type Pool type identifier
     * @throws \Switon\Pooling\Exception\InvalidSizeException If size is less than 1
     * @throws \Switon\Pooling\Exception\FullException If pool capacity exceeded
     */
    public function add(object $owner, object|array $sample, int $size = 1, string $type = 'default'): void
    {
        if ($size < 1) {
            InvalidSizeException::raise(
                'Pool "{type}" add size must be greater than 0, got {size}',
                ['type' => $type, 'owner' => $owner::class, 'size' => $size]
            );
        }

        if (!$stack = $this->pools[$owner][$type] ?? null) {
            $this->pools[$owner] ??= [];
            $this->pools[$owner][$type] = $stack = new Stack($size);
        } elseif ($stack->length() + $size > $stack->capacity()) {
            FullException::raise(
                'Pool "{type}" capacity exceeded: trying to add {size} to pool with {current}/{capacity} resources',
                ['type' => $type, 'owner' => $owner::class, 'size' => $size, 'current' => $stack->length(), 'capacity' => $stack->capacity()]
            );
        }

        if (is_array($sample)) {
            $sample = $this->maker->make($sample[0], $sample[1] ?? []);
        }

        $stack->push($sample);

        for ($i = 1; $i < $size; $i++) {
            $stack->push(clone $sample);
        }
    }

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
     * @throws \Switon\Pooling\Exception\PoolNotFoundException If pool doesn't exist
     * @throws \Switon\Pooling\Exception\FullException If pool is already full
     */
    public function release(object $owner, object $instance, string $type = 'default', ?float $elapsed = null): void
    {
        if (!$stack = $this->pools[$owner][$type] ?? null) {
            $this->eventDispatcher->dispatch(
                new ResourceReturnFailed(
                    poolManager: $this,
                    owner: $owner,
                    instance: $instance,
                    type: $type,
                    reason: 'pool_not_found',
                    message: 'Pool not found when returning resource'
                )
            );
            PoolNotFoundException::raise('Cannot release to pool "{type}": pool not found for {owner}, call add() first', ['type' => $type, 'owner' => $owner::class]);
        }

        try {
            $stack->push($instance);
        } catch (ChannelFullException) {
            $this->eventDispatcher->dispatch(
                new ResourceReturnFailed(
                    poolManager: $this,
                    owner: $owner,
                    instance: $instance,
                    type: $type,
                    reason: 'pool_full',
                    message: 'Pool is full when returning resource'
                )
            );
            FullException::raise(
                'Pool "{type}" is full: cannot return resource for {owner}, capacity is {capacity}',
                ['type' => $type, 'owner' => $owner::class, 'capacity' => $stack->capacity()]
            );
        }

        $this->eventDispatcher->dispatch(new ResourceReturned($this, $owner, $instance, $type, $elapsed));
    }

    /**
     * Acquire resource instance from pool.
     *
     * Supports blocking mode (<code>$timeout = null</code>) and timed waits.
     * Prefer <code>guard()</code> for automatic return in most use cases.
     *
     * @param object $owner Pool owner object
     * @param float|null $timeout Timeout in seconds, null for blocking
     * @param string $type Pool type identifier
     * @return object Resource instance
     * @throws \Switon\Pooling\Exception\PoolNotFoundException If pool doesn't exist
     * @throws \Switon\Pooling\Exception\BusyException If no resources available within timeout
     */
    public function acquire(object $owner, ?float $timeout = null, string $type = 'default'): object
    {
        if (!$stack = $this->pools[$owner][$type] ?? null) {
            PoolNotFoundException::raise('Cannot acquire from pool "{type}": pool not found for {owner}, call add() first', ['type' => $type, 'owner' => $owner::class]);
        }

        $this->eventDispatcher->dispatch(new ResourceAcquiring($this, $owner, $type));
        $start_time = microtime(true);
        try {
            $instance = $timeout !== null ? $stack->pop($timeout) : $stack->pop();
        } catch (ChannelEmptyException $e) {
            $instance = false;
        }

        if (!$instance) {
            $capacity = $stack->capacity();
            $this->eventDispatcher->dispatch(new ResourceBusy($this, $owner, $type, $capacity, $timeout ?? 0));
            BusyException::raise('Pool "{type}" exhausted: all {capacity} resources are in use, timeout after {timeout}s', ['type' => $type, 'owner' => $owner::class, 'capacity' => $capacity, 'timeout' => $timeout ?? 0]);
        }
        $this->eventDispatcher->dispatch(new ResourceAcquired($this, $owner, $instance, $type, microtime(true) - $start_time));

        return $instance;
    }

    /**
     * Guard a pooled resource with automatic return.
     *
     * Acquires a resource and wraps it in <code>PoolGuard</code> for scoped auto-release.
     *
     * @param object $owner Pool owner object
     * @param float|null $timeout Timeout in seconds
     * @param string $type Pool type identifier
     * @return PoolGuard Pool guard with auto-return capability
     * @throws \Switon\Pooling\Exception\PoolNotFoundException If pool doesn't exist
     * @throws \Switon\Pooling\Exception\BusyException If no resources available within timeout
     */
    public function guard(object $owner, ?float $timeout = null, string $type = 'default'): PoolGuard
    {
        $resource = $this->acquire($owner, $timeout, $type);

        return new PoolGuard(
            poolManager: $this,
            parent: $owner,
            resource: $resource,
            type: $type
        );
    }

    /**
     * Check if pool is empty (no available resources).
     *
     * @param object $owner Pool owner object
     * @param string $type Pool type identifier
     * @return bool True if pool has no available resources
     * @throws \Switon\Pooling\Exception\PoolNotFoundException If pool doesn't exist
     */
    public function isEmpty(object $owner, string $type = 'default'): bool
    {
        if (!$stack = $this->pools[$owner][$type] ?? null) {
            PoolNotFoundException::raise('Pool "{type}" not found for {owner}, call add() or create() first', ['type' => $type, 'owner' => $owner::class]);
        }

        return $stack->isEmpty();
    }

    /**
     * Check if pool exists.
     *
     * @param object $owner Pool owner object
     * @param string $type Pool type identifier
     * @return bool True if pool exists
     */
    public function exists(object $owner, string $type = 'default'): bool
    {
        return isset($this->pools[$owner][$type]);
    }

    /**
     * Get pool capacity.
     *
     * @param object $owner Pool owner object
     * @param string $type Pool type identifier
     * @return int Pool capacity
     * @throws \Switon\Pooling\Exception\PoolNotFoundException If pool doesn't exist
     */
    public function size(object $owner, string $type = 'default'): int
    {
        if (!$stack = $this->pools[$owner][$type] ?? null) {
            PoolNotFoundException::raise('Pool "{type}" not found for {owner}, call add() or create() first', ['type' => $type, 'owner' => $owner::class]);
        }

        return $stack->capacity();
    }
}
