<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Doubles;

use Dirthara\Schema\Identifier;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Constraint\Constraint;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Schema\Grammar\SqlSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;

use function sprintf;
use function strtoupper;

final class StandardSchemaGrammar extends SqlSchemaGrammar
{
    public function compileHasTable(string $table): CompiledSchema
    {
        return new CompiledSchema([sprintf('SELECT %s', $this->literal($this->identifier($table)->name))]);
    }

    public function compileConstraint(Constraint $constraint): string
    {
        return $this->constraint($constraint);
    }

    protected function driver(): DriverName
    {
        return DriverName::MySql;
    }

    protected function wrap(Identifier $identifier): string
    {
        return '"' . $identifier->name . '"';
    }

    protected function type(Column $column): string
    {
        return strtoupper($column->type->value);
    }

    protected function inlinePrimaryKeyColumn(?PrimaryKey $primary, array $columns): ?Column
    {
        return null;
    }

    protected function modifyColumn(Identifier $table, Column $column): string
    {
        return sprintf('ALTER TABLE %s ALTER COLUMN %s', $this->wrap($table), $this->column($column, false));
    }
}
