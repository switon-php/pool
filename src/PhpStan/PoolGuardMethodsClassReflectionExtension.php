<?php

declare(strict_types=1);

namespace Switon\Pooling\PhpStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use Switon\Pooling\PoolGuard;

/**
 * Exposes dynamic <code>PoolGuard</code> proxy methods to PHPStan.
 */
class PoolGuardMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    /** {@inheritDoc} */
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $classReflection->getName() === PoolGuard::class;
    }

    /** {@inheritDoc} */
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new PoolGuardMethodReflection($classReflection, $methodName);
    }
}
