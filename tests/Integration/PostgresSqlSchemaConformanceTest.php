<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use Dirthara\Schema\Table;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Grammar\PostgresSqlSchemaGrammar;
use Dirthara\Schema\Exceptions\SchemaExecutionException;
use Dirthara\Database\Connection\Driver\PostgresSqlDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function sprintf;

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

    protected function neighbour(): Connection
    {
        $this->connection->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s', self::NEIGHBOURHOOD));

        return $this->connection;
    }

    #[Test]
    public function it_drops_a_table_whose_name_is_not_a_valid_identifier(): void
    {
        $this->connection->execute('CREATE TABLE "conformance legacy-table" (id INT)');

        $this->schema->dropAll();

        self::assertSame(
            [],
            $this->connection->execute(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA()',
            )->all(),
        );
    }

    #[Test]
    public function it_drops_a_partitioned_table_and_its_partitions(): void
    {
        $this->connection->execute('CREATE TABLE conformance_events (id INT) PARTITION BY RANGE (id)');
        $this->connection->execute(
            'CREATE TABLE conformance_events_low PARTITION OF conformance_events FOR VALUES FROM (0) TO (100)',
        );

        $this->schema->dropAll();

        $this->assertNoTables('conformance_events', 'conformance_events_low');
    }

    #[Test]
    public function it_leaves_a_table_an_extension_owns_alone(): void
    {
        $this->connection->execute('CREATE TABLE conformance_extension_owned (id INT)');
        $this->connection->execute('ALTER EXTENSION plpgsql ADD TABLE conformance_extension_owned');

        try {
            $this->createUsers();

            $this->schema->dropAll();

            $this->assertNoTables(self::TABLE);
            self::assertTrue($this->schema->hasTable('conformance_extension_owned'));
        } finally {
            $this->connection->execute('ALTER EXTENSION plpgsql DROP TABLE conformance_extension_owned');
            $this->connection->execute('DROP TABLE conformance_extension_owned');
        }
    }

    #[Test]
    public function it_refuses_to_cascade_into_a_view_and_drops_nothing(): void
    {
        $this->createUsers();
        $this->schema->create('conformance_teams', static function (Table $table): void {
            $table->id();
        });
        $this->connection->execute(sprintf('CREATE VIEW conformance_emails AS SELECT email FROM %s', self::TABLE));

        try {
            $this->schema->dropAll();
            self::fail('Dropping a table a view depends on was not reported.');
        } catch (SchemaExecutionException $exception) {
            self::assertSame('drop_all', $exception->getContext()['operation']);
            self::assertTrue($this->schema->hasTable(self::TABLE));
            self::assertTrue($this->schema->hasTable('conformance_teams'));
        } finally {
            $this->connection->execute('DROP VIEW conformance_emails');
        }
    }
}
