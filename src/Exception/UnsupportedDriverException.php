<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exception;

use RuntimeException;

use function sprintf;

final class UnsupportedDriverException extends RuntimeException implements SchemaException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context)
    {
        parent::__construct($message);

        $this->context = $context;
    }

    public static function unregistered(string $driver): self
    {
        return new self(
            message: sprintf('No schema grammar has been registered for driver [%s].', self::printable($driver)),
            context: ['driver' => $driver],
        );
    }

    public static function refused(string $reason, string $driver, string $operation, string $subject): self
    {
        return new self(message: $reason, context: [
            'driver' => $driver,
            'operation' => $operation,
            'subject' => $subject,
        ]);
    }
}
