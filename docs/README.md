# Platform documentation

These documents define the proposed platform. They do not indicate that its extension runtime or SDK has already been implemented.

| Document | Purpose |
|---|---|
| [Product requirements](PRD.md) | Scope, product behavior, migration, and acceptance criteria |
| [Architecture](ARCHITECTURE.md) | Core boundaries, extension runtime, dependencies, lifecycle, and releases |
| [Plugin development](PLUGIN_DEVELOPMENT.md) | Building business capabilities and extending existing functionality |
| [Theme development](THEME_DEVELOPMENT.md) | Storefront packages, child themes, templates, settings, and assets |
| [Central media management](MEDIA_MANAGEMENT.md) | Shared library/picker, polymorphic usage, Spatie integration, optimization, privacy, and migration |
| [Media operations](MEDIA_OPERATIONS.md) | Processing profiles, order retention, durable cleanup, and recovery commands |
| [Inventory operations](INVENTORY_OPERATIONS.md) | Stock policies, reservations, staff adjustments, order transitions, and concurrency checks |
| [Checkout operations](CHECKOUT_OPERATIONS.md) | Checkout keys, duplicate prevention, receipt recovery, ownership, and retry checks |
| [Local browser testing](LOCAL_TESTING.md) | Admin setup, media worker, and product-image checks |
| [Implementation progress](progress.md) | Completed increments, verification, setup requirements, and remaining work |

Start with the PRD and architecture, then use the relevant development guide. Examples of platform APIs and commands remain proposals until implementation and contract testing finalize them.
