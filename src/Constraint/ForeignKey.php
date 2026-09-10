<?php

declare(strict_types=1);

namespace Dirthara\Schema\Constraint;

use Dirthara\Schema\Identifier;
use Dirthara\Schema\Exceptions\InvalidSchemaException;

use function count;
use function sprintf;
use function array_map;
use function array_values;

/**
 * A reference from this table's columns to another table's columns.
 *
 * The target is chained on rather than required up front, so a definition
 * reads in the order it is spoken: these columns reference that column on
 * that table. A key that never got a target is caught when the definition is
 * read, not silently compiled into nothing.
 */
final class ForeignKey implements Constraint
{
    public private(set) ?Identifier $on = null;

    /**
     * @var list<Identifier>
     */
    public private(set) array $references = [];

    public private(set) ?ReferentialAction $onDelete = null;

    public private(set) ?ReferentialAction $onUpdate = null;

    /**
     * @param list<Identifier> $columns
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly array $columns,
    ) {}

    /**
     * The columns this key points at on the referenced table.
     *
     * @throws InvalidSchemaException
     */
    public function references(string ...$columns): self
    {
        $this->references = array_values(array_map(
            static fn(string $column): Identifier => new Identifier($column),
            $columns,
        ));

        return $this;
    }

    /**
     * @throws InvalidSchemaException
     */
    public function on(string $table): self
    {
        $this->on = new Identifier($table);

        return $this;
    }

    public function onDelete(ReferentialAction $action): self
    {
        $this->onDelete = $action;

        return $this;
    }

    public function onUpdate(ReferentialAction $action): self
    {
        $this->onUpdate = $action;

        return $this;
    }

    /**
     * Why this key cannot be compiled yet, or null when it can.
     */
    public function incompleteness(): ?string
    {
        if ($this->on === null) {
            return sprintf('The foreign key [%s] does not say which table it references.', $this->name->name);
        }

        if ($this->references === []) {
            return sprintf('The foreign key [%s] does not say which columns it references.', $this->name->name);
        }

        if (count($this->references) !== count($this->columns)) {
            return sprintf(
                'The foreign key [%s] has %d column(s) but references %d.',
                $this->name->name,
                count($this->columns),
                count($this->references),
            );
        }

        return null;
    }
}
