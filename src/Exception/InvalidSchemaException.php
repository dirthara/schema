<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exception;

use InvalidArgumentException;

use function sprintf;

final class InvalidSchemaException extends InvalidArgumentException implements SchemaException
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

    public static function emptyIdentifier(): self
    {
        return new self(message: 'An identifier cannot be empty.', context: []);
    }

    public static function invalidIdentifier(string $identifier): self
    {
        return new self(
            message: sprintf(
                'The identifier [%s] may only contain letters, digits and underscores, and cannot start with a digit.',
                self::printable($identifier),
            ),
            context: ['identifier' => $identifier],
        );
    }

    public static function invalidLength(string $column, int $length): self
    {
        return new self(
            message: sprintf('The length of column [%s] must be at least 1, got %d.', $column, $length),
            context: ['column' => $column, 'length' => $length],
        );
    }

    public static function invalidPrecision(string $column, int $precision): self
    {
        return new self(
            message: sprintf('The precision of column [%s] must be at least 1, got %d.', $column, $precision),
            context: ['column' => $column, 'precision' => $precision],
        );
    }

    public static function invalidScale(string $column, int $precision, int $scale): self
    {
        return new self(
            message: sprintf(
                'The scale of column [%s] must be between 0 and its precision of %d, got %d.',
                $column,
                $precision,
                $scale,
            ),
            context: ['column' => $column, 'precision' => $precision, 'scale' => $scale],
        );
    }

    public static function nullByteInDefault(string $driver): self
    {
        return new self(message: 'A default value cannot contain a null byte.', context: ['driver' => $driver]);
    }

    public static function duplicateGrammar(string $driver): self
    {
        return new self(
            message: sprintf('A schema grammar is already registered for driver [%s].', self::printable($driver)),
            context: ['driver' => $driver],
        );
    }
}
