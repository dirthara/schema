<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Closure;
use Dirthara\Database\Database;
use Dirthara\Database\Exception\DatabaseException;
use Dirthara\Schema\Grammar\SchemaGrammarResolver;
use Dirthara\Schema\Exception\InvalidSchemaException;
use Dirthara\Schema\Exception\SchemaExecutionException;
use Dirthara\Schema\Exception\SchemaConnectionException;
use Dirthara\Schema\Exception\UnsupportedDriverException;
use Dirthara\Schema\Exception\SchemaIntrospectionException;
use Dirthara\Schema\Exception\InvalidTableDefinitionException;

final readonly class Schema
{
    public function __construct(
        private Database $database,
        private SchemaGrammarResolver $grammars,
    ) {}

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     */
    public function using(?string $connection = null): ConnectedSchema
    {
        try {
            $databaseConnection = $this->database->connection($connection);
        } catch (DatabaseException $exception) {
            throw SchemaConnectionException::unavailable($exception);
        }

        return new ConnectedSchema(
            connection: $databaseConnection,
            grammar: $this->grammars->resolve($databaseConnection->driver()),
        );
    }

    /**
     * @param Closure(Table $table): void $callback
     *
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function create(string $table, Closure $callback, ?string $connection = null): void
    {
        $this->using($connection)->create($table, $callback);
    }

    /**
     * @param Closure(Table $table): void $callback
     *
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function createIfNotExists(string $table, Closure $callback, ?string $connection = null): void
    {
        $this->using($connection)->createIfNotExists($table, $callback);
    }

    /**
     * @param Closure(Table $table): void $callback
     *
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function table(string $table, Closure $callback, ?string $connection = null): void
    {
        $this->using($connection)->table($table, $callback);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function drop(string $table, ?string $connection = null): void
    {
        $this->using($connection)->drop($table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function dropIfExists(string $table, ?string $connection = null): void
    {
        $this->using($connection)->dropIfExists($table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function rename(string $from, string $to, ?string $connection = null): void
    {
        $this->using($connection)->rename($from, $to);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaIntrospectionException
     * @throws SchemaExecutionException
     * @throws InvalidSchemaException
     */
    public function dropAll(?string $connection = null): void
    {
        $this->using($connection)->dropAll();
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaIntrospectionException
     */
    public function hasTable(string $table, ?string $connection = null): bool
    {
        return $this->using($connection)->hasTable($table);
    }
}
