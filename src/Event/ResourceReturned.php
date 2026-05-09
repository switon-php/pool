<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Pooling\PoolManagerInterface;
use function round;

/**
 * Event emitted when a resource is returned to a pool.
 *
 * Log category: <code>switon.pooling.resource.returned</code>
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Event\ResourceAcquired
 */
#[EventLevel(Severity::DEBUG)]
class ResourceReturned implements JsonSerializable
{
    public function __construct(
        public PoolManagerInterface $poolManager,
        public object               $owner,
        public object               $instance,
        public string               $type,
        public ?float               $elapsed,
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'owner' => $this->owner::class,
            'instance' => $this->instance::class . '#' . spl_object_id($this->instance),
            'type' => $this->type,
            'elapsed' => $this->elapsed !== null ? round($this->elapsed, 3) : null,
        ];
    }
}
