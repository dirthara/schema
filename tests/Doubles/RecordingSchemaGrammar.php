<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Doubles;

use LogicException;
use Dirthara\Schema\Table;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Grammar\SchemaGrammar;

final class RecordingSchemaGrammar implements SchemaGrammar
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $calls = [];

    /**
     * @param list<string> $queries
     */
    public function __construct(
        public array $queries = ['SELECT 1'],
    ) {}

    public function compileCreate(Table $definition, bool $ifNotExists): CompiledSchema
    {
        return $this->record([
            'method' => 'compileCreate',
            'table' => $definition->name->name,
            'definition' => $definition,
            'ifNotExists' => $ifNotExists,
        ]);
    }

    public function compileAlter(Table $definition): CompiledSchema
    {
        return $this->record([
            'method' => 'compileAlter',
            'table' => $definition->name->name,
            'definition' => $definition,
        ]);
    }

    public function compileDrop(string $table, bool $ifExists): CompiledSchema
    {
        return $this->record(['method' => 'compileDrop', 'table' => $table, 'ifExists' => $ifExists]);
    }

    public function compileRename(string $from, string $to): CompiledSchema
    {
        return $this->record(['method' => 'compileRename', 'from' => $from, 'to' => $to]);
    }

    public function compileHasTable(string $table): CompiledSchema
    {
        return $this->record(['method' => 'compileHasTable', 'table' => $table]);
    }

    /**
     * @return array<string, mixed>
     */
    public function lastCall(): array
    {
        return $this->calls[count($this->calls) - 1] ?? throw new LogicException('Nothing has been compiled yet.');
    }

    /**
     * @param array<string, mixed> $call
     */
    private function record(array $call): CompiledSchema
    {
        $this->calls[] = $call;

        return new CompiledSchema($this->queries);
    }
}
