<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Dirthara\Schema\Index\Index;
use Dirthara\Schema\Change\Change;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Change\AddIndex;
use Dirthara\Schema\Change\AddColumn;
use Dirthara\Schema\Change\DropIndex;
use Dirthara\Schema\Change\DropColumn;
use Dirthara\Schema\Column\ColumnType;
use Dirthara\Schema\Change\ModifyColumn;
use Dirthara\Schema\Change\RenameColumn;
use Dirthara\Schema\Change\AddConstraint;
use Dirthara\Schema\Change\DropConstraint;
use Dirthara\Schema\Constraint\Constraint;
use Dirthara\Schema\Constraint\ForeignKey;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Schema\Constraint\UniqueConstraint;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

use function implode;
use function sprintf;
use function array_map;
use function is_string;
use function array_values;
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
     * @param string|list<string> $columns
     *
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function index(string|array $columns, ?string $name = null): Index
    {
        $on = $this->keyColumns($columns);

        $index = new Index($this->keyName($name, $on, 'index'), $on);

        $this->entries[] = new AddIndex($index);

        return $index;
    }

    /**
     * @param string|list<string> $columns
     *
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function unique(string|array $columns, ?string $name = null): UniqueConstraint
    {
        $on = $this->keyColumns($columns);

        $constraint = new UniqueConstraint($this->keyName($name, $on, 'unique'), $on);

        $this->entries[] = new AddConstraint($constraint);

        return $constraint;
    }

    /**
     * A primary key is named after the table alone, because a table has only
     * one and the columns it covers can change without the name having to.
     *
     * @param string|list<string> $columns
     *
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function primary(string|array $columns, ?string $name = null): PrimaryKey
    {
        $on = $this->keyColumns($columns);

        $key = new PrimaryKey($this->keyName($name, [], 'primary'), $on);

        $this->entries[] = new AddConstraint($key);

        return $key;
    }

    /**
     * @param string|list<string> $columns
     *
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKey
    {
        $on = $this->keyColumns($columns);

        $key = new ForeignKey($this->keyName($name, $on, 'foreign'), $on);

        $this->entries[] = new AddConstraint($key);

        return $key;
    }

    /**
     * @throws InvalidSchemaException
     */
    public function dropIndex(string ...$names): void
    {
        foreach ($names as $name) {
            $this->entries[] = new DropIndex($this->identify($name));
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function dropConstraint(string ...$names): void
    {
        foreach ($names as $name) {
            $this->entries[] = new DropConstraint($this->identify($name));
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function dropPrimary(?string $name = null): void
    {
        $this->entries[] = new DropConstraint($this->keyName($name, [], 'primary'));
    }

    /**
     * Everything the definition asked for, in the order it was asked for,
     * with the constraints a column flagged for itself appended after it.
     *
     * A grammar reads uniqueness and primary keys as constraints and nowhere
     * else, so `$table->string('email')->unique()` and `$table->unique('email')`
     * arrive as the same thing.
     *
     * @return list<Change>
     *
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    public function changes(): array
    {
        $changes = array_map(static function (Change|Column $entry): Change {
            if (!$entry instanceof Column) {
                return $entry;
            }

            return $entry->changed ? new ModifyColumn($entry) : new AddColumn($entry);
        }, $this->entries);

        foreach ($this->flagged() as $change) {
            $changes[] = $change;
        }

        $this->verify($changes);

        return $changes;
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
     * The constraints a column asked for with a modifier rather than a call.
     *
     * Every column flagged primary joins one key, because a table has one
     * primary key covering however many columns it covers.
     *
     * @return list<AddConstraint>
     *
     * @throws InvalidSchemaException
     */
    private function flagged(): array
    {
        $primary = [];
        $flagged = [];

        foreach ($this->entries as $entry) {
            if (!($entry instanceof Column && $entry->primary)) { continue; }

$primary[] = $entry->name;
        }

        if ($primary !== []) {
            $flagged[] = new AddConstraint(new PrimaryKey($this->keyName(null, [], 'primary'), $primary));
        }

        foreach ($this->entries as $entry) {
            if (!($entry instanceof Column && $entry->unique)) { continue; }

$flagged[] = new AddConstraint(new UniqueConstraint($this->keyName(null, [$entry->name], 'unique'), [
                    $entry->name,
                ]));
        }

        return $flagged;
    }

    /**
     * @param list<Change> $changes
     *
     * @throws InvalidTableDefinitionException
     */
    private function verify(array $changes): void
    {
        $primaries = 0;

        foreach ($changes as $change) {
            if (!($change instanceof AddConstraint && $change->constraint instanceof PrimaryKey)) { continue; }

$primaries++;
        }

        if ($primaries > 1) {
            throw new InvalidTableDefinitionException(
                sprintf('Table [%s] defines %d primary keys, and a table has one.', $this->name->name, $primaries),
                context: ['table' => $this->name->name, 'primary_keys' => $primaries],
            );
        }

        $taken = [];

        foreach ($changes as $change) {
            if ($change instanceof AddIndex) {
                $name = $change->index->name->name;
            } elseif ($change instanceof AddConstraint) {
                $name = $change->constraint->name->name;

                $this->verifyForeignKey($change->constraint);
            } else {
                continue;
            }

            if (array_key_exists($name, $taken)) {
                throw new InvalidTableDefinitionException(
                    sprintf('Table [%s] defines [%s] more than once.', $this->name->name, $name),
                    context: ['table' => $this->name->name, 'key' => $name],
                );
            }

            $taken[$name] = true;
        }
    }

    /**
     * @throws InvalidTableDefinitionException
     */
    private function verifyForeignKey(Constraint $constraint): void
    {
        if (!$constraint instanceof ForeignKey) {
            return;
        }

        $reason = $constraint->incompleteness();

        if ($reason !== null) {
            throw new InvalidTableDefinitionException($reason, context: [
                'table' => $this->name->name,
                'key' => $constraint->name->name,
            ]);
        }
    }

    /**
     * @param string|list<string> $columns
     *
     * @return list<Identifier>
     *
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     */
    private function keyColumns(string|array $columns): array
    {
        $names = is_string($columns) ? [$columns] : $columns;

        if ($names === []) {
            throw new InvalidTableDefinitionException(
                sprintf('A key on table [%s] needs at least one column.', $this->name->name),
                context: ['table' => $this->name->name],
            );
        }

        return array_values(array_map($this->identify(...), $names));
    }

    /**
     * The name a caller gave, or one built from the table, the columns and
     * what the key is for. A generated name is what `dropIndex()` and
     * `dropConstraint()` are given later, so it has to be predictable.
     *
     * @param list<Identifier> $columns
     *
     * @throws InvalidSchemaException
     */
    private function keyName(?string $name, array $columns, string $suffix): Identifier
    {
        if ($name !== null) {
            return $this->identify($name);
        }

        $parts = array_map(static fn(Identifier $column): string => $column->name, $columns);

        return $this->identify(implode('_', [$this->name->name, ...$parts, $suffix]));
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
