---
id: columns
title: Defining columns
sidebar_position: 1
description: Every column type, the modifiers you can chain, and how identifiers are validated.
---

# Defining columns

Inside a `create()` or `table()` callback, each method on `Table` declares one
column and returns it, so modifiers chain.

```php
$table->string('email', 255)->nullable()->default(null);
```

## Column types

| Method | Type | Notes |
| --- | --- | --- |
| `id(string $name = 'id')` | `BigInteger` | Shorthand for an unsigned, auto-incrementing primary key. |
| `boolean(string $name)` | `Boolean` | |
| `tinyInteger(string $name)` | `TinyInteger` | |
| `smallInteger(string $name)` | `SmallInteger` | |
| `integer(string $name)` | `Integer` | |
| `bigInteger(string $name)` | `BigInteger` | |
| `decimal(string $name, int $precision = 8, int $scale = 2)` | `Decimal` | Exact numeric. Use it for money. |
| `float(string $name)` | `Float` | |
| `double(string $name)` | `Double` | |
| `char(string $name, int $length = 255)` | `Char` | Fixed length. |
| `string(string $name, int $length = 255)` | `String` | |
| `text(string $name)` | `Text` | |
| `date(string $name)` | `Date` | |
| `time(string $name)` | `Time` | |
| `dateTime(string $name)` | `DateTime` | |
| `timestamp(string $name)` | `Timestamp` | |
| `uuid(string $name = 'uuid')` | `Uuid` | Native `UUID` on PostgreSQL, `CHAR(36)` elsewhere. |
| `json(string $name)` | `Json` | |
| `binary(string $name)` | `Binary` | |
| `column(string $name, ColumnType $type)` | any | The escape hatch when the type is in a variable. |
| `timestamps(string $created = 'created_at', string $updated = 'updated_at')` | `Timestamp` | Declares both, nullable. Returns `void`. |

What each type becomes per database is in
[Type mapping](../grammars/type-mapping.md).

:::note
`timestamps()` is the only one that returns `void` rather than a column, because
it declares two. Reach for `timestamp()` twice if you need to modify them.
:::

## Modifiers

Every modifier returns the column.

| Modifier | Default | Meaning |
| --- | --- | --- |
| `nullable(bool $nullable = true)` | column is `NOT NULL` | Allows `NULL`. |
| `default(scalar or null $value)` | no default | Sets a `DEFAULT`. |
| `length(int $length)` | set by `string()` and `char()` | Rejects anything below `1`. |
| `precision(int $precision, int $scale)` | set by `decimal()` | Rejects a scale above the precision or below `0`. |
| `unsigned(bool $unsigned = true)` | signed | Only MySQL applies it; see below. |
| `autoIncrement(bool $autoIncrement = true)` | off | The database generates the value. |
| `primary(bool $primary = true)` | off | Joins the table's primary key. |
| `unique(bool $unique = true)` | off | Adds a unique constraint. |
| `change(bool $changed = true)` | off | Marks this as a change to an existing column. See [Altering tables](altering-tables.md). |

Each flag takes a boolean, so `nullable(false)` turns one back off — useful when
a definition is built up conditionally.

:::caution
`unsigned()` is only honoured by MySQL. PostgreSQL, SQLite and SQL Server have no
unsigned integer types, and their grammars drop the modifier rather than emit
something the server would reject. A column you rely on being non-negative needs
a check constraint, which this package does not yet model.
:::

## Defaults are written into the statement

A `DEFAULT` is part of the `CREATE TABLE`, not an argument to it, so the value
cannot be bound as a parameter. Each grammar escapes it for its own database:
quotes are doubled everywhere, MySQL also escapes backslashes, and SQL Server
prefixes the literal with `N` so a unicode default survives.

```php
$table->string('status', 40)->default("O'Brien");
```

```sql
-- MySQL
DEFAULT 'O''Brien'
-- SQL Server
DEFAULT N'O''Brien'
```

A default containing a null byte is rejected with `InvalidSchemaException`
rather than truncated into the statement.

`default(null)` is not the same as leaving the default off. The first compiles
`DEFAULT NULL`, the second compiles no default clause at all.

## Names are validated, not escaped

Every table and column name must match:

```text
/^[A-Za-z_][A-Za-z0-9_]*$/
```

Letters, digits and underscores, not starting with a digit. Anything else —
quotes, spaces, semicolons, parentheses, hyphens — throws
`InvalidSchemaException`.

The reason is that a name cannot be a bound parameter, so it ends up as text in
the statement. Rejecting is safer than escaping: escaping would put the
guarantee in four dialects' quoting rules agreeing with each other, and they do
not.

:::caution
A dot is rejected too, so a schema-qualified name such as `public.users` is not
supported. Connect to the schema you mean instead.
:::
