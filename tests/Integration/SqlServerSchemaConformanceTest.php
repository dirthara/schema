<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Grammar\SqlServerSchemaGrammar;
use Dirthara\Database\Connection\Driver\SqlServerDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;

use function sprintf;
use function str_replace;

#[Group('conformance')]
#[Group('integration')]
final class SqlServerSchemaConformanceTest extends SchemaConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::SqlServer;
    }

    protected function driver(): Driver
    {
        return new SqlServerDriver(new SqlServerTransactionGrammar(new SavepointPrefix()));
    }

    protected function grammar(): SchemaGrammar
    {
        return new SqlServerSchemaGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::SqlServer,
            name: 'conformance',
            host: $this->env('DIRTHARA_SQLSRV_HOST', 'sqlserver'),
            port: (int) $this->env('DIRTHARA_SQLSRV_PORT', '1433'),
            database: $this->env('DIRTHARA_SQLSRV_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_SQLSRV_USERNAME', 'sa'),
            password: $this->env('DIRTHARA_SQLSRV_PASSWORD', 'Dirthara!2026'),
            dsn: ['TrustServerCertificate' => 'yes'],
        );
    }

    protected function prepareDatabase(): void
    {
        $master = new PdoConnection(new ConnectionConfig(
            driver: DriverName::SqlServer,
            name: 'conformance_master',
            host: $this->config()->host,
            port: $this->config()->port,
            database: 'master',
            username: $this->config()->username,
            password: $this->config()->password,
            dsn: ['TrustServerCertificate' => 'yes'],
        ), $this->driver());

        $database = (string) $this->config()->database;

        $master->execute(sprintf(
            "IF DB_ID(N'%s') IS NULL CREATE DATABASE [%s]",
            str_replace(search: "'", replace: "''", subject: $database),
            str_replace(search: ']', replace: ']]', subject: $database),
        ));
    }

    protected function neighbour(): Connection
    {
        $this->connection->execute(sprintf(
            "IF SCHEMA_ID(N'%1\$s') IS NULL EXEC(N'CREATE SCHEMA [%1\$s]')",
            self::NEIGHBOURHOOD,
        ));

        return $this->connection;
    }

    #[Test]
    public function it_drops_a_table_whose_name_is_not_a_valid_identifier(): void
    {
        $this->connection->execute('CREATE TABLE [conformance legacy-table] (id INT)');

        $this->schema->dropAll();

        self::assertSame([], $this->connection->execute('SELECT name FROM sys.tables WHERE is_ms_shipped = 0')->all());
    }

    #[Test]
    public function it_leaves_a_table_the_server_ships_alone(): void
    {
        $this->connection->execute('CREATE TABLE conformance_shipped (id INT)');
        $this->connection->execute("EXEC sys.sp_MS_marksystemobject N'conformance_shipped'");

        try {
            $this->createUsers();

            $this->schema->dropAll();

            $this->assertNoTables(self::TABLE);
            self::assertTrue($this->schema->hasTable('conformance_shipped'));
        } finally {
            $this->connection->execute('DROP TABLE conformance_shipped');
        }
    }
}
