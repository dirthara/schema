<?php

declare(strict_types=1);

namespace Dirthara\Schema\Grammar;

use Dirthara\Schema\Table;
use Dirthara\Schema\Sql\CompiledSchema;

interface SchemaGrammar
{
    public function compileCreate(string $table, Table $definition, bool $ifNotExists): CompiledSchema;

    public function compileAlter(string $table, Table $definition): CompiledSchema;

    public function compileDrop(string $table, bool $ifExists): CompiledSchema;

    public function compileRename(string $from, string $to): CompiledSchema;

    public function compileHasTable(string $table): CompiledSchema;
}
