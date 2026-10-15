# Platform documentation

These documents describe the platform and its implemented increments. The progress log and operations guides distinguish working commands from remaining design proposals.

| Document | Purpose |
|---|---|
| [Product requirements](PRD.md) | Scope, product behavior, migration, and acceptance criteria |
| [Architecture](ARCHITECTURE.md) | Core boundaries, extension runtime, dependencies, lifecycle, and releases |
| [Fixes and acceptance](FIX_VALIDATION.md) | Resolved findings, supported contracts, Happy Tea rehearsal and remaining production scope |
| [System completeness review](COMPLETENESS_REVIEW.md) | Current verification, confirmed gaps, and work required to finish the initial platform |
| [Plugin development](PLUGIN_DEVELOPMENT.md) | Building business capabilities and extending existing functionality |
| [Theme development](THEME_DEVELOPMENT.md) | Storefront packages, child themes, templates, settings, and assets |
| [Central media management](MEDIA_MANAGEMENT.md) | Shared library/picker, polymorphic usage, Spatie integration, optimization, privacy, and migration |
| [Media operations](MEDIA_OPERATIONS.md) | Processing profiles, order retention, durable cleanup, and recovery commands |
| [Inventory operations](INVENTORY_OPERATIONS.md) | Stock policies, reservations, staff adjustments, order transitions, and concurrency checks |
| [Database setup](DATABASE_SETUP.md) | Required settings/admin initialization without demo catalog or media content |
| [Admin operations](ADMIN_OPERATIONS.md) | Staff permissions, sensitive actions, retained audit history, and access assignment |
| [Independent currency pricing](MULTI_CURRENCY.md) | Public price-book/quote contracts, currency eligibility, provenance migration, concurrency, and client upgrades |
| [Checkout operations](CHECKOUT_OPERATIONS.md) | Checkout keys, coupon identity, receipt recovery, durable confirmations, and retry checks |
| [Local browser testing](LOCAL_TESTING.md) | Admin setup, media worker, and product-image checks |
| [Implementation progress](progress.md) | Completed increments, verification, setup requirements, and remaining work |
| [Pilot validation](PILOT_VALIDATION.md) | Stationery in PKR, shared release rehearsal, client isolation, and remaining acceptance |
| [Deployment operations](DEPLOYMENT_OPERATIONS.md) | Release inventories, client preflight, backup evidence, and recovery boundaries |

Start with the PRD and architecture, then use the relevant development guide. Examples of platform APIs and commands remain proposals until implementation and contract testing finalize them.

- [Core package operations](CORE_PACKAGES.md): implemented package boundaries, validated settings, distribution requirements, and remaining extraction.

- [Implemented plugin runtime](PLUGIN_RUNTIME.md): manifests, discovery, lifecycle, hooks/providers, settings, route guards, commands, and deployment boundaries.

- [Commerce plugins and compatibility adapters](COMMERCE_PLUGINS.md)

- [Implemented theme runtime and recovery](THEME_RUNTIME.md)
