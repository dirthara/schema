<?php

declare(strict_types=1);

namespace Dirthara\Schema\Grammar;

use Dirthara\Schema\Table;
use Dirthara\Schema\Identifier;
use Dirthara\Schema\Index\Index;
use Dirthara\Schema\Change\Change;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Change\AddIndex;
use Dirthara\Schema\Change\AddColumn;
use Dirthara\Schema\Change\DropIndex;
use Dirthara\Schema\Change\DropColumn;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Change\ModifyColumn;
use Dirthara\Schema\Change\RenameColumn;
use Dirthara\Schema\Change\AddConstraint;
use Dirthara\Schema\Change\DropConstraint;
use Dirthara\Schema\Constraint\Constraint;
use Dirthara\Schema\Constraint\ForeignKey;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Schema\Constraint\UniqueConstraint;
use Dirthara\Schema\Constraint\ReferentialAction;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

use function is_int;
use function implode;
use function is_bool;
use function sprintf;
use function array_map;
use function str_replace;
use function str_contains;

abstract class SqlSchemaGrammar implements SchemaGrammar
{
    abstract protected function driver(): DriverName;

    abstract protected function wrap(Identifier $identifier): string;

    abstract protected function type(Column $column): string;

    /**
     * @param list<Column> $columns
     */
    abstract protected function inlinePrimaryKeyColumn(?PrimaryKey $primary, array $columns): ?Column;

    abstract protected function modifyColumn(Identifier $table, Column $column): string;

    /**
     * @throws InvalidSchemaException
     * @throws UnsupportedDriverException
     * @throws InvalidTableDefinitionException
     */
    public function compileCreate(string $table, Table $definition, bool $ifNotExists): CompiledSchema
    {
        $name = $this->identifier($table);

        $columns = [];
        $constraints = [];
        $indexes = [];
        $primary = null;

        foreach ($definition->changes() as $change) {
            if ($change instanceof AddColumn) {
                $columns[] = $change->column;

                continue;
            }

            if ($change instanceof AddIndex) {
                $indexes[] = $change->index;

                continue;
            }

            if ($change instanceof AddConstraint && $change->constraint instanceof PrimaryKey) {
                $primary = $change->constraint;

                continue;
            }

            if ($change instanceof AddConstraint) {
                $constraints[] = $change->constraint;

                continue;
            }

            throw $this->unsupported(
                sprintf('A table cannot be created with a [%s] change.', $change::class),
                $name,
                'create',
            );
        }

        $inlined = $this->inlinePrimaryKeyColumn($primary, $columns);

        $body = array_map(fn(Column $column): string => $this->column($column, $column === $inlined), $columns);

        if ($primary !== null && $inlined === null) {
            $body[] = sprintf(
                'CONSTRAINT %s PRIMARY KEY (%s)',
                $this->wrap($primary->name),
                $this->columnList($primary->columns),
            );
        }

        foreach ($constraints as $constraint) {
            $body[] = $this->constraint($constraint);
        }

        $compiled = new CompiledSchema([
            sprintf(
                'CREATE TABLE %s%s (%s)',
                $ifNotExists ? 'IF NOT EXISTS ' : '',
                $this->wrap($name),
                implode(', ', $body),
            ),
        ]);

        foreach ($indexes as $index) {
            $compiled->addQuery($this->createIndex($name, $index));
        }

        return $compiled;
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidTableDefinitionException
     * @throws UnsupportedDriverException
     */
    public function compileAlter(string $table, Table $definition): CompiledSchema
    {
        $name = $this->identifier($table);

        $compiled = new CompiledSchema([]);

        foreach ($definition->changes() as $change) {
            $compiled->addQuery($this->alteration($name, $change));
        }

        return $compiled;
    }

    public function compileDrop(string $table, bool $ifExists): CompiledSchema
    {
        return new CompiledSchema([
            sprintf('DROP TABLE %s%s', $ifExists ? 'IF EXISTS ' : '', $this->wrap($this->identifier($table))),
        ]);
    }

    public function compileRename(string $from, string $to): CompiledSchema
    {
        return new CompiledSchema([
            sprintf(
                'ALTER TABLE %s RENAME TO %s',
                $this->wrap($this->identifier($from)),
                $this->wrap($this->identifier($to)),
            ),
        ]);
    }

    /**
     * @throws UnsupportedDriverException
     */
    protected function alteration(Identifier $table, Change $change): string
    {
        return match (true) {
            $change instanceof AddColumn => $this->addColumn($table, $change->column),
            $change instanceof DropColumn => $this->dropColumn($table, $change->column),
            $change instanceof RenameColumn => $this->renameColumn($table, $change->from, $change->to),
            $change instanceof ModifyColumn => $this->modifyColumn($table, $change->column),
            $change instanceof AddIndex => $this->createIndex($table, $change->index),
            $change instanceof DropIndex => $this->dropIndex($table, $change->index),
            $change instanceof AddConstraint => $this->addConstraint($table, $change->constraint),
            $change instanceof DropConstraint => $this->dropConstraint($table, $change->constraint),
            default => throw $this->unsupported(
                sprintf('A table cannot be altered with a [%s] change.', $change::class),
                $table,
                'alter',
            ),
        };
    }

    protected function addColumn(Identifier $table, Column $column): string
    {
        return sprintf('ALTER TABLE %s ADD COLUMN %s', $this->wrap($table), $this->column($column, false));
    }

    protected function dropColumn(Identifier $table, Identifier $column): string
    {
        return sprintf('ALTER TABLE %s DROP COLUMN %s', $this->wrap($table), $this->wrap($column));
    }

    protected function renameColumn(Identifier $table, Identifier $from, Identifier $to): string
    {
        return sprintf(
            'ALTER TABLE %s RENAME COLUMN %s TO %s',
            $this->wrap($table),
            $this->wrap($from),
            $this->wrap($to),
        );
    }

    protected function addConstraint(Identifier $table, Constraint $constraint): string
    {
        return sprintf('ALTER TABLE %s ADD %s', $this->wrap($table), $this->constraint($constraint));
    }

    protected function dropConstraint(Identifier $table, Identifier $constraint): string
    {
        return sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $this->wrap($table), $this->wrap($constraint));
    }

