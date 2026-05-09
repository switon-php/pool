<?php

declare(strict_types=1);

namespace Switon\Pooling\Exception;

use Switon\Pooling\Exception;

/**
 * Exception for duplicate pool creation.
 *
 * Raised when <code>create()</code> is called for an existing owner/type pair.
 *
 * @see \Switon\Pooling\Exception
 * @see \Switon\Pooling\PoolManager
 */
class PoolAlreadyExistsException extends Exception
{
}
