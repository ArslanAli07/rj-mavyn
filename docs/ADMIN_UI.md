# Admin UI

The admin panel uses Bootstrap 5.3.8, the latest stable release at migration time. Its layouts load `resources/css/admin.css` and `resources/js/admin.js`. The storefront continues to use its own Tailwind assets. Vite keeps Bootstrap JavaScript in a separate chunk loaded by the admin entry.

Use Bootstrap form controls, buttons, cards, tables, alerts, and utilities when adding admin screens. `admin.css` contains the brand colors, sidebar, order status colors, and additional grid and image dimensions. Responsive extensions follow Bootstrap breakpoints (576px, 768px, and 992px). Admin pagination explicitly uses `pagination::bootstrap-5`.

The ecommerce design system uses locally hosted Inter (400/500/600, with 700 reserved for dashboard KPIs) and JetBrains Mono for identifiers. The CSS tokens define neutral surfaces, indigo actions, coral notification accents, and tinted semantic pills. Success and warning pill text is slightly darker than the base tokens to exceed 4.5:1 contrast on the supplied tints. Cards use an 8px radius; table thumbnails use a 6px radius. Currency columns align right and use tabular figures with the shared `format_price` helper.

Use sentence case for labels and actions. Page titles are 24/32px, section headings 18/28px, table cells 14/20px, and form controls 16/24px. Table headers alone use uppercase. Payment pills distinguish pending, paid, failed, and refunded. Cancellation and refund status updates show danger actions and require confirmation, including bulk updates.

Alpine handles settings tabs, product options, inventory fields, and media selection. Use `x-show.important` on elements with Bootstrap display utilities so Alpine can hide them. Bootstrap handles the responsive sidebar through its offcanvas data attributes.

Run `npm ci` and `npm run build` before serving the updated templates. Verify with `php artisan test --compact --filter=AdminBootstrapTest`. To export rendered fixture pages for browser checks, set `ADMIN_PREVIEW_DIR` to a temporary directory when running that test.
