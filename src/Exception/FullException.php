<?php

declare(strict_types=1);

namespace Switon\Pooling\Exception;

use Switon\Pooling\Exception;

/**
 * Exception for capacity overflow when adding resources to a pool.
 *
 * Raised when <code>add()</code> would exceed pool capacity.
 *
 * @see \Switon\Pooling\Exception
 * @see \Switon\Pooling\PoolManager
 */
class FullException extends Exception
{
}
