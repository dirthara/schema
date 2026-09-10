<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests;

use Closure;
use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Change\AddColumn;
use Dirthara\Schema\Change\DropColumn;
use Dirthara\Schema\Column\ColumnType;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Change\ModifyColumn;
use Dirthara\Schema\Change\RenameColumn;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

final class TableTest extends TestCase
{
    private function table(string $name = 'users'): Table
    {
        return new Table($name);
    }

    private function only(Table $table): Column
    {
        $changes = $table->changes();

        self::assertCount(1, $changes);
        self::assertInstanceOf(AddColumn::class, $changes[0]);

        return $changes[0]->column;
    }

    #[Test]
    public function it_validates_its_own_name(): void
    {
        self::assertSame('users', $this->table()->name->name);
    }

    #[Test]
    public function it_rejects_a_name_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->table('user"s');
    }

    #[Test]
    public function it_reports_the_table_whose_name_was_rejected(): void
    {
        try {
            $this->table('user"s');
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['identifier' => 'user"s', 'table' => 'user"s'], $exception->getContext());
        }
    }

    #[Test]
    public function it_starts_with_no_changes(): void
    {
        self::assertSame([], $this->table()->changes());
    }

    /**
     * @return iterable<string, array{Closure(Table): Column, ColumnType}>
     */
    public static function columnTypes(): iterable
    {
        yield 'boolean' => [static fn(Table $table): Column => $table->boolean('value'), ColumnType::Boolean];
        yield 'tinyInteger' => [
            static fn(Table $table): Column => $table->tinyInteger('value'),
            ColumnType::TinyInteger,
        ];
        yield 'smallInteger' => [
            static fn(Table $table): Column => $table->smallInteger('value'),
            ColumnType::SmallInteger,
        ];
        yield 'integer' => [static fn(Table $table): Column => $table->integer('value'), ColumnType::Integer];
        yield 'bigInteger' => [static fn(Table $table): Column => $table->bigInteger('value'), ColumnType::BigInteger];
        yield 'float' => [static fn(Table $table): Column => $table->float('value'), ColumnType::Float];
        yield 'double' => [static fn(Table $table): Column => $table->double('value'), ColumnType::Double];
        yield 'text' => [static fn(Table $table): Column => $table->text('value'), ColumnType::Text];
        yield 'date' => [static fn(Table $table): Column => $table->date('value'), ColumnType::Date];
        yield 'time' => [static fn(Table $table): Column => $table->time('value'), ColumnType::Time];
        yield 'dateTime' => [static fn(Table $table): Column => $table->dateTime('value'), ColumnType::DateTime];
        yield 'timestamp' => [static fn(Table $table): Column => $table->timestamp('value'), ColumnType::Timestamp];
        yield 'uuid' => [static fn(Table $table): Column => $table->uuid('value'), ColumnType::Uuid];
        yield 'json' => [static fn(Table $table): Column => $table->json('value'), ColumnType::Json];
        yield 'binary' => [static fn(Table $table): Column => $table->binary('value'), ColumnType::Binary];
    }

    /**
     * @param Closure(Table): Column $define
     */
    #[Test]
    #[DataProvider('columnTypes')]
    public function it_defines_a_column_of_each_type(Closure $define, ColumnType $type): void
    {
        $table = $this->table();

        $column = $define($table);

        self::assertSame($type, $column->type);
        self::assertSame('value', $column->name->name);
        self::assertSame($column, $this->only($table));
    }

    #[Test]
    public function it_defines_a_column_of_a_type_given_directly(): void
    {
        self::assertSame(ColumnType::Uuid, $this->table()->column('token', ColumnType::Uuid)->type);
    }

    #[Test]
    public function it_defines_an_auto_incrementing_key(): void
    {
        $column = $this->table()->id();

        self::assertSame('id', $column->name->name);
        self::assertSame(ColumnType::BigInteger, $column->type);
        self::assertTrue($column->unsigned);
        self::assertTrue($column->autoIncrement);
        self::assertTrue($column->primary);
    }

    #[Test]
    public function it_names_the_key_whatever_it_was_given(): void
    {
        self::assertSame('uuid', $this->table()->id('uuid')->name->name);
    }

    #[Test]
    public function it_names_a_uuid_column_after_its_type_by_default(): void
    {
        self::assertSame('uuid', $this->table()->uuid()->name->name);
    }

    #[Test]
    public function it_names_a_uuid_column_whatever_it_was_given(): void
    {
        self::assertSame('token', $this->table()->uuid('token')->name->name);
    }

    #[Test]
    public function it_gives_a_string_a_default_length(): void
    {
        self::assertSame(255, $this->table()->string('email')->length);
    }

    #[Test]
    public function it_gives_a_string_the_length_it_was_asked_for(): void
    {
        self::assertSame(64, $this->table()->string('email', 64)->length);
    }

    #[Test]
    public function it_gives_a_char_a_default_length(): void
    {
        self::assertSame(255, $this->table()->char('code')->length);
    }

    #[Test]
    public function it_gives_a_char_the_length_it_was_asked_for(): void
    {
        self::assertSame(2, $this->table()->char('code', 2)->length);
    }

    #[Test]
    public function it_gives_a_decimal_a_default_precision_and_scale(): void
    {
        $column = $this->table()->decimal('amount');

        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
    }

    #[Test]
    public function it_gives_a_decimal_the_precision_and_scale_it_was_asked_for(): void
    {
        $column = $this->table()->decimal('amount', 12, 4);

        self::assertSame(12, $column->precision);
        self::assertSame(4, $column->scale);
    }

    #[Test]
    public function it_defines_the_pair_of_record_timestamps(): void
    {
        $table = $this->table();

        $table->timestamps();

        $changes = $table->changes();

        self::assertCount(2, $changes);
        self::assertInstanceOf(AddColumn::class, $changes[0]);
        self::assertInstanceOf(AddColumn::class, $changes[1]);
        self::assertSame('created_at', $changes[0]->column->name->name);
        self::assertSame('updated_at', $changes[1]->column->name->name);
        self::assertSame(ColumnType::Timestamp, $changes[0]->column->type);
        self::assertTrue($changes[0]->column->nullable);
        self::assertTrue($changes[1]->column->nullable);
    }

    #[Test]
    public function it_names_the_record_timestamps_whatever_it_was_given(): void
    {
        $table = $this->table();

        $table->timestamps('inserted_at', 'touched_at');

        $changes = $table->changes();

        self::assertInstanceOf(AddColumn::class, $changes[0]);
        self::assertInstanceOf(AddColumn::class, $changes[1]);
        self::assertSame('inserted_at', $changes[0]->column->name->name);
        self::assertSame('touched_at', $changes[1]->column->name->name);
        self::assertTrue($changes[0]->column->nullable);
        self::assertTrue($changes[1]->column->nullable);
    }

    #[Test]
    public function it_keeps_a_modifier_chained_after_the_column_was_recorded(): void
    {
        $table = $this->table();

        $table->string('email')->nullable()->default('none');

        $column = $this->only($table);

        self::assertTrue($column->nullable);
        self::assertSame('none', $column->default);
    }

    #[Test]
    public function it_rejects_a_column_name_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->table()->string('e mail');
    }

    #[Test]
    public function it_reports_the_table_a_rejected_column_belongs_to(): void
    {
        try {
            $this->table()->string('e mail');
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['identifier' => 'e mail', 'table' => 'users'], $exception->getContext());
        }
    }

    #[Test]
    public function it_rejects_the_same_column_twice(): void
    {
        $table = $this->table();

        $table->string('email');

        $this->expectException(InvalidTableDefinitionException::class);
        $this->expectExceptionMessage('The column [email] is defined more than once on table [users].');

        $table->text('email');
    }

    #[Test]
    public function it_reports_the_column_it_refused_to_define_twice(): void
    {
        $table = $this->table();

        $table->string('email');

        try {
            $table->string('email');
        } catch (InvalidTableDefinitionException $exception) {
            self::assertSame(['table' => 'users', 'column' => 'email'], $exception->getContext());
        }
    }

    #[Test]
    public function it_records_a_dropped_column(): void
    {
        $table = $this->table();

        $table->dropColumn('legacy_id');

        $changes = $table->changes();

        self::assertInstanceOf(DropColumn::class, $changes[0]);
        self::assertSame('legacy_id', $changes[0]->column->name);
    }

    #[Test]
    public function it_records_several_dropped_columns_at_once(): void
    {
        $table = $this->table();

        $table->dropColumn('one', 'two');

        self::assertCount(2, $table->changes());
    }

    #[Test]
    public function it_rejects_a_dropped_column_name_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->table()->dropColumn('legacy id');
    }

    #[Test]
    public function it_records_a_renamed_column(): void
    {
        $table = $this->table();

        $table->renameColumn('name', 'full_name');

        $changes = $table->changes();

        self::assertInstanceOf(RenameColumn::class, $changes[0]);
        self::assertSame('name', $changes[0]->from->name);
        self::assertSame('full_name', $changes[0]->to->name);
    }

    #[Test]
    public function it_rejects_a_rename_target_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->table()->renameColumn('name', 'full name');
    }

    #[Test]
    public function it_records_a_changed_column_as_a_modification(): void
    {
        $table = $this->table();

        $table->string('email', 320)->change();

        $changes = $table->changes();

        self::assertInstanceOf(ModifyColumn::class, $changes[0]);
        self::assertSame('email', $changes[0]->column->name->name);
        self::assertSame(320, $changes[0]->column->length);
    }

    #[Test]
    public function it_keeps_every_change_in_the_order_it_was_asked_for(): void
    {
        $table = $this->table();

        $table->string('phone')->nullable();
        $table->dropColumn('legacy_id');
        $table->renameColumn('name', 'full_name');
        $table->integer('age')->change();

        self::assertSame(
            [AddColumn::class, DropColumn::class, RenameColumn::class, ModifyColumn::class],
            array_map(static fn(object $change): string => $change::class, $table->changes()),
        );
    }

    #[Test]
    public function it_builds_the_same_list_however_often_it_is_read(): void
    {
        $table = $this->table();

        $table->string('email');

        self::assertEquals($table->changes(), $table->changes());
    }
}
