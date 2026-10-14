# Project Mavyn

The master repository contains the application, core packages, and extension
runtime. Each application clone supplies its own themes and plugins. Keep their
source and client dependency configuration in separate repositories or backups;
commits to this repository do not back up ignored files.

## Dependencies for a new clone

`composer.shared.json` and `composer.shared.lock` define the shared dependencies.
`composer.json` and `composer.lock` are generated, ignored files used by normal
Composer commands and the application. `composer.client.json` is an optional,
ignored file that adds the clone's package requirements and repositories.

Install the shared application before adding client packages:

```sh
php bin/prepare-composer.php
composer install
```

To configure a storefront, copy `composer.client.example.json` to
`composer.client.json`, replace the example package names and constraints, and
copy or check out the packages into `themes/` and `plugins/`. Then run:

```sh
php bin/prepare-composer.php
composer update
php artisan platform:themes:activate your.theme-id
```

The runtime requires a theme with the ID `mavyn.theme-default` as its complete
fallback whenever themes are installed. Supply that package separately alongside
your chosen theme. A core installation without themes cannot serve a storefront.
Client configuration can add requirements and repositories; it cannot override
shared requirements. Package folders alone do not install or activate extensions.

The preparation command combines the shared manifest with the client package
list and initializes a missing lockfile from the shared lock. It preserves an
existing clone lockfile. Run `composer update` after changing either manifest,
then keep the resolved clone lockfile with that client's deployment artifacts.
Subsequent deployments use `composer install` with that lockfile. Composer's
existing hooks build catalogs and publish theme assets.

Themes, plugins, generated `public/themes/` assets, and clone Composer files are
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
