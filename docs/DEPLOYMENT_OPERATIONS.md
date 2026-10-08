# Release preflight and recovery

The implemented commands inventory a prepared release and check one configured client. They do not deploy releases, switch traffic, create backups, restore a database, or restart workers.

## Build an inventory

Resolve the locked Composer dependencies, build frontend assets, and generate the extension/theme catalogs using the existing package build workflow before sealing the artifact. Prepare migration source files and test the selected client package graph. Then run:

```sh
php artisan platform:release:manifest release-20261009-01
```

The default output is `bootstrap/cache/release.json`; `--output=/absolute/path/release.json` selects another path. An existing destination is rejected. The inventory includes source files, migrations, lockfile hashes, installed Composer versions/references, a dependency hash, and the shared source hash. Application entry points and compiled frontend assets are included. Client environments, storage, uploads, runtime caches, tests, and documentation are excluded. Only internal `vendor/mavyn` dependency symlinks are supported; their package source trees are inventoried separately.

Artifacts must retain the exact inventoried bytes and installed dependencies. Copying this inventory alongside an arbitrary code tree fails verification. The JSON is an unsigned consistency inventory supplied by a trusted operator; it does not establish publisher identity or replace artifact provenance checks. Current development package versions remain 0.1.0; distinct source hashes identify rehearsal artifacts, not publicly published releases.

## Configure an isolated client

Use a separate database, environment/application key, domain, storage, and worker scope per client. Configure `MAVYN_CLIENT_ID` (for example `stationery-pilot`), `SESSION_COOKIE=stationery-pilot_session`, `SESSION_DOMAIN=null`, `CACHE_PREFIX=stationery-pilot:`, `DB_QUEUE=stationery-pilot`, and `NOTIFICATION_QUEUE=stationery-pilot` with database queues. Database credentials and application keys belong in private client configuration.

The host launcher must bind the client environment, storage, and public paths before Laravel boots, using `useEnvironmentPath`, `useStoragePath`, and `usePublicPath`, as demonstrated by the rehearsal. Those paths are launcher responsibilities; setting `MAVYN_CLIENT_ID` alone does not change them. Give each client separate compiled-view, config, route, and event cache paths. Persistent writable storage must be outside the release directory. Publish the matching frontend build and checksum-addressed theme assets into that client's public path; preserve uploads independently.

Preflight checks the configured namespace conventions for the selected client. It cannot prove that another independently configured deployment uses different credentials, keys, or infrastructure; verify those boundaries during staging acceptance.

## Supply backup evidence and check

Create a consistent client backup and restore it into an isolated target. Verify the restored orders, private receipts, environment/key continuity, persistent files, and pending work. A trusted operator then supplies a private backup certificate next to its listed backup files:

```json
{
  "format": 1,
  "client": "stationery-pilot",
  "scope_hash": "<ReleasePreflight::scopeHash() for this configured client>",
  "created_at": 1791504000,
  "restore_verified": true,
  "files": {
    "database.dump": "<SHA-256 of backup bytes>",
    "persistent-files.tar": "<SHA-256 of backup bytes>",
    "environment.backup": "<SHA-256 of backup bytes>"
  }
}
```

Replace the example timestamp with the backup's Unix timestamp. The client/database scope hash contains no exposed connection values. The checker requires a matching client/scope, a timestamp within 24 hours (at most five minutes into the future), operator restore attestation, and matching checksums for every listed file. Paths must remain within the certificate directory. The attestation is trusted operator evidence; the command cannot determine whether a dump contains all required data or actually perform the restore.

```sh
php artisan platform:release:check --backup=/private/client-backup/backup.json
php artisan platform:release:check --backup=/private/client-backup/backup.json --record
```

`--manifest` overrides the configured manifest path. By default the check is read-only. `--record` writes its result to the new `deployment_runs` table after migration 15 is applied. JSON includes release/version metadata only after inventory verification, fixed named checks, and `verified`/`failed`; failures return nonzero. No raw exception bodies, connection credentials, or application keys enter the report. Failure to record also fails the command.

Checks cover inventoried source/dependencies, client ID, PHP/extensions, application encryption, database access, applied inventoried migrations, external writable storage, namespace conventions, active plugin/theme compatibility, matching public frontend assets, and backup evidence. A verified result is evidence of these checks, not evidence that traffic switched or that every production readiness requirement passed. The command does not execute migrations or activate extensions.

## Stage, cut over, and recover

On the actual hosting environment, rehearse the prepared artifact with restored client data first. Review migration compatibility, queues/in-flight work, client cache paths, public assets, and browser journeys. Before cutover, pause incoming writes where required, coordinate workers, take and restore-check a current backup, apply reviewed migrations, run preflight with recording, and switch the client's artifact through the hosting mechanism. Rebuild client runtime caches and start workers against the chosen release; validate checkout and retained history before expanding rollout. These are operator steps, not an implemented automated deployment command.

For a code defect with a backward-compatible schema, switch to a tested previous artifact while preserving the current database and persistent files. Publish that artifact's frontend assets and coordinate workers/caches. Check compatibility and preflight before resuming. The rehearsal demonstrates this with two orders retained across code recovery.

Database restoration is a separate recovery decision: stop writes/workers, preserve current evidence, restore into an isolated target first, and identify/reconcile transactions and receipts created after the backup. Replacing the current database with a backup can discard orders and pending notifications; the rehearsal reports one uncovered order. Do not automatically run destructive down-migrations or restore old data as part of code rollback.

The local workspace is not configured as an isolated production client and is expected to fail the client/storage/backup gates until a real client deployment is prepared. No live cutover, full merchant backup, or production recovery was performed. See [pilot validation](PILOT_VALIDATION.md) for the exact scope of the offline evidence.
