<?php

declare(strict_types=1);

namespace Switon\Pooling\Tests\Fixtures;

class TestConnectionWithState
{
    public string $id;
    public array $queries = [];
    public int $usageCount = 0;

    public function __construct(string $id = '')
    {
        $this->id = $id ?: 'conn_' . bin2hex(random_bytes(4));
    }

    public function execute(string $query): void
    {
        $this->queries[] = $query;
        $this->usageCount++;
    }

    public function reset(): void
    {
        $this->queries = [];
        $this->usageCount = 0;
    }
}
