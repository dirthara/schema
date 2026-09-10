<?php

declare(strict_types=1);

namespace Dirthara\Schema\Change;

use Dirthara\Schema\Constraint\Constraint;

final readonly class AddConstraint implements Change
{
    public function __construct(
        public Constraint $constraint,
    ) {}
}
