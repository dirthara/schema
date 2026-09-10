---
id: altering-tables
title: Altering tables
sidebar_position: 3
description: Adding, dropping, renaming and changing columns and keys on a table that already exists.
---

# Altering tables

`table()` records changes against a table that already exists. The callback
receives the same `Table` a create does, and the order you write them in is the
order they run.

```php
$schema->table('users', function (Table $table): void {
    $table->string('phone', 40)->nullable();
    $table->renameColumn('name', 'full_name');
    $table->dropColumn('legacy_id');
    $table->unique('email');
});
```

## What you can record

| Call | Change |
| --- | --- |
| any column method | Add the column |
| a column method with `change()` | Modify the existing column |
| `dropColumn(string ...$names)` | Drop one or more columns |
| `renameColumn(string $from, string $to)` | Rename a column |
| `index()`, `unique()`, `primary()`, `foreign()` | Add the key |
| `dropIndex(string ...$names)` | Drop one or more indexes |
| `dropConstraint(string ...$names)` | Drop one or more constraints |
| `dropPrimary(?string $name = null)` | Drop the primary key |

## Changing a column

`change()` marks a column as a modification rather than an addition.

```php
$table->string('email', 320)->nullable()->change();
```

:::caution
A modification replaces the column's definition; it is not merged with what is
already there. Anything you do not restate is reset — on PostgreSQL a
`change()` without `default()` compiles `DROP DEFAULT`. Restate every modifier
the column should keep.
:::

Not every database can do this. SQLite has no statement for it at all, and SQL
Server cannot change a column and its default in one step. Both refuse with
`UnsupportedDriverException`; see
[Dialect differences](../grammars/dialect-differences.md).

## Order matters

Changes are kept in the order they were recorded, because dropping a column and
adding one of the same name is not the same statement in either order. The one
exception is constraints promoted from a column flag, which are appended after
the columns — a key cannot be declared before the column it covers exists.

## An alter is not a diff

The package never reads the current table. It compiles what you asked for and
nothing else, which has two consequences worth knowing:

- Declaring a column inside `table()` **adds** it. If it already exists the
  database will say so.
- Removing a column from your definition does nothing. Dropping is something you
  ask for with `dropColumn()`.

:::note
`hasTable()` is the only introspection the package offers. There is no way to
read back a table's columns, so a definition cannot be reconciled against a live
schema. That belongs to a migration package built on top of this one.
:::

## Validation happens before any SQL

The definition is checked when it is read, so a contradiction is reported
without touching the database:

| Problem | Exception |
| --- | --- |
| Two primary keys | `InvalidTableDefinitionException` |
| Two keys with the same name | `InvalidTableDefinitionException` |
| A foreign key with no target or mismatched column counts | `InvalidTableDefinitionException` |
| A key over no columns | `InvalidTableDefinitionException` |
| An invalid table or column name | `InvalidSchemaException` |
| Something this database cannot do | `UnsupportedDriverException` |
