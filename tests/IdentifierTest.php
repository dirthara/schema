<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests;

use Dirthara\Schema\Identifier;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Exceptions\InvalidSchemaException;

final class IdentifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedNames(): iterable
    {
        yield 'a word' => ['users'];
        yield 'an underscore separator' => ['created_at'];
        yield 'a leading underscore' => ['_internal'];
        yield 'trailing digits' => ['address2'];
        yield 'mixed case' => ['createdAt'];
        yield 'a single letter' => ['x'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedNames(): iterable
    {
        yield 'a double quote' => ['use"rs'];
        yield 'a single quote' => ["use'rs"];
        yield 'a backtick' => ['use`rs'];
        yield 'a bracket' => ['[users]'];
        yield 'a semicolon' => ['users; DROP TABLE users'];
        yield 'a space' => ['user name'];
        yield 'a qualifying dot' => ['public.users'];
        yield 'a leading digit' => ['1st_place'];
        yield 'a hyphen' => ['created-at'];
        yield 'a parenthesis' => ['count(*)'];
        yield 'a newline' => ["users\nDROP"];
    }

    #[Test]
    #[DataProvider('acceptedNames')]
    public function it_accepts_a_plain_name(string $name): void
    {
        self::assertSame($name, new Identifier($name)->name);
    }

    #[Test]
    #[DataProvider('rejectedNames')]
    public function it_rejects_anything_a_grammar_would_have_to_escape(string $name): void
    {
        $this->expectException(InvalidSchemaException::class);

        new Identifier($name);
    }

    #[Test]
    public function it_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('An identifier cannot be empty.');

        new Identifier('');
    }

    #[Test]
    public function it_rejects_a_name_that_is_only_whitespace(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('An identifier cannot be empty.');

        new Identifier('   ');
    }

    #[Test]
    public function it_reports_the_name_it_rejected(): void
    {
        try {
            new Identifier('user name');
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['identifier' => 'user name'], $exception->getContext());
            self::assertStringContainsString('[user name]', $exception->getMessage());
        }
    }
}
