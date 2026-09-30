<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Schema\Grammar\MySqlSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function sprintf;

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

    protected function neighbour(): Connection
    {
        $root = new PdoConnection(
            new ConnectionConfig(
                driver: DriverName::MySql,
                name: 'conformance_root',
                host: $this->env('DIRTHARA_MYSQL_HOST', 'mysql'),
                port: (int) $this->env('DIRTHARA_MYSQL_PORT', '3306'),
                username: $this->env('DIRTHARA_MYSQL_ROOT_USERNAME', 'root'),
                password: $this->env('DIRTHARA_MYSQL_ROOT_PASSWORD', 'dirthara'),
            ),
            $this->driver(),
        );

        $root->execute(sprintf('CREATE DATABASE IF NOT EXISTS %s', self::NEIGHBOURHOOD));

        return $root;
    }

    #[Test]
    public function it_drops_a_table_whose_name_is_not_a_valid_identifier(): void
    {
        $this->connection->execute('CREATE TABLE `conformance legacy-table` (id INT)');

        $this->schema->dropAll();

        self::assertSame([], $this->connection->execute('SHOW TABLES')->all());
    }

    #[Test]
    public function it_leaves_a_view_alone(): void
    {
        $this->createUsers();
        $this->connection->execute(sprintf('CREATE VIEW conformance_emails AS SELECT email FROM %s', self::TABLE));

        try {
            $this->schema->dropAll();

            $this->assertNoTables(self::TABLE);
            self::assertSame(
                [['TABLE_NAME' => 'conformance_emails']],
                $this->connection->execute(
                    'SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()',
                )->all(),
            );
        } finally {
            $this->connection->execute('DROP VIEW conformance_emails');
        }
    }
}
