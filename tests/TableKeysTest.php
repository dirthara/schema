<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests;

use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Change\Change;
use Dirthara\Schema\Change\AddIndex;
use Dirthara\Schema\Change\AddColumn;
use Dirthara\Schema\Change\DropIndex;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Change\AddConstraint;
use Dirthara\Schema\Change\DropConstraint;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Schema\Constraint\UniqueConstraint;
use Dirthara\Schema\Constraint\ReferentialAction;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

final class TableKeysTest extends TestCase
{
    private function table(string $name = 'users'): Table
    {
        return new Table($name);
    }

    /**
     * @return list<string>
     */
    private function kinds(Table $table): array
    {
        return array_map(static fn(Change $change): string => $change::class, $table->changes());
    }

    #[Test]
    public function it_names_an_index_after_the_table_and_its_column(): void
    {
        self::assertSame('users_name_index', $this->table()->index('name')->name->name);
    }

    #[Test]
    public function it_names_a_composite_index_after_every_column(): void
    {
        self::assertSame('users_last_first_index', $this->table()->index(['last', 'first'])->name->name);
    }

    #[Test]
    public function it_uses_the_index_name_it_was_given(): void
    {
        self::assertSame('by_name', $this->table()->index('name', 'by_name')->name->name);
    }

    #[Test]
    public function it_records_an_index(): void
    {
        $table = $this->table();

        $index = $table->index('name');

        $changes = $table->changes();

        self::assertInstanceOf(AddIndex::class, $changes[0]);
        self::assertSame($index, $changes[0]->index);
    }

    #[Test]
    public function it_names_a_unique_constraint_after_the_table_and_its_column(): void
    {
        self::assertSame('users_email_unique', $this->table()->unique('email')->name->name);
    }

    #[Test]
    public function it_records_a_unique_constraint(): void
    {
        $table = $this->table();

        $constraint = $table->unique('email');

        $changes = $table->changes();

        self::assertInstanceOf(AddConstraint::class, $changes[0]);
        self::assertSame($constraint, $changes[0]->constraint);
    }

    #[Test]
    public function it_names_a_primary_key_after_the_table_alone(): void
    {
        self::assertSame('users_primary', $this->table()->primary(['tenant_id', 'id'])->name->name);
    }

    #[Test]
    public function it_uses_the_primary_key_name_it_was_given(): void
    {
        self::assertSame('users_pkey', $this->table()->primary('id', 'users_pkey')->name->name);
    }

    #[Test]
    public function it_records_a_primary_key_over_several_columns(): void
    {
        $key = $this->table()->primary(['tenant_id', 'id']);

        self::assertSame(['tenant_id', 'id'], array_map(static fn($c): string => $c->name, $key->columns));
    }

    #[Test]
    public function it_names_a_foreign_key_after_the_table_and_its_column(): void
    {
        self::assertSame('users_team_id_foreign', $this->table()->foreign('team_id')->name->name);
    }

    #[Test]
    public function it_records_a_foreign_key_with_its_target(): void
    {
        $table = $this->table();

        $key = $table->foreign('team_id')->references('id')->on('teams')->onDelete(ReferentialAction::Cascade);

        $changes = $table->changes();

        self::assertInstanceOf(AddConstraint::class, $changes[0]);
        self::assertSame($key, $changes[0]->constraint);
        self::assertSame('teams', $key->on?->name);
        self::assertSame(ReferentialAction::Cascade, $key->onDelete);
    }

    #[Test]
    public function it_rejects_a_key_over_no_columns(): void
    {
        $this->expectException(InvalidTableDefinitionException::class);
        $this->expectExceptionMessage('A key on table [users] needs at least one column.');

        $this->table()->index([]);
    }

