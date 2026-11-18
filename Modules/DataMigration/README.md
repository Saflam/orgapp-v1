# DataMigration module — foundation

This is a system-level module for the initial legacy import. It is not an
organization-selectable business module and must only be accessible to system
administrators.

## Included in this first stage

- Module registration.
- Configurable legacy connection name (defaults to the existing `legacy` DB connection).
- Read-only source inspection command.
- Migration run, batch, source-to-target mapping, and issue tracking tables.
- Eloquent models for those tracking tables.

No legacy business records are imported by this stage.

## Installation

1. Copy the `Modules/DataMigration` directory into the Saflivo project.
2. Merge the `DataMigration` entry from `modules_statuses.json`.
3. Run `composer dump-autoload`.
4. Run `php artisan optimize:clear`.
5. Run `php artisan migrate`.
6. Verify the legacy connection with `php artisan datamigration:check-legacy`.

The existing `legacy` connection in `config/database.php` is reused. Configure
`LEGACY_DB_*` in the local `.env`. In production, use a dedicated MariaDB account
that has SELECT privileges only on the legacy database. Do not use a root account.

Optional environment settings:

```dotenv
DATA_MIGRATION_LEGACY_CONNECTION=legacy
DATA_MIGRATION_CHUNK_SIZE=200
```

The command only checks connectivity, required table presence, and row counts.
It does not write to the legacy database or import any records.

## Tracking tables

- `data_migration_runs`: one record per dry-run or import execution.
- `data_migration_batches`: resumable batch cursor and counters.
- `data_migration_mappings`: persistent legacy source ID to Saflivo target ID mapping.
- `data_migration_issues`: validation failures and manual-review items.

Do not store full legacy user/member rows in issue details. These tables should
contain identifiers, concise diagnostic details, and checksums only.
