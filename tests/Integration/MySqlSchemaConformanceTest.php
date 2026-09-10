<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Schema\Grammar\MySqlSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

#[Group('conformance')]
#[Group('integration')]
final class MySqlSchemaConformanceTest extends SchemaConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::MySql;
    }

    protected function driver(): Driver
    {
        return new MySqlDriver(new StandardTransactionGrammar(new SavepointPrefix()));
    }

    protected function grammar(): SchemaGrammar
    {
        return new MySqlSchemaGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::MySql,
            name: 'conformance',
            host: $this->env('DIRTHARA_MYSQL_HOST', 'mysql'),
            port: (int) $this->env('DIRTHARA_MYSQL_PORT', '3306'),
            database: $this->env('DIRTHARA_MYSQL_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_MYSQL_USERNAME', 'dirthara'),
            password: $this->env('DIRTHARA_MYSQL_PASSWORD', 'dirthara'),
        );
    }
}
