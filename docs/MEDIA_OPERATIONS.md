# Media operations

Implemented in the media lifecycle increment. Legacy file/history backfill remains the next item in [progress](progress.md).

## Policies and presentation

`MediaPolicyRegistry` accepts registrations from trusted application providers, never from requests. Slots validate their registered owner and setting key, JPEG/PNG/WebP/GIF image type, active/public visibility, maximum byte size, distinct selections, and count. Single slots accept one image; current galleries accept 30 images. Reordering preserves per-usage alt/caption metadata. Replacing or detaching a selection leaves the original intact.

Uploads accept the library's image/document/audio/video allowlist, up to 20 MiB. Images must decode, be at most 8192 pixels per side, and contain at most 40 million pixels. Documents and private assets cannot be selected in public image slots.

Profiles generate WebP images without enlarging the original: thumbnail (320), product-card (640), product-detail (1280), and hero (1920). Card/detail/hero profiles also generate responsive sources. Trusted extensions may register bounded profiles (at most eight, at most 4096 pixels per dimension). Public presentation supplies URL, srcset, source dimensions, alt/caption, and processing status. Pending or failed processing uses the original with no derived srcset. Originals are immutable; upload a new asset to replace an image.

Private originals, conversions, and responsive files stay on `media_private`. Authorized admin delivery validates the requested profile/responsive filename and sends `Cache-Control: no-store, private`. Private files never enter public presentation or picker responses.

## Historical retention

New checkout order items snapshot the selected library main image and its metadata within the checkout transaction. An independent retention hold blocks deletion even after the product or order is deleted. Order views use the retained original; changing the product selection or asset title does not rewrite the order snapshot. Holds have no automatic expiry or release endpoint. Historical legacy orders require the next migration step.

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

Back up media disks together with database records, usages, retention holds, and cleanup journals. Investigate unknown folders before deleting them manually. Existing public receipts and legacy uploads remain unchanged until the migration milestone.
