<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests;

use Throwable;
use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\ConnectedSchema;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Schema\Tests\Doubles\ThrowingConnection;
use Dirthara\Schema\Exceptions\SchemaExecutionException;
use Dirthara\Schema\Exceptions\SchemaConnectionException;
use Dirthara\Schema\Tests\Doubles\RecordingSchemaGrammar;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Schema\Exceptions\SchemaIntrospectionException;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

final class ConnectedSchemaTest extends TestCase
{
    private RecordingSchemaGrammar $grammar;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new RecordingSchemaGrammar();
        $this->connection = new PdoConnection(
            new ConnectionConfig(driver: DriverName::SQLite, name: 'schema', database: ':memory:'),
            new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix())),
        );
    }

    private function schema(?Connection $connection = null): ConnectedSchema
    {
        return new ConnectedSchema($connection ?? $this->connection, $this->grammar);
    }

    /**
     * @param list<string> $queries
     */
    private function compiling(array $queries): ConnectedSchema
    {
        $this->grammar->queries = $queries;

        return $this->schema();
    }

    private function failingWith(Throwable $failure): ConnectedSchema
    {
        return new ConnectedSchema(new ThrowingConnection($failure, 'reporting'), $this->grammar);
    }

    #[Test]
    public function it_compiles_a_create_without_the_if_not_exists_flag(): void
    {
        $this->schema()->create('users', static function (Table $table): void {});

        self::assertSame(
            ['method' => 'compileCreate', 'table' => 'users', 'ifNotExists' => false],
            $this->callWithoutDefinition(),
        );
    }

    #[Test]
    public function it_passes_a_definition_named_after_the_table_to_the_callback(): void
    {
        $received = null;

        $this->schema()->create('users', static function (Table $table) use (&$received): void {
            $received = $table;
        });

        self::assertInstanceOf(Table::class, $received);
        self::assertSame('users', $received->name);
    }

    #[Test]
    public function it_compiles_the_definition_the_callback_was_given(): void
    {
        $received = null;

        $this->schema()->create('users', static function (Table $table) use (&$received): void {
            $received = $table;
        });

        self::assertSame($received, $this->grammar->lastCall()['definition']);
    }

    #[Test]
    public function it_compiles_a_create_with_the_if_not_exists_flag(): void
    {
        $this->schema()->createIfNotExists('users', static function (Table $table): void {});

        self::assertSame(
            ['method' => 'compileCreate', 'table' => 'users', 'ifNotExists' => true],
            $this->callWithoutDefinition(),
        );
    }

    #[Test]
    public function it_compiles_an_alter(): void
    {
        $this->schema()->table('users', static function (Table $table): void {});

        self::assertSame(['method' => 'compileAlter', 'table' => 'users'], $this->callWithoutDefinition());
    }

    #[Test]
    public function it_compiles_a_drop_without_the_if_exists_flag(): void
    {
        $this->schema()->drop('users');

        self::assertSame(
            ['method' => 'compileDrop', 'table' => 'users', 'ifExists' => false],
            $this->grammar->lastCall(),
        );
    }

    #[Test]
    public function it_compiles_a_drop_with_the_if_exists_flag(): void
    {
        $this->schema()->dropIfExists('users');

        self::assertSame(
            ['method' => 'compileDrop', 'table' => 'users', 'ifExists' => true],
            $this->grammar->lastCall(),
        );
    }

    #[Test]
    public function it_compiles_a_rename(): void
    {
        $this->schema()->rename('users', 'people');

        self::assertSame(
            ['method' => 'compileRename', 'from' => 'users', 'to' => 'people'],
            $this->grammar->lastCall(),
        );
    }

    #[Test]
    public function it_runs_the_compiled_statement(): void
    {
        $this->compiling([
            'CREATE TABLE users (id INTEGER PRIMARY KEY)',
        ])->create('users', static function (Table $table): void {});

        self::assertNotNull($this->connection->execute("SELECT name FROM sqlite_master WHERE name = 'users'")->first());
    }

    #[Test]
    public function it_runs_every_compiled_statement_in_order(): void
    {
        $this->compiling([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)',
            'CREATE UNIQUE INDEX users_email ON users (email)',
        ])->create('users', static function (Table $table): void {});

        self::assertSame(
            [['name' => 'users'], ['name' => 'users_email']],
            $this->connection->execute('SELECT name FROM sqlite_master ORDER BY rowid')->all(),
        );
    }

    #[Test]
    public function it_reports_a_table_that_exists(): void
    {
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY)');

        $schema = $this->compiling(["SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'"]);

        self::assertTrue($schema->hasTable('users'));
        self::assertSame(['method' => 'compileHasTable', 'table' => 'users'], $this->grammar->lastCall());
    }

    #[Test]
    public function it_reports_a_table_that_does_not_exist(): void
    {
        $schema = $this->compiling(["SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'missing'"]);

        self::assertFalse($schema->hasTable('missing'));
    }

    #[Test]
    public function it_reports_a_grammar_that_compiled_no_introspection_query(): void
    {
        $schema = $this->compiling([]);

        $this->expectException(SchemaIntrospectionException::class);
        $this->expectExceptionMessage('The schema grammar compiled no query to introspect with.');

        $schema->hasTable('users');
    }

    #[Test]
    public function it_reports_the_operation_when_a_grammar_compiled_no_introspection_query(): void
    {
        try {
            $this->compiling([])->hasTable('users');
        } catch (SchemaIntrospectionException $exception) {
            self::assertSame(
                ['connection' => 'schema', 'driver' => 'sqlite', 'operation' => 'has_table', 'table' => 'users'],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_translates_a_failing_statement(): void
    {
        $schema = $this->compiling(['CREATE TABLE']);

        $this->expectException(SchemaExecutionException::class);

        $schema->create('users', static function (Table $table): void {});
    }

    #[Test]
    public function it_keeps_the_query_exception_as_the_previous_exception(): void
    {
        try {
            $this->compiling(['CREATE TABLE'])->create('users', static function (Table $table): void {});
        } catch (SchemaExecutionException $exception) {
            self::assertInstanceOf(QueryException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_describes_the_operation_that_failed(): void
    {
        try {
            $this->compiling(['CREATE TABLE'])->create('users', static function (Table $table): void {});
        } catch (SchemaExecutionException $exception) {
            self::assertSame(
                [
                    'connection' => 'schema',
                    'driver' => 'sqlite',
                    'operation' => 'create',
                    'table' => 'users',
                    'query' => 'CREATE TABLE',
                ],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_describes_both_names_when_a_rename_fails(): void
    {
        try {
            $this->compiling(['ALTER NONSENSE'])->rename('users', 'people');
        } catch (SchemaExecutionException $exception) {
            self::assertSame(
                [
                    'connection' => 'schema',
                    'driver' => 'sqlite',
                    'operation' => 'rename',
                    'table' => 'users',
                    'to' => 'people',
                    'query' => 'ALTER NONSENSE',
                ],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_translates_a_failing_introspection_query(): void
    {
        $schema = $this->compiling(['SELECT FROM nowhere']);

        $this->expectException(SchemaIntrospectionException::class);

        $schema->hasTable('users');
    }

    #[Test]
    public function it_describes_the_introspection_that_failed(): void
    {
        try {
            $this->compiling(['SELECT FROM nowhere'])->hasTable('users');
        } catch (SchemaIntrospectionException $exception) {
            self::assertSame(
                [
                    'connection' => 'schema',
                    'driver' => 'sqlite',
                    'operation' => 'has_table',
                    'table' => 'users',
                    'query' => 'SELECT FROM nowhere',
                ],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_translates_a_connection_failure_while_executing(): void
    {
        $schema = $this->failingWith(new ConnectionException('The server is unreachable.'));

        $this->expectException(SchemaConnectionException::class);
        $this->expectExceptionMessage('The server is unreachable.');

        $schema->drop('users');
    }

    #[Test]
    public function it_describes_the_connection_that_failed_while_executing(): void
    {
        try {
            $this->failingWith(new ConnectionException('The server is unreachable.'))->drop('users');
        } catch (SchemaConnectionException $exception) {
            self::assertSame(
                [
                    'connection' => 'reporting',
                    'driver' => 'sqlite',
                    'operation' => 'drop',
                    'table' => 'users',
                    'query' => 'SELECT 1',
                ],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_translates_a_connection_failure_while_introspecting(): void
    {
        $schema = $this->failingWith(new ConnectionException('The server is unreachable.'));

        $this->expectException(SchemaConnectionException::class);

        $schema->hasTable('users');
    }

    #[Test]
    public function it_describes_the_connection_that_failed_while_introspecting(): void
    {
        try {
            $this->failingWith(new ConnectionException('The server is unreachable.'))->hasTable('users');
        } catch (SchemaConnectionException $exception) {
            self::assertSame(
                [
                    'connection' => 'reporting',
                    'driver' => 'sqlite',
                    'operation' => 'has_table',
                    'table' => 'users',
                    'query' => 'SELECT 1',
                ],
                $exception->getContext(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function callWithoutDefinition(): array
    {
        $call = $this->grammar->lastCall();

        unset($call['definition']);

        return $call;
    }
}
