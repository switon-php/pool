<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Pooling\PoolManagerInterface;

use function round;

/**
 * Event emitted after a resource is acquired from a pool.
 *
 * Log category: <code>switon.pooling.resource.acquired</code>
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Event\ResourceAcquiring
 * @see \Switon\Pooling\Event\ResourceReturned
 */
#[EventLevel(Severity::DEBUG)]
class ResourceAcquired implements JsonSerializable, SenderAwareInterface
{
    /**
     * @param PoolManagerInterface<object, \Switon\Core\OwnerAwareInterface<object>> $sender Pool manager that emitted the event

     * @param object $owner Owner of the pooled resource
     * @param object $instance Acquired resource instance
     * @param string $type Pool type
     * @param float $elapsed Acquire time in seconds
     */
    public function __construct(
        public PoolManagerInterface $sender,
        public object $owner,
        public object $instance,
        public string $type,
        public float $elapsed,
    ) {
    }

    /** @return PoolManagerInterface<object, \Switon\Core\OwnerAwareInterface<object>> */
    public function getSender(): PoolManagerInterface
    {
        return $this->sender;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'owner' => $this->owner::class,
            'instance' => $this->instance::class . '#' . spl_object_id($this->instance),
            'type' => $this->type,
            'elapsed' => round($this->elapsed, 3),
        ];
    }
}
