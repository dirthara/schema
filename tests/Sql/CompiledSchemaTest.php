<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Sql;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Sql\CompiledSchema;

final class CompiledSchemaTest extends TestCase
{
    #[Test]
    public function it_carries_the_queries_it_was_built_with(): void
    {
        self::assertSame(
            ['CREATE TABLE users (id INTEGER)'],
            new CompiledSchema(['CREATE TABLE users (id INTEGER)'])->queries,
        );
    }

    #[Test]
    public function it_appends_a_query(): void
    {
        $schema = new CompiledSchema(['CREATE TABLE users (id INTEGER)']);

        $schema->addQuery('CREATE INDEX users_id ON users (id)');

        self::assertSame(['CREATE TABLE users (id INTEGER)', 'CREATE INDEX users_id ON users (id)'], $schema->queries);
    }

    #[Test]
    public function it_has_no_cleanup_unless_given_one(): void
    {
        self::assertSame([], new CompiledSchema(['DROP TABLE users'])->cleanup);
    }

    #[Test]
    public function it_carries_the_cleanup_it_was_built_with(): void
    {
        self::assertSame(
            ['PRAGMA foreign_keys = ON'],
            new CompiledSchema(['PRAGMA foreign_keys = OFF'], ['PRAGMA foreign_keys = ON'])->cleanup,
        );
    }
}
