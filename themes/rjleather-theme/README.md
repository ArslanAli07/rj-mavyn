# RJ Motorbike storefront

Independent Mavyn theme for RJ Motorbike. The landing page and the site shell
(header and footer) follow the supplied storefront design, restyled in the
store's own palette: ink black, one yellow accent (`#EDE734`), cool greys and
white. No colour, wording or photograph is taken from the reference design; the
reference supplied the layout, proportions and section rhythm.

No core application source, plugin or commerce rule is changed. Pricing, stock,
validation, coupons, checkout, order ownership and fulfilment remain in the
host.

The package ID is `rjleather.theme-storefront`. It inherits `mavyn.theme-default`,
which provides the complete account, authentication, checkout, tracking and content
template inventory. The RJ Motorbike layout and styles apply to those pages too; the
shared admin keeps its own interface.

## Landing page structure

| Section | Content and data source |
|---|---|
| Announcement bar | Theme setting `announcement_text` |
| Header row 1 | Store logo (media library), centred search form, country/locale, account, wishlist and bag |
| Header row 2 | Shop All, categories, Our Story, Contact |
| Hero | Admin hero slides with a working dot pager and arrows; falls back to the theme settings `hero_title`, `hero_description`, `hero_button` |
| Category tiles | Admin categories flagged **Show on homepage** (image + label + piece count) |
| Product row | The first matching category group, four products, real discount, wishlist and product link |
| Top sellers band | Bestsellers (then featured, then all) in a dark horizontal strip with arrows |
| Further product rows | One row per matching group: For Men, For Women, MotoGP, Motorcycle |
| Collection showcase | Three categories (Customize, Leather Collection, Moto Speeds when present) with overlay panels and arrows |
| Bespoke gifting | Split feature with a gallery image and a contact action |
| Editorial | Two care-and-craft cards linked to About and FAQ, using gallery media when uploaded |
| Newsletter | Inline form posting to the existing newsletter endpoint |
| Footer | Contact details, promo card, social links, accepted payments, four link columns and the legal row |

## Navigation

The primary navigation is built from the catalogue structure in
`resources/catalogue.php`, the single source shared by the navigation and the
landing page:

```
Shop all · For Men ▾ · For Women ▾ · MotoGP ▾ · Motorcycle ▾ · Collections ▾ · Our Story · Contact
```

Every group dropdown lists that group's subcategories and links to their real
category pages. The panels open on hover, on keyboard focus and from the caret
button (touch), and close with Escape or an outside click. The mobile menu
repeats the same tree with the subcategories indented, and the footer *Discover*
column lists the top-level categories.

Because the platform stores categories flat, a subcategory is recognised by the
`{group}-{subcategory}` slug that the seed script writes, so nesting follows the
catalogue automatically. A category the merchant creates by hand appears as a
top-level entry, and one named `for-men-something` nests under For Men.

The category structure is resolved by name from the Admin catalogue (`For Men`,
`For Women`, `MotoGP`, `Motorcycle`, plus the `Leather Collection`, `Moto Speeds`
and `Customize` collections). Create those categories in Admin and enable
**Show on homepage** for the ones you want featured: the matching section then
uses that category's real products, images and links.

Until the catalogue holds them, each section keeps the designed shape and holds a
reserved slot per piece, labelled with the subcategory it will carry, so the
storefront always reads as a finished design rather than an empty page. The slot
map follows the agreed catalogue structure:

| Group | Subcategories |
|---|---|
| For Men | Chappals, Chaps, Coats, Gloves, Jackets, Leather Handbags, Leather Kilts, Movies Leather Jacket for Men, Pants, Vests |
| For Women | Chaps, Coats, Film Leather Jacket for Women, Gloves, Jackets, Leather Kilts & Skirts, Movies Jackets, Pants, Vests |
| MotoGP | Brands, Footwear, Gloves, Jackets, Pants, Race, Race Suits |
| Motorcycle | Footwear, Gloves, Jackets, Pants, Race Suit, Vests |
| Collections | Leather Collection, Moto Speeds, Customize |

A group that exists in Admin always wins over the reserved slot, so nothing the
merchant adds is ever hidden by the fallback.

### Creating the catalogue structure

