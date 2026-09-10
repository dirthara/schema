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
}
