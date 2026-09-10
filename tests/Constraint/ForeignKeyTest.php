<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Constraint;

use Dirthara\Schema\Identifier;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Constraint\ForeignKey;
use Dirthara\Schema\Constraint\ReferentialAction;
use Dirthara\Schema\Exceptions\InvalidSchemaException;

final class ForeignKeyTest extends TestCase
{
    private function key(string ...$columns): ForeignKey
    {
        return new ForeignKey(
            new Identifier('orders_user_id_foreign'),
            array_values(array_map(
                static fn(string $column): Identifier => new Identifier($column),
                $columns === [] ? ['user_id'] : $columns,
            )),
        );
    }

    #[Test]
    public function it_carries_its_name_and_columns(): void
    {
        $key = $this->key();

        self::assertSame('orders_user_id_foreign', $key->name->name);
        self::assertSame('user_id', $key->columns[0]->name);
    }

    #[Test]
    public function it_starts_without_a_target_or_actions(): void
    {
        $key = $this->key();

        self::assertNull($key->on);
        self::assertSame([], $key->references);
        self::assertNull($key->onDelete);
        self::assertNull($key->onUpdate);
    }

    #[Test]
    public function it_returns_itself_from_every_modifier(): void
    {
        $key = $this->key();

        self::assertSame($key, $key->references('id'));
        self::assertSame($key, $key->on('users'));
        self::assertSame($key, $key->onDelete(ReferentialAction::Cascade));
        self::assertSame($key, $key->onUpdate(ReferentialAction::Restrict));
    }

    #[Test]
    public function it_records_its_target(): void
    {
        $key = $this->key()->references('id')->on('users');

        self::assertSame('users', $key->on?->name);
        self::assertSame(['id'], array_map(static fn(Identifier $c): string => $c->name, $key->references));
    }

    #[Test]
    public function it_records_referential_actions(): void
    {
        $key = $this->key()->onDelete(ReferentialAction::SetNull)->onUpdate(ReferentialAction::Cascade);

        self::assertSame(ReferentialAction::SetNull, $key->onDelete);
        self::assertSame(ReferentialAction::Cascade, $key->onUpdate);
    }

    #[Test]
    public function it_rejects_a_referenced_column_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->key()->references('i d');
    }

    #[Test]
    public function it_rejects_a_referenced_table_a_grammar_would_have_to_escape(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->key()->on('user"s');
    }

    #[Test]
    public function it_is_complete_once_it_has_a_target(): void
    {
        self::assertNull($this->key()->references('id')->on('users')->incompleteness());
    }

    #[Test]
    public function it_reports_a_missing_table(): void
    {
        self::assertSame(
            'The foreign key [orders_user_id_foreign] does not say which table it references.',
            $this->key()->references('id')->incompleteness(),
        );
    }

    #[Test]
    public function it_reports_missing_referenced_columns(): void
    {
        self::assertSame(
            'The foreign key [orders_user_id_foreign] does not say which columns it references.',
            $this->key()->on('users')->incompleteness(),
        );
    }

    #[Test]
    public function it_reports_a_column_count_that_does_not_match(): void
    {
        self::assertSame(
            'The foreign key [orders_user_id_foreign] has 1 column(s) but references 2.',
            $this->key()->on('users')->references('id', 'tenant_id')->incompleteness(),
        );
    }

    #[Test]
    public function it_accepts_a_composite_key_with_matching_counts(): void
    {
        self::assertNull(
            $this->key('user_id', 'tenant_id')->on('users')->references('id', 'tenant_id')->incompleteness(),
        );
    }
}
