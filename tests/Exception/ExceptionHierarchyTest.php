<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Exception;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Exception\SchemaException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Exception\InvalidSchemaException;
use Dirthara\Schema\Exception\UnsupportedDriverException;
use Dirthara\Schema\Exception\SchemaIntrospectionException;
use Dirthara\Schema\Exception\InvalidTableDefinitionException;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return iterable<string, array{SchemaException, class-string}>
     */
    public static function exceptions(): iterable
    {
        yield 'invalid schema' => [InvalidSchemaException::emptyIdentifier(), InvalidArgumentException::class];
        yield 'invalid table definition' => [
            InvalidTableDefinitionException::keyWithoutColumns('users'),
            InvalidArgumentException::class,
        ];
        yield 'unsupported driver' => [UnsupportedDriverException::unregistered('oracle'), RuntimeException::class];
        yield 'introspection' => [SchemaIntrospectionException::nothingCompiled([]), RuntimeException::class];
    }

    /**
     * @param class-string $parent
     */
    #[Test]
    #[DataProvider('exceptions')]
    public function it_extends_the_spl_exception_that_fits_the_failure(SchemaException $exception, string $parent): void
    {
        self::assertInstanceOf($parent, $exception);
    }

    #[Test]
    public function it_escapes_control_characters_in_a_rejected_identifier(): void
    {
        $exception = InvalidSchemaException::invalidIdentifier("users\nforged log line");

        self::assertSame(
            'The identifier [users\\nforged log line] may only contain letters, digits and underscores, and cannot '
            . 'start with a digit.',
            $exception->getMessage(),
        );
        self::assertSame(['identifier' => "users\nforged log line"], $exception->context);
    }

    #[Test]
    public function it_escapes_control_characters_in_a_driver_name(): void
    {
        self::assertSame(
            'No schema grammar has been registered for driver [or\\racle].',
            UnsupportedDriverException::unregistered("or\racle")->getMessage(),
        );
        self::assertSame(
            'A schema grammar is already registered for driver [or\\racle].',
            InvalidSchemaException::duplicateGrammar("or\racle")->getMessage(),
        );
    }
}
