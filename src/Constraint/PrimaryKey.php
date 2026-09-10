<?php

declare(strict_types=1);

namespace Dirthara\Schema\Constraint;

use Dirthara\Schema\Identifier;

final readonly class PrimaryKey implements Constraint
{
    /**
     * @param list<Identifier> $columns
     */
    public function __construct(
        public Identifier $name,
        public array $columns,
    ) {}
}
