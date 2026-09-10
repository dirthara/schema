<?php

declare(strict_types=1);

namespace Dirthara\Schema\Grammar;

use Dirthara\Schema\Identifier;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Column\ColumnType;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Constraint\Constraint;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Schema\Constraint\UniqueConstraint;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;

use function count;
use function sprintf;
use function str_replace;

class SQLiteSchemaGrammar extends SqlSchemaGrammar
{
    /**
     * @throws InvalidSchemaException
     */
    public function compileHasTable(string $table): CompiledSchema
    {
        $name = $this->identifier($table);

        return new CompiledSchema([
            sprintf(
                'SELECT "name" FROM "sqlite_master" WHERE "type" = %s AND "name" = %s',
                $this->literal('table'),
                $this->literal($name->name),
            ),
        ]);
    }

    protected function driver(): DriverName
    {
        return DriverName::SQLite;
    }

    protected function wrap(Identifier $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier->name) . '"';
    }

    protected function type(Column $column): string
    {
        return match ($column->type) {
            ColumnType::Boolean,
            ColumnType::TinyInteger,
            ColumnType::SmallInteger,
            ColumnType::Integer,
            ColumnType::BigInteger,
                => 'INTEGER',
            ColumnType::Decimal => $column->precision === null
                ? 'NUMERIC'
                : sprintf('NUMERIC(%d, %d)', $column->precision, $column->scale ?? 0),
            ColumnType::Float, ColumnType::Double => 'REAL',
            ColumnType::Char => sprintf('CHAR(%d)', $column->length ?? 255),
            ColumnType::String => sprintf('VARCHAR(%d)', $column->length ?? 255),
            ColumnType::Text, ColumnType::Json => 'TEXT',
            ColumnType::Date => 'DATE',
            ColumnType::Time => 'TIME',
            ColumnType::DateTime, ColumnType::Timestamp => 'DATETIME',
            ColumnType::Uuid => 'CHAR(36)',
            ColumnType::Binary => 'BLOB',
        };
    }

    protected function unsigned(Column $column): string
    {
        return '';
    }

    protected function inlinePrimaryKeyColumn(?PrimaryKey $primary, array $columns): ?Column
    {
        if ($primary === null || count($primary->columns) !== 1) {
            return null;
        }

        return array_find(
            $columns,
            static fn($column) => $column->name->name === $primary->columns[0]->name && $column->autoIncrement,
        );
    }

    /**
     * @throws UnsupportedDriverException
     */
    protected function autoIncrement(Column $column, bool $inlinePrimaryKey): string
    {
        if (!$column->autoIncrement) {
            return '';
        }

        if (!$inlinePrimaryKey) {
            throw $this->unsupported(
                'SQLite can only auto-increment a single-column integer primary key.',
                $column->name,
                'create',
            );
        }

        return ' AUTOINCREMENT';
    }

    /**
     * @throws UnsupportedDriverException
     */
    protected function addColumn(Identifier $table, Column $column): string
    {
        if (!$column->nullable && !$column->hasDefault) {
            throw $this->unsupported(
                sprintf(
                    'SQLite cannot add the NOT NULL column [%s] without a default to the existing table [%s].',
                    $column->name->name,
                    $table->name,
                ),
                $column->name,
                'alter',
            );
        }

        return parent::addColumn($table, $column);
    }

    /**
     * @throws UnsupportedDriverException
     */
    protected function addConstraint(Identifier $table, Constraint $constraint): string
    {
        if (!$constraint instanceof UniqueConstraint) {
            throw $this->unsupported(
                sprintf(
                    'SQLite cannot add a [%s] to the existing table [%s]; declare it when the table is created.',
                    $constraint instanceof PrimaryKey ? 'primary key' : 'foreign key',
                    $table->name,
                ),
                $constraint->name,
                'alter',
            );
        }

        return sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s)',
            $this->wrap($constraint->name),
            $this->wrap($table),
            $this->columnList($constraint->columns),
        );
    }

    protected function dropConstraint(Identifier $table, Identifier $constraint): string
    {
        return $this->dropIndex($table, $constraint);
    }

    protected function dropIndex(Identifier $table, Identifier $index): string
    {
        return sprintf('DROP INDEX %s', $this->wrap($index));
    }

    /**
     * @throws UnsupportedDriverException
     */
    protected function modifyColumn(Identifier $table, Column $column): string
    {
        throw $this->unsupported(
            sprintf(
                'SQLite cannot change the column [%s] on the existing table [%s].',
                $column->name->name,
                $table->name,
            ),
            $column->name,
            'alter',
        );
    }
}
