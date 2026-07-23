<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Pooling\PoolManagerInterface;

/**
 * Event emitted when a resource return attempt fails.
 *
 * Log category: <code>switon.pooling.resource.return.failed</code>
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Event\ResourceReturned
 */
#[EventLevel(Severity::WARNING)]
class ResourceReturnFailed implements JsonSerializable, SenderAwareInterface
{
    /**
     * @param PoolManagerInterface<object, \Switon\Core\OwnerAwareInterface<object>> $sender Pool manager that emitted the event

     * @param object $owner Owner of the pooled resource
     * @param object $instance Returned resource instance
     * @param string $type Pool type
     * @param string $reason Failure reason code
     * @param string $message Failure detail message
     */
    public function __construct(
        public PoolManagerInterface $sender,
        public object $owner,
        public object $instance,
        public string $type,
        public string $reason,
        public string $message,
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
            'reason' => $this->reason,
            'message' => $this->message,
        ];
    }
}
