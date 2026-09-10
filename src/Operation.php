<?php

declare(strict_types=1);

namespace Dirthara\Schema;

enum Operation: string
{
    case Create = 'create';
    case CreateIfNotExists = 'create_if_not_exists';
    case Alter = 'alter';
    case Drop = 'drop';
    case DropIfExists = 'drop_if_exists';
    case Rename = 'rename';
    case HasTable = 'has_table';
}
