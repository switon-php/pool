<?php

declare(strict_types=1);

namespace Switon\Pooling\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use stdClass;
use Switon\Pooling\PoolGuard;
use Switon\Pooling\PoolManagerInterface;

#[AllowMockObjectsWithoutExpectations]
class PoolGuardTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $scoped = new PoolGuard($poolManager, $parent, $resource, 'default');

        $this->assertSame($parent, $scoped->parent);
        $this->assertSame($resource, $scoped->resource);
    }

    public function testPropertiesAreReadonly(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $scoped = new PoolGuard($poolManager, new stdClass(), new stdClass());

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // @phpstan-ignore-next-line intentional readonly mutation assertion
        $scoped->parent = new stdClass();
    }

    public function testCanBeUsedWithNamedParameters(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $scoped = new PoolGuard(
            poolManager: $poolManager,
            parent: $parent,
            resource: $resource,
            type: 'default'
        );

        $this->assertSame($parent, $scoped->parent);
        $this->assertSame($resource, $scoped->resource);
    }

    public function testAutomaticallyReturnsResourceOnDestruction(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->expects($this->once())
            ->method('release')
            ->with($parent, $resource, 'test_type', $this->isFloat());

        $scoped = new PoolGuard($poolManager, $parent, $resource, 'test_type');
        unset($scoped); // Trigger __destruct
    }

    public function testAutomaticallyReturnsResourceWithDefaultTypeOnDestruction(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->expects($this->once())
            ->method('release')
            ->with($parent, $resource, 'default', $this->isFloat());

        $scoped = new PoolGuard($poolManager, $parent, $resource);
        unset($scoped);
    }

    public function testCloseReturnsResourceImmediately(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->expects($this->once())
            ->method('release')
            ->with($parent, $resource, 'default', $this->isFloat());

        $guard = new PoolGuard($poolManager, $parent, $resource);
        $guard->close();
        unset($guard);
    }

    public function testReleaseAliasReturnsResourceImmediately(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->expects($this->once())
            ->method('release')
            ->with($parent, $resource, 'alias_type', $this->isFloat());

        $guard = new PoolGuard($poolManager, $parent, $resource, 'alias_type');
        $guard->release();
        unset($guard);
    }

    public function testCloseIsIdempotentAndPreventsDoubleReturnOnDestruct(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->expects($this->once())
            ->method('release')
            ->with($parent, $resource, 'default', $this->isFloat());

        $guard = new PoolGuard($poolManager, $parent, $resource);
        $guard->close();
        $guard->close();
        unset($guard);
    }

    public function testGetElapsedReturnsNonNegativeFloat(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $guard = new PoolGuard($poolManager, new stdClass(), new stdClass());

        $this->assertIsFloat($guard->getElapsed());
        $this->assertGreaterThanOrEqual(0.0, $guard->getElapsed());
    }

    public function testClosePropagatesReleaseFailureForExplicitCall(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->method('release')
            ->willThrowException(new \RuntimeException('release failed'));

        $guard = new PoolGuard($poolManager, $parent, $resource);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('release failed');
        $guard->close();
    }

    public function testDestructNeverThrowsEvenWhenReleaseThrows(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new stdClass();

        $poolManager->expects($this->once())
            ->method('release')
            ->willThrowException(new \RuntimeException('release failed'));

        $scoped = new PoolGuard($poolManager, $parent, $resource);

        // Should not throw from __destruct().
        unset($scoped);
        $this->assertTrue(true);
    }

    public function testCallProxiesToUnderlyingResource(): void
    {
        // Arrange
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new class {
            public function query(string $sql): string
            {
                return "Result: {$sql}";
            }

            public function sum(int $a, int $b): int
            {
                return $a + $b;
            }
        };

        $guard = new PoolGuard($poolManager, $parent, $resource);

        // Act & Assert
        $this->assertSame('Result: SELECT 1', $guard->query('SELECT 1'),
            '__call should proxy method calls to the underlying resource');
        $this->assertSame(5, $guard->sum(2, 3),
            '__call should forward all arguments correctly');
    }

    public function testCallPropagatesUnderlyingResourceException(): void
    {
        $poolManager = $this->createMock(PoolManagerInterface::class);
        $parent = new stdClass();
        $resource = new class {
            public function fail(): void
            {
                throw new \RuntimeException('resource failure');
            }
        };

        $guard = new PoolGuard($poolManager, $parent, $resource);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('resource failure');
        $guard->fail();
    }
}
