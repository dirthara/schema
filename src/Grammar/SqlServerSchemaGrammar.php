<?php

declare(strict_types=1);

namespace Dirthara\Schema\Grammar;

use Dirthara\Schema\Table;
use Dirthara\Schema\Identifier;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Column\ColumnType;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Database\Connection\Driver\DriverName;

use function sprintf;
use function str_replace;

class SqlServerSchemaGrammar extends SqlSchemaGrammar
{
    public function compileCreate(string $table, Table $definition, bool $ifNotExists): CompiledSchema
    {
        $queries = parent::compileCreate($table, $definition, false)->queries;

        if (!$ifNotExists) {
            return new CompiledSchema($queries);
        }

        $queries[0] = sprintf(
            "IF OBJECT_ID(%s, N'U') IS NULL %s",
            $this->literal($this->wrap($this->identifier($table))),
            $queries[0],
        );

        return new CompiledSchema($queries);
    }

    public function compileRename(string $from, string $to): CompiledSchema
    {
        return new CompiledSchema([
            sprintf(
                'EXEC sp_rename %s, %s',
                $this->literal($this->identifier($from)->name),
                $this->literal($this->identifier($to)->name),
            ),
        ]);
    }

    public function compileHasTable(string $table): CompiledSchema
    {
        return new CompiledSchema([
            sprintf(
                'SELECT [TABLE_NAME] FROM [INFORMATION_SCHEMA].[TABLES] '
                . 'WHERE [TABLE_SCHEMA] = SCHEMA_NAME() AND [TABLE_NAME] = %s',
                $this->literal($this->identifier($table)->name),
            ),
        ]);
    }

    protected function driver(): DriverName
    {
        return DriverName::SqlServer;
    }

    protected function wrap(Identifier $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier->name) . ']';
    }

    protected function type(Column $column): string
    {
        return match ($column->type) {
            ColumnType::Boolean => 'BIT',
            ColumnType::TinyInteger => 'TINYINT',
            ColumnType::SmallInteger => 'SMALLINT',
            ColumnType::Integer => 'INT',
            ColumnType::BigInteger => 'BIGINT',
            ColumnType::Decimal => sprintf('DECIMAL(%d, %d)', $column->precision ?? 8, $column->scale ?? 2),
            ColumnType::Float => 'REAL',
            ColumnType::Double => 'FLOAT',
            ColumnType::Char => sprintf('NCHAR(%d)', $column->length ?? 255),
            ColumnType::String => sprintf('NVARCHAR(%d)', $column->length ?? 255),
            ColumnType::Text, ColumnType::Json => 'NVARCHAR(MAX)',
            ColumnType::Date => 'DATE',
            ColumnType::Time => 'TIME',
            ColumnType::DateTime, ColumnType::Timestamp => 'DATETIME2',
            ColumnType::Uuid => 'UNIQUEIDENTIFIER',
            ColumnType::Binary => 'VARBINARY(MAX)',
        };
    }

    protected function inlinePrimaryKeyColumn(?PrimaryKey $primary, array $columns): ?Column
    {
        return null;
    }

    protected function unsigned(Column $column): string
    {
        return '';
    }

    protected function inlinesIndexes(): bool
    {
        return true;
    }

    protected function autoIncrement(Column $column, bool $inlinePrimaryKey): string
    {
        return $column->autoIncrement ? ' IDENTITY(1,1)' : '';
    }

    protected function stringLiteral(string $value): string
    {
        return "N'" . $this->escape($value) . "'";
    }

    protected function addColumn(Identifier $table, Column $column): string
    {
        return sprintf('ALTER TABLE %s ADD %s', $this->wrap($table), $this->column($column, false));
    }

    protected function renameColumn(Identifier $table, Identifier $from, Identifier $to): string
    {
        return sprintf(
            "EXEC sp_rename %s, %s, N'COLUMN'",
            $this->literal($table->name . '.' . $from->name),
            $this->literal($to->name),
        );
    }

    protected function modifyColumn(Identifier $table, Column $column): string
    {
        if ($column->hasDefault) {
            throw $this->unsupported(
                sprintf(
                    'SQL Server keeps a default in its own constraint, so the column [%s] on table [%s] cannot be '
                    . 'changed and given a default in one step.',
                    $column->name->name,
                    $table->name,
                ),
                $column->name,
                'alter',
            );
        }

        return sprintf(
            'ALTER TABLE %s ALTER COLUMN %s %s %s',
            $this->wrap($table),
            $this->wrap($column->name),
            $this->type($column),
            $column->nullable ? 'NULL' : 'NOT NULL',
        );
    }
}
