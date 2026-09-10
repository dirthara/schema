<p align="center">
  <img src="logo-no-bg.png" alt="Dirthara" width="480">
</p>

# Dirthara Schema

Schema definition and migrations for the Dirthara framework. Describe a table
once and compile the DDL for MySQL, PostgreSQL, SQLite, or SQL Server, with
identifiers validated and quoted for the database they are compiled for.

## Installation

```sh
composer require dirthara/schema
```

The package requires PHP 8.5, the `pdo` extension, and
[`dirthara/database`](https://github.com/dirthara/database) `^0.1`, which
Composer installs for you. That package owns the connections, drivers, and
grammars; this one compiles schema for them. Each database also needs its own
PDO extension: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, or `pdo_sqlsrv`.

Usage documentation lives in [`docs`](docs), which is published as a Docusaurus
site by a separate package.

## Docker development environment

Requires Docker with Docker Compose. The development image provides PHP 8.5 CLI,
Composer 2.10.3, Mago, Xdebug, and a PDO driver for every database the package
supports: `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`, and `pdo_sqlsrv`. Extensions
are installed with
[install-php-extensions](https://github.com/mlocati/docker-php-extension-installer),
pinned in the Dockerfile alongside every other tool version.

Build the image and start the PHP container in the background:

```sh
LOCAL_UID=$(id -u) LOCAL_GID=$(id -g) docker compose up -d --build php
```

This is the command CI runs too, so the suite runs against the same PHP build in
both places. The image is PHP 8.5 by default; set `PHP_VERSION` to build another
version, which is how CI walks its matrix.

The container runs as the non-root `developer` user with your host user and group
IDs, so files created in the mounted repository remain editable on the host.
Both IDs default to 1000. Rebuild with the command above when they change.

The container stays running so you can open a shell at any time:

```sh
docker compose exec php bash
```

Run PHP or Composer commands against the mounted repository:

```sh
docker compose exec php php --version
docker compose exec php php --ri PDO
docker compose exec php composer --version
```

Install dependencies with:

```sh
docker compose exec php composer install
```

Stop and remove the development container when finished:

```sh
docker compose down
```

`docker compose up -d php` also starts PostgreSQL, MySQL, and SQL Server and
waits until each reports healthy, because the integration tests need them. The
first start pulls roughly a gigabyte of images and SQL Server takes around thirty
seconds to accept connections. The SQL Server image is published for amd64 only,
so its tests skip on an arm64 host.

## Tests

Run the suite through Composer in the PHP container:

```sh
docker compose exec php composer test
```

Most of the suite compiles DDL and asserts on the SQL, which needs no server.
`tests/Integration` holds the conformance suite that every database runs against
a real one: compiling a definition, executing it, and reading back what the
server actually created. Compiled DDL that looks right is not evidence that a
server accepts it, and a dialect's rules cannot be judged without one that
rejects the wrong statement.

| Database | Service | Notes |
| --- | --- | --- |
| SQLite | none | In memory, so it always runs. |
| PostgreSQL | `postgres` | `postgres:18-alpine`. |
| MySQL | `mysql` | `mysql:8.4`. |
| SQL Server | `sqlserver` | `mssql/server:2022-latest`, amd64 only. |

The SQL Server suite passes `TrustServerCertificate=yes` through the connection's
`dsn` parameters, because ODBC Driver 18 encrypts and verifies by default and the
development container presents a self-signed certificate. Production connections
should trust a real certificate chain instead.

Each suite skips when its PDO driver is missing, and reads its connection from
`DIRTHARA_POSTGRES_*`, `DIRTHARA_MYSQL_*`, and `DIRTHARA_SQLSRV_*`
(`_HOST`, `_PORT`, `_DATABASE`, `_USERNAME`, `_PASSWORD`), defaulting to the
services in `compose.yaml`.

### Coverage

Xdebug is installed but inactive, so the suite runs at full speed.
`composer test-coverage` turns it on for that one command and writes
`build/coverage/clover.xml`:

```sh
docker compose exec php composer test-coverage
docker compose exec php composer coverage
```

`composer coverage` fails when line coverage of `src` is below 100% and lists
every uncovered line. It needs the database services running, since the dialect
behaviour that only a real server reaches is covered nowhere else. Run everything
CI runs, in CI's order, with:

```sh
docker compose exec php composer ci
```

## Mago

Run all Mago checks through Composer in the PHP container:

```sh
docker compose exec php composer mago
```

Inside the container shell, use `composer mago` directly. Rebuild the PHP image
after pulling changes to its Dockerfile. This command checks formatting, runs the
linter and static analyzer, and checks architecture rules with `mago guard`.
Every check runs even if an earlier check fails, and the command fails if any
check fails. It does not modify files. Architecture rules apply when configured
in `mago.toml`.

`composer lint` reports lint violations without touching files. `composer
lint-fix` applies the fixes it can, including the potentially unsafe ones.

Mago 1.47.3 runs through its official Docker image. Only Docker Compose is needed
on the host, and the PHP container does not need to be running. The image is
downloaded automatically on first use.

Check formatting, lint, and analyze the source:

```sh
docker compose run --rm mago fmt --check
docker compose run --rm mago lint
docker compose run --rm mago analyze
```

Apply formatting with:

```sh
docker compose run --rm mago fmt
```

Add missing strict type declarations, then format all source and test files:

```sh
docker compose run --rm mago lint --only strict-types --fix --potentially-unsafe
docker compose run --rm mago fmt
```

The strict types rule reports violations as errors. Its fix needs
`--potentially-unsafe` because strict typing changes PHP's coercion behavior.
Imports are sorted shortest first within separate class, function, and constant
lists, with blank lines between the lists.

Mago runs with user and group IDs 1000 by default so edited files remain owned by
your host account. If your IDs differ, export them before running these commands:

```sh
export LOCAL_UID=$(id -u) LOCAL_GID=$(id -g)
```

The `mago.toml` configuration targets PHP 8.5 and the `src` and `tests` directories, with `vendor`
available for dependency analysis. The `tools` profile keeps Mago out of the
normal background services; explicitly running the service activates it.

## Contributing

Every supported version has its own branch, fixes land on the earliest supported
branch that has the bug, and pull requests need the `CI` check to pass with full
coverage of `src`. See [CONTRIBUTING.md](CONTRIBUTING.md) for the branching and
release strategy.

## Security

Report vulnerabilities privately through GitHub's advisory form rather than in a
public issue. See [SECURITY.md](SECURITY.md) for the supported versions, what is
in scope, and what to include in a report.

## License

Released under the MIT License. See [LICENSE](LICENSE).
