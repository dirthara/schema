<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Grammar\PostgresSqlSchemaGrammar;
use Dirthara\Database\Connection\Driver\PostgresSqlDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

#[Group('conformance')]
#[Group('integration')]
final class PostgresSqlSchemaConformanceTest extends SchemaConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::PostgresSql;
    }

    protected function driver(): Driver
    {
        return new PostgresSqlDriver(new StandardTransactionGrammar(new SavepointPrefix()));
    }

    protected function grammar(): SchemaGrammar
    {
        return new PostgresSqlSchemaGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::PostgresSql,
            name: 'conformance',
            host: $this->env('DIRTHARA_POSTGRES_HOST', 'postgres'),
            port: (int) $this->env('DIRTHARA_POSTGRES_PORT', '5432'),
            database: $this->env('DIRTHARA_POSTGRES_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_POSTGRES_USERNAME', 'dirthara'),
            password: $this->env('DIRTHARA_POSTGRES_PASSWORD', 'dirthara'),
        );
    }
}
