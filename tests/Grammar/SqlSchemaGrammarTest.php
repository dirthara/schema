<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Grammar;

use Dirthara\Schema\Table;
use Dirthara\Schema\Identifier;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Tests\Doubles\UnknownConstraint;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Tests\Doubles\StandardSchemaGrammar;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;

final class SqlSchemaGrammarTest extends TestCase
{
    private StandardSchemaGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new StandardSchemaGrammar();
    }

    /**
     * @return list<string>
     */
    private function alter(Table $table): array
    {
        return $this->grammar->compileAlter($table)->queries;
    }

    #[Test]
    public function it_declares_a_primary_key_as_a_table_constraint(): void
    {
        $table = new Table('users');
        $table->id();

        self::assertSame(
            [
                'CREATE TABLE "users" ("id" BIG_INTEGER UNSIGNED NOT NULL, '
                    . 'CONSTRAINT "users_primary" PRIMARY KEY ("id"))',
            ],
            $this->grammar->compileCreate($table, false)->queries,
        );
    }

    #[Test]
    public function it_adds_a_constraint_with_alter_table(): void
    {
        $table = new Table('users');
        $table->unique('email');

        self::assertSame(
            ['ALTER TABLE "users" ADD CONSTRAINT "users_email_unique" UNIQUE ("email")'],
            $this->alter($table),
        );
    }

    #[Test]
    public function it_adds_a_primary_key_with_alter_table(): void
    {
        $table = new Table('users');
        $table->primary('id');

        self::assertSame(
            ['ALTER TABLE "users" ADD CONSTRAINT "users_primary" PRIMARY KEY ("id")'],
            $this->alter($table),
        );
    }

    #[Test]
    public function it_drops_a_constraint_with_alter_table(): void
    {
        $table = new Table('users');
        $table->dropConstraint('users_email_unique');

        self::assertSame(['ALTER TABLE "users" DROP CONSTRAINT "users_email_unique"'], $this->alter($table));
    }

    #[Test]
    public function it_names_the_table_when_dropping_an_index(): void
    {
        $table = new Table('users');
        $table->dropIndex('users_name_index');

        self::assertSame(['DROP INDEX "users_name_index" ON "users"'], $this->alter($table));
    }

    #[Test]
    public function it_modifies_a_column(): void
    {
        $table = new Table('users');
        $table->string('email', 320)->change();

        self::assertSame(['ALTER TABLE "users" ALTER COLUMN "email" STRING NOT NULL'], $this->alter($table));
    }

    #[Test]
    public function it_refuses_a_constraint_it_has_no_clause_for(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('constraint cannot be compiled');

        $this->grammar->compileConstraint(new UnknownConstraint(new Identifier('odd')));
    }

    #[Test]
    public function it_reports_the_constraint_it_could_not_compile(): void
    {
        try {
            $this->grammar->compileConstraint(new UnknownConstraint(new Identifier('odd')));
        } catch (UnsupportedDriverException $exception) {
            self::assertSame(
                ['driver' => 'mysql', 'operation' => 'constraint', 'subject' => 'odd'],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_refuses_a_default_containing_a_null_byte(): void
    {
        $table = new Table('users');
        $table->string('status')->default("pending\0truncated");

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('A default value cannot contain a null byte.');

        $this->grammar->compileCreate($table, false);
    }

    #[Test]
    public function it_reports_the_driver_that_refused_the_null_byte(): void
    {
        $table = new Table('users');
        $table->string('status')->default("pending\0truncated");

        try {
            $this->grammar->compileCreate($table, false);
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['driver' => 'mysql'], $exception->getContext());
        }
    }

    #[Test]
    public function it_compiles_a_has_table_query(): void
    {
        self::assertSame(["SELECT 'users'"], $this->grammar->compileHasTable('users')->queries);
    }
}
