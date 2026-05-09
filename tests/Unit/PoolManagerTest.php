<?php

declare(strict_types=1);

namespace Switon\Pooling\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\MakerInterface;
use Switon\Pooling\Event\ResourceAcquired;
use Switon\Pooling\Event\ResourceAcquiring;
use Switon\Pooling\Event\ResourceBusy;
use Switon\Pooling\Event\ResourceReturned;
use Switon\Pooling\Event\ResourceReturnFailed;
use Switon\Pooling\Exception\BusyException;
use Switon\Pooling\Exception\FullException;
use Switon\Pooling\Exception\InvalidSizeException;
use Switon\Pooling\Exception\PoolAlreadyExistsException;
use Switon\Pooling\Exception\PoolNotFoundException;
use Switon\Pooling\PoolManager;
use Switon\Pooling\Tests\Fixtures\{TestConnection, TestPoolOwner};
use Switon\Pooling\Tests\TestCase;

/**
 * Test cases for PoolManager class.
 *
 * Tests connection pool management functionality including:
 * - Pool creation and removal
 * - Adding connections to pool
 * - Popping and pushing connections
 * - Pool capacity and size management
 * - Exception handling
 * - Event dispatching
 *
 * @group pool
 */
class PoolManagerTest extends TestCase
{
    protected TestPoolOwner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = new TestPoolOwner();
    }

    /**
     * Test that create() creates a new pool with specified capacity.
     */
    public function testCreatePoolWithCapacity(): void
    {
        // Act - PHPUnit should have called setUp() which initializes $this->poolManager
        $this->poolManager->create($this->owner, 10, 'default');

        // Assert
        $this->assertTrue($this->poolManager->exists($this->owner, 'default'),
            'Pool should exist after creation');
        $this->assertSame(10, $this->poolManager->size($this->owner, 'default'),
            'Pool capacity should match created capacity');
    }

    /**
     * Test that create() throws PoolAlreadyExistsException when pool already exists.
     */
    public function testCreateThrowsExceptionWhenPoolAlreadyExists(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');

        // Act & Assert
        $this->expectException(PoolAlreadyExistsException::class);

        $this->poolManager->create($this->owner, 10, 'default');
    }

    /**
     * Test that create() supports multiple pool types per owner.
     */
    public function testCreateMultiplePoolTypes(): void
    {
        // Act
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->create($this->owner, 5, 'readonly');
        $this->poolManager->create($this->owner, 3, 'slave');

        // Assert
        $this->assertTrue($this->poolManager->exists($this->owner, 'default'));
        $this->assertTrue($this->poolManager->exists($this->owner, 'readonly'));
        $this->assertTrue($this->poolManager->exists($this->owner, 'slave'));
        $this->assertSame(10, $this->poolManager->size($this->owner, 'default'));
        $this->assertSame(5, $this->poolManager->size($this->owner, 'readonly'));
        $this->assertSame(3, $this->poolManager->size($this->owner, 'slave'));
    }

    /**
     * Test that remove() removes a specific pool type.
     */
    public function testRemoveSpecificPoolType(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->create($this->owner, 5, 'readonly');

        // Act
        $this->poolManager->remove($this->owner, 'default');

        // Assert
        $this->assertFalse($this->poolManager->exists($this->owner, 'default'),
            'Removed pool type should not exist');
        $this->assertTrue($this->poolManager->exists($this->owner, 'readonly'),
            'Other pool types should still exist');
    }

    /**
     * Test that remove() with null type removes all pools for owner.
     */
    public function testRemoveAllPoolsForOwner(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->create($this->owner, 5, 'readonly');

        // Act
        $this->poolManager->remove($this->owner, null);

        // Assert
        $this->assertFalse($this->poolManager->exists($this->owner, 'default'));
        $this->assertFalse($this->poolManager->exists($this->owner, 'readonly'));
    }

    /**
     * Test that removed pool type can be recreated.
     */
    public function testRemoveTypeAllowsRecreate(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->remove($this->owner, 'default');

        // Act
        $this->poolManager->create($this->owner, 6, 'default');

        // Assert
        $this->assertTrue($this->poolManager->exists($this->owner, 'default'));
        $this->assertSame(6, $this->poolManager->size($this->owner, 'default'));
    }

    /**
     * Test that removing a non-existing pool type keeps existing pools untouched.
     */
    public function testRemoveNonExistingTypeDoesNotAffectExistingPools(): void
    {
        // Arrange
        $connection = new TestConnection('conn_default');
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Act
        $this->poolManager->remove($this->owner, 'readonly');

        // Assert
        $this->assertTrue($this->poolManager->exists($this->owner, 'default'));
        $this->assertSame(10, $this->poolManager->size($this->owner, 'default'));
        $this->assertSame($connection, $this->poolManager->acquire($this->owner, null, 'default'));
    }

    /**
     * Test that remove() on unknown owner/type is a no-op.
     */
    public function testRemoveTypeForUnknownOwnerIsNoop(): void
    {
        // Arrange
        $unknownOwner = new TestPoolOwner('UnknownOwner');

        // Act
        $this->poolManager->remove($unknownOwner, 'default');

        // Assert
        $this->assertFalse($this->poolManager->exists($unknownOwner, 'default'));
    }

    /**
     * Test that remove() all pools for unknown owner is a no-op.
     */
    public function testRemoveAllForUnknownOwnerIsNoop(): void
    {
        // Arrange
        $unknownOwner = new TestPoolOwner('UnknownOwnerAll');

        // Act
        $this->poolManager->remove($unknownOwner, null);

        // Assert
        $this->assertFalse($this->poolManager->exists($unknownOwner, 'default'));
    }

    /**
     * Test that add() creates pool automatically if it doesn't exist.
     */
    public function testAddCreatesPoolIfNotExists(): void
    {
        // Arrange
        $connection = new TestConnection();

        // Act
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Assert
        $this->assertTrue($this->poolManager->exists($this->owner, 'default'),
            'Pool should be created automatically by add()');
    }

    /**
     * Test that add() adds single connection to pool.
     */
    public function testAddSingleConnection(): void
    {
        // Arrange
        $connection = new TestConnection('conn1');
        $this->poolManager->create($this->owner, 10, 'default');

        // Act
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Assert
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');
        $this->assertSame($connection, $acquired,
            'Acquired connection should be the same instance that was added');
    }

    /**
     * Test that add() clones sample connection for multiple instances.
     */
    public function testAddClonesConnectionForMultipleInstances(): void
    {
        // Arrange
        $sample = new TestConnection('sample');
        $this->poolManager->create($this->owner, 10, 'default');

        // Act
        $this->poolManager->add($this->owner, $sample, 3, 'default');

        // Assert
        $conn1 = $this->poolManager->acquire($this->owner, null, 'default');
        $conn2 = $this->poolManager->acquire($this->owner, null, 'default');
        $conn3 = $this->poolManager->acquire($this->owner, null, 'default');

        // LIFO order: conn1=last pushed (clone), conn3=first pushed (original sample)
        $this->assertNotSame($conn1, $conn2, 'Connections should be different instances');
        $this->assertNotSame($conn2, $conn3, 'Connections should be different instances');
        $this->assertNotSame($conn1, $conn3, 'Connections should be different instances');
        $this->assertSame($sample, $conn3, 'First pushed item is the original sample (LIFO: last popped)');
        $this->assertInstanceOf(TestConnection::class, $conn1);
        $this->assertInstanceOf(TestConnection::class, $conn2);
        $this->assertInstanceOf(TestConnection::class, $conn3);
    }

    /**
     * Test that add() throws FullException when capacity exceeded.
     */
    public function testAddThrowsFullExceptionWhenCapacityExceeded(): void
    {
        // Arrange
        $connection = new TestConnection();
        $this->poolManager->create($this->owner, 2, 'default');
        $this->poolManager->add($this->owner, $connection, 2, 'default');

        // Act & Assert
        $this->expectException(FullException::class);

        $this->poolManager->add($this->owner, $connection, 1, 'default');
    }

    public function testAddThrowsInvalidSizeExceptionWhenSizeIsZero(): void
    {
        $this->expectException(InvalidSizeException::class);
        $this->poolManager->add($this->owner, new TestConnection('zero'), 0, 'default');
    }

    public function testAddThrowsInvalidSizeExceptionWhenSizeIsNegative(): void
    {
        $this->expectException(InvalidSizeException::class);
        $this->poolManager->add($this->owner, new TestConnection('negative'), -1, 'default');
    }

    /**
     * Test that add() creates instances from array configuration.
     */
    public function testAddCreatesInstanceFromArrayConfig(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');

        // Act
        $this->poolManager->add($this->owner, [TestConnection::class, ['id' => 'from_array']], 1, 'default');

        // Assert
        $connection = $this->poolManager->acquire($this->owner, null, 'default');
        $this->assertInstanceOf(TestConnection::class, $connection);
        $this->assertSame('from_array', $connection->id);
    }

    /**
     * Test that add() clones array-configured sample for multiple instances.
     */
    public function testAddArrayConfigWithMultipleSizeClonesSample(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 2, 'default');

        // Act
        $this->poolManager->add($this->owner, [TestConnection::class, ['id' => 'bulk']], 2, 'default');

        // Assert
        $first = $this->poolManager->acquire($this->owner, null, 'default');
        $second = $this->poolManager->acquire($this->owner, null, 'default');

        $this->assertInstanceOf(TestConnection::class, $first);
        $this->assertInstanceOf(TestConnection::class, $second);
        $this->assertNotSame($first, $second, 'Instances should be cloned, not reused');
        $this->assertSame('bulk', $first->id);
        $this->assertSame('bulk', $second->id);
    }

    /**
     * Test that add() uses empty constructor args when array sample has no params.
     */
    public function testAddArrayConfigWithoutParamsUsesEmptyArguments(): void
    {
        $this->container->remove(MakerInterface::class);
        $this->container->remove(PoolManager::class);

        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(TestConnection::class, [])
            ->willReturn(new TestConnection('from_array_no_params'));

        $this->container->set(MakerInterface::class, $maker);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $this->poolManager->add($this->owner, [TestConnection::class], 1, 'default');
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');

        $this->assertInstanceOf(TestConnection::class, $acquired);
        $this->assertSame('from_array_no_params', $acquired->id);
    }

    /**
     * Test that acquire() retrieves connection from pool.
     */
    public function testAcquireRetrievesConnection(): void
    {
        // Arrange
        $connection = new TestConnection('conn1');
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Act
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');

        // Assert
        $this->assertSame($connection, $acquired,
            'Acquired connection should match added connection');
    }

    /**
     * Test that acquire() throws PoolNotFoundException when pool doesn't exist.
     */
    public function testAcquireThrowsExceptionWhenPoolNotFound(): void
    {
        // Act & Assert
        $this->expectException(PoolNotFoundException::class);

        $this->poolManager->acquire($this->owner, null, 'default');
    }

    /**
     * Test that acquire() throws exception when no connections available.
     */
    public function testAcquireThrowsBusyExceptionWhenPoolEmpty(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');

        // Act & Assert
        $this->expectException(BusyException::class);

        $this->poolManager->acquire($this->owner, null, 'default');
    }

    public function testAcquireDispatchesResourceBusyEventWhenPoolEmptyWithoutTimeout(): void
    {
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $this->poolManager->create($this->owner, 10, 'default');

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertInstanceOf(ResourceAcquiring::class, $event);
                } else {
                    $this->assertInstanceOf(ResourceBusy::class, $event);
                }
                return $event;
            });

        $this->expectException(BusyException::class);
        $this->poolManager->acquire($this->owner, null, 'default');
    }

    /**
     * Test that acquire() dispatches ResourceAcquiring event.
     */
    public function testAcquireDispatchesResourceAcquiringEvent(): void
    {
        // Arrange - Replace stub with mock for event verification
        // Need to remove first, then recreate container services
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection();
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertInstanceOf(ResourceAcquiring::class, $event, 'First event should be ResourceAcquiring');
                } else {
                    $this->assertInstanceOf(ResourceAcquired::class, $event, 'Second event should be ResourceAcquired');
                }
                return $event;
            });

        // Act
        $this->poolManager->acquire($this->owner, null, 'default');
    }

    /**
     * Test that acquire() dispatches ResourceAcquired event with timing.
     */
    public function testAcquireDispatchesResourceAcquiredEvent(): void
    {
        // Arrange - Replace stub with mock for event verification
        // Need to remove first, then recreate container services
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection();
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertInstanceOf(ResourceAcquiring::class, $event, 'First event should be ResourceAcquiring');
                } else {
                    $this->assertInstanceOf(ResourceAcquired::class, $event, 'Second event should be ResourceAcquired');
                }
                return $event;
            });

        // Act
        $this->poolManager->acquire($this->owner, null, 'default');
    }

    /**
     * Test that acquire() dispatches ResourceBusy event when timeout occurs.
     *
     * Note: In non-coroutine mode, Channel throws ChannelEmptyException directly
     * instead of returning false, so ResourceBusy event may not be dispatched.
     * This test runs in a coroutine context when Swoole is available.
     */
    public function testAcquireDispatchesResourceBusyEventOnTimeout(): void
    {
        // Skip this test if Swoole extension is not available
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for this test');
            return;
        }

        // Run test in coroutine context
        \Swoole\Coroutine\run(function () {
            // Arrange - Create everything inside coroutine context so Channel detects coroutine
            $container = new \Switon\Testing\Container\Container();
            $container->set(\Switon\Core\ContainerInterface::class, $container);
            $container->set(\Psr\Container\ContainerInterface::class, $container);

            /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
            $container->set(EventDispatcherInterface::class, $eventDispatcher);
            $container->set(PoolManager::class, PoolManager::class);
            $poolManager = $container->get(PoolManager::class);

            $poolManager->create($this->owner, 10, 'default');

            $eventDispatcher
                ->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function ($event) {
                    static $callCount = 0;
                    $callCount++;
                    if ($callCount === 1) {
                        $this->assertInstanceOf(ResourceAcquiring::class, $event, 'First event should be ResourceAcquiring');
                    } else {
                        $this->assertInstanceOf(ResourceBusy::class, $event, 'Second event should be ResourceBusy');
                    }
                    return $event;
                });

            // Act & Assert
            try {
                $poolManager->acquire($this->owner, 0.1, 'default');
                $this->fail('Expected BusyException was not thrown');
            } catch (BusyException $e) {
                // Expected exception
            }
        });
    }

    /**
     * Test that release() returns connection to pool.
     */
    public function testReleaseReturnsConnectionToPool(): void
    {
        // Arrange
        $connection = new TestConnection('conn1');
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');

        // Act
        $this->poolManager->release($this->owner, $acquired, 'default');

        // Assert
        $reAcquired = $this->poolManager->acquire($this->owner, null, 'default');
        $this->assertSame($connection, $reAcquired,
            'Connection should be available again after release');
    }

    /**
     * Test that release() throws PoolNotFoundException when pool doesn't exist.
     */
    public function testReleaseThrowsExceptionWhenPoolNotFound(): void
    {
        // Arrange
        $connection = new TestConnection();

        // Act & Assert
        $this->expectException(PoolNotFoundException::class);

        $this->poolManager->release($this->owner, $connection, 'default');
    }

    public function testReleaseDispatchesReturnFailedEventWhenPoolNotFound(): void
    {
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection('missing_pool');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                $this->assertInstanceOf(ResourceReturnFailed::class, $event);
                $this->assertSame('pool_not_found', $event->reason);
                return true;
            }));

        $this->expectException(PoolNotFoundException::class);
        $this->poolManager->release($this->owner, $connection, 'default');
    }

    /**
     * Test that release() dispatches ResourceReturned event with elapsed time.
     */
    public function testReleaseDispatchesResourceReturnedEvent(): void
    {
        // Arrange - Replace stub with mock for event verification
        // Need to remove first, then recreate container services
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection();
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');

        $this->eventDispatcher
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->with($this->isInstanceOf(ResourceReturned::class));

        // Act
        $this->poolManager->release($this->owner, $acquired, 'default');
    }

    /**
     * Test that isEmpty() returns true when pool is empty.
     */
    public function testIsEmptyReturnsTrueWhenPoolEmpty(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');

        // Act & Assert
        $this->assertTrue($this->poolManager->isEmpty($this->owner, 'default'),
            'Empty pool should return true for isEmpty()');
    }

    /**
     * Test that isEmpty() returns false when pool has connections.
     */
    public function testIsEmptyReturnsFalseWhenPoolHasConnections(): void
    {
        // Arrange
        $connection = new TestConnection();
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Act & Assert
        $this->assertFalse($this->poolManager->isEmpty($this->owner, 'default'),
            'Pool with connections should return false for isEmpty()');
    }

    /**
     * Test that isEmpty() throws PoolNotFoundException when pool doesn't exist.
     */
    public function testIsEmptyThrowsExceptionWhenPoolNotFound(): void
    {
        // Act & Assert
        $this->expectException(PoolNotFoundException::class);

        $this->poolManager->isEmpty($this->owner, 'default');
    }

    /**
     * Test that exists() returns false for non-existent pool.
     */
    public function testExistsReturnsFalseForNonExistentPool(): void
    {
        // Act & Assert
        $this->assertFalse($this->poolManager->exists($this->owner, 'default'),
            'Non-existent pool should return false');
    }

    /**
     * Test that exists() returns true for existing pool.
     */
    public function testExistsReturnsTrueForExistingPool(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 10, 'default');

        // Act & Assert
        $this->assertTrue($this->poolManager->exists($this->owner, 'default'),
            'Existing pool should return true');
    }

    /**
     * Test that size() returns pool capacity.
     */
    public function testSizeReturnsPoolCapacity(): void
    {
        // Arrange
        $this->poolManager->create($this->owner, 15, 'default');

        // Act & Assert
        $this->assertSame(15, $this->poolManager->size($this->owner, 'default'),
            'size() should return pool capacity');
    }

    /**
     * Test that size() throws PoolNotFoundException when pool doesn't exist.
     */
    public function testSizeThrowsExceptionWhenPoolNotFound(): void
    {
        // Act & Assert
        $this->expectException(PoolNotFoundException::class);

        $this->poolManager->size($this->owner, 'default');
    }

    /**
     * Test that get() returns PoolGuard instance.
     */
    public function testGetReturnsPoolGuardInstance(): void
    {
        // Arrange
        $connection = new TestConnection();
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Act
        $scoped = $this->poolManager->guard($this->owner, null, 'default');

        // Assert
        $this->assertInstanceOf(\Switon\Pooling\PoolGuard::class, $scoped);
        $this->assertSame($connection, $scoped->resource);
        $this->assertSame($this->owner, $scoped->parent);
    }

    /**
     * Test that guard() throws PoolNotFoundException when pool doesn't exist.
     */
    public function testGuardThrowsExceptionWhenPoolNotFound(): void
    {
        $this->expectException(PoolNotFoundException::class);
        $this->poolManager->guard($this->owner, null, 'default');
    }

    /**
     * Test that guard() acquires from the requested type and timeout path.
     */
    public function testGuardUsesRequestedTypeAndTimeout(): void
    {
        $readonly = new TestConnection('readonly-1');
        $this->poolManager->create($this->owner, 1, 'readonly');
        $this->poolManager->add($this->owner, $readonly, 1, 'readonly');

        $guard = $this->poolManager->guard($this->owner, 0.05, 'readonly');

        $this->assertSame($this->owner, $guard->parent);
        $this->assertSame($readonly, $guard->resource);
    }

    /**
     * Test that guard() works after remove(type) and recreate(type).
     */
    public function testGuardWorksAfterRemoveAndRecreateType(): void
    {
        $this->poolManager->create($this->owner, 1, 'default');
        $this->poolManager->add($this->owner, new TestConnection('first'), 1, 'default');
        $this->poolManager->remove($this->owner, 'default');

        $this->poolManager->create($this->owner, 1, 'default');
        $recreated = new TestConnection('second');
        $this->poolManager->add($this->owner, $recreated, 1, 'default');

        $guard = $this->poolManager->guard($this->owner, null, 'default');

        $this->assertSame($recreated, $guard->resource);
    }

    /**
     * Test that multiple owners can have separate pools.
     */
    public function testMultipleOwnersHaveSeparatePools(): void
    {
        // Arrange
        $owner1 = new TestPoolOwner('Owner1');
        $owner2 = new TestPoolOwner('Owner2');
        $connection1 = new TestConnection('conn1');
        $connection2 = new TestConnection('conn2');

        // Act
        $this->poolManager->create($owner1, 10, 'default');
        $this->poolManager->create($owner2, 10, 'default');
        $this->poolManager->add($owner1, $connection1, 1, 'default');
        $this->poolManager->add($owner2, $connection2, 1, 'default');

        // Assert
        $acquired1 = $this->poolManager->acquire($owner1, null, 'default');
        $acquired2 = $this->poolManager->acquire($owner2, null, 'default');

        $this->assertSame($connection1, $acquired1,
            'Owner1 should get its own connection');
        $this->assertSame($connection2, $acquired2,
            'Owner2 should get its own connection');
    }

    /**
     * Test that connections can be reused after push.
     */
    public function testConnectionsCanBeReused(): void
    {
        // Arrange
        $connection = new TestConnection('conn1');
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');

        // Act - Pop, use, and push back
        $conn1 = $this->poolManager->acquire($this->owner, null, 'default');
        $this->poolManager->release($this->owner, $conn1, 'default');

        $conn2 = $this->poolManager->acquire($this->owner, null, 'default');

        // Assert
        $this->assertSame($connection, $conn1);
        $this->assertSame($connection, $conn2,
            'Same connection instance should be reused');
    }

    /**
     * Test that manual release() without guard timing reports null elapsed.
     */
    public function testReleaseWithoutAcquireDispatchesNullElapsed(): void
    {
        // Arrange - replace dispatcher with mock to inspect event payload
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection('released_without_acquire');
        $this->poolManager->create($this->owner, 10, 'default');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                $this->assertInstanceOf(ResourceReturned::class, $event);
                $this->assertNull($event->elapsed);
                return $event;
            });

        // Act
        $this->poolManager->release($this->owner, $connection, 'default');

        // Assert: pool now contains one available resource
        $this->assertFalse($this->poolManager->isEmpty($this->owner, 'default'));
    }

    /**
     * Test that manual release() reports null elapsed even after acquire().
     */
    public function testManualReleaseAfterAcquireUsesNullElapsed(): void
    {
        // Arrange - replace dispatcher with mock to inspect both releases
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection('elapsed_case');
        $this->poolManager->create($this->owner, 10, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');
        $eventElapses = [];

        $this->eventDispatcher
            ->expects($this->atLeast(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$eventElapses) {
                if ($event instanceof ResourceReturned) {
                    $eventElapses[] = $event->elapsed;
                }
                return $event;
            });

        // Act: both are manual releases and do not carry guard elapsed.
        $this->poolManager->release($this->owner, $acquired, 'default');
        $this->poolManager->release($this->owner, $acquired, 'default');

        // Assert
        $this->assertCount(2, $eventElapses);
        $this->assertNull($eventElapses[0]);
        $this->assertNull($eventElapses[1]);
    }

    public function testGuardCloseDispatchesReturnedEventWithElapsed(): void
    {
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $connection = new TestConnection('guard_elapsed');
        $this->poolManager->create($this->owner, 1, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');
        $guard = $this->poolManager->guard($this->owner, null, 'default');

        $capturedElapsed = null;
        $this->eventDispatcher
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$capturedElapsed) {
                if ($event instanceof ResourceReturned) {
                    $capturedElapsed = $event->elapsed;
                }
                return $event;
            });

        $guard->close();

        $this->assertIsFloat($capturedElapsed);
        $this->assertGreaterThanOrEqual(0.0, $capturedElapsed);
    }

    /**
     * Test that release() throws when pool is already full.
     */
    public function testReleaseThrowsFullExceptionWhenPoolAlreadyFull(): void
    {
        // Arrange: capacity=1 and one idle resource already in pool.
        $this->poolManager->create($this->owner, 1, 'default');
        $this->poolManager->add($this->owner, new TestConnection('idle'), 1, 'default');

        // Act & Assert
        $this->expectException(FullException::class);
        $this->poolManager->release($this->owner, new TestConnection('extra'), 'default');
    }

    public function testReleaseDispatchesReturnFailedEventWhenPoolAlreadyFull(): void
    {
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $this->poolManager->create($this->owner, 1, 'default');
        $this->poolManager->add($this->owner, new TestConnection('idle'), 1, 'default');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                $this->assertInstanceOf(ResourceReturnFailed::class, $event);
                $this->assertSame('pool_full', $event->reason);
                return true;
            }));

        $this->expectException(FullException::class);
        $this->poolManager->release($this->owner, new TestConnection('extra'), 'default');
    }

    public function testReleasePoolFullFailureEventCarriesRequestedPoolType(): void
    {
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(PoolManager::class);

        /** @var \PHPUnit\Framework\MockObject\MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(PoolManager::class, PoolManager::class);
        $this->poolManager = $this->container->get(PoolManager::class);

        $this->poolManager->create($this->owner, 1, 'readonly');
        $this->poolManager->add($this->owner, new TestConnection('idle'), 1, 'readonly');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                $this->assertInstanceOf(ResourceReturnFailed::class, $event);
                $this->assertSame('pool_full', $event->reason);
                $this->assertSame('readonly', $event->type);
                return true;
            }));

        $this->expectException(FullException::class);
        $this->poolManager->release($this->owner, new TestConnection('extra'), 'readonly');
    }

    /**
     * Test that release() fails after the pool type is removed.
     */
    public function testReleaseThrowsPoolNotFoundAfterPoolTypeRemoved(): void
    {
        // Arrange
        $connection = new TestConnection('to_be_released');
        $this->poolManager->create($this->owner, 2, 'default');
        $this->poolManager->add($this->owner, $connection, 1, 'default');
        $acquired = $this->poolManager->acquire($this->owner, null, 'default');
        $this->poolManager->remove($this->owner, 'default');

        // Act & Assert
        $this->expectException(PoolNotFoundException::class);
        $this->poolManager->release($this->owner, $acquired, 'default');
    }

    /**
     * Test that remove(owner, null) does not affect other owners.
     */
    public function testRemoveAllPoolsForOneOwnerDoesNotAffectOtherOwner(): void
    {
        // Arrange
        $owner2 = new TestPoolOwner('Owner2-isolated');
        $this->poolManager->create($this->owner, 2, 'default');
        $this->poolManager->create($owner2, 2, 'default');
        $this->poolManager->add($owner2, new TestConnection('owner2-conn'), 1, 'default');

        // Act
        $this->poolManager->remove($this->owner, null);

        // Assert
        $this->assertFalse($this->poolManager->exists($this->owner, 'default'));
        $this->assertTrue($this->poolManager->exists($owner2, 'default'));
        $this->assertInstanceOf(TestConnection::class, $this->poolManager->acquire($owner2, null, 'default'));
    }
}
