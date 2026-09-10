<?php

declare(strict_types=1);

namespace Dirthara\Schema\Constraint;

enum ReferentialAction: string
{
    case Cascade = 'cascade';
    case Restrict = 'restrict';
    case SetNull = 'set_null';
    case SetDefault = 'set_default';
    case NoAction = 'no_action';
}
