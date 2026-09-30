<?php

declare(strict_types=1);

namespace Dirthara\Schema\Grammar;

use Dirthara\Schema\Identifier;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Column\ColumnType;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Exceptions\SchemaIntrospectionException;

use function implode;
use function sprintf;
use function array_map;
use function str_replace;

class MySqlSchemaGrammar extends SqlSchemaGrammar
{
    public function compileHasTable(string $table): CompiledSchema
    {
        return new CompiledSchema([
            sprintf(
                'SELECT `TABLE_NAME` FROM `information_schema`.`TABLES` '
                . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
                $this->literal($this->identifier($table)->name),
            ),
        ]);
    }

    public function compileTables(): CompiledSchema
    {
        return new CompiledSchema([
            sprintf(
                'SELECT `TABLE_NAME` AS `name` FROM `information_schema`.`TABLES` '
                . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_TYPE` = %s ORDER BY `TABLE_NAME`',
                $this->literal('BASE TABLE'),
            ),
        ]);
    }

    /**
     * @throws SchemaIntrospectionException
     */
    public function compileDropAll(array $tables): CompiledSchema
    {
        return new CompiledSchema([
            sprintf('DROP TABLE IF EXISTS %s', implode(', ', array_map(
                fn(array $table): string => $this->quote($this->introspected($table, 'name')),
                $tables,
            ))),
        ]);
    }

    protected function driver(): DriverName
    {
        return DriverName::MySql;
    }

    protected function wrap(Identifier $identifier): string
    {
        return $this->quote($identifier->name);
    }

    protected function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    protected function type(Column $column): string
    {
        return match ($column->type) {
            ColumnType::Boolean => 'TINYINT(1)',
            ColumnType::TinyInteger => 'TINYINT',
            ColumnType::SmallInteger => 'SMALLINT',
            ColumnType::Integer => 'INT',
            ColumnType::BigInteger => 'BIGINT',
            ColumnType::Decimal => sprintf('DECIMAL(%d, %d)', $column->precision ?? 8, $column->scale ?? 2),
            ColumnType::Float => 'FLOAT',
            ColumnType::Double => 'DOUBLE',
            ColumnType::Char => sprintf('CHAR(%d)', $column->length ?? 255),
            ColumnType::String => sprintf('VARCHAR(%d)', $column->length ?? 255),
            ColumnType::Text => 'TEXT',
            ColumnType::Date => 'DATE',
            ColumnType::Time => 'TIME',
            ColumnType::DateTime => 'DATETIME',
            ColumnType::Timestamp => 'TIMESTAMP',
            ColumnType::Uuid => 'CHAR(36)',
            ColumnType::Json => 'JSON',
            ColumnType::Binary => 'BLOB',
        };
    }

    protected function inlinePrimaryKeyColumn(?PrimaryKey $primary, array $columns): ?Column
    {
        return null;
    }

    protected function inlinesIndexes(): bool
    {
        return true;
    }

    protected function autoIncrement(Column $column, bool $inlinePrimaryKey): string
    {
        return $column->autoIncrement ? ' AUTO_INCREMENT' : '';
    }

    protected function modifyColumn(Identifier $table, Column $column): string
    {
        return sprintf('ALTER TABLE %s MODIFY COLUMN %s', $this->wrap($table), $this->column($column, false));
    }

    protected function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "''"], $value);
    }
}
