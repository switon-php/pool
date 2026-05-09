<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Pooling\PoolManagerInterface;

/**
 * Event emitted before acquiring a resource from a pool.
 *
 * Log category: <code>switon.pooling.resource.acquiring</code>
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Event\ResourceAcquired
 */
#[EventLevel(Severity::DEBUG)]
class ResourceAcquiring implements JsonSerializable
{
    public function __construct(
        public PoolManagerInterface $poolManager,
        public object               $owner,
        public string               $type,
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'owner' => $this->owner::class,
            'type' => $this->type,
        ];
    }
}
