---
id: error-handling
title: Error handling
sidebar_position: 7
description: The exception hierarchy, the context each exception carries, and what to log.
---

# Error handling

## The hierarchy

Every exception the package throws extends `SchemaException`, so one `catch`
covers all of them.

```text
SchemaException
├── InvalidSchemaException
├── InvalidTableDefinitionException
├── UnsupportedDriverException
├── SchemaConnectionException
├── SchemaExecutionException
└── SchemaIntrospectionException
```

| Exception | Thrown when |
| --- | --- |
| `InvalidSchemaException` | A name, length, precision or default is not usable. |
| `InvalidTableDefinitionException` | The definition contradicts itself: two primary keys, a duplicate key name, an incomplete foreign key. |
| `UnsupportedDriverException` | This database cannot do what was asked, or no grammar is registered for the driver. |
| `SchemaConnectionException` | The connection could not be resolved or reached. |
| `SchemaExecutionException` | The server rejected a schema statement. |
| `SchemaIntrospectionException` | The server rejected an introspection query, such as the one behind `hasTable()` or `dropAll()`, or returned a table the grammar could not read. |

The first three are raised before anything is sent, so a definition that cannot
compile never touches the database.

## Context

`SchemaException` carries an `array<string, mixed>` of diagnostic data
alongside the message.

```php
try {
    $schema->create('users', $definition);
} catch (SchemaException $exception) {
    $logger->error($exception->getMessage(), $exception->getContext() + [
        'exception' => $exception,
    ]);
}
```

The `exception` key must contain the caught exception even when the context
already has an entry under that name.

`addContext()` merges more in and returns the exception, so a layer that knows
something the thrower did not can add it and rethrow:

```php
throw $exception->addContext(['migration' => $migration::class]);
```

### What each carries

| Source | Keys |
| --- | --- |
| A failed statement | `connection`, `driver`, `operation`, `table`, `query` |
| A failed rename | the above, plus `to` |
| A failed `dropAll()` statement | `connection`, `driver`, `operation`, `tables`, `query` |
| A `dropAll()` whose cleanup also failed | the above, plus `cleanup_query` |
| A failed table listing for `dropAll()` | `connection`, `driver`, `operation`, `query` |
| A listed table the grammar could not read | `driver`, `column`, `connection`, `operation`, `tables` |
| An invalid name | `identifier`, and `table` when it was declared on one |
| An invalid length or precision | `column`, plus `length`, `precision` or `scale` |
| A duplicate column | `table`, `column` |
| Too many primary keys | `table`, `primary_keys` |
| A duplicate key name | `table`, `key` |
| An incomplete foreign key | `table`, `key` |
| An unsupported operation | `driver`, `operation`, `subject` |
| An unregistered driver | `driver` |

`operation` is the value of an `Operation` case: `create`,
`create_if_not_exists`, `alter`, `drop`, `drop_if_exists`, `rename`, `drop_all`
or `has_table`.

`dropAll()` works on many tables at once, so its context carries `tables` — the
names it was about to drop — rather than a single `table`. The failing statement
in `query` says which one the server refused.

When a `dropAll()` statement fails and the statement that restores a setting
afterwards fails too, the exception thrown is the one for the drop, because that
is what went wrong first. `cleanup_query` in its context says which restoring
statement also failed, so you know the connection may be left with that setting
changed.

:::danger
Context is written to logs. It never carries a username, a password or a
credential-bearing DSN, and a grammar of your own must keep that split. A
default value does appear in the compiled `query`, so do not put a secret in
one.
:::

## The original is always attached

An exception raised by `dirthara/database` is wrapped, not swallowed. The
original is the `previous` exception, so the driver's own message and SQLSTATE
stay reachable.

```php
catch (SchemaExecutionException $exception) {
    $exception->getPrevious();   // the QueryException from dirthara/database
}
```

Wrapping is deliberate: a caller should not need `dirthara/database` in a
`catch` block to handle a failure this package caused.

## Catching the right thing

```php
use Dirthara\Schema\Exceptions\UnsupportedDriverException;
use Dirthara\Schema\Exceptions\InvalidTableDefinitionException;

try {
    $schema->table('users', $changes);
} catch (InvalidTableDefinitionException $exception) {
    // the definition is wrong; fix the code
} catch (UnsupportedDriverException $exception) {
    // this database cannot do it; the definition may be fine elsewhere
}
```

The distinction matters when SQLite is your test database and something else
runs in production: `UnsupportedDriverException` says the definition is
unsupported *here*, not that it is wrong. See
[Dialect differences](grammars/dialect-differences.md).
