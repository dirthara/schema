<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Doubles;

use LogicException;
use Dirthara\Schema\Table;
use Dirthara\Schema\Sql\CompiledSchema;
use Dirthara\Schema\Grammar\SchemaGrammar;

use function count;
use function is_string;

final class RecordingSchemaGrammar implements SchemaGrammar
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $calls = [];

    /**
     * @param list<string> $queries
     * @param list<string> $tableQueries
     * @param list<string> $cleanup
     */
    public function __construct(
        public array $queries = ['SELECT 1'],
        public array $tableQueries = ["SELECT 'users' AS name"],
        public array $cleanup = [],
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

    public function compileTables(): CompiledSchema
    {
        $this->calls[] = ['method' => 'compileTables'];

        return new CompiledSchema($this->tableQueries);
    }

    public function compileDropAll(array $tables): CompiledSchema
    {
        $this->calls[] = ['method' => 'compileDropAll', 'tables' => $tables];

        return new CompiledSchema($this->queries, $this->cleanup);
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        $methods = [];

        foreach ($this->calls as $call) {
            $methods[] = is_string($call['method'] ?? null) ? $call['method'] : '';
        }

        return $methods;
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
