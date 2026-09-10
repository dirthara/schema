<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Grammar;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Grammar\SchemaGrammarResolver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Tests\Doubles\RecordingSchemaGrammar;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;

final class SchemaGrammarResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_a_grammar_registered_by_driver_name(): void
    {
        $grammar = new RecordingSchemaGrammar();

        $resolver = new SchemaGrammarResolver([DriverName::SQLite->value => $grammar]);

        self::assertSame($grammar, $resolver->resolve(DriverName::SQLite));
    }

    #[Test]
    public function it_resolves_a_grammar_registered_by_string(): void
    {
        $grammar = new RecordingSchemaGrammar();

        $resolver = new SchemaGrammarResolver();
        $resolver->register('mysql', $grammar);

        self::assertSame($grammar, $resolver->resolve('mysql'));
    }

    #[Test]
    public function it_registers_a_grammar_for_a_driver_enum(): void
    {
        $grammar = new RecordingSchemaGrammar();

        $resolver = new SchemaGrammarResolver();
        $resolver->register(DriverName::PostgresSql, $grammar);

        self::assertSame($grammar, $resolver->resolve('pgsql'));
    }

    #[Test]
    public function it_rejects_a_second_grammar_for_the_same_driver(): void
    {
        $resolver = new SchemaGrammarResolver([DriverName::SQLite->value => new RecordingSchemaGrammar()]);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('A schema grammar is already registered for driver [sqlite].');

        $resolver->register(DriverName::SQLite, new RecordingSchemaGrammar());
    }

    #[Test]
    public function it_reports_the_driver_it_could_not_resolve(): void
    {
        $resolver = new SchemaGrammarResolver();

        try {
            $resolver->resolve(DriverName::SqlServer);
        } catch (UnsupportedDriverException $exception) {
            self::assertSame('No schema grammar has been registered for driver [sqlsrv].', $exception->getMessage());
            self::assertSame(['driver' => 'sqlsrv'], $exception->getContext());
        }
    }

    #[Test]
    public function it_reports_the_driver_of_a_rejected_duplicate(): void
    {
        $resolver = new SchemaGrammarResolver(['mysql' => new RecordingSchemaGrammar()]);

        try {
            $resolver->register('mysql', new RecordingSchemaGrammar());
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['driver' => 'mysql'], $exception->getContext());
        }
    }
}
