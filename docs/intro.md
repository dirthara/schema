---
id: intro
title: Dirthara Schema
sidebar_label: Introduction
sidebar_position: 1
description: Describe a table once and compile the DDL for MySQL, PostgreSQL, SQLite or SQL Server.
---

# Dirthara Schema

Describe a table once and let the package write the `CREATE TABLE` for whichever
database the connection points at. The same definition compiles for MySQL,
PostgreSQL, SQLite and SQL Server.

```php
$schema->create('users', function (Table $table): void {
    $table->id();
    $table->string('email', 255)->unique();
    $table->string('name')->nullable();
    $table->timestamps();
});
```

## What it does

- **A definition, not a string.** You describe columns, keys and indexes as
  objects. A grammar turns them into the dialect of the database you are
  connected to.
- **Identifiers are validated, then quoted.** A table or column name cannot be
  bound as a parameter, so it is checked against a strict pattern before it ever
  reaches a statement.
- **Alters use the same definition.** Adding a column, dropping one and renaming
  one are recorded on the same `Table` object that describes a create.
- **Differences are reported, not hidden.** Where a database genuinely cannot do
  something, the package says so instead of guessing.

## What it does not do

- It does not run or track migrations. It compiles and executes schema
  statements; deciding which ones to run, and in what order, belongs to a
  migration package built on top of this one.
- It does not read an existing schema back. There is `hasTable()` and nothing
  more; full introspection is not implemented.

## Where to go next

- [Installation](installation.md) for the requirements and the Composer package.
- [Getting started](getting-started.md) to build and run a first table.
- [Defining columns](tables/columns.md) for every column type and modifier.
- [Dialect differences](grammars/dialect-differences.md) before you rely on
  something one database does and another does not.
