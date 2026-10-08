# Happy Tea child example

Development release 0.1.0. Installed through the client Composer lockfile. Templates follow `Mavyn\Contracts\Themes\TemplateInventory`; theme settings and selection live in the client database. See [implemented runtime](../../docs/THEME_RUNTIME.md) for contracts, preview, assets, deployment and recovery.

Ship prebuilt `dist/` files with SHA-256 checksums in `dist/manifest.json`. Rebuild the installed catalog and publish assets after changing package metadata/resources. Never place merchant uploads, payment credentials or commerce services in this package.
