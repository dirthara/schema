<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Grammar;

use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Column\ColumnType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Grammar\SQLiteSchemaGrammar;
use Dirthara\Schema\Constraint\ReferentialAction;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;

final class SQLiteSchemaGrammarTest extends TestCase
{
    private SQLiteSchemaGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new SQLiteSchemaGrammar();
    }

    private function table(string $name = 'users'): Table
    {
        return new Table($name);
    }

    /**
     * @return list<string>
     */
    private function create(Table $table, bool $ifNotExists = false): array
    {
        return $this->grammar->compileCreate($table->name->name, $table, $ifNotExists)->queries;
    }

    /**
     * @return list<string>
     */
    private function alter(Table $table): array
    {
        return $this->grammar->compileAlter($table->name->name, $table)->queries;
    }

    #[Test]
    public function it_compiles_a_create(): void
    {
        $table = $this->table();
        $table->string('email', 120);

        self::assertSame(['CREATE TABLE "users" ("email" VARCHAR(120) NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_compiles_a_create_if_not_exists(): void
    {
        $table = $this->table();
        $table->text('body');

        self::assertSame(['CREATE TABLE IF NOT EXISTS "users" ("body" TEXT NOT NULL)'], $this->create($table, true));
    }

    #[Test]
    public function it_declares_an_auto_incrementing_key_on_the_column(): void
    {
        $table = $this->table();
        $table->id();

        self::assertSame(
            ['CREATE TABLE "users" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT)'],
            $this->create($table),
        );
    }

    #[Test]
    public function it_declares_a_key_it_cannot_inline_as_a_table_constraint(): void
    {
        $table = $this->table();
        $table->bigInteger('tenant_id');
        $table->bigInteger('id');
        $table->primary(['tenant_id', 'id']);

        self::assertSame(
            [
                'CREATE TABLE "users" ("tenant_id" INTEGER NOT NULL, "id" INTEGER NOT NULL, '
                    . 'CONSTRAINT "users_primary" PRIMARY KEY ("tenant_id", "id"))',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_declares_a_non_incrementing_single_key_as_a_table_constraint(): void
    {
        $table = $this->table();
        $table->uuid('id');
        $table->primary('id');

        self::assertSame(
            ['CREATE TABLE "users" ("id" CHAR(36) NOT NULL, CONSTRAINT "users_primary" PRIMARY KEY ("id"))'],
            $this->create($table),
        );
    }

    #[Test]
    public function it_drops_the_unsigned_modifier(): void
    {
        $table = $this->table();
        $table->integer('score')->unsigned();

        self::assertSame(['CREATE TABLE "users" ("score" INTEGER NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_compiles_a_nullable_column(): void
    {
        $table = $this->table();
        $table->string('name')->nullable();

        self::assertSame(['CREATE TABLE "users" ("name" VARCHAR(255) NULL)'], $this->create($table));
    }

    /**
     * @return iterable<string, array{string|int|float|bool|null, string}>
     */
    public static function defaults(): iterable
    {
        yield 'a string' => ['pending', "'pending'"];
        yield 'a quoted string' => ["O'Brien", "'O''Brien'"];
        yield 'an integer' => [7, '7'];
        yield 'a float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, '0'];
        yield 'null' => [null, 'NULL'];
    }

    #[Test]
    #[DataProvider('defaults')]
    public function it_compiles_a_default(string|int|float|bool|null $default, string $expected): void
    {
        $table = $this->table();
        $table->string('status')->default($default);

        self::assertSame(
            [sprintf('CREATE TABLE "users" ("status" VARCHAR(255) NOT NULL DEFAULT %s)', $expected)],
            $this->create($table),
        );
    }

    /**
     * @return iterable<string, array{ColumnType, string}>
     */
    public static function types(): iterable
    {
        yield 'boolean' => [ColumnType::Boolean, 'INTEGER'];
        yield 'tiny integer' => [ColumnType::TinyInteger, 'INTEGER'];
        yield 'big integer' => [ColumnType::BigInteger, 'INTEGER'];
        yield 'float' => [ColumnType::Float, 'REAL'];
        yield 'double' => [ColumnType::Double, 'REAL'];
        yield 'text' => [ColumnType::Text, 'TEXT'];
        yield 'json' => [ColumnType::Json, 'TEXT'];
        yield 'date' => [ColumnType::Date, 'DATE'];
        yield 'time' => [ColumnType::Time, 'TIME'];
        yield 'date time' => [ColumnType::DateTime, 'DATETIME'];
        yield 'timestamp' => [ColumnType::Timestamp, 'DATETIME'];
        yield 'uuid' => [ColumnType::Uuid, 'CHAR(36)'];
        yield 'binary' => [ColumnType::Binary, 'BLOB'];
        yield 'decimal' => [ColumnType::Decimal, 'NUMERIC'];
    }

    #[Test]
    #[DataProvider('types')]
    public function it_maps_a_type(ColumnType $type, string $expected): void
    {
        $table = $this->table();
        $table->column('value', $type);

        self::assertSame([sprintf('CREATE TABLE "users" ("value" %s NOT NULL)', $expected)], $this->create($table));
    }

    #[Test]
    public function it_maps_a_decimal_with_a_precision(): void
    {
        $table = $this->table();
        $table->decimal('amount', 12, 4);

        self::assertSame(['CREATE TABLE "users" ("amount" NUMERIC(12, 4) NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_maps_a_char_with_a_length(): void
    {
        $table = $this->table();
        $table->char('code', 2);

        self::assertSame(['CREATE TABLE "users" ("code" CHAR(2) NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_compiles_a_unique_constraint(): void
    {
        $table = $this->table();
        $table->string('email')->unique();

        self::assertSame(
            [
                'CREATE TABLE "users" ("email" VARCHAR(255) NOT NULL, '
                    . 'CONSTRAINT "users_email_unique" UNIQUE ("email"))',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_compiles_a_foreign_key(): void
    {
        $table = $this->table();
        $table->bigInteger('team_id');
        $table->foreign('team_id')->references('id')->on('teams')->onDelete(ReferentialAction::Cascade);

        self::assertSame(
            [
                'CREATE TABLE "users" ("team_id" INTEGER NOT NULL, '
                    . 'CONSTRAINT "users_team_id_foreign" FOREIGN KEY ("team_id") '
                    . 'REFERENCES "teams" ("id") ON DELETE CASCADE)',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_compiles_every_referential_action(): void
    {
        $table = $this->table();
        $table->bigInteger('team_id');
        $table
            ->foreign('team_id')
            ->references('id')
            ->on('teams')
            ->onDelete(ReferentialAction::SetNull)
            ->onUpdate(ReferentialAction::NoAction);

        self::assertStringContainsString('ON DELETE SET NULL ON UPDATE NO ACTION', $this->create($table)[0]);
    }

    #[Test]
    public function it_compiles_an_index_as_its_own_statement(): void
    {
        $table = $this->table();
        $table->string('name');
        $table->index('name');

        self::assertSame(
            [
                'CREATE TABLE "users" ("name" VARCHAR(255) NOT NULL)',
                'CREATE INDEX "users_name_index" ON "users" ("name")',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_compiles_a_drop(): void
    {
        self::assertSame(['DROP TABLE "users"'], $this->grammar->compileDrop('users', false)->queries);
    }

    #[Test]
    public function it_compiles_a_drop_if_exists(): void
    {
        self::assertSame(['DROP TABLE IF EXISTS "users"'], $this->grammar->compileDrop('users', true)->queries);
    }

    #[Test]
    public function it_compiles_a_rename(): void
    {
        self::assertSame(
            ['ALTER TABLE "users" RENAME TO "people"'],
            $this->grammar->compileRename('users', 'people')->queries,
        );
    }

    #[Test]
    public function it_compiles_a_has_table_query(): void
    {
        self::assertSame(
            ['SELECT "name" FROM "sqlite_master" WHERE "type" = \'table\' AND "name" = \'users\''],
            $this->grammar->compileHasTable('users')->queries,
        );
    }

    #[Test]
    public function it_compiles_an_added_column(): void
    {
        $table = $this->table();
        $table->string('phone')->nullable();

        self::assertSame(['ALTER TABLE "users" ADD COLUMN "phone" VARCHAR(255) NULL'], $this->alter($table));
    }

    #[Test]
    public function it_compiles_a_dropped_column(): void
    {
        $table = $this->table();
        $table->dropColumn('legacy');

        self::assertSame(['ALTER TABLE "users" DROP COLUMN "legacy"'], $this->alter($table));
    }

    #[Test]
    public function it_compiles_a_renamed_column(): void
    {
        $table = $this->table();
        $table->renameColumn('name', 'full_name');

        self::assertSame(['ALTER TABLE "users" RENAME COLUMN "name" TO "full_name"'], $this->alter($table));
    }

    #[Test]
    public function it_adds_a_unique_constraint_as_a_unique_index(): void
    {
        $table = $this->table();
        $table->unique('email');

        self::assertSame(['CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email")'], $this->alter($table));
    }

    #[Test]
    public function it_drops_a_constraint_as_an_index(): void
    {
        $table = $this->table();
        $table->dropConstraint('users_email_unique');

        self::assertSame(['DROP INDEX "users_email_unique"'], $this->alter($table));
    }

    #[Test]
    public function it_drops_an_index_without_naming_the_table(): void
    {
        $table = $this->table();
        $table->dropIndex('users_name_index');

        self::assertSame(['DROP INDEX "users_name_index"'], $this->alter($table));
    }

    #[Test]
    public function it_refuses_to_change_a_column(): void
    {
        $table = $this->table();
        $table->string('email')->change();

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('SQLite cannot change the column [email] on the existing table [users].');

        $this->alter($table);
    }

    #[Test]
    public function it_refuses_to_add_a_foreign_key_to_an_existing_table(): void
    {
        $table = $this->table();
        $table->foreign('team_id')->references('id')->on('teams');

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('SQLite cannot add a [foreign key] to the existing table [users]');

        $this->alter($table);
    }

    #[Test]
    public function it_refuses_to_add_a_primary_key_to_an_existing_table(): void
    {
        $table = $this->table();
        $table->primary('id');

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('SQLite cannot add a [primary key] to the existing table [users]');

        $this->alter($table);
    }

    #[Test]
    public function it_refuses_to_add_a_not_null_column_without_a_default(): void
    {
        $table = $this->table();
        $table->string('phone');

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('SQLite cannot add the NOT NULL column [phone] without a default');

        $this->alter($table);
    }

    #[Test]
    public function it_refuses_to_auto_increment_a_column_that_is_not_the_key(): void
    {
        $table = $this->table();
        $table->bigInteger('counter')->autoIncrement();

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('SQLite can only auto-increment a single-column integer primary key.');

        $this->create($table);
    }

    #[Test]
    public function it_reports_the_driver_and_operation_it_refused(): void
    {
        $table = $this->table();
        $table->string('email')->change();

        try {
            $this->alter($table);
        } catch (UnsupportedDriverException $exception) {
            self::assertSame(
                ['driver' => 'sqlite', 'operation' => 'alter', 'subject' => 'email'],
                $exception->getContext(),
            );
        }
    }

    #[Test]
    public function it_refuses_to_create_a_table_from_a_change(): void
    {
        $table = $this->table();
        $table->dropColumn('legacy');

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('A table cannot be created with a');

        $this->create($table);
    }
}
