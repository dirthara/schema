<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exception;

use Throwable;
use RuntimeException;
use Dirthara\Database\Exception\QueryException;

use function sprintf;

final class SchemaIntrospectionException extends RuntimeException implements SchemaException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);

        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function queryFailed(QueryException $previous, array $context): self
    {
        return new self(message: $previous->getMessage(), context: $context, previous: $previous);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function nothingCompiled(array $context): self
    {
        return new self(message: 'The schema grammar compiled no query to introspect with.', context: $context);
    }

    public static function unreadableColumn(string $driver, string $column): self
    {
        return new self(
            message: sprintf('The introspected table has no usable [%s].', self::printable($column)),
            context: ['driver' => $driver, 'column' => $column],
        );
    }
}
