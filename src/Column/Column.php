<?php

declare(strict_types=1);

namespace Dirthara\Schema\Column;

use Dirthara\Schema\Identifier;
use Dirthara\Schema\Exception\InvalidSchemaException;

final class Column
{
    public private(set) ?int $length = null;

    public private(set) ?int $precision = null;

    public private(set) ?int $scale = null;

    public private(set) bool $nullable = false;

    public private(set) bool $hasDefault = false;

    public private(set) string|int|float|bool|null $default = null;

    public private(set) bool $autoIncrement = false;

    public private(set) bool $primary = false;

    public private(set) bool $unique = false;

    public private(set) bool $unsigned = false;

    public private(set) bool $changed = false;

    public function __construct(
        public readonly Identifier $name,
        public readonly ColumnType $type,
    ) {}

    /**
     * @throws InvalidSchemaException
     */
    public function length(int $length): self
    {
        if ($length < 1) {
            throw InvalidSchemaException::invalidLength($this->name->name, $length);
        }

        $this->length = $length;

        return $this;
    }

    /**
     * @throws InvalidSchemaException
     */
    public function precision(int $precision, int $scale): self
    {
        if ($precision < 1) {
            throw InvalidSchemaException::invalidPrecision($this->name->name, $precision);
        }

        if ($scale < 0 || $scale > $precision) {
            throw InvalidSchemaException::invalidScale($this->name->name, $precision, $scale);
        }

        $this->precision = $precision;
        $this->scale = $scale;

        return $this;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function default(string|int|float|bool|null $default): self
    {
        $this->hasDefault = true;
        $this->default = $default;

        return $this;
    }

    public function autoIncrement(bool $autoIncrement = true): self
    {
        $this->autoIncrement = $autoIncrement;

        return $this;
    }

    public function primary(bool $primary = true): self
    {
        $this->primary = $primary;

        return $this;
    }

    public function unique(bool $unique = true): self
    {
        $this->unique = $unique;

        return $this;
    }

    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    public function change(bool $changed = true): self
    {
        $this->changed = $changed;

        return $this;
    }
}
