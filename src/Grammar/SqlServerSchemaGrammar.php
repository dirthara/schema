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
use Dirthara\Schema\Exception\SchemaIntrospectionException;

use function sprintf;
use function str_replace;
use function array_values;

class SqlServerSchemaGrammar extends SqlSchemaGrammar
{
    public function compileCreate(Table $definition, bool $ifNotExists): CompiledSchema
    {
        $queries = parent::compileCreate($definition, false)->queries;

        if (!$ifNotExists) {
            return new CompiledSchema($queries);
        }

        $queries[0] = sprintf(
            "IF OBJECT_ID(%s, N'U') IS NULL %s",
            $this->literal($this->wrap($definition->name)),
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

    public function compileTables(): CompiledSchema
    {
        return new CompiledSchema([
            'SELECT SCHEMA_NAME([t].[schema_id]) AS [schema], [t].[name] AS [name], [fk].[name] AS [foreign_key] '
                . 'FROM [sys].[tables] AS [t] '
                . 'LEFT JOIN [sys].[foreign_keys] AS [fk] ON [fk].[parent_object_id] = [t].[object_id] '
                . 'WHERE [t].[schema_id] = SCHEMA_ID() AND [t].[is_ms_shipped] = 0 '
                . 'ORDER BY [t].[name], [fk].[name]',
        ]);
    }

    /**
     * @throws SchemaIntrospectionException
     */
    public function compileDropAll(array $tables): CompiledSchema
    {
        $keys = [];
        $drops = [];

        foreach ($tables as $table) {
            $name =
                $this->quote($this->introspected($table, 'schema'))
                . '.'
                . $this->quote($this->introspected($table, 'name'));

            if (($table['foreign_key'] ?? null) !== null) {
                $keys[] = sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $name,
                    $this->quote($this->introspected($table, 'foreign_key')),
                );
            }

            $drops[$name] = sprintf('DROP TABLE IF EXISTS %s', $name);
        }

        return new CompiledSchema([...$keys, ...array_values($drops)]);
    }

    protected function driver(): DriverName
    {
        return DriverName::SqlServer;
    }

    protected function wrap(Identifier $identifier): string
    {
        return $this->quote($identifier->name);
    }

    protected function quote(string $name): string
    {
        return '[' . str_replace(']', ']]', $name) . ']';
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
            ColumnType::Time => $this->fractionalSeconds('TIME', $column),
            ColumnType::DateTime, ColumnType::Timestamp => $this->fractionalSeconds('DATETIME2', $column),
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
