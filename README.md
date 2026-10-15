# Project Mavyn

The master repository contains the application, core packages, extension runtime,
and a bundled default theme seed in `resources/themes/default`. Setup copies the
seed to `themes/default` when that directory is absent. Each application clone
owns its installed themes and plugins. Keep customizations and client dependency
configuration in separate repositories or backups; commits to this repository do
not back up ignored files.

## Dependencies for a new clone

`composer.shared.json` and `composer.shared.lock` define the shared dependencies.
`composer.json` and `composer.lock` are generated, ignored files used by normal
Composer commands and the application. `composer.client.json` is an optional,
ignored file that adds the clone's package requirements and repositories.

Prepare the dependency manifest, then run project setup:

```sh
php bin/prepare-composer.php
composer setup
```

Both preparation and `composer setup` copy the bundled default theme before
Composer installs its path package. The theme ships prebuilt CSS and JavaScript;
no separate theme build or download is required. Setup preserves an existing
`themes/default` directory, including local edits. It then installs dependencies,
creates `.env` if needed, generates the application key, migrates the database,
and builds the shared admin assets. Configure database access before running it.
For dependency installation alone, use `composer install` after preparation.

To configure a storefront, copy `composer.client.example.json` to
`composer.client.json`, replace the example package names and constraints, and
copy or check out the packages into `themes/` and `plugins/`. Then run:

```sh
php bin/prepare-composer.php
composer update
php artisan platform:themes:activate your.theme-id
```

The bundled `mavyn/theme-default` package supplies the complete storefront fallback
and is installed through the shared lockfile. A fresh database selects it unless
the reference Happy Tea theme is installed. Additional themes and plugins remain
optional client packages.
Client configuration can add requirements and repositories; it cannot override
shared requirements. An older client's identical `mavyn/theme-default: ^0.1`
requirement is accepted. Package folders alone do not install or activate extensions.

The preparation command combines the shared manifest with the client package
list and initializes a missing lockfile from the shared lock. It preserves an
existing clone lockfile. Run `composer update` after changing either manifest,
then keep the resolved clone lockfile with that client's deployment artifacts.
Subsequent deployments use `composer install` with that lockfile. Composer's
existing hooks build catalogs and validate theme assets. Theme files are served directly from their packages; see [theme asset loading](docs/THEME_RUNTIME.md#assets).

Themes, plugins, legacy `public/themes/` copies, and clone Composer files are
ignored. Core extension APIs, admin tooling, and test fixtures remain shared.
The existing local Happy Tea package list and lockfile have been retained.

For an existing clone, back up its theme/plugin folders and local Composer files
before pulling the commit that removes those files from Git. Restore them after
the pull, create its `composer.client.json`, and run the preparation command.
Git can remove previously tracked files when applying that commit even though
new clone files are ignored afterward.

## Updating shared dependencies

Edit `composer.shared.json` and resolve its lockfile separately:

```sh
COMPOSER=composer.shared.json composer update --no-install --no-scripts
php bin/prepare-composer.php
composer update
```

Commit the shared manifest and lockfile. Do not commit clone dependency files or
extension source. Shared updates reach each clone through Git; regenerate the
clone manifest and resolve/test its dependency graph before deployment.

See [Plugin runtime](docs/PLUGIN_RUNTIME.md) and
[Theme runtime](docs/THEME_RUNTIME.md) for activation and runtime details.

For the resolved completeness findings, supported shipping/attributes/financial contracts and Happy Tea migration acceptance, see [fix validation](docs/FIX_VALIDATION.md). Core-only validation uses `composer test:core`; the full integration suite requires the client reference packages.

Installed plugins support [local editing without repeated Composer updates](docs/PLUGIN_RUNTIME.md#local-development-without-repeated-composer-updates). With `APP_ENV=local`, PHP and platform-manifest changes load directly from the plugin package. New dependencies/autoload mappings still require Composer; new migrations require `php artisan migrate`.

Default `php artisan migrate --seed` initializes only required platform data, including the first administrator and missing core settings. Store catalog/media/demo content is excluded. See [database setup](docs/DATABASE_SETUP.md) for credentials, unattended setup and idempotent behavior.
