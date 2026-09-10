<?php

declare(strict_types=1);

namespace Dirthara\Schema\Sql;

use function implode;

final class CompiledSchema
{
    /**
     * @param list<string> $queries
     */
    public function __construct(
        public array $queries,
    ) {}

    public function addQuery(string $query): void
    {
        $this->queries[] = $query;
    }

    public function toSql(): string
    {
        return implode(';', $this->queries);
    }
}
