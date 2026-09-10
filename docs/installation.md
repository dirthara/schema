---
id: installation
title: Installation
sidebar_position: 2
description: Requirements, the Composer package, and wiring a Schema instance.
---

# Installation

```sh
composer require dirthara/schema
```

## Requirements

| Requirement | Why |
| --- | --- |
| PHP 8.5 | The package uses asymmetric property visibility and interface properties. |
| `ext-pdo` | Statements are executed through the connection from `dirthara/database`. |
| `dirthara/database` `^0.1` | Owns the connections, drivers and PDO handling this package compiles for. |

Each database also needs its own PDO extension. Install only the ones you use:

| Database | Extension |
| --- | --- |
| MySQL | `pdo_mysql` |
| PostgreSQL | `pdo_pgsql` |
| SQLite | `pdo_sqlite` |
| SQL Server | `pdo_sqlsrv` |

## Wiring it up

A `Schema` needs two things: the `Database` it should run statements on, and a
resolver that knows which grammar belongs to which driver.

```php
use Dirthara\Schema\Schema;
use Dirthara\Schema\Grammar\SchemaGrammarResolver;
use Dirthara\Schema\Grammar\MySqlSchemaGrammar;
use Dirthara\Schema\Grammar\SQLiteSchemaGrammar;
use Dirthara\Database\Connection\Driver\DriverName;

$grammars = new SchemaGrammarResolver([
    DriverName::SQLite->value => new SQLiteSchemaGrammar(),
    DriverName::MySql->value => new MySqlSchemaGrammar(),
]);

$schema = new Schema($database, $grammars);
```

Register only the grammars you need. Asking for a driver that has none throws
[`UnsupportedDriverException`](error-handling.md), which is a clearer failure
than compiling the wrong dialect.

:::tip
A framework integration would build this once and hand the `Schema` to
application code. Nothing here holds a connection, so building it early costs
nothing.
:::

See [Schema and connections](schema.md) for what `Schema` exposes and how it
picks a connection.
