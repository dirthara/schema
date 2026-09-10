<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Closure;
use Dirthara\Database\Database;
use Dirthara\Schema\Grammar\SchemaGrammarResolver;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Schema\Exceptions\SchemaExecutionException;
use Dirthara\Schema\Exceptions\SchemaConnectionException;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;
use Dirthara\Schema\Exceptions\SchemaIntrospectionException;

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
            throw SchemaConnectionException::fromDatabaseException($exception);
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
     */
    public function table(string $table, Closure $callback, ?string $connection = null): void
    {
        $this->using($connection)->table($table, $callback);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     */
    public function drop(string $table, ?string $connection = null): void
    {
        $this->using($connection)->drop($table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     */
    public function dropIfExists(string $table, ?string $connection = null): void
    {
        $this->using($connection)->dropIfExists($table);
    }

    /**
     * @throws SchemaConnectionException
     * @throws UnsupportedDriverException
     * @throws SchemaExecutionException
     */
    public function rename(string $from, string $to, ?string $connection = null): void
    {
        $this->using($connection)->rename($from, $to);
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
