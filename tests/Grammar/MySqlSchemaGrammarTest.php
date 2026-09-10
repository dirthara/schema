<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Grammar;

use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Column\ColumnType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Grammar\MySqlSchemaGrammar;
use Dirthara\Schema\Constraint\ReferentialAction;
use Dirthara\Schema\Exceptions\InvalidSchemaException;

use function sprintf;

final class MySqlSchemaGrammarTest extends TestCase
{
    private MySqlSchemaGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new MySqlSchemaGrammar();
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
    public function it_quotes_with_backticks(): void
    {
        $table = new Table('users');
        $table->string('email', 120);

        self::assertSame(['CREATE TABLE `users` (`email` VARCHAR(120) NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_compiles_a_create_if_not_exists(): void
    {
        $table = new Table('users');
        $table->text('body');

        self::assertSame(['CREATE TABLE IF NOT EXISTS `users` (`body` TEXT NOT NULL)'], $this->create($table, true));
    }

    #[Test]
    public function it_declares_the_key_after_the_columns(): void
    {
        $table = new Table('users');
        $table->id();

        self::assertSame(
            [
                'CREATE TABLE `users` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, '
                    . 'CONSTRAINT `users_primary` PRIMARY KEY (`id`))',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_keeps_the_unsigned_modifier(): void
    {
        $table = new Table('users');
        $table->integer('score')->unsigned();

        self::assertSame(['CREATE TABLE `users` (`score` INT UNSIGNED NOT NULL)'], $this->create($table));
    }

    /**
     * @return iterable<string, array{ColumnType, string}>
     */
    public static function types(): iterable
    {
        yield 'boolean' => [ColumnType::Boolean, 'TINYINT(1)'];
        yield 'tiny integer' => [ColumnType::TinyInteger, 'TINYINT'];
        yield 'small integer' => [ColumnType::SmallInteger, 'SMALLINT'];
        yield 'integer' => [ColumnType::Integer, 'INT'];
        yield 'big integer' => [ColumnType::BigInteger, 'BIGINT'];
        yield 'float' => [ColumnType::Float, 'FLOAT'];
        yield 'double' => [ColumnType::Double, 'DOUBLE'];
        yield 'text' => [ColumnType::Text, 'TEXT'];
        yield 'json' => [ColumnType::Json, 'JSON'];
        yield 'date' => [ColumnType::Date, 'DATE'];
        yield 'time' => [ColumnType::Time, 'TIME'];
        yield 'date time' => [ColumnType::DateTime, 'DATETIME'];
        yield 'timestamp' => [ColumnType::Timestamp, 'TIMESTAMP'];
        yield 'uuid' => [ColumnType::Uuid, 'CHAR(36)'];
        yield 'binary' => [ColumnType::Binary, 'BLOB'];
        yield 'decimal' => [ColumnType::Decimal, 'DECIMAL(8, 2)'];
    }

    #[Test]
    #[DataProvider('types')]
    public function it_maps_a_type(ColumnType $type, string $expected): void
    {
        $table = new Table('users');
        $table->column('value', $type);

        self::assertSame([sprintf('CREATE TABLE `users` (`value` %s NOT NULL)', $expected)], $this->create($table));
    }

    #[Test]
    public function it_maps_a_decimal_with_a_precision(): void
    {
        $table = new Table('users');
        $table->decimal('amount', 12, 4);

        self::assertSame(['CREATE TABLE `users` (`amount` DECIMAL(12, 4) NOT NULL)'], $this->create($table));
    }

    #[Test]
    public function it_escapes_a_backslash_as_well_as_a_quote(): void
    {
        $table = new Table('users');
        $table->string('status', 40)->default("O'Brien\\");

        self::assertSame(
            ["CREATE TABLE `users` (`status` VARCHAR(40) NOT NULL DEFAULT 'O''Brien\\\\')"],
            $this->create($table),
        );
    }

    #[Test]
    public function it_reports_mysql_when_a_default_contains_a_null_byte(): void
    {
        $table = new Table('users');
        $table->string('status', 40)->default("pending\0truncated");

        try {
            $this->create($table);

            self::fail('The grammar accepted a null byte in a default.');
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['driver' => 'mysql'], $exception->getContext());
        }
    }

    #[Test]
    public function it_compiles_a_unique_constraint(): void
    {
        $table = new Table('users');
        $table->string('email', 191)->unique();

        self::assertSame(
            [
                'CREATE TABLE `users` (`email` VARCHAR(191) NOT NULL, '
                    . 'CONSTRAINT `users_email_unique` UNIQUE (`email`))',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_compiles_a_foreign_key(): void
    {
        $table = new Table('users');
        $table->bigInteger('team_id')->unsigned();
        $table->foreign('team_id')->references('id')->on('teams')->onDelete(ReferentialAction::Cascade);

        self::assertSame(
            [
                'CREATE TABLE `users` (`team_id` BIGINT UNSIGNED NOT NULL, '
                    . 'CONSTRAINT `users_team_id_foreign` FOREIGN KEY (`team_id`) '
                    . 'REFERENCES `teams` (`id`) ON DELETE CASCADE)',
            ],
            $this->create($table),
        );
    }

    #[Test]
    public function it_declares_an_index_inside_the_create(): void
    {
        $table = new Table('users');
        $table->string('name');
        $table->index('name');

        self::assertSame(
            ['CREATE TABLE `users` (`name` VARCHAR(255) NOT NULL, INDEX `users_name_index` (`name`))'],
            $this->create($table),
        );
    }

    #[Test]
    public function it_keeps_a_conditional_create_with_an_index_to_one_statement(): void
    {
        $table = new Table('users');
        $table->string('name');
        $table->index('name');

        self::assertSame(
            [
                'CREATE TABLE IF NOT EXISTS `users` (`name` VARCHAR(255) NOT NULL, '
                    . 'INDEX `users_name_index` (`name`))',
            ],
            $this->create($table, true),
        );
    }

    #[Test]
    public function it_still_creates_an_index_as_a_statement_when_altering(): void
    {
        $table = new Table('users');
        $table->index('name');

        self::assertSame(['CREATE INDEX `users_name_index` ON `users` (`name`)'], $this->alter($table));
    }

    #[Test]
    public function it_compiles_a_drop(): void
    {
        self::assertSame(['DROP TABLE `users`'], $this->grammar->compileDrop('users', false)->queries);
    }

    #[Test]
    public function it_compiles_a_drop_if_exists(): void
    {
        self::assertSame(['DROP TABLE IF EXISTS `users`'], $this->grammar->compileDrop('users', true)->queries);
    }

    #[Test]
    public function it_compiles_a_rename(): void
    {
        self::assertSame(
            ['ALTER TABLE `users` RENAME TO `people`'],
            $this->grammar->compileRename('users', 'people')->queries,
        );
    }

    #[Test]
    public function it_compiles_a_has_table_query_scoped_to_the_current_database(): void
    {
        self::assertSame(
            [
                'SELECT `TABLE_NAME` FROM `information_schema`.`TABLES` '
                    . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'users'",
            ],
            $this->grammar->compileHasTable('users')->queries,
        );
    }

    #[Test]
    public function it_compiles_an_added_column(): void
    {
        $table = new Table('users');
        $table->string('phone', 40)->nullable();

        self::assertSame(['ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(40) NULL'], $this->alter($table));
    }

    #[Test]
    public function it_adds_a_not_null_column_without_a_default(): void
    {
        $table = new Table('users');
        $table->string('phone', 40);

        self::assertSame(['ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(40) NOT NULL'], $this->alter($table));
    }

    #[Test]
    public function it_compiles_a_dropped_column(): void
    {
        $table = new Table('users');
        $table->dropColumn('legacy');

        self::assertSame(['ALTER TABLE `users` DROP COLUMN `legacy`'], $this->alter($table));
    }

    #[Test]
    public function it_compiles_a_renamed_column(): void
    {
        $table = new Table('users');
        $table->renameColumn('name', 'full_name');

        self::assertSame(['ALTER TABLE `users` RENAME COLUMN `name` TO `full_name`'], $this->alter($table));
    }

    #[Test]
    public function it_modifies_a_column(): void
    {
        $table = new Table('users');
        $table->string('email', 320)->change();

        self::assertSame(['ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(320) NOT NULL'], $this->alter($table));
    }

    #[Test]
    public function it_adds_a_constraint_to_an_existing_table(): void
    {
        $table = new Table('users');
        $table->unique('email');

        self::assertSame(
            ['ALTER TABLE `users` ADD CONSTRAINT `users_email_unique` UNIQUE (`email`)'],
            $this->alter($table),
        );
    }

    #[Test]
    public function it_drops_a_constraint_from_an_existing_table(): void
    {
        $table = new Table('users');
        $table->dropConstraint('users_email_unique');

        self::assertSame(['ALTER TABLE `users` DROP CONSTRAINT `users_email_unique`'], $this->alter($table));
    }

    #[Test]
    public function it_names_the_table_when_dropping_an_index(): void
    {
        $table = new Table('users');
        $table->dropIndex('users_name_index');

        self::assertSame(['DROP INDEX `users_name_index` ON `users`'], $this->alter($table));
    }
}