    #[Test]
    public function it_rejects_a_key_column_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->table()->unique('e mail');
    }

    #[Test]
    public function it_rejects_a_key_name_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->table()->index('name', 'by name');
    }

    #[Test]
    public function it_records_a_dropped_index(): void
    {
        $table = $this->table();

        $table->dropIndex('users_name_index');

        $changes = $table->changes();

        self::assertInstanceOf(DropIndex::class, $changes[0]);
        self::assertSame('users_name_index', $changes[0]->index->name);
    }

    #[Test]
    public function it_records_several_dropped_indexes_at_once(): void
    {
        $table = $this->table();

        $table->dropIndex('one_index', 'two_index');

        self::assertSame([DropIndex::class, DropIndex::class], $this->kinds($table));
    }

    #[Test]
    public function it_records_a_dropped_constraint(): void
    {
        $table = $this->table();

        $table->dropConstraint('users_email_unique');

        $changes = $table->changes();

        self::assertInstanceOf(DropConstraint::class, $changes[0]);
        self::assertSame('users_email_unique', $changes[0]->constraint->name);
    }

    #[Test]
    public function it_drops_the_primary_key_by_its_generated_name(): void
    {
        $table = $this->table();

        $table->dropPrimary();

        $changes = $table->changes();

        self::assertInstanceOf(DropConstraint::class, $changes[0]);
        self::assertSame('users_primary', $changes[0]->constraint->name);
    }

    #[Test]
    public function it_drops_the_primary_key_by_the_name_it_was_given(): void
    {
        $table = $this->table();

        $table->dropPrimary('users_pkey');

        $changes = $table->changes();

        self::assertInstanceOf(DropConstraint::class, $changes[0]);
        self::assertSame('users_pkey', $changes[0]->constraint->name);
    }

    #[Test]
    public function it_turns_a_column_unique_flag_into_a_constraint(): void
    {
        $table = $this->table();

        $table->string('email')->unique();

        $changes = $table->changes();

        self::assertSame([AddColumn::class, AddConstraint::class], $this->kinds($table));
        self::assertInstanceOf(AddConstraint::class, $changes[1]);
        self::assertInstanceOf(UniqueConstraint::class, $changes[1]->constraint);
        self::assertSame('users_email_unique', $changes[1]->constraint->name->name);
    }

    #[Test]
    public function it_turns_a_column_primary_flag_into_a_key(): void
    {
        $table = $this->table();

        $table->id();

        $changes = $table->changes();

        self::assertInstanceOf(AddConstraint::class, $changes[1]);
        self::assertInstanceOf(PrimaryKey::class, $changes[1]->constraint);
        self::assertSame('users_primary', $changes[1]->constraint->name->name);
        self::assertSame(['id'], array_map(static fn($c): string => $c->name, $changes[1]->constraint->columns));
    }

    #[Test]
    public function it_gathers_every_primary_flagged_column_into_one_key(): void
    {
        $table = $this->table();

        $table->bigInteger('tenant_id')->primary();
        $table->bigInteger('id')->primary();

        $changes = $table->changes();

        self::assertSame([AddColumn::class, AddColumn::class, AddConstraint::class], $this->kinds($table));
        self::assertInstanceOf(AddConstraint::class, $changes[2]);
        self::assertInstanceOf(PrimaryKey::class, $changes[2]->constraint);
        self::assertSame(
            ['tenant_id', 'id'],
            array_map(static fn($c): string => $c->name, $changes[2]->constraint->columns),
        );
    }

    #[Test]
    public function it_puts_the_flagged_primary_key_before_the_flagged_unique_constraints(): void
    {
        $table = $this->table();

        $table->id();
        $table->string('email')->unique();

        $changes = $table->changes();

        self::assertInstanceOf(AddConstraint::class, $changes[2]);
        self::assertInstanceOf(AddConstraint::class, $changes[3]);
        self::assertInstanceOf(PrimaryKey::class, $changes[2]->constraint);
        self::assertInstanceOf(UniqueConstraint::class, $changes[3]->constraint);
    }

    #[Test]
    public function it_keeps_an_explicit_key_where_it_was_asked_for(): void
    {
        $table = $this->table();

        $table->string('email');
        $table->unique('email');
        $table->string('name');

        self::assertSame([AddColumn::class, AddConstraint::class, AddColumn::class], $this->kinds($table));
    }

    #[Test]
    public function it_rejects_a_second_primary_key(): void
    {
        $table = $this->table();

        $table->id();
        $table->primary('id', 'another_primary');

        $this->expectException(InvalidTableDefinitionException::class);
        $this->expectExceptionMessage('Table [users] defines 2 primary keys, and a table has one.');

        $table->changes();
    }

    #[Test]
    public function it_reports_how_many_primary_keys_it_found(): void
    {
        $table = $this->table();

        $table->id();
        $table->primary('id', 'another_primary');

        try {
            $table->changes();
        } catch (InvalidTableDefinitionException $exception) {
            self::assertSame(['table' => 'users', 'primary_keys' => 2], $exception->getContext());
        }
    }

    #[Test]
    public function it_rejects_two_keys_with_the_same_name(): void
    {
        $table = $this->table();

        $table->index('name');
        $table->index('name');

        $this->expectException(InvalidTableDefinitionException::class);
        $this->expectExceptionMessage('Table [users] defines [users_name_index] more than once.');

        $table->changes();
    }

    #[Test]
    public function it_reports_the_key_it_found_twice(): void
    {
        $table = $this->table();

        $table->unique('email');
        $table->string('email')->unique();

        try {
            $table->changes();
        } catch (InvalidTableDefinitionException $exception) {
            self::assertSame(['table' => 'users', 'key' => 'users_email_unique'], $exception->getContext());
        }
    }

    #[Test]
    public function it_allows_an_index_and_a_constraint_over_the_same_column(): void
    {
        $table = $this->table();

        $table->index('email');
        $table->unique('email');

        self::assertSame([AddIndex::class, AddConstraint::class], $this->kinds($table));
    }

    #[Test]
    public function it_rejects_a_foreign_key_that_never_got_a_target(): void
    {
        $table = $this->table();

        $table->foreign('team_id');

        $this->expectException(InvalidTableDefinitionException::class);
        $this->expectExceptionMessage('does not say which table it references');

        $table->changes();
    }

    #[Test]
    public function it_rejects_a_foreign_key_whose_column_counts_do_not_match(): void
    {
        $table = $this->table();

        $table->foreign('team_id')->on('teams')->references('id', 'tenant_id');

        $this->expectException(InvalidTableDefinitionException::class);
        $this->expectExceptionMessage('has 1 column(s) but references 2');

        $table->changes();
    }

    #[Test]
    public function it_reports_the_incomplete_foreign_key(): void
    {
        $table = $this->table();

        $table->foreign('team_id');

        try {
            $table->changes();
        } catch (InvalidTableDefinitionException $exception) {
            self::assertSame(['table' => 'users', 'key' => 'users_team_id_foreign'], $exception->getContext());
        }
    }

    #[Test]
    public function it_accepts_a_complete_definition(): void
    {
        $table = $this->table('orders');

        $table->id();
        $table->bigInteger('user_id');
        $table->string('reference')->unique();
        $table->index('user_id');
        $table->foreign('user_id')->references('id')->on('users')->onDelete(ReferentialAction::Cascade);

        self::assertSame(
            [
                AddColumn::class,
                AddColumn::class,
                AddColumn::class,
                AddIndex::class,
                AddConstraint::class,
                AddConstraint::class,
                AddConstraint::class,
            ],
            $this->kinds($table),
        );
    }
}
