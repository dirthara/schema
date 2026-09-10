---
id: keys-and-indexes
title: Keys and indexes
sidebar_position: 2
description: Primary keys, unique constraints, foreign keys and indexes, and the names they are given.
---

# Keys and indexes

## Declaring them

| Method | Returns | Compiles to |
| --- | --- | --- |
| `primary(string or array $columns, ?string $name = null)` | `PrimaryKey` | `PRIMARY KEY` |
| `unique(string or array $columns, ?string $name = null)` | `UniqueConstraint` | `UNIQUE` |
| `foreign(string or array $columns, ?string $name = null)` | `ForeignKey` | `FOREIGN KEY` |
| `index(string or array $columns, ?string $name = null)` | `Index` | `CREATE INDEX` |

Each takes one column name or a list of them.

```php
$table->primary(['tenant_id', 'id']);
$table->unique('email');
$table->index(['last_name', 'first_name']);
```

A key over no columns throws `InvalidTableDefinitionException`.

## Two ways to say the same thing

A column can carry `primary()` and `unique()` itself. These are equivalent:

```php
$table->string('email')->unique();
$table->string('email');
$table->unique('email');
```

A column flag is turned into a real constraint when the definition is read, so a
grammar sees uniqueness and primary keys in one place and never has to check
both. The flag version is appended after the columns; an explicitly declared key
stays where you wrote it.

Every column flagged `primary()` joins **one** key rather than producing several,
because a table has a single primary key covering however many columns:

```php
$table->bigInteger('tenant_id')->primary();
$table->bigInteger('id')->primary();
// -> PRIMARY KEY ("tenant_id", "id")
```

Declaring a primary key twice — once with a flag and once with `primary()` —
throws `InvalidTableDefinitionException`.

## Foreign keys

The target is chained on, so the definition reads in the order you would say it.

```php
use Dirthara\Schema\Constraint\ReferentialAction;

$table->foreign('team_id')
    ->references('id')
    ->on('teams')
    ->onDelete(ReferentialAction::Cascade)
    ->onUpdate(ReferentialAction::Restrict);
```

| `ReferentialAction` | Clause |
| --- | --- |
| `Cascade` | `CASCADE` |
| `Restrict` | `RESTRICT` |
| `SetNull` | `SET NULL` |
| `SetDefault` | `SET DEFAULT` |
| `NoAction` | `NO ACTION` |

There is no case for "whatever the database does by default". Leaving the action
unset says that, and says it without claiming to know which of the above the
default happens to be.

A foreign key that never got a table, never got referenced columns, or whose
column counts do not line up throws `InvalidTableDefinitionException` when the
definition is read — before any SQL is sent.

## Generated names

A name you do not supply is built from the table, the columns and what the key
is for:

| Key | Generated name |
| --- | --- |
| `index('name')` | `users_name_index` |
| `index(['last', 'first'])` | `users_last_first_index` |
| `unique('email')` | `users_email_unique` |
| `foreign('team_id')` | `users_team_id_foreign` |
| `primary('id')` | `users_primary` |

A primary key is named after the table alone, because there is only one and the
columns it covers can change without the name having to.

The names are predictable on purpose: dropping a key later means naming it, and
you need to be able to work out what it was called.

```php
$table->dropIndex('users_name_index');
$table->dropConstraint('users_email_unique');
$table->dropPrimary();
```

:::note
Two keys resolving to the same name throw `InvalidTableDefinitionException`. That
usually means the same key was declared twice, once as a column flag and once
explicitly.
:::

:::caution
Long names can exceed a database's identifier limit — 63 characters on
PostgreSQL, 64 on MySQL. A composite index over several long column names can
reach it. Pass a name explicitly when that happens; the package does not
truncate for you.
:::
