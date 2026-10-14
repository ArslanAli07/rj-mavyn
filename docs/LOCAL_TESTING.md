# Local browser testing

Use http://mavyn.test/ and http://mavyn.test/admin/login. Set local `APP_URL=http://mavyn.test` so media URLs use the same host.

## Setup

`php artisan migrate --seed` initializes only required platform settings and the first administrator. Interactive first setup asks for credentials; unattended setup uses `MAVYN_ADMIN_EMAIL`/`MAVYN_ADMIN_PASSWORD`. Existing accounts/settings and store records are preserved. See [database setup](DATABASE_SETUP.md).

- `php artisan storage:link --force` points `public/storage` to this workspace’s `storage/app/public`.
- `php artisan admin:provision <email>` creates an active super admin using a hidden password prompt (minimum 12 characters). Existing emails are rejected; the command does not reset accounts.
- For local setup only, `php artisan admin:provision <email> --local-password-file` generates credentials in `storage/app/private/local-admin.txt` with mode 0600. The file is outside public storage and excluded from Git; an existing credential file is never overwritten.
- `php artisan queue:work --sleep=1 --tries=3 --timeout=120` processes queued thumbnail/card conversions. Keep the worker running and restart it after code/configuration changes.

The current local admin email is `owner@mavyn.test`; read its password from the private credential file. Default admin credentials are no longer seeded.

## Product main-image journey

1. Open Media Library and upload a public JPEG, PNG, WebP, or GIF image.
2. Create/edit a product and choose its main image from the library. Save it and inspect its storefront card/detail page.
3. Select the same image for a second product; the library should show two tracked `catalog.product.main` usages and disable deletion.
4. Replace the first product’s selection; the second product retains the shared image and its original file survives.
5. Clear a selection and save to detach its usage. An existing legacy image becomes visible again; otherwise the existing placeholder behavior applies.
6. Private assets and non-image documents must not appear in this picker. Submitted IDs are also checked on the server.

Legacy uploads remain available. Choose either a library asset or a legacy upload in one save; selecting both is rejected. Library galleries support up to 30 distinct public images, with Earlier/Later controls to persist their order. Library selections take precedence over the legacy gallery; clear and save to restore the legacy gallery. Omitting picker fields in a partial request preserves existing references.

Automated verification: `php artisan test`. Browser verification completed on 2026-10-08 with no JavaScript errors. Local sample records are named “Media browser check”, “Media check first product”, “Media check second product”, and “Browser check … image”. The first product is detached after the check; the second retains the shared image. No existing store records were imported or modified.

## Other image fields

- Category create/edit: choose a category image from the shared library; clear/save to restore its legacy feature image.
- Hero slide create/edit: select desktop and optional mobile images. A new slide requires a desktop library image or upload. Clearing mobile selection falls back to an existing legacy mobile image, then desktop. A slide with no legacy desktop image cannot clear its only desktop reference without selecting/uploading a replacement.
- General Settings → Branding & Gallery: select the store logo, favicon, and ordered site gallery images. Logo/favicon render in storefront/admin/authentication layouts; logos also render in transactional emails. The site gallery appears on both the homepage and gallery page, including its lightbox. Clear/save restores the bundled branding or legacy gallery.
- Content deletion detaches all tracked image slots, including through supported bulk delete routes. Originals remain in the library and other content references continue working. Library deletion remains blocked while any reference exists.

The extended browser check passed on desktop/mobile on 2026-10-08. One sample slide, the sample category/product galleries, and the site gallery remain for inspection. Branding selections were restored after testing; duplicate sample slides were removed. The suite now passes 84 tests / 495 assertions.

For lifecycle status, responsive profiles, private derivatives, cleanup retries, and reconciliation, see [Media operations](MEDIA_OPERATIONS.md). Run `php artisan media:preflight` after changing media storage/processing configuration.
