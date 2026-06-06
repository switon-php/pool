<?php

declare(strict_types=1);

namespace Switon\Pooling\Tests\Fixtures;

class TestPoolOwner
{
    public string $name;

    public function __construct(string $name = 'TestOwner')
    {
        $this->name = $name;
    }
}