    protected function createIndex(Identifier $table, Index $index): string
    {
        return sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $this->wrap($index->name),
            $this->wrap($table),
            $this->columnList($index->columns),
        );
    }

    protected function dropIndex(Identifier $table, Identifier $index): string
    {
        return sprintf('DROP INDEX %s ON %s', $this->wrap($index), $this->wrap($table));
    }

    /**
     * @throws UnsupportedDriverException
     */
    protected function constraint(Constraint $constraint): string
    {
        if ($constraint instanceof UniqueConstraint) {
            return sprintf(
                'CONSTRAINT %s UNIQUE (%s)',
                $this->wrap($constraint->name),
                $this->columnList($constraint->columns),
            );
        }

        if ($constraint instanceof PrimaryKey) {
            return sprintf(
                'CONSTRAINT %s PRIMARY KEY (%s)',
                $this->wrap($constraint->name),
                $this->columnList($constraint->columns),
            );
        }

        if ($constraint instanceof ForeignKey) {
            return $this->foreignKey($constraint);
        }

        throw $this->unsupported(
            sprintf('A [%s] constraint cannot be compiled.', $constraint::class),
            $constraint->name,
            'constraint',
        );
    }

    protected function foreignKey(ForeignKey $key): string
    {
        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->wrap($key->name),
            $this->columnList($key->columns),
            $this->wrap($key->on ?? $key->name),
            $this->columnList($key->references),
        );

        if ($key->onDelete !== null) {
            $sql .= ' ON DELETE ' . $this->action($key->onDelete);
        }

        if ($key->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . $this->action($key->onUpdate);
        }

        return $sql;
    }

    protected function action(ReferentialAction $action): string
    {
        return match ($action) {
            ReferentialAction::Cascade => 'CASCADE',
            ReferentialAction::Restrict => 'RESTRICT',
            ReferentialAction::SetNull => 'SET NULL',
            ReferentialAction::SetDefault => 'SET DEFAULT',
            ReferentialAction::NoAction => 'NO ACTION',
        };
    }

    /**
     * @throws UnsupportedDriverException
     * @throws InvalidSchemaException
     */
    protected function column(Column $column, bool $inlinePrimaryKey): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->type($column) . $this->unsigned($column);

        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->literal($column->default);
        }

        if ($inlinePrimaryKey) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql . $this->autoIncrement($column, $inlinePrimaryKey);
    }

    protected function unsigned(Column $column): string
    {
        return $column->unsigned ? ' UNSIGNED' : '';
    }

    protected function autoIncrement(Column $column, bool $inlinePrimaryKey): string
    {
        return '';
    }

    /**
     * @param list<Identifier> $columns
     */
    protected function columnList(array $columns): string
    {
        return implode(', ', array_map($this->wrap(...), $columns));
    }

    /**
     * @throws InvalidSchemaException
     */
    protected function literal(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $this->boolean($value);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            return sprintf('%.17G', $value);
        }

        if (str_contains($value, "\0")) {
            throw new InvalidSchemaException('A default value cannot contain a null byte.', context: [
                'driver' => $this->driver()->value,
            ]);
        }

        return $this->stringLiteral($value);
    }

    protected function stringLiteral(string $value): string
    {
        return "'" . $this->escape($value) . "'";
    }

    protected function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    protected function boolean(bool $value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * @throws InvalidSchemaException
     */
    protected function identifier(string $name): Identifier
    {
        return new Identifier($name);
    }

    protected function unsupported(string $message, Identifier $subject, string $operation): UnsupportedDriverException
    {
        return new UnsupportedDriverException($message, context: [
            'driver' => $this->driver()->value,
            'operation' => $operation,
            'subject' => $subject->name,
        ]);
    }
}
