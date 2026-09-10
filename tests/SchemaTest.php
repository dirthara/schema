<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests;

use Dirthara\Schema\Table;
use Dirthara\Schema\Schema;
use Dirthara\Database\Database;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\ConnectedSchema;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Grammar\SchemaGrammarResolver;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Schema\Exceptions\SchemaExecutionException;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Schema\Exceptions\SchemaConnectionException;
use Dirthara\Schema\Tests\Doubles\RecordingSchemaGrammar;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

final class SchemaTest extends TestCase
{
    private RecordingSchemaGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new RecordingSchemaGrammar();
    }

    private function config(string $name): ConnectionConfig
    {
        return new ConnectionConfig(driver: DriverName::SQLite, name: $name, database: ':memory:');
    }

    /**
     * @throws ConnectionException
     */
    private function database(ConnectionConfig ...$configs): Database
    {
        $manager = new ConnectionManager(
            new ConnectionFactory([new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))]),
            $configs === [] ? [$this->config('default')] : $configs,
        );

        return new Database($manager, new QueryGrammarResolver());
    }

    /**
     * @throws ConnectionException
     */
    private function schema(?Database $database = null, ?SchemaGrammarResolver $grammars = null): Schema
    {
        return new Schema(
            $database ?? $this->database(),
            $grammars ?? new SchemaGrammarResolver([DriverName::SQLite->value => $this->grammar]),
        );
    }

    #[Test]
    public function it_scopes_itself_to_the_default_connection(): void
    {
        self::assertInstanceOf(ConnectedSchema::class, $this->schema()->using());
    }

    #[Test]
    public function it_scopes_itself_to_a_named_connection(): void
    {
        $schema = $this->schema($this->database($this->config('default'), $this->config('reporting')));

        self::assertInstanceOf(ConnectedSchema::class, $schema->using('reporting'));
    }

    #[Test]
    public function it_reports_an_unconfigured_connection(): void
    {
        $this->expectException(SchemaConnectionException::class);
        $this->expectExceptionMessage('The requested database connection is not configured.');

        $this->schema()->using('missing');
    }

    #[Test]
    public function it_keeps_the_database_exception_as_the_previous_exception(): void
    {
        try {
            $this->schema()->using('missing');
        } catch (SchemaConnectionException $exception) {
            self::assertInstanceOf(ConnectionException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_driver_without_a_grammar(): void
    {
        $schema = $this->schema(grammars: new SchemaGrammarResolver());

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('No schema grammar has been registered for driver [sqlite].');

        $schema->using();
    }

    #[Test]
    public function it_delegates_a_create(): void
    {
        $this->schema()->create('users', static function (Table $table): void {});

        self::assertSame('compileCreate', $this->grammar->lastCall()['method']);
        self::assertFalse($this->grammar->lastCall()['ifNotExists']);
    }

    #[Test]
    public function it_delegates_a_create_if_not_exists(): void
    {
        $this->schema()->createIfNotExists('users', static function (Table $table): void {});

        self::assertSame('compileCreate', $this->grammar->lastCall()['method']);
        self::assertTrue($this->grammar->lastCall()['ifNotExists']);
    }

    #[Test]
    public function it_delegates_an_alter(): void
    {
        $this->schema()->table('users', static function (Table $table): void {});

        self::assertSame('compileAlter', $this->grammar->lastCall()['method']);
    }

    #[Test]
    public function it_delegates_a_drop(): void
    {
        $this->schema()->drop('users');

        self::assertSame(
            ['method' => 'compileDrop', 'table' => 'users', 'ifExists' => false],
            $this->grammar->lastCall(),
        );
    }

    #[Test]
    public function it_delegates_a_drop_if_exists(): void
    {
        $this->schema()->dropIfExists('users');

        self::assertSame(
            ['method' => 'compileDrop', 'table' => 'users', 'ifExists' => true],
            $this->grammar->lastCall(),
        );
    }

    #[Test]
    public function it_delegates_a_rename(): void
    {
        $this->schema()->rename('users', 'people');

        self::assertSame(
            ['method' => 'compileRename', 'from' => 'users', 'to' => 'people'],
            $this->grammar->lastCall(),
        );
    }

    #[Test]
    public function it_delegates_a_has_table(): void
    {
        self::assertTrue($this->schema()->hasTable('users'));
        self::assertSame(['method' => 'compileHasTable', 'table' => 'users'], $this->grammar->lastCall());
    }

    #[Test]
    public function it_runs_a_delegated_operation_on_the_named_connection(): void
    {
        $this->grammar->queries = ['NOT SQL'];

        $schema = $this->schema($this->database($this->config('default'), $this->config('reporting')));

        try {
            $schema->drop('users', 'reporting');
        } catch (SchemaExecutionException $exception) {
            self::assertSame('reporting', $exception->getContext()['connection']);
        }
    }
}
