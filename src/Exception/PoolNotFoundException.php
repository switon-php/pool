<?php

declare(strict_types=1);

namespace Switon\Pooling\Exception;

use Switon\Pooling\Exception;

/**
 * Exception for missing pool access.
 *
 * Raised when acquire/release/inspection is requested for an unknown owner/type pair.
 *
 * @see \Switon\Pooling\Exception
 * @see \Switon\Pooling\PoolManager
 */
class PoolNotFoundException extends Exception
{
}
