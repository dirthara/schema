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
    public function compileCreate(Table $definition, bool $ifNotExists): CompiledSchema;

    public function compileAlter(Table $definition): CompiledSchema;

    public function compileDrop(string $table, bool $ifExists): CompiledSchema;

    public function compileRename(string $from, string $to): CompiledSchema;

    public function compileHasTable(string $table): CompiledSchema;

    public function compileTables(): CompiledSchema;

    /**
     * @param non-empty-list<array<string, mixed>> $tables
     */
    public function compileDropAll(array $tables): CompiledSchema;
}
```

`CompiledSchema` holds a `list<string>` of statements, run in order.
`compileHasTable()` must compile a query whose result set is empty when the
table does not exist.

### Dropping every table

`dropAll()` is split across two methods, because the tables have to be found
before they can be dropped:

1. `compileTables()` compiles a query that selects one row per user table in the
   connection's current database or schema, with the table's name in a `name`
   column. It may select anything else the drop needs — the schema to qualify a
   name with, or the foreign keys to remove first — and must leave out the
   database's own internal tables.
2. `compileDropAll()` receives those rows, exactly as the server returned them,
   and compiles the statements that drop the tables. It is only called when
   there is at least one row.

Because the grammar wrote the query, it is the one that knows what the rows
contain. `SqlSchemaGrammar::introspected()` reads a column from a row as a
non-empty string, and throws `SchemaIntrospectionException` when it cannot.

A drop that has to change a setting first — SQLite turning off foreign key
enforcement — returns the statement that puts it back as `cleanup`:

```php
return new CompiledSchema(
    ['PRAGMA foreign_keys = OFF', ...$drops],
    ['PRAGMA foreign_keys = ON'],
);
```

`ConnectedSchema` runs the cleanup after the queries whether or not one of them
failed. Only compile a cleanup that restores what the database reported, so a
setting that was already off is not switched on.

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
| `compileTables(): CompiledSchema` | The query that lists every user table. |
| `compileDropAll(array $tables): CompiledSchema` | The statements that drop the listed tables. |

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

The one exception is a name `compileTables()` read back from the server. That
table already exists, so it cannot be refused, and its name need not match the
pattern an `Identifier` enforces. Quote it with your dialect's escaping applied
to the raw string — the built-in grammars keep that in a `quote(string $name)`
method that `wrap()` also calls — and never pass it through anything that does
not escape the quote character.

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
