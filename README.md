# Switon Pool Package

Connection pooling and borrowed-resource lifecycle management for Switon Framework.

## Installation

```bash
composer require switon/pool
```

**Requirements:** PHP 8.3+

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Pooling\PoolManagerInterface;

final class DemoConnection
{
    public function ping(): string
    {
        return 'pong';
    }
}

final class DemoService
{
    #[Autowired] protected PoolManagerInterface $poolManager;

    public function ping(): string
    {
        $conn = $this->poolManager->guard($this, timeout: 1.0);
        return $conn->ping();
    }
}
```

Docs: https://docs.switon.dev/latest/pool

## License

MIT.
