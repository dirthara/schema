<?php

declare(strict_types=1);

namespace Dirthara\Schema\Tests\Constraint;

use Dirthara\Schema\Identifier;
use PHPUnit\Framework\TestCase;
use Dirthara\Schema\Index\Index;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Schema\Constraint\PrimaryKey;
use Dirthara\Schema\Constraint\UniqueConstraint;

final class ConstraintTest extends TestCase
{
    #[Test]
    public function a_primary_key_carries_its_name_and_columns(): void
    {
        $key = new PrimaryKey(new Identifier('users_primary'), [new Identifier('id')]);

        self::assertSame('users_primary', $key->name->name);
        self::assertSame('id', $key->columns[0]->name);
    }

    #[Test]
    public function a_unique_constraint_carries_its_name_and_columns(): void
    {
        $constraint = new UniqueConstraint(new Identifier('users_email_unique'), [new Identifier('email')]);

        self::assertSame('users_email_unique', $constraint->name->name);
        self::assertSame('email', $constraint->columns[0]->name);
    }

    #[Test]
    public function an_index_carries_its_name_and_columns(): void
    {
        $index = new Index(new Identifier('users_name_index'), [new Identifier('name')]);

        self::assertSame('users_name_index', $index->name->name);
        self::assertSame('name', $index->columns[0]->name);
    }
}
