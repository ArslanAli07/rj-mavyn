# Mavyn core

Independent PHP 8.3+ commerce foundations. Depends on `mavyn/contracts`, Composer semver, and mbstring; no Laravel or host application classes are required.

Public services:

- `Money`: integer minor units, decimal strings, percentage rounding; two-decimal currencies only.
- `CouponPricing`: minimum subtotal, fixed/percentage discount, optional cap, subtotal clamping. Eligibility and usage limits belong to the transactional host adapter.
- `CouponIdentity`: normalized email plus canonical IP hash, including IPv4-mapped IPv6 normalization. This identifies a pair; it does not verify email ownership.
- `ProductConfiguration`: canonical variant/options/request signatures, preserving historical checkout identity.
- `PaymentMethods`: enabled built-in method IDs through the `SettingsStore` boundary. Provider execution and registration are later plugin work.
- `SettingsSchema`: declared keys, defaults, storage types, permission/secret metadata, and validation rule metadata. The host interprets validation rules and authorizes writes; the package performs no persistence.

`src/settings.json` is included in Composer distribution and is the shared source for host form defaults and validation. Credentials, arbitrary keys, and raw media asset IDs are excluded. Media selection remains a separately validated library operation. Existing legacy rows remain readable and are not rewritten by installation.

Root Composer uses versioned path repositories for development. Production installations must include `packages/` alongside the host when using its current lockfile. Copying the host alone is insufficient. Packages can also be distributed independently through tagged Composer releases; this change does not publish them.

Persistence, locking, inventory reservations, outbox delivery, shared admin routes/views, and migrations remain host adapters for now. Extraction is incremental: compatibility wrappers preserve `App` names and existing serialized jobs/model identities. The Plugins namespace now implements catalog validation/dependency ordering, typed settings normalization, hooks, provider registries, and registration contexts. Discovery/state/routes/admin are host adapters. See ../../docs/PLUGIN_RUNTIME.md for implemented extension APIs. This package is not a complete standalone storefront.
