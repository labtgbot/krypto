# Local PHP/MariaDB DB-Backed Tests

This repository includes a reproducible local Docker Compose environment for
database-backed security and reliability checks. No production credentials are
required. The Compose defaults use local-only database credentials and the
ChangeNOW/payment/OAuth settings remain disabled unless a developer explicitly
changes them.

## Start

```bash
docker compose up -d
```

The default stack starts:

- `db`: MariaDB 10.11 with database `krypto`, user `krypto`, and password `krypto`.
- `db-bootstrap`: one-shot PHP container that loads `install/assets/sql/krypto.sql`
  on an empty database and seeds local admin/user fixtures.
- `app`: PHP 8.3 dev server at `http://localhost:8080`.

The database is also exposed on host port `3307` for manual inspection:

```bash
mysql -h 127.0.0.1 -P 3307 -u krypto -pkrypto krypto
```

## Test

Run the normal non-DB suite outside or inside the container:

```bash
php scripts/run_tests.php
php scripts/lint_php.php
```

Run the DB-backed mode inside the app container:

```bash
docker compose exec app php scripts/run_tests.php --db
```

Run only DB-backed tests:

```bash
docker compose exec app php scripts/run_tests.php --only-db
```

The DB smoke test is opt-in. Without `--db` or `--only-db`, it prints a skip
message and exits successfully so existing local and CI workflows keep working
without a database.

## Reset Database

Reset the schema and recreate the minimal fixtures:

```bash
docker compose exec app php scripts/db_bootstrap.php --reset --seed-fixtures
```

For a fully fresh container volume:

```bash
docker compose down -v
docker compose up -d
```

`scripts/db_bootstrap.php` loads the fresh installer schema from
`install/assets/sql/krypto.sql`. The `--apply-migrations` flag is available for
legacy upgrade experiments that intentionally start from an older schema; do not
combine it with a fresh schema unless the migration under test is known to be
idempotent for that state.

## Fixtures

The local fixture factory lives in `tests/support/db_fixtures.php` and creates:

- Admin: `dev.admin@example.test`
- User: `dev.user@example.test`
- Session payload: `$_SESSION['kr_login']` JSON for the selected fixture user

Both default passwords are the SHA-512 application hash of `password`. These
accounts are for local test containers only.

## Logs

Inspect service logs:

```bash
docker compose logs db
docker compose logs db-bootstrap
docker compose logs app
```

Follow logs while reproducing an issue:

```bash
docker compose logs -f app db
```

## Troubleshooting

- If `app` is not available, check `docker compose ps` and
  `docker compose logs db-bootstrap`; the app waits for bootstrap success.
- If the DB smoke test says it is skipped, run it through
  `php scripts/run_tests.php --db` or `php scripts/run_tests.php --only-db`.
- If port `8080` or `3307` is already in use, change the host-side port in
  `docker-compose.yml`; the internal container ports stay the same.
- If schema bootstrap fails after manual experiments, run
  `docker compose exec app php scripts/db_bootstrap.php --reset --seed-fixtures`.
- No production credentials should be copied into this environment. Use local
  dummy values through `KRYPTO_*` variables when a test needs configuration.
