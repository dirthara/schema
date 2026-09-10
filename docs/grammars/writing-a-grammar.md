---
id: writing-a-grammar
title: Writing a grammar
sidebar_position: 3
description: The SchemaGrammar contract, the hooks SqlSchemaGrammar leaves open, and how to add a dialect.
---

# Writing a grammar

A grammar turns a definition into statements. Adding support for another
database means writing one and registering it.

## The contract

```php
interface SchemaGrammar
{
    public function compileCreate(string $table, Table $definition, bool $ifNotExists): CompiledSchema;

    public function compileAlter(string $table, Table $definition): CompiledSchema;

    public function compileDrop(string $table, bool $ifExists): CompiledSchema;

    public function compileRename(string $from, string $to): CompiledSchema;

    public function compileHasTable(string $table): CompiledSchema;
}
```

`CompiledSchema` holds a `list<string>` of statements, run in order.
`compileHasTable()` must compile a query whose result set is empty when the
table does not exist.

## Start from the base

Implementing the interface directly means rewriting the shape of a
`CREATE TABLE`. `SqlSchemaGrammar` already has it, and leaves five methods for a
dialect to fill in:

| Method | What it decides |
| --- | --- |
| `driver(): DriverName` | Which driver this compiles for, used in exception context. |
| `wrap(Identifier $identifier): string` | How a name is quoted. |
| `type(Column $column): string` | The native type for each `ColumnType`. |
| `inlinePrimaryKeyColumn(?PrimaryKey $primary, array $columns): ?Column` | The column a key is declared on, or `null` for a table constraint. |
| `modifyColumn(Identifier $table, Column $column): string` | How an existing column is changed. |
| `compileHasTable(string $table): CompiledSchema` | The introspection query. |

Everything else has a standard-SQL implementation you override only when your
database disagrees. The ones dialects reach for most:

| Hook | Default | Overridden by |
| --- | --- | --- |
| `unsigned(Column $column)` | `' UNSIGNED'` | SQLite, PostgreSQL, SQL Server return `''` |
| `autoIncrement(Column $column, bool $inlinePrimaryKey)` | `''` | each dialect |
| `boolean(bool $value)` | `'1'` or `'0'` | PostgreSQL returns `TRUE` or `FALSE` |
| `escape(string $value)` | doubles quotes | MySQL also escapes backslashes |
| `stringLiteral(string $value)` | `'…'` | SQL Server prefixes `N` |
| `inlinesIndexes()` | `false` | MySQL and SQL Server return `true` |
| `createIndex()`, `dropIndex()` | `CREATE INDEX`, `DROP INDEX … ON` | SQLite and PostgreSQL |
| `addColumn()`, `renameColumn()` | `ALTER TABLE …` | SQL Server |

## Refusing an operation

Where a database genuinely cannot do something, refuse with the helper rather
than emitting something that will fail confusingly:

```php
throw $this->unsupported(
    'SQLite cannot change the column on an existing table.',
    $column->name,
    'alter',
);
```

That builds an `UnsupportedDriverException` carrying the driver, the operation
and the subject.

## Rules to keep

:::danger
Never concatenate a name into SQL yourself. Pass it through `wrap()`, and turn a
raw string into an `Identifier` with `identifier()` first so it is validated.
A value that reaches a statement unchecked is the one bug class this package
exists to prevent.
:::

Use `literal()` for defaults rather than quoting inline. It handles null,
booleans, integers, floats and strings, and rejects a null byte.

Exception context carries the connection, driver, operation, table and SQL —
never a username, a password or a credential-bearing DSN.

## Registering it

```php
$grammars->register(DriverName::MySql, new MyOwnMySqlGrammar());
```

## Proving it works

Compiled SQL that reads correctly is not evidence a server accepts it, and a
constraint appearing in a statement is not evidence it is enforced. The package
keeps a shared conformance suite in `tests/Integration` that creates real
tables, then asks the server to break its own rules. A new dialect subclasses
`SchemaConformanceTestCase`, supplies a driver, config and grammar, and inherits
every shared test.
