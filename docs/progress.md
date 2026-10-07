# Implementation progress

Updated: 2026-10-08

Delivery order: complete the core/media prerequisites, then implement and validate plugins before themes. Local delivery is the first end-to-end plugin, followed by manual payments and food/availability behavior.

- [x] Establish baseline tests, fix initial checkout/access issues, and extract order-placement services.
- [x] Build media foundation: upload/library, Copy URL, private access, polymorphic reuse, picker component/API, queued conversions.
- [x] Initialize local MySQL database and apply/verify all migrations.
- [x] Prepare local access: securely provision admin, verify public storage link, start conversion worker, and validate browser flows.
- [x] Integrate media picker into product main images; verify storefront cards/detail pages, sharing, replacement, detach, and image/privacy validation.
- [x] Extend media picker integration to product galleries, categories, homepage slides, branding/logos, and galleries; verify storefront rendering.
- [x] Complete media policies: type/count rules, ordering, historical retention, responsive images, processing/retry status, and durable cleanup.
- [ ] Migrate legacy uploads/references and public receipts; reconcile files, associations, history, and private access.
- [ ] Complete commerce correctness: tracked inventory/reservations, durable checkout idempotency, decimal-safe pricing, and guest coupon identity policy.
- [ ] Add granular admin permissions/auditing and queued/outbox notifications.
- [ ] Extract distributable core/contracts packages; finish generic terminology and validated settings.
- [ ] Build plugin runtime: manifests, actions/filters/registries, lifecycle, dependency checks, and extension settings.
- [ ] Extract local delivery, manual payments, food attributes, and store availability into plugins.
- [ ] Build theme runtime: default fallback, preview/activation, settings/assets, and child themes; extract Happy Tea storefront.
- [ ] Validate non-food pilot, existing-store migration, shared core deployment to both pilots, and recovery procedures.

Completed: baseline (46/47 passing), saved SEO title fix, guest-order ownership checks, disabled-payment validation, fresh checkout product/variant/option/pricing checks, coupon revalidation under lock, private new receipts with protected admin delivery, scoped cart state, and order placement/access/payment service extraction. New identifiers use neutral prefixes; historical identifiers/status values are preserved.

Media foundation: Spatie 11.23.9 installed/locked; polymorphic asset ownership and usage references; admin upload/search/details/metadata/Copy URL; protected private files; reusable picker component/API; queued thumbnail/card/detail/hero WebP conversions and responsive sources; reuse/detach and in-use deletion protection. Media access currently requires an active super admin.

Validation: 99 tests / 573 assertions pass, including 13 checkout regressions and 39 media/provisioning/content/lifecycle tests with real GD conversion jobs/private derivatives. Composer validation, route caching (temporary cache), formatting and diff whitespace checks pass. Sitemap output is isolated during tests. New migrations are tested with in-memory SQLite. All migrations were also applied to the newly created local MySQL `mavyn` database and verified as Ran; no production deployment performed. Browser interaction has now been validated on `http://mavyn.test/` with headless Chromium: admin login, library uploads, create/edit picker selection, reuse across two products, disabled in-use deletion, public delivery, storefront cards/details, replacement, and clearing all pass with no JavaScript errors. Conversion jobs produced WebP thumbnails/cards with no pending or failed jobs.

Current state: first implementation increment verified; local MySQL schema initialized, including receipt disk and media tables. No seeders or store-data import were run. Local browser checks created one clearly named sample category, two products, and two library assets. The sample category/product now also use library images/galleries, and one sample slide and a site gallery remain for inspection; duplicate test slides were cleaned up. Logo/favicon selections were restored after verification. `public/storage` now points to this workspace’s `storage/app/public`; the public disk uses that storage root. Local `APP_URL` is `http://mavyn.test/`. A conversion worker was started with `php artisan queue:work --sleep=1 --tries=3 --timeout=120`. Existing receipt files remain on their legacy disk pending migration.

Remaining correctness work: explicit tracked inventory/reservations, durable idempotency, decimal-safe pricing, guest coupon identity policy, queued/outbox notifications, granular permissions, and moving legacy public receipts into private storage.

Completed milestones: product main images and ordered galleries, category images, desktop/mobile slides, store logo/favicon, and homepage/gallery images use the shared picker and tracked usages. Single image slots allow one image; galleries allow up to 30 distinct public JPEG/PNG/WebP/GIF images with persisted ordering. Replacement/clear/deletion detaches references without deleting library originals. Legacy fallbacks remain available. Settings media belongs to registered `core.setting` records; bare asset IDs are not stored in arbitrary settings values. Settings saves invalidate relevant caches without a global flush. Selected logos also render in transactional email templates.

Extended browser validation: ordered multi-selection/reordering, product thumbnail interaction, category save, desktop/mobile slide save/rendering, logo/favicon selection, homepage gallery, gallery lightbox, mobile viewport, public delivery, and tracked-usage deletion protection all pass with no JavaScript errors. Admin guard visibility has a regression test; private/document/missing IDs, duplicate/excessive selections, partial updates, legacy fallback, atomic validation, and shared-file retention are covered.

Completed media lifecycle: trusted slot/profile registries enforce owner/type/size/count rules and ordering; checkout snapshots selected library images and creates permanent historical holds. Responsive card/detail/hero images use original fallback while queued or failed. Processing status, generation-safe retries, protected private derivatives, and shared-cache operation locks are implemented. Deletion commits a cleanup journal before filesystem work; idempotent workers and hourly reconciliation recover failures and interrupted uploads. Unknown folders are reported and preserved. See [Media operations](MEDIA_OPERATIONS.md).

Lifecycle validation: 15 additional tests cover policies, metadata ordering, real responsive processing, missing originals, stale/duplicate retries, private delivery, durable cleanup failure/recovery, transaction rollback, and checkout/history retention. Local MySQL migration and media preflight pass; existing sample assets were regenerated, and the updated queue worker is running. Browser checks on `http://mavyn.test/` verify ready status/admin retry, responsive storefront delivery, private no-store responses/guest rejection, and queued deletion through the worker with no JavaScript errors. The disposable private test upload was cleaned up.

Next milestone: migrate legacy uploads/references and public receipts, reconcile historical associations, and verify private access. Existing legacy orders/files are not backfilled by this increment. Plugins remain scheduled before themes. The complete platform is not yet implemented.

Local admin setup: `php artisan admin:provision <email>` prompts for a password of at least 12 characters. No fixed admin password is seeded. The local-only `--local-password-file` option generates a random password and writes credentials to `storage/app/private/local-admin.txt` with mode 0600. Browser setup used this option; credentials are private and excluded from Git.
