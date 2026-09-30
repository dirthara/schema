<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Fixtures;

use RuntimeException;
use Dirthara\Schema\Exception\SchemaException;
use Dirthara\Schema\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements SchemaException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
