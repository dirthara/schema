<?php

declare(strict_types=1);

namespace Dirthara\Schema\Index;

use Dirthara\Schema\Identifier;

/**
 * A plain index. Uniqueness is a constraint rather than an index here, even
 * where a database happens to implement one with the other.
 */
final readonly class Index
{
    /**
     * @param list<Identifier> $columns
     */
    public function __construct(
        public Identifier $name,
        public array $columns,
    ) {}
}
