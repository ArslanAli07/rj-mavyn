# Default storefront

Development release 0.1.0. The shared repository bundles this seed and setup copies
it into `themes/default` before Composer installs it through the shared lockfile.
Existing installed directories are preserved, so clone customizations survive
repeated setup. To change defaults for future clones, edit this seed; to customize
one store, edit its installed copy and back it up separately.

Templates follow `Mavyn\Contracts\Themes\TemplateInventory`; theme settings and
selection live in the client database. See `docs/THEME_RUNTIME.md` in the
application for contracts, preview, assets, deployment and recovery.

The prebuilt `dist/` files include storefront styles, the module entry and its
Alpine dependency, recovered from the last committed default theme and storefront
build (`89f2b0e`). Layouts use the current theme asset route instead of host Vite
entries. No npm build is required to use this package. After changing built assets,
update their SHA-256 checksums in `dist/manifest.json`. Rebuild the installed catalog
and validate assets after changing package metadata/resources. Never place merchant
uploads, payment credentials or commerce services in this package.
