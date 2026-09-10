<?php

declare(strict_types=1);

namespace Dirthara\Schema\Constraint;

use Dirthara\Schema\Identifier;

/**
 * A rule the database enforces on a table, as opposed to an index, which is
 * only a way to reach rows faster.
 *
 * Every constraint is named, because dropping one later needs a name and a
 * caller who did not supply one still has to be able to.
 */
interface Constraint
{
    public Identifier $name { get; }
}
