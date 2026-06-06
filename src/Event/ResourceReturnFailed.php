<?php

declare(strict_types=1);

namespace Switon\Pooling\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Pooling\PoolManagerInterface;

/**
 * Event emitted when a resource return attempt fails.
 *
 * Log category: <code>switon.pooling.resource.return_failed</code>
 *
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Event\ResourceReturned
 */
#[EventLevel(Severity::WARNING)]
class ResourceReturnFailed implements JsonSerializable
{
    /**
     * @param PoolManagerInterface<object, object> $poolManager Pool manager that could not accept the returned resource.
     */
    public function __construct(
        public PoolManagerInterface $poolManager,
        public object               $owner,
        public object               $instance,
        public string               $type,
        public string               $reason,
        public string               $message,
    ) {
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
