<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exceptions;

use Dirthara\Database\Connection\Exceptions\QueryException;

final class SchemaExecutionException extends SchemaException
{
    public static function fromQueryException(QueryException $exception): self
    {
        return new self(message: $exception->getMessage(), code: (int) $exception->getCode(), previous: $exception);
    }
}
