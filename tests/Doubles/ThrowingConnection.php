<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Doubles;

use Throwable;
use LogicException;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Transaction\TransactionManager;

/**
 * Fails every query with the exception the test chose.
 *
 * A real SQLite connection cannot be made to raise a ConnectionException on
 * demand, and the two catch arms in ConnectedSchema translate to different
 * exceptions, so the difference has to be observable.
 */
final class ThrowingConnection implements Connection
{
    public function __construct(
        private readonly Throwable $failure,
        private readonly string $name = 'default',
        private readonly DriverName $driver = DriverName::SQLite,
    ) {}

    public function execute(string $query, array $parameters = []): Result
    {
        throw $this->failure;
    }

    public function lastInsertId(?string $sequence = null): ?string
    {
        throw new LogicException('The test double does not read the last insert id.');
    }

    public function transactions(): TransactionManager
    {
        throw new LogicException('The test double does not manage transactions.');
    }

    public function disconnect(): void {}

    public function name(): string
    {
        return $this->name;
    }

    public function driver(): DriverName
    {
        return $this->driver;
    }
}
