# Security Policy

## Supported versions

The package is pre-1.0. Only the latest release line receives fixes; there are
no backports to earlier ones.

| Version | Supported |
| --- | --- |
| 0.1.x | Yes |
| Older | No |

## Reporting a vulnerability

Report vulnerabilities privately through GitHub, using
[Report a vulnerability](https://github.com/dirthara/schema/security/advisories/new)
on the repository's Security tab. That opens a private advisory visible only to
you and the maintainers.

Please do not open a public issue, pull request, or discussion for a
vulnerability. A public report tells everyone running the package about the
problem before there is a version that fixes it.

Include what you have:

- Which version you found it in.
- What an attacker can do, and what they need to already have to do it.
- The smallest code or configuration that shows the problem.
- The database and PDO driver, if the behaviour depends on them.

You will get an acknowledgement that the report was received and an assessment
once the report has been reproduced. If a fix is warranted, the advisory is
published together with the release that contains it, crediting you unless you
ask otherwise.

## Scope

The package compiles DDL from schema definitions. Almost nothing in a
`CREATE TABLE` can be a bound parameter: table names, column names, index names,
types, collations, and defaults are all text in the statement. Everything that
gets past the boundary between a definition and that text is in scope,
including:

- An identifier, type, collation, or default reaching compiled DDL in a way that
  lets it change the statement's structure, rather than being rejected or
  quoted for the target database.
- A quoting routine that a value can escape by closing the quote the compiler
  opened.
- Compiled DDL that differs from the definition it was built from, such as a
  constraint, a `NOT NULL`, or a foreign key that is silently dropped for one
  database.
- Credentials or definition values appearing in exception messages, exception
  context, dumps, or stack traces.

Out of scope:

- Raw DDL an application hands the package verbatim. A raw statement is the
  caller's responsibility, and the package documents the definition API as the
  alternative.
- Bugs in PHP, PDO, or a database driver extension. Report those upstream; if
  the package can defend against one, that is worth reporting here too.
- A migration an application chooses to run against production, and whatever it
  drops. The package compiles what it is told to compile.
- A database misconfiguration the package faithfully connected to, such as an
  account with more privileges than it needs.

## Hardening notes

Identifiers and other non-parameterisable fragments are validated on
construction and rejected when they do not match a strict pattern, then quoted
for the database they are compiled for. Validation and quoting are both
required: a pattern alone does not survive a new database's quoting rules, and
quoting alone does not stop an identifier that is valid text but wrong. A
compiler of your own should use the package's quoting helpers rather than
concatenating identifiers into SQL directly.

Exception context is written to logs. It carries the connection name, driver,
operation, SQLSTATE, and the SQL — never a username, a password, or a
credential-bearing DSN. Keep that split in your own compilers, middleware, and
exceptions.
