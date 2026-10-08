# Media operations

Media lifecycle and resumable legacy-import tooling are implemented. Existing-store backfill requires its source records and files; see [progress](progress.md) for the current local inventory.

## Policies and presentation

`MediaPolicyRegistry` accepts registrations from trusted application providers, never from requests. Slots validate their registered owner and setting key, JPEG/PNG/WebP/GIF image type, active/public visibility, maximum byte size, distinct selections, and count. Single slots accept one image; current galleries accept 30 images. Reordering preserves per-usage alt/caption metadata. Replacing or detaching a selection leaves the original intact.

Uploads accept the library's image/document/audio/video allowlist, up to 20 MiB. Images must decode, be at most 8192 pixels per side, and contain at most 40 million pixels. Documents and private assets cannot be selected in public image slots.

Profiles generate WebP images without enlarging the original: thumbnail (320), product-card (640), product-detail (1280), and hero (1920). Card/detail/hero profiles also generate responsive sources. Trusted extensions may register bounded profiles (at most eight, at most 4096 pixels per dimension). Public presentation supplies URL, srcset, source dimensions, alt/caption, and processing status. Pending or failed processing uses the original with no derived srcset. Originals are immutable; upload a new asset to replace an image.

Private originals, conversions, and responsive files stay on `media_private`. Authorized admin delivery validates the requested profile/responsive filename and sends `Cache-Control: no-store, private`. Private files never enter public presentation or picker responses.

## Historical retention

New checkout order items snapshot the selected library main image and its metadata within the checkout transaction. An independent retention hold blocks deletion even after the product or order is deleted. Order views use the retained original; changing the product selection or asset title does not rewrite the order snapshot. Holds have no automatic expiry or release endpoint. Legacy migration follows the order item's saved image path, including when its product has changed or been deleted, and creates an independent hold. Existing snapshots are preserved; missing holds can be repaired.

## Processing and cleanup

Run `php artisan media:preflight` before enabling workers. GD WebP encoding, writable dedicated local media disks, the default numeric Spatie path generator without prefixes, and a shared cache lock store are required. Optimizer binaries are optional; missing optimizers affect compression, not correctness. Current private delivery and image decoding require local disks; remote disk support is outside this increment.

Run a supervised worker:

```sh
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

Use `php artisan queue:restart` after code/config deployment. Image jobs record pending/processing/ready/failed status and attempt counts. Admin details show errors and a retry action. Fresh pending/processing jobs cannot be duplicated; jobs stalled for ten minutes can be requeued. Generation checks prevent old jobs from overwriting a newer retry. Conversion and cleanup use the same per-asset cache lock.

Deleting an unused asset first commits `deleting` status and a durable `media_cleanup_tasks` path manifest. It becomes unavailable to pickers immediately. A queued worker removes its dedicated storage directories outside a database transaction, then removes metadata and records completion. Storage/queue failure leaves retryable intent. Repeated cleanup is safe; the journal survives asset deletion. Originals, derivatives, and responsive files share the asset's isolated numeric directory. Custom path generators/prefixes are rejected by preflight and cleanup path validation.

Admin details offer cleanup retry while deletion is pending or failed. Referenced or historically retained media cannot enter deletion. Cleanup does not run arbitrary filesystem paths or sweep unknown directories.

## Reconciliation and recovery

```sh
php artisan media:reconcile
php artisan media:reconcile --apply
```

The first command reports only. `--apply` queues uploads interrupted for more than 24 hours, cleanup tasks idle for ten minutes, and pending/processing images stalled for ten minutes. One failed candidate is logged and does not prevent other candidates from being examined. Unknown storage directories are reported and preserved in both modes. Missing originals are reported; apply mode marks processing failed. Restore the original from backup before retrying; derivatives are not substitutes for the source.

The apply command is scheduled hourly with overlap protection. A production scheduler must invoke `php artisan schedule:run` every minute; starting a queue worker alone does not run scheduled reconciliation. The local worker was started for browser testing; no production supervisor or cron was installed.

Back up media disks together with database records, usages, retention holds, cleanup journals, and legacy import journals. Investigate unknown folders before deleting them manually. Legacy migration preserves public image source files; verified public receipt copies are removed after their private replacements and order references are committed.

## Legacy import and receipt privacy

Back up the database, public/private storage, and referenced merchant images in the document root before migrating an existing store. Keep backups outside public storage. Coordinate merchant edits during import: the command detects changed references, but its command lock does not suspend application writers. Apply schema migrations and run media preflight first, then:

```sh
php artisan media:migrate-legacy
php artisan media:migrate-legacy --apply
php artisan media:migrate-legacy
```

The default command inventories and verifies without changing files or business records. Both modes return a failure exit code for missing originals, unsafe paths, checksum mismatches, unsupported files, privacy conflicts, or unfinished recovery. Independent targets continue after a failure. A shared cache lock prevents concurrent commands; it expires after 24 hours if the process crashes. Clear a stale lock only after confirming that the prior process has stopped.

Sources include product main/gallery images, categories, desktop/mobile slides, logo/favicon settings, homepage galleries, saved order images, and public receipts. `images/` and `assets/` references resolve through the document-root `legacy_assets` adapter; product gallery rows use the public disk. A `storage/` prefix explicitly selects the public disk. Remote URLs, traversal, symlinks, and generated media folders require manual reconciliation. Raster images must meet the existing type, size, and dimension policies; packaged SVG artwork stays outside this import.

`legacy_media_imports` records source identity, SHA-256 checksum, visibility, destination, asset ID when applicable, state, and import/copy errors. Compatible public images with identical bytes share one library original. Receipts have a separate private identity and never merge into a public image. Changed sources and conflicting destination bytes require manual reconciliation.

Content imports retain current library selections and their legacy fallbacks. Successful imports create ordered usages and clear legacy columns/rows atomically. Galleries verify every member before replacing their visible legacy representation; a missing member leaves the whole gallery available. Duplicate images collapse in first-occurrence order, matching the distinct-image slot policy. Per-usage alt text is retained. Public source originals remain available for recovery and external links. Unreferenced public-disk files are reported and preserved; the document root is read only for referenced merchant images.

Historical imports use saved order-item paths and permanent holds. Legacy path strings remain as provenance. Import journals protect interrupted image copies from ordinary stale-upload cleanup. Rerunning migration resumes their original copy and processing generation without creating another asset.

Receipt migration verifies a staged private copy in `storage/app/private/receipts/legacy`, commits all orders sharing the old path to that private destination, then removes the public copy. A failed unlink leaves a `copied` journal that a later apply run finishes even though order references are already private. Partial staging copies are retryable. Private storage must reside outside public roots. Paths shared between receipts and public content are reported as privacy conflicts requiring manual resolution.

Receipt delivery requires admin authentication and sends `Cache-Control: no-store, private` with `nosniff`. Receipts remain outside public pickers. Existing private receipts are checked for missing originals and file-policy violations. Migrated receipt originals remain retained by their journal after order deletion; other shared receipts remain while another order references them. No automatic historical receipt expiry is implemented.

After importing, process queued conversions, inspect processing status, verify representative storefront images and authorized receipts, and verify that old public receipt URLs no longer serve their bytes. Restore missing private originals from the coordinated backup. For a full rollback, restore the database and files together while preserving private receipt access/storage rules.

Local audit on 2026-10-08: the journal schema is applied, but there are no products, categories, orders, or media assets. Three default slides reference missing `images/hero_cakes.jpg`, `images/hero_pastries.jpg`, and `images/hero_brownies.jpg`. Both report and apply preserve these unresolved references. Isolated fixtures validate migration behavior; existing-store backfill remains outstanding.
