<?php

declare(strict_types=1);

namespace Switon\Pooling\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;
use Switon\Core\OwnerAwareInterface;

class TestConnection implements OwnerAwareInterface
{
    #[Autowired] protected TestPoolOwner $owner;

    public string $id;
    public bool $connected = true;

    public function __construct(string $id = '')
    {
        $this->id = $id ?: 'conn_' . bin2hex(random_bytes(4));
    }

    public function getOwner(): TestPoolOwner
    {
        return $this->owner;
    }

    public function query(string $sql): string
    {
        return "Result for: {$sql}";
    }

    public function close(): void
    {
        $this->connected = false;
    }
}
