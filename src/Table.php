<?php

declare(strict_types=1);

namespace Dirthara\Schema;

final readonly class Table
{
    public function __construct(
        public string $name,
    ) {}
}
