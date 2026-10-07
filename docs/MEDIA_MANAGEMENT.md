# Central Media Management

Implementation status: current policies, profiles, order retention, responsive presentation, retries, cleanup journals, and reconciliation are described in [Media operations](MEDIA_OPERATIONS.md). This document also contains target architecture and future contracts; legacy backfill, quotas, generated-document policies, and the plugin/theme runtime remain pending.
Status: Proposed architecture and developer contract  
Date: 2026-10-08  
Related documents: [PRD](PRD.md), [architecture](ARCHITECTURE.md), [plugin development](PLUGIN_DEVELOPMENT.md), [theme development](THEME_DEVELOPMENT.md)

## 1. Decision and scope

Media management is a core platform capability built on `spatie/laravel-medialibrary`. All merchant uploads use a common upload pipeline, admin library/picker, metadata system, permissions, storage policy, and conversion pipeline. Themes and plugins consume core media contracts instead of implementing separate upload stores or depending directly on Spatie internals.

Central means one library per client store, not unrestricted sharing between clients. The separate-deployment/database model continues to apply.

This document adds the user's requirement for polymorphic media management to the platform design. No package, schema, UI, or conversion job has been implemented by this document. Proposed API names and table extensions require implementation and contract tests.

## 2. Package responsibility

Core owns the Composer dependency and a platform adapter around it. Select and lock a supported package version against the actual PHP/Laravel runtime during implementation; do not assume a documentation version range guarantees compatibility with the project's Laravel version.

