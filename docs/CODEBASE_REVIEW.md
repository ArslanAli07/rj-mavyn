# Codebase review — 2026-10-13

The review covered the Laravel host, routes and middleware, customer/admin controllers, models and migrations, commerce services, media lifecycle, notification outbox, Composer core/contracts packages, bundled plugins and theme runtime, storefront templates, frontend entry point, and test/deployment tooling. This is a code review with automated verification, not a guarantee that every possible input or deployment is bug-free.

## Verification

| Check | Result |
| --- | --- |
| Existing suite before changes | 282 tests, 2,091 assertions passed |
| Final suite | **342 tests, 2,342 assertions passed** |
| New regression cases since the original review baseline | 60 |
| PHP syntax | 362 PHP files passed, including tests and migrations |
| Composer manifest | `composer validate --no-check-publish` passed |
| Frontend | `npm run build -- --outDir /tmp/mavyn-review-build` passed |
| Checkout concurrency | Disposable MySQL scenarios passed |
| Inventory concurrency | Disposable MySQL shared/variant stock and cancellation scenarios passed |
| Plugin/theme concurrency | Disposable MySQL activation, dependency and settings scenarios passed |
| Patch whitespace | `git diff --check` passed |

The local `vendor` directory initially lacked the monorepo packages. `composer install` restored nine path packages and rebuilt their catalogs/assets without changing either lockfile. MySQL checks created and dropped isolated, randomly named test databases. Feature tests used SQLite and fake/test storage and mail. No live customer orders or external email delivery were used. The frontend build went to `/tmp`, preserving the checked-in production build.

## Fixed findings

Regression tests were run before the corresponding fixes to reproduce failures, then rerun after the changes. The fixes include:

| Priority | Failure and resulting behavior | Main implementation |
| --- | --- | --- |
| High | An admin audit failure rolled back deleted category/slide/gallery/order rows after their files were already removed. Legacy file cleanup now waits for the outer transaction to commit. Tests cover rollback preservation and successful cleanup for all four paths. | Admin category, slide, product and order controllers |
| High | Submitting an option value from another product or another group silently skipped it and deleted the group's existing choices. Nested IDs now require the correct product/group ownership. | Admin product validation |
| High | Repeated variant/group IDs overwrote the same records during synchronization. Duplicate IDs are rejected before changes. | Admin product validation |
| High | Newsletter exports permitted spreadsheet formulas in subscriber names and did not escape quotes/newlines correctly. CSV now uses `fputcsv` and prefixes dangerous cells as text. | Admin newsletter export |
| Medium | Guest carts disappeared from the visible cart on login/registration because the existing transfer method was never called. Successful authentication now transfers and merges the guest cart, retaining different configurations. | Authentication controllers, `CartService` |
| Medium | Calling guest transfer without a customer login could match the guest row itself and delete it. The operation now requires the customer guard. Merges are transactional; combined quantities exceeding 20 are capped with a customer notice. | `CartService::mergeGuestCart` |
| Medium | Reconfiguring an item into another existing cart row retained its stale price/snapshot. The surviving row now receives the freshly resolved configuration. | `CartService::update` |
| Medium | Changing an account email retained verification of the previous address. Email changes now clear `email_verified_at`; unchanged emails preserve it. | Account profile update |
| Medium | Product detail/related caches retained inactive/deleted products and stale variant prices. These queries now read current catalog data. | Storefront product controller |
| Medium | Unknown category/occasion filters returned the entire shop, and inactive filters could expose their products. Filters now resolve active records and return an empty result for unavailable selections. | Shop controller |
| Medium | Price filtering used regular prices even when cards displayed discounted prices. It now filters `COALESCE(discount_price, price)`. | Shop controller |
| Medium | Array-valued search queries and newsletter names caused server errors. These fields, shop filters and guest wishlist IDs now receive structural validation. | Search, shop, wishlist and newsletter controllers |
| Medium | Guest wishlist requests displayed inactive products. Guest selections now require active products. | Wishlist controller |
| Medium | Date-only coupons expired at the beginning of their final day. They now remain valid through that day's end. Future start dates remain unavailable. | Coupon model |
| Medium | Negative option adjustments could create negative unit and line prices. Such configurations are rejected; valid reductions and zero prices remain supported. | Product configuration service |
| Medium | Replacing a legacy main image deleted the original still referenced by historical orders. Replacement now preserves that original. | Admin product controller |
| Medium | Clearing an admin note silently retained the old note. An explicitly submitted empty note now clears it without generating a status notification. | Admin order controller |
| Medium | SEO persistence accepted an unvalidated nested `page_key` and could move settings to a different page. Allowed page/field keys are now validated and only validated values are persisted. | Admin SEO controller |
| Medium | SQLite rollbacks failed when dropping media lifecycle columns or tracking tokens while their indexes remained. Rollbacks now drop those indexes first. | Media lifecycle and tracking-token migrations |
| Medium | Rolling back nullable slide fields failed on existing null values. The rollback restores the previous defaults before restoring required columns. | Slide content migration |
| Test reliability | The inventory concurrency fixture depended on current opening hours and accepted any cart validation failure as stock rejection. Its ordering window is now explicit and only stock errors count as expected rejections. | Inventory concurrency script |
| Medium | Generated product/category/occasion slugs reached database errors on duplicate or colliding names. The actual generated URL is now validated before writes, and a concurrent slug conflict returns a field validation error. Names generating an empty URL are rejected; blank category slugs on updates preserve the existing URL. | `CatalogSlugService`, admin catalog controllers |
| Low | Both themes and six models used a missing JPEG fallback. All references now use a shipped SVG placeholder, which is included in release inventories. | Public image, models, themes, release manifest |
| High | Simultaneous cart additions could create duplicate configured rows; cart reconfiguration could save only part of a merge. Cart mutations and checkout now serialize through a persistent lock row for each cart identity, and mutations use transactions. MySQL races verify empty/existing/guest carts, reconfiguration, and an addition during checkout. | `CartOwnerLockService`, `CartService`, cart owner migration |

