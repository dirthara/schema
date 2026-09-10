<?php

declare(strict_types=1);

namespace Dirthara\Schema\Column;

enum ColumnType: string
{
    case Boolean = 'boolean';
    case TinyInteger = 'tiny_integer';
    case SmallInteger = 'small_integer';
    case Integer = 'integer';
    case BigInteger = 'big_integer';
    case Decimal = 'decimal';
    case Float = 'float';
    case Double = 'double';
    case Char = 'char';
    case String = 'string';
    case Text = 'text';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'date_time';
    case Timestamp = 'timestamp';
    case Uuid = 'uuid';
    case Json = 'json';
    case Binary = 'binary';
}