Spatie provides model-associated files, collections, conversions, and filesystem integration. Its standard migration uses `morphs('model')` in the `media` table; see the [official media migration](https://raw.githubusercontent.com/spatie/laravel-medialibrary/main/database/migrations/create_media_table.php.stub). It does not by itself define this platform's shared admin picker or reusable many-owner usage model.

Use the base Media Library package. Build the shared admin/picker with the existing platform UI. Media Library Pro is a separate optional UI product, not an assumed requirement or license; see [Spatie introduction](https://spatie.be/docs/laravel-medialibrary/v11/introduction).

## 3. Data model: asset ownership and polymorphic usage

Use Spatie's existing `media` table rather than creating a competing file metadata/storage system. Add a small core layer for reusable assets and their references:

| Table/model | Responsibility | Important fields/concepts |
|---|---|---|
| `media_assets` / `MediaAsset` | Stable library item, lifecycle, access classification, and original ownership | ID, uploader, title, default alt text/caption, visibility, status, timestamps |
| `media` / Spatie `Media` | Original file, storage disks, file metadata, conversions, responsive derivatives | Existing `model_type/model_id`, collection, file name, MIME, size, disk, generated conversions |
| `media_usages` / `MediaUsage` | Reference one reusable asset from different core/plugin records | Asset ID, polymorphic `mediable_type/mediable_id`, slot/collection, sort order, usage alt/caption/crop metadata |

`MediaAsset` implements Spatie's media-model contract. Each library asset owns one original in its core `original` collection, plus generated derivatives. An asset's owner is stable even when it is selected for another product or theme section.

Examples of polymorphic usage targets: product, category, content block, store-branding record, order attachment, or a plugin-owned gallery record. Slot examples: `catalog.product.gallery`, `store.logo`, `content.hero`, or `acme.gallery.images`.

Spatie's owner relationship is polymorphic; the usage relationship adds intentional reuse across multiple records. Do not represent multiple owners by changing one Spatie row's `model_type/model_id`, and do not duplicate the file merely to select it in another page.

Register durable morph aliases for core/plugin model types rather than persisting refactor-sensitive PHP class names. Validate aliases against the installed extension catalog; reject arbitrary submitted class names. References to an unavailable plugin model remain visible as retained usage metadata and protect against accidental deletion.

Add indexed asset and polymorphic target lookups and uniqueness rules for the chosen usage/slot semantics. Single-image slots replace their usage reference, not the globally owned original. Multi-image slots preserve ordering. Exact schema, ID types, and constraints will be finalized with migrations.

## 4. Shared admin and picker

The Media screen supports upload, grid/list browsing, search, filters by type/date/usage, metadata editing, processing status, Copy URL for public media, and permitted archival/deletion. Media details show original metadata, available derivatives, uploader, and where the asset is used. Copied public URLs can be reused outside platform-managed fields; external/manual URL uses cannot be reliably enumerated, so absence of tracked usages never proves absence of external references. Private media has no permanent public Copy URL action.

The shared picker is available in core/plugin admin forms and theme/content configuration. It supports single/multiple selection, allowed types, dimensional constraints, and ordering. It returns authorized asset IDs, not arbitrary filesystem paths or user-submitted URLs.

Reuse is explicit: selecting an existing asset creates a usage reference. Detaching removes that reference. Deleting the binary is a separate operation requiring unused status and retention-policy checks. Bulk deletion uses the same rules.

Default library metadata can be overridden per usage, so the same photograph can have suitable alt text/caption in different contexts. Decorative usage can explicitly request empty alt text. Metadata edits do not mutate original file bytes.

## 5. File types and privacy

The library can store supported images, documents, audio, and video under typed collection policies. Storage support does not mean every type has thumbnails, optimization, or public delivery.

Each registered slot declares allowed MIME/types, size/count limits, visibility, permissions, and applicable conversion profiles. Validate actual content/MIME and file limits server-side. Active content such as SVG/HTML requires an explicit sanitization/serving policy; it is not accepted indiscriminately as a safe image.

Public catalog/content assets use public delivery. Receipts, customer documents, and restricted plugin uploads use private disks and authorized delivery; originals and every derivative must share the intended protection. Private assets are excluded from public-content pickers and anonymous APIs. Signed links, when used, are short-lived and generated only after authorization.

Public/private visibility is a durable asset policy. Changing it requires usage review; attaching a private receipt to a public page must fail rather than silently publishing it. Private collections can prohibit general reuse and require ownership-specific checks.

Generated PDFs/invoices may use a dedicated generated-document policy through the same media service when persistence is required. Their retention/access rules remain transactional, not generic marketing-media rules.

## 6. Optimization, conversions, and responsive delivery

Core defines reusable conversion profiles such as thumbnail, product card, product detail, and hero. Profile IDs are namespaced/versioned; plugins/themes may declare additional bounded profiles through a registry. Arbitrary conversion instructions from anonymous requests are unsupported.

Spatie supports derived images and queued conversion processing; see [defining conversions](https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions). Responsive image output can provide appropriately sized sources; see [responsive images](https://spatie.be/docs/laravel-medialibrary/v11/responsive-images/getting-started-with-responsive-images).

Apply format/quality, resize/crop, metadata, and optimization policies centrally. Preserve the original for reprocessing and avoid destructive replacement during optimization. Image optimizers require configured system tools, not just a Composer dependency; see [Spatie image optimization](https://spatie.be/docs/image/v3/image-manipulations/optimizing-images).

Deployment preflight checks selected encoders/optimizer binaries, image processing extensions, storage permissions, and workers. Missing optional tooling is reported as degraded capability; required profiles must pass checks. Video transcoding, audio optimization, and document previews are separate declared capabilities, not automatic compression of every file type.

Asset/profile generation keys prevent duplicate work where possible; retries are safe and processing failures remain observable. New profiles can generate lazily or through controlled backfill jobs. Profile changes must not invalidate historical/private access policy or flood requests with synchronous conversion work.

Media DTOs include authorized original/derivative URLs, dimensions, MIME, alt text, optional `srcset`, and conversion readiness. Render a defined placeholder or allowed original fallback while processing; never generate large derivatives in a storefront request. Cached URLs account for file/profile revision and access classification.

## 7. Core, plugin, and theme contracts

Proposed core operations: upload/import, select/query, attach/detach, reorder, update metadata, resolve presentation/download, request registered conversions, archive, and delete. All check client context, permissions, target ownership, and collection policy.

Illustrative API shape, not an implemented interface:

```php
$asset = $mediaLibrary->upload($upload, $policy, $actor);
$mediaLibrary->attach($asset->id, $product, 'catalog.product.gallery', $usageMetadata);
$image = $mediaLibrary->present($asset->id, 'core.product-card.v1', $viewer);
```

Plugins register namespaced slots/collection policies and optional conversion profiles. They use the core picker/service instead of adding another media package version, table, direct `Storage::put` flow, or unaudited file deletion. Authorized provider-download pipelines may use core storage internals through the same adapter contract.

Themes consume prepared Media DTOs and shared rendering components. Theme settings store validated references to persistent content/branding records whose media usages are tracked, rather than leaving unindexed asset IDs scattered in arbitrary JSON. A presentation theme switch does not own or delete merchant uploads.

Theme/plugin packaged icons, styles, fonts, and illustrative assets remain immutable package assets. They are not merchant uploads. Import sample merchant images into the library explicitly when needed; do not require media-library entries for every bundled CSS icon.

## 8. Retention, replacement, and failure handling

Deleting a Spatie-owning model can delete associated files; see [retrieving/deleting media](https://spatie.be/docs/laravel-medialibrary/v11/basic-usage/retrieving-media). The central asset owner therefore must not be hard-deleted merely because a product, page, or plugin is removed.

Detach/reconcile usage on source-record deletion and retain unknown-plugin usages until explicitly reconciled. Disable/remove an extension without destroying its referenced assets. A usage-aware lifecycle service controls cleanup; database cascades alone cannot protect file lifecycle or retained history.

Replace image bytes by creating a new immutable asset/version and updating chosen usages. Preserve the old asset when historical orders/documents still refer to it. Order snapshots retain protected media references and relevant presentation metadata; storing a URL alone does not guarantee the file survives.

Deletion checks all live usages and historical retention holds atomically with attachment operations. Background storage deletion is tracked/retriable; a database transaction cannot roll back object-store writes. Clean up failed uploads/orphans through a reconciler with a documented grace period, not blind deletion of apparently unused files.

Backups/recovery include media metadata, originals, required derivatives or regeneration instructions, usage references, retention holds, and storage credentials/configuration. Client isolation includes conversion jobs, paths, caches, and public delivery configuration.

## 9. Migration from the current store

Inventory product main/gallery images, category/slide images, branding/gallery settings, private receipts, and retained order-image paths. Import idempotently with a legacy-path-to-asset mapping and checksum metadata; preserve existing business associations and identify missing files.

Create usage references for products/content/branding and retention holds for historical order media. Deduplicate only compatible assets within the same client/access policy; never merge a private receipt into a public catalog asset based solely on a matching hash.

Keep a tested compatibility resolver for legacy references during rollout. Verify URLs, sorting, alt text, image dimensions, receipt authorization, file counts/checksums, and historical invoices/order pages. Convert public receipt storage to private delivery with a documented cutover that removes old public access.

The existing Intervention image dependency can remain during migration if needed. Evaluate removing redundant processing only after centralized conversions preserve required behavior; do not assume Spatie uses or needs `intervention/image-laravel`.

## 10. Acceptance criteria

1. Upload once and select the same asset for a product, content section, and plugin record without duplicating the original.
2. Central library shows every usage; detaching/deleting a product does not break other references.
3. Single/multiple slots enforce types/counts/ordering and server-side permissions.
4. Public themes cannot select/read private receipts or their thumbnails.
5. Queued profiles produce expected dimensions/format; pending/failed conversions have safe UI fallback and retry behavior.
6. Core, themes, and plugins use the same picker and media contracts; disabling extensions/themes preserves uploads and history.
7. Concurrent attach/delete and failed storage/database operations cannot silently remove an in-use asset.
8. Migration preserves associations, originals, historical media, and private access; recovery rehearsal includes file storage.

## 11. Remaining implementation decisions

Finalize the compatible package version, MediaAsset schema/retention holds, morph-alias registry, policy/profile schemas, initial MIME/size limits, conversion formats/tooling, private delivery mechanism, migration mapping, storage quotas, and cleanup grace period before their dependent implementation.
