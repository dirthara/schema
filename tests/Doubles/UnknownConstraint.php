<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Doubles;

use Dirthara\Schema\Identifier;
use Dirthara\Schema\Constraint\Constraint;

final readonly class UnknownConstraint implements Constraint
{
    public function __construct(
        public Identifier $name,
    ) {}
}
