<?php

declare(strict_types=1);

namespace Dirthara\Schema\Change;

use Dirthara\Schema\Identifier;

final readonly class DropConstraint implements Change
{
    public function __construct(
        public Identifier $constraint,
    ) {}
}
