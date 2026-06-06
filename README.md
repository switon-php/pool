# Switon Pool Package

[![Pool CI](https://img.shields.io/github/actions/workflow/status/switon-php/pool/ci.yml?branch=main&label=Pool%20CI)](https://github.com/switon-php/pool/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's resource pool manager for pooling resources by owner and type, guard-based auto-return, and pool lifecycle
events.

## Highlights

- **Owner-scoped pools:** each owner gets its own pool space.
- **Multiple pool types:** one owner can keep separate variants such as `default` and `readonly`.
- **Scoped borrowing:** `PoolGuard` returns resources automatically when it goes out of scope.
- **Capacity control:** pool size, timeout, and busy conditions are handled explicitly.
- **Observable lifecycle:** resource acquiring, returning, and busy states can be observed.

## Installation

```bash
composer require switon/pool
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Pooling\PoolManagerInterface;

final class DatabaseClient
{
    public function query(string $sql): array
    {
        return [];
    }
}

final class ReportService
{
    #[Autowired] protected PoolManagerInterface $poolManager;

    public function __construct()
    {
        $this->poolManager->add($this, new DatabaseClient(), 4);
    }

    public function load(): array
    {
        $db = $this->poolManager->guard($this, timeout: 1.0);

        return $db->query('SELECT * FROM reports');
    }
}
```

Docs: https://docs.switon.dev/latest/pool

## License

MIT.
