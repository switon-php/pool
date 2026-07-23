<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Core\SenderAwareInterface;
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
class ResourceAcquiring implements JsonSerializable, SenderAwareInterface
{
    /**
     * @param PoolManagerInterface<object, \Switon\Core\OwnerAwareInterface<object>> $sender Pool manager that emitted the event

     * @param object $owner Owner of the pooled resource
     * @param string $type Pool type
     */
    public function __construct(
        public PoolManagerInterface $sender,
        public object $owner,
        public string $type,
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
            'type' => $this->type,
        ];
    }
}
