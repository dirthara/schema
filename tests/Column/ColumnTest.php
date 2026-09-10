<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Column;

use Dirthara\Schema\Identifier;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Column\Column;
use Dirthara\Schema\Column\ColumnType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Schema\Exceptions\InvalidSchemaException;

final class ColumnTest extends TestCase
{
    private function column(ColumnType $type = ColumnType::String): Column
    {
        return new Column(new Identifier('email'), $type);
    }

    #[Test]
    public function it_carries_its_name_and_type(): void
    {
        $column = $this->column(ColumnType::Text);

        self::assertSame('email', $column->name->name);
        self::assertSame(ColumnType::Text, $column->type);
    }

    #[Test]
    public function it_starts_with_every_modifier_off(): void
    {
        $column = $this->column();

        self::assertNull($column->length);
        self::assertNull($column->precision);
        self::assertNull($column->scale);
        self::assertFalse($column->nullable);
        self::assertFalse($column->hasDefault);
        self::assertNull($column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->primary);
        self::assertFalse($column->unique);
        self::assertFalse($column->unsigned);
        self::assertFalse($column->changed);
    }

    #[Test]
    public function it_returns_itself_from_every_modifier(): void
    {
        $column = $this->column();

        self::assertSame($column, $column->length(10));
        self::assertSame($column, $column->precision(8, 2));
        self::assertSame($column, $column->nullable());
        self::assertSame($column, $column->default('x'));
        self::assertSame($column, $column->autoIncrement());
        self::assertSame($column, $column->primary());
        self::assertSame($column, $column->unique());
        self::assertSame($column, $column->unsigned());
        self::assertSame($column, $column->change());
    }

    #[Test]
    public function it_applies_a_chain_of_modifiers(): void
    {
        $column = $this->column()->length(255)->nullable()->unique();

        self::assertSame(255, $column->length);
        self::assertTrue($column->nullable);
        self::assertTrue($column->unique);
    }

    #[Test]
    public function it_turns_a_flag_back_off(): void
    {
        $column = $this->column()->nullable()->nullable(false)->primary()->primary(false);

        self::assertFalse($column->nullable);
        self::assertFalse($column->primary);
    }

    /**
     * @return iterable<string, array{string|int|float|bool|null}>
     */
    public static function defaults(): iterable
    {
        yield 'a string' => ['pending'];
        yield 'an integer' => [0];
        yield 'a float' => [1.5];
        yield 'a boolean' => [true];
        yield 'null' => [null];
    }

    #[Test]
    #[DataProvider('defaults')]
    public function it_records_a_default(string|int|float|bool|null $default): void
    {
        $column = $this->column()->default($default);

        self::assertTrue($column->hasDefault);
        self::assertSame($default, $column->default);
    }

    #[Test]
    public function it_separates_a_null_default_from_no_default(): void
    {
        self::assertFalse($this->column()->hasDefault);
        self::assertTrue($this->column()->default(null)->hasDefault);
    }

    #[Test]
    public function it_records_a_precision_and_a_scale(): void
    {
        $column = $this->column(ColumnType::Decimal)->precision(10, 4);

        self::assertSame(10, $column->precision);
        self::assertSame(4, $column->scale);
    }

    #[Test]
    public function it_allows_a_scale_equal_to_the_precision(): void
    {
        self::assertSame(4, $this->column(ColumnType::Decimal)->precision(4, 4)->scale);
    }

    #[Test]
    public function it_rejects_a_length_below_one(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('The length of column [email] must be at least 1, got 0.');

        $this->column()->length(0);
    }

    #[Test]
    public function it_reports_the_length_it_rejected(): void
    {
        try {
            $this->column()->length(-5);
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['column' => 'email', 'length' => -5], $exception->getContext());
        }
    }

    #[Test]
    public function it_rejects_a_precision_below_one(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('The precision of column [email] must be at least 1, got 0.');

        $this->column(ColumnType::Decimal)->precision(0, 0);
    }

    #[Test]
    public function it_reports_the_precision_it_rejected(): void
    {
        try {
            $this->column(ColumnType::Decimal)->precision(0, 0);
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['column' => 'email', 'precision' => 0], $exception->getContext());
        }
    }

    #[Test]
    public function it_rejects_a_scale_above_the_precision(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('The scale of column [email] must be between 0 and its precision of 4, got 6.');

        $this->column(ColumnType::Decimal)->precision(4, 6);
    }

    #[Test]
    public function it_rejects_a_negative_scale(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->column(ColumnType::Decimal)->precision(4, -1);
    }

    #[Test]
    public function it_reports_the_scale_it_rejected(): void
    {
        try {
            $this->column(ColumnType::Decimal)->precision(4, 6);
        } catch (InvalidSchemaException $exception) {
            self::assertSame(['column' => 'email', 'precision' => 4, 'scale' => 6], $exception->getContext());
        }
    }

    #[Test]
    public function it_leaves_the_precision_untouched_when_the_scale_is_rejected(): void
    {
        $column = $this->column(ColumnType::Decimal);

        try {
            $column->precision(4, 6);
        } catch (InvalidSchemaException) {
            self::assertNull($column->precision);
            self::assertNull($column->scale);
        }
    }
}
