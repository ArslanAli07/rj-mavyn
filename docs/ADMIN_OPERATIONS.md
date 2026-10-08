# Admin access and audit operations

Admin access uses the `admin` guard independently of customer login. `admin.permissions` protects every admin application route; missing or unknown route mappings fail closed. Login is rate limited to five requests per minute. Inactive/deleted admins cannot enter the workspace, and permissions are refreshed from the database on each protected request so revocation takes effect on the next request.

## Assigning access

Super admins retain all registered permissions. In **Staff access**, an authorized operator can create accounts, change roles/grants, reset passwords (minimum 12 characters), and deactivate accounts. There is no staff-delete endpoint. Use another authorized account to change your own role, grants, or active status. Owner changes lock the owner rows and preserve at least one active super admin.

| Preset | Access |
|---|---|
| Super admin | All registered permissions |
| Catalog | Catalog view/edit, inventory adjustments, public media view/manage; no deletions or private files |
| Fulfillment | Order view and fulfillment changes; no payment confirmation, receipts, or deletion |
| Support | Order/customer viewing and contact-message handling |
| Custom | Explicit selected permissions |
| Legacy admin / unknown roles | No implicit access; assign grants explicitly |

Preset permissions are materialized when saving staff access. Existing accounts with null permissions use the known preset for their role; an explicit permission array is authoritative. To remove a preset capability, select Custom and adjust the checkboxes. Grant the corresponding `.view` permission with editing/deletion permissions if the staff member needs to browse records and follow redirects after saves.

`staff.manage` allows access management, but a non-owner cannot grant capabilities they do not possess, edit a stronger account, or create/edit super admins. Super-admin status is not an assignable ordinary permission. Unknown permission keys are rejected. The CLI `admin:provision` remains the infrastructure-level owner provisioning path and records a console audit event without credentials.

## Sensitive permissions

Most areas have separate `.view`, `.manage`, and `.delete` grants. Bulk deletion requires the same delete grant as single deletion; other bulk actions use management grants. Dashboard/analytics access is separately controlled by `dashboard.view`.

- `inventory.manage` controls actual product/variant stock or tracking changes, including stock fields submitted through catalog forms. Unchanged stock can accompany a catalog edit. Checks run within inventory services against the locked catalog state.
- `orders.manage` controls fulfillment changes and staff notes. The optional customer-email checkbox records durable status notifications through the same single/bulk transaction; private staff notes never enter those messages. `orders.payment` separately controls payment changes, including bulk payment actions. The order-state service checks permission again against locked current payment state.
- `orders.receipts` plus `orders.view` permits private receipt access. Order viewers without this grant see no receipt preview/link.
- `media.private` is required in addition to the normal media permission to access or operate on private assets. Public media viewers cannot list private assets or retrieve their files; pickers remain public-image-only. Media upload/association operations require `media.manage`, while asset deletion requires `media.delete`.
- `newsletter.export` plus `newsletter.view` permits CSV export.
- `audit.view` permits the read-only audit browser. Access to other admin areas does not grant audit access.

Navigation and order polling follow permissions. Restricted staff land in a neutral workspace rather than the revenue dashboard. Hidden controls are convenience only; server-side checks protect direct requests, JSON requests, bulk actions, uploads, polling, and private files.

## Audit behavior

`admin_audit_logs` records actor ID/name, timestamp, route, action, entity type/ID, and selected before/after fields. Staff roles/grants, passwords (redacted), settings, catalog/inventory, order/payment changes, content, and media changes are observed. Bulk database writes use model events so each affected record has its own audit. Successful mutation requests additionally record an operation event, covering operations such as sitemap regeneration that may not change an observed row.

Authenticated admin mutations and their audit records share a database transaction. Audit persistence failure rolls back database changes; denied requests and validation errors do not create successful-operation records. This does not make external files, SMTP, caches, or other external side effects transactional. Existing domain journals remain responsible for filesystem/worker recovery.

Password values/hashes, arbitrary setting values, private staff notes, and inventory reason text are redacted. Customer emails, receipt paths, request bodies, and credentials are not copied into audit changes. Permission arrays and order/payment/stock fields remain available for review. Successful receipt reads, private media-file reads, and newsletter exports record access events without content.

Audit rows have no actor/entity deletion cascade and survive subject deletion. There are no HTTP edit/delete endpoints, and model mutation of audit rows is rejected. This is application-level retained history, not protection against an operator with direct database access. Include the table in backups. No automatic retention purge or denied-login log is implemented.

Coverage is authenticated admin requests and console owner provisioning. Arbitrary SQL, customer checkout, and worker-only changes do not receive an admin actor; existing inventory movements and media journals retain their automated domain history. Extension activation auditing will be added with the future extension runtime.

## Migration and verification

Apply `2026_10_09_000010_add_admin_permissions_and_audit` before serving the updated application. It adds nullable permission arrays and the audit table; it does not reclassify existing accounts or grant legacy ordinary admins access. An existing active super admin can assign access after migration.

Functional checks cover customer/admin guard separation, inactive/unknown accounts, read/write/delete grants, bulk per-record audits, private media, receipt/payment permissions, stock permissions, export access, immediate revocation, invalid grants, privilege escalation, secret redaction, web validation redirects, immutable history, and database rollback on audit failure. Server-side rendering of workspace/staff/audit views is tested. Browser interaction and simultaneous owner-change testing have not been performed for this increment.
