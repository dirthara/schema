<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Closure;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\SchemaExecutionException;
use Dirthara\Schema\Exceptions\SchemaConnectionException;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Schema\Exceptions\SchemaIntrospectionException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final readonly class ConnectedSchema
{
    public function __construct(
        private Connection $connection,
        private SchemaGrammar $grammar,
    ) {}

    /**
     * @param Closure(Table): void $callback
     *
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     */
    public function create(string $table, Closure $callback): void
    {
        $definition = $this->define($table, $callback);

        $this->execute(
            $this->grammar->compileCreate(table: $table, definition: $definition, ifNotExists: false),
            Operation::Create,
            $table,
        );
    }

    /**
     * @param Closure(Table): void $callback
     *
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     */
    public function createIfNotExists(string $table, Closure $callback): void
    {
        $definition = $this->define($table, $callback);

        $this->execute(
            $this->grammar->compileCreate(table: $table, definition: $definition, ifNotExists: true),
            Operation::CreateIfNotExists,
            $table,
        );
    }

    /**
     * @param Closure(Table): void $callback
     *
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     */
    public function table(string $table, Closure $callback): void
    {
        $definition = $this->define($table, $callback);

        $this->execute($this->grammar->compileAlter(table: $table, definition: $definition), Operation::Alter, $table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     */
    public function drop(string $table): void
    {
        $this->execute($this->grammar->compileDrop(table: $table, ifExists: false), Operation::Drop, $table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     */
    public function dropIfExists(string $table): void
    {
        $this->execute($this->grammar->compileDrop(table: $table, ifExists: true), Operation::DropIfExists, $table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     */
    public function rename(string $from, string $to): void
    {
        $this->execute($this->grammar->compileRename(from: $from, to: $to), Operation::Rename, $from, ['to' => $to]);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaIntrospectionException
     */
    public function hasTable(string $table): bool
    {
        return $this->introspect($this->grammar->compileHasTable($table), $table)->first() !== null;
    }

    /**
     * @param Closure(Table): void $callback
     *
     * @throws InvalidSchemaException
     */
    private function define(string $table, Closure $callback): Table
    {
        $definition = new Table($table);

        $callback($definition);

        return $definition;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     */
    private function execute(CompiledSchema $schema, Operation $operation, string $table, array $extra = []): void
    {
        foreach ($schema->queries as $query) {
            try {
                $this->connection->execute($query);
            } catch (ConnectionException $exception) {
                throw SchemaConnectionException::fromDatabaseException($exception)->addContext($this->context(
                    $operation,
                    $table,
                    [...$extra, 'query' => $query],
                ));
            } catch (QueryException $exception) {
                throw SchemaExecutionException::fromQueryException($exception)->addContext($this->context(
                    $operation,
                    $table,
                    [...$extra, 'query' => $query],
                ));
            }
        }
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaIntrospectionException
     */
    private function introspect(CompiledSchema $schema, string $table): Result
    {
        $result = null;

        foreach ($schema->queries as $query) {
            try {
                $result = $this->connection->execute($query);
            } catch (ConnectionException $exception) {
                throw SchemaConnectionException::fromDatabaseException($exception)->addContext($this->context(
                    Operation::HasTable,
                    $table,
                    ['query' => $query],
                ));
            } catch (QueryException $exception) {
                throw SchemaIntrospectionException::fromQueryException($exception)->addContext($this->context(
                    Operation::HasTable,
                    $table,
                    ['query' => $query],
                ));
            }
        }

        return $result ?? throw new SchemaIntrospectionException('The schema grammar compiled no query to introspect with.', context: $this->context(
            Operation::HasTable,
            $table,
        ));
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function context(Operation $operation, string $table, array $extra = []): array
    {
        return [
            'connection' => $this->connection->name(),
            'driver' => $this->connection->driver()->value,
            'operation' => $operation->value,
            'table' => $table,
            ...$extra,
        ];
    }
}
