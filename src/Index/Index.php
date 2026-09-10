<?php

declare(strict_types=1);

namespace Dirthara\Schema\Index;

use Dirthara\Schema\Identifier;

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
