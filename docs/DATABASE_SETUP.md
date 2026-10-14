# Required database initialization

The default migration/seeding path initializes the platform without adding store content:

```sh
php artisan migrate --seed
```

Migrations create the schema and required runtime/configuration records. The default `DatabaseSeeder` runs only `AdminSeeder` and `InitialSettingsSeeder`. It never calls product, category, image, slide, delivery-zone, coupon, testimonial, FAQ or customer seeders. The old `MAVYN_DEMO_SEED` flag has no effect on default seeding.

## First administrator

If the admins table is empty, interactive seeding asks for an email and a hidden password of at least 12 characters. The account is active and has the `super_admin` role. Passwords are hashed and creation is audited with credentials redacted. No fixed administrator email/password is embedded in the seeders.

For unattended setup, configure private environment values before running seeding:

```dotenv
MAVYN_ADMIN_NAME="Store Owner"
MAVYN_ADMIN_EMAIL=owner@example.com
MAVYN_ADMIN_PASSWORD="your-own-strong-password"
```

```sh
php artisan migrate --seed --force --no-interaction
```

Keep these credentials out of Git. Remove the bootstrap password from the environment after provisioning and rebuild/clear any cached configuration that contained it. Existing administrator accounts are preserved; seeding does not reset passwords, change roles or create another account when an administrator already exists. Missing credentials in noninteractive setup leave administrator creation to `php artisan admin:provision owner@example.com`; a warning explains this. Partially configured/invalid credentials fail validation and roll back the bootstrap transaction.

## Initial settings

Seeding adds only missing declared platform defaults, respecting the configured financial identity/country/timezone: application/site name, notification switches, supported currency/symbol, flat tax, country, timezone and locale. Unconfigured optional content/contact fields retain their normal fallbacks. There is no gallery, logo/image reference, sample business address, provider account, FAQ or marketing-copy seed. Installed reference-plugin migrations can initialize their own required settings/activation records and retain legacy configuration during an upgrade.

Repeated `php artisan db:seed` is idempotent. Existing settings (including their types/groups/timestamps), account credentials, catalog, slides, media and other client records remain unchanged. It is an initializer, not a database reset or existing-store cleanup operation.

The former home-slide migration now only creates its table; fresh databases no longer receive the three sample cake/pastry/brownie slides or their missing image references. Already-applied migrations are not rerun, and existing slides/content are preserved. Historical schema/data backfills continue to adapt existing records; they do not create a sample catalog on an empty database.

## Explicit demo fixtures

Legacy Happy Tea fixtures remain available only through an explicitly named seeder:

```sh
php artisan db:seed --class=DemoDatabaseSeeder
```

This is restricted to local/testing environments and an empty store database. It rejects existing merchant records before invoking legacy fixture seeders. Demo fixtures contain legacy image paths and require matching client assets; they are not required platform data and are not a production migration/import tool.

## Verification

Core-only `migrate --seed` succeeds without themes/plugins and leaves all catalog/media/customer/order tables empty. Regression coverage verifies strong/hashed admin credentials, the hidden interactive prompt, no default account in unattended setup without credentials, idempotency and preservation of existing content, atomic rollback for invalid credentials and explicit-demo rejection for populated/production databases. Content-media tests now create their own slide fixtures instead of depending on migration-created demo content.

Final validation: 384 application tests / 8,236 assertions and 21 core-only tests / 111 assertions pass. Fresh setup, empty-store storefront/admin pages, configured currency preservation, repeated seeding and credential handling are covered. No existing merchant database was reset or seeded during validation.
