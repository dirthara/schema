<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Integration;

use PDO;
use Dirthara\Schema\Table;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\ConnectedSchema;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Grammar\SchemaGrammar;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function getenv;
use function in_array;

abstract class SchemaConformanceTestCase extends TestCase
{
    protected const string TABLE = 'conformance_users';

    protected Connection $connection;

    protected ConnectedSchema $schema;

    abstract protected function driverName(): DriverName;

    abstract protected function driver(): Driver;

    abstract protected function grammar(): SchemaGrammar;

    abstract protected function config(): ConnectionConfig;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array($this->driverName()->value, PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped(sprintf('The %s PDO driver is not installed.', $this->driverName()->value));
        }

        $this->connection = new PdoConnection($this->config(), $this->driver());
        $this->schema = new ConnectedSchema($this->connection, $this->grammar());

        $this->schema->dropIfExists(self::TABLE);
    }

    protected function tearDown(): void
    {
        $this->schema->dropIfExists(self::TABLE);
        $this->schema->dropIfExists('conformance_renamed');

        parent::tearDown();
    }

    protected function env(string $name, string $fallback): string
    {
        $value = getenv($name);

        return $value === false ? $fallback : $value;
    }

    protected function createUsers(): void
    {
        $this->schema->create(self::TABLE, static function (Table $table): void {
            $table->id();
            $table->string('email', 120);
            $table->string('name', 120)->nullable();
        });
    }

    protected function insert(string $columns, string $values): void
    {
        $this->connection->execute(sprintf('INSERT INTO %s (%s) VALUES (%s)', self::TABLE, $columns, $values));
    }

    protected function rowCount(): int
    {
        $row = $this->connection->execute(sprintf('SELECT COUNT(*) AS total FROM %s', self::TABLE))->first();

        return (int) ($row['total'] ?? $row['TOTAL'] ?? 0);
    }

    #[Test]
    public function it_creates_a_table_the_server_reports(): void
    {
        $this->createUsers();

        self::assertTrue($this->schema->hasTable(self::TABLE));
    }

    #[Test]
    public function it_does_not_report_a_table_that_was_never_created(): void
    {
        self::assertFalse($this->schema->hasTable('conformance_absent'));
    }

    #[Test]
    public function it_creates_a_usable_table(): void
    {
        $this->createUsers();

        $this->insert('email, name', "'ada@example.com', 'Ada'");

        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function it_skips_a_create_when_the_table_is_already_there(): void
    {
        $this->createUsers();

        $this->schema->createIfNotExists(self::TABLE, static function (Table $table): void {
            $table->id();
            $table->string('email', 120);
        });

        self::assertTrue($this->schema->hasTable(self::TABLE));
    }

    #[Test]
    public function it_drops_a_table(): void
    {
        $this->createUsers();

        $this->schema->drop(self::TABLE);

        self::assertFalse($this->schema->hasTable(self::TABLE));
    }

    #[Test]
    public function it_ignores_dropping_a_table_that_is_not_there(): void
    {
        $this->schema->dropIfExists('conformance_absent');

        self::assertFalse($this->schema->hasTable('conformance_absent'));
    }

    #[Test]
    public function it_renames_a_table(): void
    {
        $this->createUsers();

        $this->schema->rename(self::TABLE, 'conformance_renamed');

        self::assertFalse($this->schema->hasTable(self::TABLE));
        self::assertTrue($this->schema->hasTable('conformance_renamed'));
    }

    #[Test]
    public function it_adds_a_column(): void
    {
        $this->createUsers();

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->string('phone', 40)->nullable();
        });

        $this->insert('email, phone', "'ada@example.com', '555'");

        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function it_drops_a_column(): void
    {
        $this->createUsers();

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->dropColumn('name');
        });

        $this->expectException(QueryException::class);

        $this->insert('email, name', "'ada@example.com', 'Ada'");
    }

    #[Test]
    public function it_renames_a_column(): void
    {
        $this->createUsers();

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->renameColumn('name', 'full_name');
        });

        $this->insert('email, full_name', "'ada@example.com', 'Ada'");

        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function it_adds_and_drops_an_index(): void
    {
        $this->createUsers();

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->index('name');
        });

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->dropIndex('conformance_users_name_index');
        });

        self::assertTrue($this->schema->hasTable(self::TABLE));
    }

    #[Test]
    public function it_enforces_a_unique_constraint_declared_at_create(): void
    {
        $this->schema->create(self::TABLE, static function (Table $table): void {
            $table->id();
            $table->string('email', 120)->unique();
        });

        $this->insert('email', "'ada@example.com'");

        $this->expectException(QueryException::class);

        $this->insert('email', "'ada@example.com'");
    }

    #[Test]
    public function it_enforces_a_unique_constraint_added_later(): void
    {
        $this->createUsers();

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->unique('email');
        });

        $this->insert('email', "'ada@example.com'");

        $this->expectException(QueryException::class);

        $this->insert('email', "'ada@example.com'");
    }

    #[Test]
    public function it_stops_enforcing_a_unique_constraint_that_was_dropped(): void
    {
        $this->createUsers();

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->unique('email');
        });

        $this->schema->table(self::TABLE, static function (Table $table): void {
            $table->dropConstraint('conformance_users_email_unique');
        });

        $this->insert('email', "'ada@example.com'");
        $this->insert('email', "'ada@example.com'");

        self::assertSame(2, $this->rowCount());
    }

    #[Test]
    public function it_enforces_a_not_null_column(): void
    {
        $this->createUsers();

        $this->expectException(QueryException::class);

        $this->insert('name', "'Ada'");
    }

    #[Test]
    public function it_applies_a_default(): void
    {
        $this->schema->create(self::TABLE, static function (Table $table): void {
            $table->id();
            $table->string('email', 120);
            $table->string('status', 20)->default('pending');
        });

        $this->insert('email', "'ada@example.com'");

        $row = $this->connection->execute(sprintf('SELECT status FROM %s', self::TABLE))->first();

        self::assertSame('pending', $row['status'] ?? $row['STATUS'] ?? null);
    }

    #[Test]
    public function it_applies_a_default_that_needs_escaping(): void
    {
        $this->schema->create(self::TABLE, static function (Table $table): void {
            $table->id();
            $table->string('email', 120);
            $table->string('quirky', 40)->default("O'Brien\\");
        });

        $this->insert('email', "'ada@example.com'");

        $row = $this->connection->execute(sprintf('SELECT quirky FROM %s', self::TABLE))->first();

        self::assertSame("O'Brien\\", $row['quirky'] ?? $row['QUIRKY'] ?? null);
    }

    #[Test]
    public function it_auto_increments_the_key(): void
    {
        $this->createUsers();

        $this->insert('email', "'one@example.com'");
        $this->insert('email', "'two@example.com'");

        $rows = $this->connection->execute(sprintf('SELECT id FROM %s ORDER BY id', self::TABLE))->all();

        self::assertCount(2, $rows);
        self::assertNotSame($rows[0]['id'], $rows[1]['id']);
    }
}
