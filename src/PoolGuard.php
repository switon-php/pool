<?php

declare(strict_types=1);

namespace Switon\Pooling;

use Throwable;

use function microtime;

/**
 * Guard for a pooled resource with automatic return on destruction.
 *
 * Guidance:
 * - use this as the default borrowing shape so resources return automatically when scope ends
 * - call <code>close()</code> or <code>release()</code> early only when you want to hand the resource back before destruction
 *
 * @template TParent of object
 * @template TResource of object
 *
 * @mixin TResource
 *
 * @method mixed send(mixed ...$arguments) Forwarded send-style proxy call.
 * Method calls are proxied to <code>$resource</code> via <code>__call()</code>.
 *
 * @see \Switon\Pooling\PoolManagerInterface
 * @see \Switon\Pooling\PoolManager
 *
 * @property-read TParent $parent
 * @property-read TResource $resource
 */
class PoolGuard
{
    /** Whether this guard already returned its resource. */
    protected bool $closed = false;
    protected float $borrowedAt;
    protected ?float $elapsed = null;

    /**
     * Create pool guard that auto-returns resource to pool.
     *
     * @param PoolManagerInterface<TParent, TResource> $poolManager Pool manager for returning resource
     * @param TParent $parent The original client instance that owns the pool
     * @param TResource $resource The resource borrowed from the pool
     * @param string $type Pool type identifier
     */
    public function __construct(
        protected PoolManagerInterface $poolManager,
        public readonly object         $parent,
        public readonly object         $resource,
        protected readonly string      $type = 'default'
    ) {
        $this->borrowedAt = microtime(true);
    }

    /**
     * Returns the elapsed borrow time in seconds.
     */
    public function getElapsed(): float
    {
        return $this->elapsed ?? (microtime(true) - $this->borrowedAt);
    }

    /**
     * Returns the borrowed resource to the pool.
     *
     * This method is idempotent: calling it multiple times returns only once.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $elapsed = $this->getElapsed();
        $this->poolManager->release($this->parent, $this->resource, $this->type, $elapsed);
        $this->elapsed = $elapsed;
        $this->closed = true;
    }

    /**
     * Alias of <code>close()</code>.
     */
    public function release(): void
    {
        $this->close();
    }

    /**
     * Automatically returns the resource to the pool when the guard is destroyed.
     */
    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // Never throw from destructors: pool may have been removed,
            // container may be shutting down, or the pool may be full.
        }
    }

    /**
     * Forward method calls to the underlying resource.
     *
     * Allows transparent access without reading <code>$resource</code> directly.
     *
     * @param string $method Method name
     * @param array<int, mixed> $arguments Method arguments
     *
     * @return mixed Method result
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->resource->$method(...$arguments);
    }
}
