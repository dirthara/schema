<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use Dirthara\Schema\Table;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Schema\Grammar\SQLiteSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Schema\Exception\SchemaExecutionException;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function sprintf;

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

    protected function neighbour(): Connection
    {
        $this->connection->execute(sprintf("ATTACH DATABASE ':memory:' AS %s", self::NEIGHBOURHOOD));

        return $this->connection;
    }

    protected function createCycle(): void
    {
        $this->schema->create('conformance_left', static function (Table $table): void {
            $table->id();
            $table->bigInteger('right_id')->nullable();
            $table->foreign('right_id')->references('id')->on('conformance_right');
        });

        $this->schema->create('conformance_right', static function (Table $table): void {
            $table->id();
            $table->bigInteger('left_id')->nullable();
            $table->foreign('left_id')->references('id')->on('conformance_left');
        });
    }

    private function enforcesForeignKeys(): bool
    {
        return $this->connection->execute('PRAGMA foreign_keys')->first() === ['foreign_keys' => 1];
    }

    #[Test]
    public function it_drops_tables_whose_foreign_keys_are_enforced(): void
    {
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->createBlog();

        $this->schema->dropAll();

        $this->assertNoTables(self::TABLE, 'conformance_posts', 'conformance_comments');
    }

    #[Test]
    public function it_drops_referencing_rows_of_a_cycle_whose_foreign_keys_are_enforced(): void
    {
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->createCycle();
        $this->connection->execute('INSERT INTO conformance_left (id) VALUES (1)');
        $this->connection->execute('INSERT INTO conformance_right (id, left_id) VALUES (1, 1)');
        $this->connection->execute('UPDATE conformance_left SET right_id = 1');

        $this->schema->dropAll();

        $this->assertNoTables('conformance_left', 'conformance_right');
    }

    #[Test]
    public function it_restores_foreign_key_enforcement_afterwards(): void
    {
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->createBlog();

        $this->schema->dropAll();

        self::assertTrue($this->enforcesForeignKeys());
    }

    #[Test]
    public function it_leaves_foreign_key_enforcement_off_when_it_was_off(): void
    {
        $this->createBlog();

        $this->schema->dropAll();

        self::assertFalse($this->enforcesForeignKeys());
    }

    #[Test]
    public function it_reports_foreign_keys_it_cannot_suspend_inside_a_transaction(): void
    {
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->createBlog();
        $this->connection->execute('BEGIN');

        try {
            $this->schema->dropAll();
            self::fail('Dropping a referenced table inside a transaction was not reported.');
        } catch (SchemaExecutionException $exception) {
            self::assertSame('drop_all', $exception->context['operation']);
            self::assertSame([self::TABLE, 'conformance_posts', 'conformance_comments'], $exception->context['tables']);
        } finally {
            $this->connection->execute('ROLLBACK');
        }

        self::assertTrue($this->enforcesForeignKeys());
        self::assertTrue($this->schema->hasTable(self::TABLE));
    }

    #[Test]
    public function it_leaves_the_internal_tables_alone(): void
    {
        $this->createUsers();
        $this->insert('email', "'ada@example.com'");

        $this->schema->dropAll();

        self::assertSame(
            [['name' => 'sqlite_sequence']],
            $this->connection->execute("SELECT name FROM sqlite_master WHERE type = 'table'")->all(),
        );
    }

    #[Test]
    public function it_drops_a_table_rather_than_the_temporary_table_that_shadows_it(): void
    {
        $this->createUsers();
        $this->connection->execute(sprintf('CREATE TEMPORARY TABLE %s (id INT)', self::TABLE));

        $this->schema->dropAll();

        self::assertSame(
            [],
            $this->connection->execute(sprintf(
                "SELECT name FROM main.sqlite_master WHERE name = '%s'",
                self::TABLE,
            ))->all(),
        );
        self::assertSame([], $this->connection->execute(sprintf('SELECT id FROM temp.%s', self::TABLE))->all());
    }

    #[Test]
    public function it_drops_a_table_whose_name_is_not_a_valid_identifier(): void
    {
        $this->connection->execute('CREATE TABLE "conformance legacy-table" (id INT)');

        $this->schema->dropAll();

        self::assertSame(
            [],
            $this->connection->execute('SELECT name FROM sqlite_master WHERE type = \'table\'')->all(),
        );
    }
}
