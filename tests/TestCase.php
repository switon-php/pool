<?php

declare(strict_types=1);

namespace Switon\Pooling\Tests;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Pooling\PoolManagerInterface;
use Switon\Testing\TestCase as BaseTestCase;

/**
 * Base test case for Pool tests.
 */
abstract class TestCase extends BaseTestCase
{
    #[Autowired] protected PoolManagerInterface $poolManager;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector->inject($this);
    }
}