```sh
php themes/rjleather-theme/bin/seed-catalog-structure.php
```

This creates the groups, their subcategories and the collections in the merchant
catalogue using the names above — categories only, with no products, images or
demo content. The groups and collections are active and featured for the
homepage; the subcategories are active so their pages resolve, and they are
nested under their group in the navigation by their slug. The script is
idempotent: run it again to update, never to duplicate. Delete anything from
Admin → Categories if you would rather not keep it.

## Photography

**Nothing has to be uploaded for the storefront to look finished.** The theme
ships brand-appropriate leather artwork in `resources/images/skins/` (generated
by `bin/generate-skin-artwork.py`), and every image slot falls back to it:

| Slot | Fallback | Where real media comes from |
|---|---|---|
| Hero banner | `hero-leather.jpg` | Admin → Home slides (desktop + mobile) |
| Category tiles | `skin-*.jpg` (rotating tones) | Admin → Categories → category image |
| Collection showcase | `skin-onyx/oxblood/chestnut.jpg` | Admin → Categories → category image |
| Bespoke gifting | `workshop.jpg` | Admin → homepage gallery (`homepage_gallery`) |
| Editorial cards | `workshop.jpg`, `care.jpg` | Admin → homepage gallery |
| Newsletter band | `care.jpg` | Admin → homepage gallery |
| Product cards | product image in Admin | Admin → product image |

Real data always wins: as soon as a slide, category image, gallery item or
product image exists, the theme uses it instead of the artwork. No code change is
needed to swap anything.

### Photography brief

Ready-made prompts for every image, with sizes and destinations, live in
[PHOTOGRAPHY-PROMPTS.md](PHOTOGRAPHY-PROMPTS.md). It covers the hero slides, the
category tiles, the collection showcase, the gifting, editorial and newsletter
blocks, product photography and the brand files.

When you are ready to shoot, this is everything the landing page needs.

| Asset | Size / ratio | Subject | Where it goes |
|---|---|---|---|
| Hero | 2000×1000 (2:1) + 1000×1250 mobile | A rider in a leather jacket, or a jacket laid out on a bench. Keep the left 45% calm — the headline sits there | Admin → Home slides |
| Category tile | 1200×900 (4:3) | One hero piece per group: For Men, For Women, MotoGP, Motorcycle, Leather Collection | Admin → Categories → image |
| Collection card | 900×1125 (4:5) | Portrait of a statement piece or a collection mood | Admin → Categories → image |
| Product | 1200×1200 (1:1) | The piece on a plain stone or paper backdrop; the card adds the beige stage | Admin → product image |
| Bespoke | 1600×1067 (3:2) | The bench: tools, thread, a half-finished panel | Admin → homepage gallery |
| Editorial | 1600×1067 (3:2) | Hands stitching, hardware, a finished jacket detail | Admin → homepage gallery |
| Logo | SVG, transparent | Wordmark plus the monogram | Admin → General Settings → logo |

To replace the packaged artwork instead of using Admin, overwrite the file in
`resources/images/skins/` with the same name and run
`npm run build --prefix themes/rjleather-theme` followed by
`php artisan platform:themes:assets`.

`dist/images/hero.jpg` is the last-resort social preview image; once a logo or
social image is uploaded in Admin it is used instead.

## Content and configuration

- Manage real products, variants, option groups, images and categories in Admin.
- Theme settings control the announcement, hero copy, section titles and the yellow
  accent (Admin → Themes → RJ Motorbike). General Settings control store name, logo,
  favicon, policies and contact content used by the footer.
- Catalog filters submit the existing category/collection/price/stock parameters
  to the host. Sorting changes the current page's displayed cards and is labeled
  **Sort this page**. Search and pagination use the existing host routes.
- Guest favorites persist locally and use the host's wishlist `ids` parameter.
  Signed-in favorites and newsletter subscriptions use the existing endpoints.
- Pricing, stock, validation, coupons, checkout, order ownership and fulfillment
  remain in the host. Unavailable payment/fulfillment providers keep checkout
  disabled with the existing notices. The theme installs no commerce plugins.
- Theme preview retains the host's token, navigation, disabled forms, fetch
  restrictions and private/noindex response behavior.

