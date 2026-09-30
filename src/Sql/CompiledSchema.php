<?php

declare(strict_types=1);

namespace Dirthara\Schema\Sql;

final class CompiledSchema
{
    /**
     * @param list<string> $queries
     * @param list<string> $cleanup
     */
    public function __construct(
        public array $queries,
        public array $cleanup = [],
    ) {}

    public function addQuery(string $query): void
    {
        $this->queries[] = $query;
    }
}
