<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Schema\Grammar\SQLiteSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

#[Group('conformance')]
final class SQLiteSchemaConformanceTest extends SchemaConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::SQLite;
    }

    protected function driver(): Driver
    {
        return new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()));
    }

    protected function grammar(): SchemaGrammar
    {
        return new SQLiteSchemaGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(driver: DriverName::SQLite, name: 'conformance', database: ':memory:');
    }
}
