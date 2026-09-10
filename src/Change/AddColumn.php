<?php

declare(strict_types=1);

namespace Dirthara\Schema\Change;

use Dirthara\Schema\Column\Column;

final readonly class AddColumn implements Change
{
    public function __construct(
        public Column $column,
    ) {}
}
