<?php

declare(strict_types=1);

namespace Switon\Pooling\PhpStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

/**
 * Represents one dynamic proxy method exposed by <code>PoolGuard::__call()</code> to PHPStan.
 */
final class PoolGuardMethodReflection implements MethodReflection
{
    public function __construct(
        private ClassReflection $declaringClass,
        private string          $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function getVariants(): array
    {
        return [new class () implements ParametersAcceptor {
            public function getTemplateTypeMap(): TemplateTypeMap
            {
                return TemplateTypeMap::createEmpty();
            }

            public function getResolvedTemplateTypeMap(): TemplateTypeMap
            {
                return TemplateTypeMap::createEmpty();
            }

            public function getParameters(): array
            {
                return [];
            }

            public function isVariadic(): bool
            {
                return true;
            }

            public function getReturnType(): Type
            {
                return new MixedType();
            }
        }];
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createYes();
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }
}
