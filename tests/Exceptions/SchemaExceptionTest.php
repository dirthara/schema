<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Exceptions;

use Exception;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Exceptions\SchemaException;

final class SchemaExceptionTest extends TestCase
{
    #[Test]
    public function it_is_an_exception(): void
    {
        self::assertInstanceOf(Exception::class, new SchemaException());
    }

    #[Test]
    public function it_carries_a_message_a_code_and_a_previous_exception(): void
    {
        $previous = new RuntimeException('The driver failed.');

        $exception = new SchemaException('The table could not be created.', 42, $previous);

        self::assertSame('The table could not be created.', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function it_defaults_to_an_empty_context(): void
    {
        self::assertSame([], new SchemaException()->getContext());
    }

    #[Test]
    public function it_carries_the_context_it_was_given(): void
    {
        $exception = new SchemaException(context: ['table' => 'users']);

        self::assertSame(['table' => 'users'], $exception->getContext());
    }

    #[Test]
    public function it_adds_context_to_what_it_already_carries(): void
    {
        $exception = new SchemaException(context: ['table' => 'users']);

        self::assertSame($exception, $exception->addContext(['operation' => 'create']));
        self::assertSame(['table' => 'users', 'operation' => 'create'], $exception->getContext());
    }

    #[Test]
    public function it_overwrites_a_context_key_it_already_carries(): void
    {
        $exception = new SchemaException(context: ['table' => 'users']);

        $exception->addContext(['table' => 'orders']);

        self::assertSame(['table' => 'orders'], $exception->getContext());
    }
}
