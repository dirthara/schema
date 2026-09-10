<?php

declare(strict_types=1);

namespace Dirthara\Schema\Grammar;

use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;

use function sprintf;
use function array_key_exists;

final class SchemaGrammarResolver
{
    /**
     * @var array<string, SchemaGrammar>
     */
    private array $grammars = [];

    /**
     * @param iterable<string|DriverName, SchemaGrammar> $grammars
     *
     * @throws InvalidSchemaException
     */
    public function __construct(iterable $grammars = [])
    {
        foreach ($grammars as $driver => $grammar) {
            $this->register($driver, $grammar);
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function register(string|DriverName $driver, SchemaGrammar $grammar): void
    {
        $name = $this->name($driver);

        if (array_key_exists($name, $this->grammars)) {
            throw new InvalidSchemaException(
                sprintf('A schema grammar is already registered for driver [%s].', $name),
                context: ['driver' => $name],
            );
        }

        $this->grammars[$name] = $grammar;
    }

    /**
     * @throws UnsupportedDriverException
     */
    public function resolve(string|DriverName $driver): SchemaGrammar
    {
        $name = $this->name($driver);

        return (
            $this->grammars[$name] ?? throw new UnsupportedDriverException(
                sprintf('No schema grammar has been registered for driver [%s].', $name),
                context: ['driver' => $name],
            )
        );
    }

    private function name(string|DriverName $driver): string
    {
        return $driver instanceof DriverName ? $driver->value : $driver;
    }
}
