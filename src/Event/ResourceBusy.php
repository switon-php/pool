<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Pooling\PoolManagerInterface;

/**
 * Event emitted when acquire() times out because all resources are in use.
 *
 * Log category: <code>switon.pooling.resource.busy</code>
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Exception\BusyException
 */
#[EventLevel(Severity::WARNING)]
class ResourceBusy implements JsonSerializable
{
    public function __construct(
        public PoolManagerInterface $poolManager,
        public object               $owner,
        public string               $type,
        public int                  $capacity,
        public float                $timeout,
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'owner' => $this->owner::class,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'timeout' => $this->timeout,
        ];
    }
}
