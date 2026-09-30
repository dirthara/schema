---
id: schema
title: Schema and connections
sidebar_position: 4
description: The Schema and ConnectedSchema classes, choosing a connection, and registering grammars.
---

# Schema and connections

## Two classes

`Schema` is the entry point. Every method takes an optional connection name and
resolves the right connection and grammar for you.

`ConnectedSchema` is the same set of methods already bound to one connection and
one grammar. `Schema` delegates to it, and `using()` hands you one directly.

```php
$schema->drop('users', 'reporting');

// the same thing
$schema->using('reporting')->drop('users');
```

Reach for `using()` when several statements share a connection, so the lookup
happens once and the intent reads once.

## The methods

| Method | Returns | Notes |
| --- | --- | --- |
| `using(?string $connection = null)` | `ConnectedSchema` | Resolves the connection and its grammar. |
| `create(string $table, Closure $callback, ?string $connection = null)` | `void` | Fails if the table exists. |
| `createIfNotExists(string $table, Closure $callback, ?string $connection = null)` | `void` | |
| `table(string $table, Closure $callback, ?string $connection = null)` | `void` | Applies changes to an existing table. |
| `drop(string $table, ?string $connection = null)` | `void` | Fails if the table is missing. |
| `dropIfExists(string $table, ?string $connection = null)` | `void` | |
| `rename(string $from, string $to, ?string $connection = null)` | `void` | |
| `hasTable(string $table, ?string $connection = null)` | `bool` | |
| `dropAll(?string $connection = null)` | `void` | Drops every user table on the connection. See [Dropping every table](#dropping-every-table). |

Passing `null` as the connection uses the default connection configured in
`dirthara/database`.

## Dropping every table

:::danger
`dropAll()` destroys every user table on the connection and all the data in
them. It does not ask which tables are yours, and nothing it removes can be
recovered. Point it only at a connection you mean to empty.
:::

```php
$schema->dropAll();

// a named connection
$schema->dropAll('reporting');

// the same thing
$schema->using('reporting')->dropAll();
```

It lists the tables the connection can see, then drops them. You do not pass
table names and you do not have to work out an order: tables that reference one
another through foreign keys, including ones that reference each other in a
cycle, are dropped together.

### What it removes

Every user table in the database or schema the connection is pointed at, and
nothing else:

| Database | Scope | Left alone |
| --- | --- | --- |
| MySQL | The connection's database, `DATABASE()` | Other databases on the server, views |
| PostgreSQL | The first schema on the search path, `CURRENT_SCHEMA()` | Other schemas, views, tables an extension owns |
| SQLite | The `main` database | Attached databases, temporary tables, SQLite's own `sqlite_` tables |
| SQL Server | The connection's default schema, `SCHEMA_ID()` | Other schemas and databases, tables the server ships |

A table is removed whether or not it was created through this package, and
whatever its name — including a name `create()` would refuse. The schema itself
is never dropped or recreated, so its owner, grants and default privileges are
untouched.

Tables are the only objects in scope, because they are the only objects this
package models. Views, sequences you created yourself, stored procedures,
functions, triggers, types and extensions are neither listed nor dropped. A
trigger or index belongs to its table and goes with it.

### What it does not do

`dropAll()` is a schema operation. It knows nothing about migrations:

- it does not read migration history, so a history table on the connection is
  dropped like any other
- it does not call anyone's rollback logic
- it touches one connection. It never goes looking for the others you have
  configured; to empty several, call it once for each.

### An empty schema

With no tables to drop it does nothing and succeeds, so calling it twice is
safe.

### Foreign keys

| Database | How related tables are dropped |
| --- | --- |
| MySQL | One `DROP TABLE` naming every table. MySQL resolves the references between them itself. |
| PostgreSQL | One `DROP TABLE` naming every table, without `CASCADE`. |
| SQLite | One `DROP TABLE` per table. Foreign key enforcement, if on, is switched off first and back on afterwards. |
| SQL Server | Each foreign key declared on the tables is dropped first, then each table. |

No setting is changed on MySQL, PostgreSQL or SQL Server. On SQLite the change
is made on the connection only, and enforcement is switched back on after the
drops whether or not they succeeded. Enforcement that was off is left off.

:::caution
SQLite ignores a change to foreign key enforcement inside a transaction. Called
inside one, with enforcement on, `dropAll()` cannot drop a table another table's
rows still reference, and throws a `SchemaExecutionException` instead. Call it
outside a transaction.
:::

A reference from outside the scope stops the drop rather than being removed: a
table in another MySQL database or another schema that references one of these
tables makes the statement fail. So does a PostgreSQL view over one of the
tables, because dropping the table would mean dropping the view with it. That
is deliberate — `dropAll()` never reaches past the tables it was asked to drop.

### When it fails

A failure throws, and the exception names the operation as `drop_all`. Listing
the tables that fails throws `SchemaIntrospectionException`; a drop the server
refuses throws `SchemaExecutionException`. See
[Error handling](error-handling.md).

:::caution
`dropAll()` is not atomic, and it does not pretend to be. It is not wrapped in a
transaction, because MySQL cannot roll back DDL and wrapping it would promise
something only some databases keep. On PostgreSQL the single statement either
drops every table or none; on MySQL an InnoDB-only drop is atomic too. On SQLite
and SQL Server each table is its own statement, so a failure partway leaves the
tables before it dropped and the ones after it in place. After a failure, assume
nothing about what is left and run it again once the cause is fixed.
:::

## Registering grammars

`SchemaGrammarResolver` maps a driver to the grammar that compiles for it. It
takes an iterable keyed by driver, and grammars can also be added afterwards.

```php
use Dirthara\Schema\Grammar\SchemaGrammarResolver;
use Dirthara\Schema\Grammar\PostgresSqlSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;

$grammars = new SchemaGrammarResolver();
$grammars->register(DriverName::PostgresSql, new PostgresSqlSchemaGrammar());

$grammars->resolve(DriverName::PostgresSql);
```

Both `register()` and `resolve()` accept a `DriverName` or its string value.

| Situation | Exception |
| --- | --- |
| Two grammars registered for one driver | `InvalidSchemaException` |
| Resolving a driver with no grammar | `UnsupportedDriverException` |

Registering twice is treated as a mistake rather than a replacement, because
silently taking the second one hides a wiring bug that only shows up as wrong
SQL.

## Statements run one at a time

A single operation can need more than one statement — a create with an index on
SQLite is a `CREATE TABLE` followed by a `CREATE INDEX`. Those are executed in
order, as separate statements, because a connection prepares what it is given
and no driver accepts two statements in one prepare.

:::caution
They are not wrapped in a transaction. Most databases commit DDL implicitly
anyway, and MySQL cannot roll it back at all. A create that fails partway can
leave the table without its indexes.
:::
