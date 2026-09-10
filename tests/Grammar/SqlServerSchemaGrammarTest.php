<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Grammar;

use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Column\ColumnType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Constraint\ReferentialAction;
use Dirthara\Schema\Grammar\SqlServerSchemaGrammar;
use Dirthara\Schema\Exceptions\InvalidSchemaException;
use Dirthara\Schema\Exceptions\UnsupportedDriverException;

use function sprintf;

final class SqlServerSchemaGrammarTest extends TestCase
{
    private SqlServerSchemaGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new SqlServerSchemaGrammar();
    }

    /**
     * @return list<string>
     */
    private function create(Table $table, bool $ifNotExists = false): array
    {
        return $this->grammar->compileCreate($table, $ifNotExists)->queries;
    }

    /**
     * @return list<string>
     */
    private function alter(Table $table): array
    {
        return $this->grammar->compileAlter($table)->queries;
    }

    #[Test]
    public function it_quotes_with_brackets(): void
    {
        $table = new Table('users');
        $table->string('email', 120);

        self::assertSame(['CREATE TABLE [users] ([email] NVARCHAR(120) NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_guards_a_create_if_not_exists_with_object_id(): void
    {
        $table = new Table('users');
        $table->text('body');

        self::assertSame(
            ["IF OBJECT_ID(N'[users]', N'U') IS NULL CREATE TABLE [users] ([body] NVARCHAR(MAX) NOT NULL)"],
            $this->create($table, true),
        );
    }

    #[Test]
    public function it_declares_an_index_inside_the_create(): void
    {
        $table = new Table('users');
        $table->string('name');
        $table->index('name');

        self::assertSame(
            ['CREATE TABLE [users] ([name] NVARCHAR(255) NOT NULL, INDEX [users_name_index] ([name]))'],
            $this->create($table),
        );
    }

    #[Test]
    public function it_keeps_a_conditional_create_with_an_index_to_one_guarded_statement(): void
    {
        $table = new Table('users');
        $table->string('name');
        $table->index('name');

        self::assertSame(
            [
                "IF OBJECT_ID(N'[users]', N'U') IS NULL CREATE TABLE [users] "
                    . '([name] NVARCHAR(255) NOT NULL, INDEX [users_name_index] ([name]))',
            ],
            $this->create($table, true),
        );
    }

    #[Test]
    public function it_still_creates_an_index_as_a_statement_when_altering(): void
    {
        $table = new Table('users');
        $table->index('name');

        self::assertSame(['CREATE INDEX [users_name_index] ON [users] ([name])'], $this->alter($table));
    }

    #[Test]
    public function it_generates_the_key_as_an_identity_column(): void
    {
        $table = new Table('users');
        $table->id();

        self::assertSame(
            [
                'CREATE TABLE [users] ([id] BIGINT NOT NULL IDENTITY(1,1), '
                    . 'CONSTRAINT [users_primary] PRIMARY KEY ([id]))',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_drops_the_unsigned_modifier(): void
    {
        $table = new Table('users');
        $table->integer('score')->unsigned();

        self::assertSame(['CREATE TABLE [users] ([score] INT NOT NULL)'], $this->create($table));
    }

    /**
     * @return iterable<string, array{ColumnType, string}>
     */
    public static function types(): iterable
    {
        yield 'boolean' => [ColumnType::Boolean, 'BIT'];
        yield 'tiny integer' => [ColumnType::TinyInteger, 'TINYINT'];
        yield 'small integer' => [ColumnType::SmallInteger, 'SMALLINT'];
        yield 'integer' => [ColumnType::Integer, 'INT'];
        yield 'big integer' => [ColumnType::BigInteger, 'BIGINT'];
        yield 'float' => [ColumnType::Float, 'REAL'];
        yield 'double' => [ColumnType::Double, 'FLOAT'];
        yield 'text' => [ColumnType::Text, 'NVARCHAR(MAX)'];
        yield 'json' => [ColumnType::Json, 'NVARCHAR(MAX)'];
        yield 'date' => [ColumnType::Date, 'DATE'];
        yield 'time' => [ColumnType::Time, 'TIME'];
        yield 'date time' => [ColumnType::DateTime, 'DATETIME2'];
        yield 'timestamp' => [ColumnType::Timestamp, 'DATETIME2'];
        yield 'uuid' => [ColumnType::Uuid, 'UNIQUEIDENTIFIER'];
        yield 'binary' => [ColumnType::Binary, 'VARBINARY(MAX)'];
        yield 'decimal' => [ColumnType::Decimal, 'DECIMAL(8, 2)'];
    }

    #[Test]
    #[DataProvider('types')]
    public function it_maps_a_type(ColumnType $type, string $expected): void
    {
        $table = new Table('users');
        $table->column('value', $type);

        self::assertSame([sprintf('CREATE TABLE [users] ([value] %s NOT NULL)', $expected)], $this->create($table));
    }

    #[Test]
    public function it_writes_a_string_default_as_a_national_literal(): void
    {
        $table = new Table('users');
        $table->string('status', 40)->default("O'Brien");

        self::assertSame(
            ["CREATE TABLE [users] ([status] NVARCHAR(40) NOT NULL DEFAULT N'O''Brien')"],
            $this->create($table),
        );
    }

    #[Test]
    public function it_writes_a_boolean_default_as_a_bit(): void
    {
        $table = new Table('users');
        $table->boolean('active')->default(true);

        self::assertSame(['CREATE TABLE [users] ([active] BIT NOT NULL DEFAULT 1)'], $this->create($table));
    }

    #[Test]
    public function it_reports_sql_server_when_a_default_contains_a_null_byte(): void
    {
        $table = new Table('users');
        $table->string('status', 40)->default("pending\0truncated");

        try {
            $this->create($table);

            self::fail('The grammar accepted a null byte in a default.');
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['driver' => 'sqlsrv'], $exception->getContext());
        }
    }

    #[Test]
    public function it_compiles_a_unique_constraint(): void
    {
        $table = new Table('users');
        $table->string('email', 191)->unique();

        self::assertSame(
            [
                'CREATE TABLE [users] ([email] NVARCHAR(191) NOT NULL, '
                    . 'CONSTRAINT [users_email_unique] UNIQUE ([email]))',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_compiles_a_foreign_key(): void
    {
        $table = new Table('users');
        $table->bigInteger('team_id');
        $table->foreign('team_id')->references('id')->on('teams')->onDelete(ReferentialAction::Cascade);

        self::assertSame(
            [
                'CREATE TABLE [users] ([team_id] BIGINT NOT NULL, '
                    . 'CONSTRAINT [users_team_id_foreign] FOREIGN KEY ([team_id]) '
                    . 'REFERENCES [teams] ([id]) ON DELETE CASCADE)',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_compiles_a_drop(): void
    {
        self::assertSame(['DROP TABLE [users]'], $this->grammar->compileDrop('users', false)->queries);
    }

    #[Test]
    public function it_compiles_a_drop_if_exists(): void
    {
        self::assertSame(['DROP TABLE IF EXISTS [users]'], $this->grammar->compileDrop('users', true)->queries);
    }

    #[Test]
    public function it_renames_a_table_with_a_stored_procedure(): void
    {
        self::assertSame(
            ["EXEC sp_rename N'users', N'people'"],
            $this->grammar->compileRename('users', 'people')->queries,
        );
    }

    #[Test]
    public function it_compiles_a_has_table_query_scoped_to_the_current_schema(): void
    {
        self::assertSame(
            [
                'SELECT [TABLE_NAME] FROM [INFORMATION_SCHEMA].[TABLES] '
                    . "WHERE [TABLE_SCHEMA] = SCHEMA_NAME() AND [TABLE_NAME] = N'users'",
            ],
            $this->grammar->compileHasTable('users')->queries,
        );
    }

    #[Test]
    public function it_adds_a_column_without_the_column_keyword(): void
    {
        $table = new Table('users');
        $table->string('phone', 40)->nullable();

        self::assertSame(['ALTER TABLE [users] ADD [phone] NVARCHAR(40) NULL'], $this->alter($table));
    }

    #[Test]
    public function it_drops_a_column_with_the_column_keyword(): void
    {
        $table = new Table('users');
        $table->dropColumn('legacy');

        self::assertSame(['ALTER TABLE [users] DROP COLUMN [legacy]'], $this->alter($table));
    }

    #[Test]
    public function it_renames_a_column_with_a_stored_procedure(): void
    {
        $table = new Table('users');
        $table->renameColumn('name', 'full_name');

        self::assertSame(["EXEC sp_rename N'users.name', N'full_name', N'COLUMN'"], $this->alter($table));
    }

    #[Test]
    public function it_modifies_a_column(): void
    {
        $table = new Table('users');
        $table->string('email', 320)->change();

        self::assertSame(['ALTER TABLE [users] ALTER COLUMN [email] NVARCHAR(320) NOT NULL'], $this->alter($table));
    }

    #[Test]
    public function it_modifies_a_column_to_be_nullable(): void
    {
        $table = new Table('users');
        $table->string('email', 320)->nullable()->change();

        self::assertSame(['ALTER TABLE [users] ALTER COLUMN [email] NVARCHAR(320) NULL'], $this->alter($table));
    }

    #[Test]
    public function it_refuses_to_change_a_column_and_its_default_at_once(): void
    {
        $table = new Table('users');
        $table->string('email', 320)->default('none')->change();

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('SQL Server keeps a default in its own constraint');

        $this->alter($table);
    }

    #[Test]
    public function it_adds_a_constraint_to_an_existing_table(): void
    {
        $table = new Table('users');
        $table->unique('email');

        self::assertSame(
            ['ALTER TABLE [users] ADD CONSTRAINT [users_email_unique] UNIQUE ([email])'],
            $this->alter($table),
        );
    }

    #[Test]
    public function it_drops_a_constraint_from_an_existing_table(): void
    {
        $table = new Table('users');
        $table->dropConstraint('users_email_unique');

        self::assertSame(['ALTER TABLE [users] DROP CONSTRAINT [users_email_unique]'], $this->alter($table));
    }

    #[Test]
    public function it_names_the_table_when_dropping_an_index(): void
    {
        $table = new Table('users');
        $table->dropIndex('users_name_index');

        self::assertSame(['DROP INDEX [users_name_index] ON [users]'], $this->alter($table));
    }
}
