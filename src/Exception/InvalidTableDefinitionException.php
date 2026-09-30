<?php

declare(strict_types=1);

namespace Dirthara\Schema\Exception;

use InvalidArgumentException;

use function sprintf;

final class InvalidTableDefinitionException extends InvalidArgumentException implements SchemaException
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

    public static function duplicateColumn(string $table, string $column): self
    {
        return new self(
            message: sprintf('The column [%s] is defined more than once on table [%s].', $column, $table),
            context: ['table' => $table, 'column' => $column],
        );
    }

    public static function tooManyPrimaryKeys(string $table, int $primaryKeys): self
    {
        return new self(
            message: sprintf('Table [%s] defines %d primary keys, and a table has one.', $table, $primaryKeys),
            context: ['table' => $table, 'primary_keys' => $primaryKeys],
        );
    }

    public static function duplicateKey(string $table, string $key): self
    {
        return new self(message: sprintf('Table [%s] defines [%s] more than once.', $table, $key), context: [
            'table' => $table,
            'key' => $key,
        ]);
    }

    public static function incompleteForeignKey(string $table, string $key, string $reason): self
    {
        return new self(message: $reason, context: ['table' => $table, 'key' => $key]);
    }

    public static function keyWithoutColumns(string $table): self
    {
        return new self(message: sprintf('A key on table [%s] needs at least one column.', $table), context: [
            'table' => $table,
        ]);
    }
}