No prototype products, reviews, discounts, club benefits or shipping promises are
imported into the merchant database.

## Styles

- `resources/css/theme.css` — the design system: tokens, buttons, chips, cards,
  hero, tiles, band, showcase, split, editorial, newsletter, header and footer.
- `resources/css/storefront.css` — the catalog, product and cart pages.
- `dist/design/css/styles.css` — the shared component styles used by the
  inherited and catalog pages.

Colours come from the `--rj-*` tokens, so a restyle never needs markup changes.
The accent follows the `accent_color` theme setting.

## Build and activate

```sh
npm ci --ignore-scripts --prefix themes/rjleather
npm run build --prefix themes/rjleather
php artisan platform:themes:assets
php artisan platform:themes:activate rjleather.theme-storefront
php artisan platform:extensions:validate
```

Vite bundles a single Alpine runtime with the supplied GSAP core and motion
helpers. The theme layout loads this bundle while retaining default CSS for
inherited templates. All frontend files stay in the packages and are served
through the host's checksum-validated `/theme-assets/` route.

`composer.client.json` installs the two local theme packages via a Composer path
repository. The generated clone `composer.json` and lockfile preserve all existing
core dependency versions. Theme directories and clone Composer files are ignored
by the master repository: keep this folder, `themes/default`, the client manifest
and resolved clone lockfile in a separate client repository or backup.

### Changing the theme manifest

`composer.json` metadata (settings, templates) is read from Composer's installed
package data. After editing it, refresh the installation and rebuild the catalog:

```sh
composer update rjleather/theme-storefront --no-scripts
rm -rf vendor/rjleather && composer install --no-scripts
php artisan platform:extensions:build
php artisan platform:themes:assets
```

## Handover to another developer

The storefront spans three separate things. Only this repository changed.

| What | Where it lives | Contains |
|---|---|---|
| Platform | `github.com/alihassan-1177/mavyn` | core, contracts, docs, the `resources/themes/default` seed. **Unchanged by this work.** |
| Theme (this repo) | `github.com/alihassan-1177/rjleather-theme` | every template, style, script and the built assets in `dist/` |
| Client bundle | not in Git | `plugins/mavyn.currency-changer`, `plugins/mavyn.stripe`, `composer.client.json` |

`themes/`, `plugins/` and `composer.client.json` are ignored by the platform
repository on purpose: each application clone owns its installed extensions, and
the platform repository must never carry client code.

A new clone therefore does:

```sh
git clone git@github.com:alihassan-1177/mavyn.git
cd mavyn
# copy the client bundle: plugins/ and composer.client.json
cp ../client-bundle/composer.client.json .
cp -R ../client-bundle/plugins .
git clone git@github.com:alihassan-1177/rjleather-theme.git themes/rjleather-theme
php bin/prepare-composer.php
composer update
php artisan platform:extensions:build
php artisan platform:themes:activate rjleather.theme-storefront
php themes/rjleather-theme/bin/seed-catalog-structure.php
```

`dist/` is committed with a checksum manifest, so no npm build is needed to run
the storefront. Rebuild only after changing sources:

```sh
npm ci --ignore-scripts --prefix themes/rjleather-theme
npm run build --prefix themes/rjleather-theme
php artisan platform:themes:assets
```

The theme settings are stored per theme id in the client database, so the
colleague's Admin shows the same defaults; the catalogue structure comes from the
seed script, and the products, images and content are the merchant's own.

## Verification

`tests/browser.cjs` checks the landing page design, desktop/mobile rendering, the
hero pager, catalog filters, pagination, search, required variant/options, cart
totals, image lightbox, guest/authenticated wishlists, newsletter, inherited pages
and shared admin. `tests/prepare-browser.php` prepares a disposable SQLite fixture
database under `/tmp/rjleather-browser`; it never seeds the merchant database. The
fixture router in that temporary directory serves test images without adding public
files to the application. Playwright and Chromium are test-only tools supplied
separately. Actual numeric price-filter results are also verified against MySQL in
a temporary tables isolated to the verification connection.

The reference files `dist/design/css/styles.css` and
`dist/design/js/motion.js` retain the supplied design's presentation behavior.
Theme-specific styles and integrations are under `resources/`.
