<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exception;

use RuntimeException;
use Dirthara\Database\Exception\QueryException;

final class SchemaExecutionException extends RuntimeException implements SchemaException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context, QueryException $previous)
    {
        parent::__construct($message, previous: $previous);

        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function statementFailed(QueryException $previous, array $context): self
    {
        return new self(message: $previous->getMessage(), context: $context, previous: $previous);
    }
}
