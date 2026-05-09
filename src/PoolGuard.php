<?php

declare(strict_types=1);

namespace Switon\Pooling;

use Throwable;
use function microtime;

/**
 * Guard for a pooled resource with automatic return on destruction.
 *
 * Use when you want scoped resource usage without manual <code>release()</code>.
 * Method calls are proxied to <code>$resource</code> via <code>__call()</code>.
 *
 * @see \Switon\Pooling\PoolManagerInterface
 * @see \Switon\Pooling\PoolManager
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
     * @param PoolManagerInterface $poolManager Pool manager for returning resource
     * @param object $parent The original client instance that owns the pool
     * @param object $resource The resource borrowed from the pool
     * @param string $type Pool type identifier
     */
    public function __construct(
        protected PoolManagerInterface $poolManager,
        public readonly object         $parent,
        public readonly object         $resource,
        protected readonly string      $type = 'default'
    )
    {
        $this->borrowedAt = microtime(true);
    }

    /**
     * Get elapsed borrow time in seconds.
     */
    public function getElapsed(): float
    {
        return $this->elapsed ?? (microtime(true) - $this->borrowedAt);
    }

    /**
     * Return the borrowed resource to the pool.
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
     * Automatically return resource to pool when destroyed.
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
     * @return mixed Method result
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->resource->$method(...$arguments);
    }
}
