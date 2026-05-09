<?php

declare(strict_types=1);

namespace Switon\Pooling\Exception;

use Switon\Pooling\Exception;

/**
 * Exception for pool exhaustion during resource acquisition.
 *
 * Raised when <code>acquire()</code> reaches timeout without an available resource.
 *
 * @see \Switon\Pooling\Exception
 * @see \Switon\Pooling\PoolManager
 * @see \Switon\Pooling\Event\ResourceBusy
 */
class BusyException extends Exception
{
}
