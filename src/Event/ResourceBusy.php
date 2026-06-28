<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Core\SenderAwareInterface;
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
class ResourceBusy implements JsonSerializable, SenderAwareInterface
{
    /**
     * @param object $owner Owner of the pooled resource
     * @param string $type Pool type
     * @param int $capacity Pool capacity
     * @param float $timeout Acquire timeout
     */
    public function __construct(
        public PoolManagerInterface $sender,
        public object $owner,
        public string $type,
        public int $capacity,
        public float $timeout,
    ) {
    }

    public function getSender(): PoolManagerInterface
    {
        return $this->sender;
    }

    /** @return array<string, mixed> */
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
