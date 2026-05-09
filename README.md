# krypto
Krypto is a script for an online cryptocurrency service: online trading, advanced data, market analysis, watchlist, portfolio, subscriptions.

See [docs/platform-analysis.md](docs/platform-analysis.md) for a full analysis of what the platform is, what it is written in, and where it can run.

See [docs/production-deployment-security.md](docs/production-deployment-security.md) for the production checklist that blocks direct web access to installer, config, vendor, and mutable storage paths.

See [docs/upload-storage-deployment.md](docs/upload-storage-deployment.md) for Apache, Nginx, IIS, and reverse proxy guards that keep public upload storage static-only.

See [docs/authenticated-encryption-migration.md](docs/authenticated-encryption-migration.md) for the versioned secret encryption migration plan and rollback notes.

See [docs/local-db-tests.md](docs/local-db-tests.md) for the reproducible PHP/MariaDB environment, schema bootstrap, DB-backed smoke tests, reset commands, logs, and troubleshooting notes.

See [docs/changenow-migration-tasks.md](docs/changenow-migration-tasks.md) for the proposed task breakdown to migrate Krypto into an open ChangeNOW-powered swap application.
