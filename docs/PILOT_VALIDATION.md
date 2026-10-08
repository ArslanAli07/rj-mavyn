# Stationery pilot and shared release validation

The selected non-food catalog is stationery in PKR. The synthetic Paper Trail store uses the default theme, local Pakistan delivery, and manual payments. Food attributes and store availability are disabled. A separate Tea Pilot uses Happy Tea and all four commerce plugins. Fixtures only run in the testing environment; they never seed the merchant database.

## Catalog and checkout

The stationery fixture creates an Everyday Journal through the admin controller with A5/A4 variants, required paper-color options, optional paid gift wrap, and shared inventory. The storefront uses product/variant wording and hides food fields. Both themes now preserve fractional PKR prices through the shared integer-based `Money::format` helper; the old display rounded 125.55 to 126.

Two journals with gift wrap cost 260.00. A 5.25 coupon and 6.35 delivery fee produce a 261.10 total. Tests exercise guest checkout with a private wallet receipt, two reserved units, retained variant/option/title snapshots, one confirmation outbox entry, owned payment instructions, tracking, and idempotent replay. An unrelated signed-in customer cannot access the order. The existing guest coupon email/IP policy remains in effect.

This fixture covers PKR and the existing Pakistan delivery/manual-payment policies. Other currencies, carrier integrations, and tax calculations require their own implementation and acceptance.

## Reproduce the release rehearsal

```sh
php artisan test --filter='NonFoodPilotTest|ReleasePreflightTest|MoneyTest'
php tests/Support/pilot-deployment.php
```

The standalone script creates two isolated SQLite clients with different databases, application keys, domains, storage directories, session cookies, cache prefixes, and queue names. It copies the current source inventory and installed dependencies into two temporary shared artifacts. The synthetic baseline reinstates the former rounded-price formatter; it is a regression fixture, not an earlier published release. The candidate contains the shared fix. Both clients receive the same candidate source hash with no client-specific source changes.

Each client starts with an order, backs up its fixture database/environment/receipt, and verifies the database copy through a separate connection and integrity check. Candidate checkout creates a second order and retains the first, existing brand/theme/plugin state, stock reservations, receipts, and outbox messages. Cross-client tracking tokens, cookies (including renamed session cookies), and private receipt paths are rejected. A tampered inventory fails preflight and produces a failed history entry.

Recovery runs compatible baseline code against the current client database and storage. It retains both orders. The backup has only the first order, explicitly exposing one post-backup transaction that would be lost by replacing the current database. No live database is restored or changed by this script.

The script removes its own temporary fixtures on completion. Use `--keep` to retain synthetic artifacts for inspection; the private temporary directory then contains fixture keys, sessions, databases, and receipts. The final JSON contains fixed check descriptions and release/profile metadata, with no credentials.

## Acceptance still required

This is an offline application/HTTP-kernel rehearsal. It does not validate a web server, browser layout/accessibility, live SMTP, MySQL backup restoration, production worker coordination, or a hosting provider. Backup coverage is limited to the known fixture database, environment, and receipt; production requires all persistent client files and recovery dependencies.

Existing-store backfill still requires its actual records and original media/receipt files. The three missing local slide originals remain unresolved. Public package publication, real staging cutover with client isolation, and approved live deployment remain outstanding. See [deployment operations](DEPLOYMENT_OPERATIONS.md) and [implementation progress](progress.md).
