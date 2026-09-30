<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Closure;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\SchemaExecutionException;
use Dirthara\Schema\Exceptions\SchemaConnectionException;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;
use Dirthara\Schema\Exceptions\SchemaIntrospectionException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

use function array_column;
use function array_unique;
use function array_values;

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
     * @throws InvalidTableDefinitionException
     * @throws UnsupportedDriverException
     */
    public function create(string $table, Closure $callback): void
    {
        $definition = $this->define($table, $callback);

        $this->execute(
            $this->grammar->compileCreate(definition: $definition, ifNotExists: false),
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
     * @throws InvalidTableDefinitionException
     * @throws UnsupportedDriverException
     */
    public function createIfNotExists(string $table, Closure $callback): void
    {
        $definition = $this->define($table, $callback);

        $this->execute(
            $this->grammar->compileCreate(definition: $definition, ifNotExists: true),
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
     * @throws InvalidTableDefinitionException
     * @throws UnsupportedDriverException
     */
    public function table(string $table, Closure $callback): void
    {
        $definition = $this->define($table, $callback);

        $this->execute($this->grammar->compileAlter($definition), Operation::Alter, $table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws UnsupportedDriverException
     */
    public function drop(string $table): void
    {
        $this->execute($this->grammar->compileDrop(table: $table, ifExists: false), Operation::Drop, $table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws UnsupportedDriverException
     */
    public function dropIfExists(string $table): void
    {
        $this->execute($this->grammar->compileDrop(table: $table, ifExists: true), Operation::DropIfExists, $table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws UnsupportedDriverException
     */
    public function rename(string $from, string $to): void
    {
        $this->execute($this->grammar->compileRename(from: $from, to: $to), Operation::Rename, $from, ['to' => $to]);
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaIntrospectionException
     * @throws InvalidSchemaException
     * @throws UnsupportedDriverException
     */
    public function hasTable(string $table): bool
    {
        return (
            $this->introspect($this->grammar->compileHasTable($table), Operation::HasTable, $table)->first() !== null
        );
    }

    /**
     * @throws SchemaConnectionException
     * @throws SchemaIntrospectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws UnsupportedDriverException
     */
    public function dropAll(): void
    {
        $tables = $this->introspect($this->grammar->compileTables(), Operation::DropAll)->all();

        if ($tables === []) {
            return;
        }

        $extra = ['tables' => array_values(array_unique(array_column($tables, 'name')))];

        try {
            $compiled = $this->grammar->compileDropAll($tables);
        } catch (SchemaIntrospectionException $exception) {
            throw $exception->addContext($this->context(Operation::DropAll, null, $extra));
        }

        $this->execute($compiled, Operation::DropAll, extra: $extra);
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
    private function execute(
        CompiledSchema $schema,
        Operation $operation,
        ?string $table = null,
        array $extra = [],
    ): void {
        $failure = null;

        try {
            foreach ($schema->queries as $query) {
                $this->run($query, $operation, $table, $extra);
            }
        } catch (SchemaConnectionException|SchemaExecutionException $exception) {
            $failure = $exception;
        }

        foreach ($schema->cleanup as $query) {
            try {
                $this->run($query, $operation, $table, $extra);
            } catch (SchemaConnectionException|SchemaExecutionException $exception) {
                throw $failure?->addContext(['cleanup_query' => $query]) ?? $exception;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @throws SchemaConnectionException
     * @throws SchemaExecutionException
     */
    private function run(string $query, Operation $operation, ?string $table, array $extra): void
    {
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

    /**
     * @throws SchemaConnectionException
     * @throws SchemaIntrospectionException
     */
    private function introspect(CompiledSchema $schema, Operation $operation, ?string $table = null): Result
    {
        $result = null;

        foreach ($schema->queries as $query) {
            try {
                $result = $this->connection->execute($query);
            } catch (ConnectionException $exception) {
                throw SchemaConnectionException::fromDatabaseException($exception)->addContext($this->context(
                    $operation,
                    $table,
                    ['query' => $query],
                ));
            } catch (QueryException $exception) {
                throw SchemaIntrospectionException::fromQueryException($exception)->addContext($this->context(
                    $operation,
                    $table,
                    ['query' => $query],
                ));
            }
        }

        return $result ?? throw new SchemaIntrospectionException('The schema grammar compiled no query to introspect with.', context: $this->context(
            $operation,
            $table,
        ));
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function context(Operation $operation, ?string $table, array $extra = []): array
    {
        return [
            'connection' => $this->connection->name(),
            'driver' => $this->connection->driver()->value,
            'operation' => $operation->value,
            ...($table === null ? [] : ['table' => $table]),
            ...$extra,
        ];
    }
}
