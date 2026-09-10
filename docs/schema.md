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

Passing `null` as the connection uses the default connection configured in
`dirthara/database`.

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
