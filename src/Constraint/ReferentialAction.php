<?php

declare(strict_types=1);

namespace Dirthara\Schema\Constraint;

/**
 * What the database does to a referencing row when the row it points at goes
 * away or changes key.
 *
 * There is no case for "whatever the database does by default": leaving the
 * action unset says that, and says it without claiming to know which of these
 * the default happens to be.
 */
enum ReferentialAction: string
{
    case Cascade = 'cascade';
    case Restrict = 'restrict';
    case SetNull = 'set_null';
    case SetDefault = 'set_default';
    case NoAction = 'no_action';
}
