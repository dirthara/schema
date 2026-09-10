<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Dirthara\Schema\Change\Change;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Change\AddColumn;
use Dirthara\Schema\Change\DropColumn;
use Dirthara\Schema\Column\ColumnType;
use Dirthara\Schema\Change\ModifyColumn;
use Dirthara\Schema\Change\RenameColumn;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

use function sprintf;
use function array_map;
use function array_key_exists;

final class Table
{
    /**
     * @var list<Change|Column>
     */
    private array $entries = [];

    /**
     * @var array<string, true>
     */
    private array $defined = [];

    public readonly Identifier $name;

    /**
     * @throws InvalidSchemaException
     */
    public function __construct(string $name)
    {
        try {
            $this->name = new Identifier($name);
        } catch (InvalidSchemaException $exception) {
            throw $exception->addContext(['table' => $name]);
        }
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function column(string $name, ColumnType $type): Column
    {
        return $this->define($name, $type);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function id(string $name = 'id'): Column
    {
        return $this->define($name, ColumnType::BigInteger)->unsigned()->autoIncrement()->primary();
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function boolean(string $name): Column
    {
        return $this->define($name, ColumnType::Boolean);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function tinyInteger(string $name): Column
    {
        return $this->define($name, ColumnType::TinyInteger);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function smallInteger(string $name): Column
    {
        return $this->define($name, ColumnType::SmallInteger);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function integer(string $name): Column
    {
        return $this->define($name, ColumnType::Integer);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function bigInteger(string $name): Column
    {
        return $this->define($name, ColumnType::BigInteger);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->define($name, ColumnType::Decimal)->precision($precision, $scale);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function float(string $name): Column
    {
        return $this->define($name, ColumnType::Float);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function double(string $name): Column
    {
        return $this->define($name, ColumnType::Double);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function char(string $name, int $length = 255): Column
    {
        return $this->define($name, ColumnType::Char)->length($length);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function string(string $name, int $length = 255): Column
    {
        return $this->define($name, ColumnType::String)->length($length);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function text(string $name): Column
    {
        return $this->define($name, ColumnType::Text);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function date(string $name): Column
    {
        return $this->define($name, ColumnType::Date);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function time(string $name): Column
    {
        return $this->define($name, ColumnType::Time);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function dateTime(string $name): Column
    {
        return $this->define($name, ColumnType::DateTime);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function timestamp(string $name): Column
    {
        return $this->define($name, ColumnType::Timestamp);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function uuid(string $name = 'uuid'): Column
    {
        return $this->define($name, ColumnType::Uuid);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function json(string $name): Column
    {
        return $this->define($name, ColumnType::Json);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function binary(string $name): Column
    {
        return $this->define($name, ColumnType::Binary);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function timestamps(string $created = 'created_at', string $updated = 'updated_at'): void
    {
        $this->timestamp($created)->nullable();
        $this->timestamp($updated)->nullable();
    }

    /**
     * @throws InvalidSchemaException
     */
    public function dropColumn(string ...$names): void
    {
        foreach ($names as $name) {
            $this->entries[] = new DropColumn($this->identify($name));
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->entries[] = new RenameColumn($this->identify($from), $this->identify($to));
    }

    /**
     * @return list<Change>
     */
    public function changes(): array
    {
        return array_map(static function (Change|Column $entry): Change {
            if (!$entry instanceof Column) {
                return $entry;
            }

            return $entry->changed ? new ModifyColumn($entry) : new AddColumn($entry);
        }, $this->entries);
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    private function define(string $name, ColumnType $type): Column
    {
        $identifier = $this->identify($name);

        if (array_key_exists($identifier->name, $this->defined)) {
            throw new InvalidTableDefinitionException(
                sprintf(
                    'The column [%s] is defined more than once on table [%s].',
                    $identifier->name,
                    $this->name->name,
                ),
                context: ['table' => $this->name->name, 'column' => $identifier->name],
            );
        }

        $this->defined[$identifier->name] = true;

        $column = new Column($identifier, $type);

        $this->entries[] = $column;

        return $column;
    }

    /**
     * @throws InvalidSchemaException
     */
    private function identify(string $name): Identifier
    {
        try {
            return new Identifier($name);
        } catch (InvalidSchemaException $exception) {
            throw $exception->addContext(['table' => $this->name->name]);
        }
    }
}
