---
id: type-mapping
title: Type mapping
sidebar_position: 1
description: What each ColumnType becomes on MySQL, PostgreSQL, SQLite and SQL Server.
---

# Type mapping

A `ColumnType` names what the column is for, not what a database calls it.
Mapping it onto a native type is the grammar's job.

| `ColumnType` | SQLite | MySQL | PostgreSQL | SQL Server |
| --- | --- | --- | --- | --- |
| `Boolean` | `INTEGER` | `TINYINT(1)` | `BOOLEAN` | `BIT` |
| `TinyInteger` | `INTEGER` | `TINYINT` | `SMALLINT` | `TINYINT` |
| `SmallInteger` | `INTEGER` | `SMALLINT` | `SMALLINT` | `SMALLINT` |
| `Integer` | `INTEGER` | `INT` | `INTEGER` | `INT` |
| `BigInteger` | `INTEGER` | `BIGINT` | `BIGINT` | `BIGINT` |
| `Decimal` | `NUMERIC(p, s)` | `DECIMAL(p, s)` | `NUMERIC(p, s)` | `DECIMAL(p, s)` |
| `Float` | `REAL` | `FLOAT` | `REAL` | `REAL` |
| `Double` | `REAL` | `DOUBLE` | `DOUBLE PRECISION` | `FLOAT` |
| `Char` | `CHAR(n)` | `CHAR(n)` | `CHAR(n)` | `NCHAR(n)` |
| `String` | `VARCHAR(n)` | `VARCHAR(n)` | `VARCHAR(n)` | `NVARCHAR(n)` |
| `Text` | `TEXT` | `TEXT` | `TEXT` | `NVARCHAR(MAX)` |
| `Date` | `DATE` | `DATE` | `DATE` | `DATE` |
| `Time` | `TIME` | `TIME` | `TIME` | `TIME` |
| `DateTime` | `DATETIME` | `DATETIME` | `TIMESTAMP` | `DATETIME2` |
| `Timestamp` | `DATETIME` | `TIMESTAMP` | `TIMESTAMP` | `DATETIME2` |
| `Uuid` | `CHAR(36)` | `CHAR(36)` | `UUID` | `UNIQUEIDENTIFIER` |
| `Json` | `TEXT` | `JSON` | `JSONB` | `NVARCHAR(MAX)` |
| `Binary` | `BLOB` | `BLOB` | `BYTEA` | `VARBINARY(MAX)` |

`n` is the length from `string()` or `char()`, defaulting to `255`. `p` and `s`
are the precision and scale from `decimal()`, defaulting to `8` and `2`.

## Choices worth knowing about

**SQLite collapses the integers.** Every integer width becomes `INTEGER`,
because SQLite has one integer storage class and its `AUTOINCREMENT` is only
legal on a column declared exactly `INTEGER`. A `tinyInteger` and a `bigInteger`
are the same column there.

**`Timestamp` is `DATETIME2` on SQL Server, not `TIMESTAMP`.** SQL Server's
`TIMESTAMP` is a row-version type with nothing to do with time, and using it
would silently give you the wrong column.

**PostgreSQL gets `TIMESTAMP` without a time zone.** If your application stores
instants rather than wall-clock times, `TIMESTAMPTZ` is the better column and
this package does not yet offer it.

**`Json` is `JSONB` on PostgreSQL.** Better for almost every use, but it does not
preserve key order or duplicate keys. `Text` is the fallback on SQLite and SQL
Server, which have no native JSON column.

:::caution
Because a type is per database, a column is not guaranteed to behave the same
everywhere. `Boolean` is a real `BOOLEAN` on PostgreSQL and an `INTEGER` on
SQLite, so a value read back is `true` from one and `1` from the other.
:::