Tests were added in `CustomerCartRegressionTest`, `CatalogRegressionTest`, `CouponNewsletterRegressionTest`, `AdminProductIntegrityTest`, `LegacyFileTransactionTest`, and `MigrationRollbackTest`, with additional cases in existing profile, product option, SEO and order notification tests. File transaction tests exercise real commits and full migration rollback, rather than relying solely on a surrounding test transaction.

The subsequent fixes add 18 cases in `CatalogSlugTest`, `PlaceholderImageTest`, and `CartMutationIntegrityTest`, plus five MySQL contention scenarios in the existing checkout concurrency script. The full suite increased from 324 to 342 cases. The slug tests include a conflict inserted after validation; the cart rollback test injects a source-row deletion failure and checks that both rows retain their original quantities.

## Remaining findings and limits

- **Legacy cleanup recovery:** cleanup after commit prevents rollback data loss, but these callbacks do not have the media library's durable cleanup journal. A crash immediately after commit can leave an orphaned legacy file. Uploads written before a later transaction rollback can also leave orphaned files. Preservation is preferable to losing a referenced original, but recovery/retention remains separate work.
- No manual browser/accessibility pass, live SMTP/provider test, dependency vulnerability audit, fulltext search test against MySQL, production deployment test, or coverage measurement was performed. The Vite build and existing rendered-checkout JavaScript tests provide narrower frontend evidence.

## Behavior and operational implications

Product detail and related queries now trade the old ten-minute cache for catalog freshness; assess query volume under production traffic before reintroducing caching with complete invalidation. Login merges cap matching rows at the existing 20-unit limit and show a notice; checkout still revalidates stock. Preserving replaced legacy main images consumes additional storage until a retention-aware cleanup process is introduced. The fixes to the three existing migrations affect rollback behavior only and do not require reapplying those migrations.

The cart concurrency fix adds migration `2026_10_13_000001_create_cart_owner_locks`; **apply this new migration before serving the updated cart code**. It creates an initially empty table without modifying existing cart contents. Lock rows contain hashed cart identities and creation timestamps and are created lazily. They must remain present even after a cart becomes empty. No automatic lock-row retention cleanup is implemented. Catalog slug collisions now require a different product title/name or, for category/occasion creation, an explicitly distinct slug.

Run the verification again with:

```sh
php artisan test --compact
php tests/Support/checkout-concurrency.php
php tests/Support/inventory-concurrency.php
php tests/Support/plugin-concurrency.php
composer validate --no-check-publish
npm run build -- --outDir /tmp/mavyn-review-build
git diff --check
```

The three concurrency scripts require access to local MySQL and permission to create/drop their disposable databases.
