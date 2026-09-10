<?php

declare(strict_types=1);

namespace Dirthara\Schema\Constraint;

use Dirthara\Schema\Identifier;

interface Constraint
{
    public Identifier $name { get; }
}
