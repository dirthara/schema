<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exceptions;

use Dirthara\Database\Exceptions\DatabaseException;

final class SchemaConnectionException extends SchemaException
{
    public static function fromDatabaseException(DatabaseException $exception): self
    {
        return new self(message: $exception->getMessage(), code: (int) $exception->getCode(), previous: $exception);
    }
}
