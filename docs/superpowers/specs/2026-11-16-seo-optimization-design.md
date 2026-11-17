# Full-Scale SEO Optimization & Search Dominance Specification

- **Date:** 2026-11-16
- **Brand Target:** RJ Motorbike
- **Target Geographies:** United States, United Kingdom, Australia (International English)
- **Status:** Approved / In Implementation

---

## 1. Executive Summary & Goals
This project completes the comprehensive technical, structural, and on-page SEO optimization for RJ Motorbike (spanning 2,000+ catalog and informational URLs). The objectives are:
1. Maximize organic search rankings for transactional keywords (*"custom leather motorcycle jacket"*, *"made to measure biker jacket"*, *"MotoGP leather suit"*).
2. Capture high-intent informational queries through an integrated content hub (guides on sizing, leather types, styles, and racing gear).
3. Ensure 100% eligibility for Google rich snippets (Product, Offer, BreadcrumbList, FAQPage, Article/BlogPosting).
4. Resolve 100% of duplicate, short, or missing metadata across all 1,844 products and 151 categories.
5. Guarantee crawler efficiency by eliminating 302 redirects in the XML sitemap and applying strict `noindex` rules to utility/cart routes.

---

## 2. Completed Foundation (Phases 1–3 Baseline)
The following elements were engineered during initial phases and form the active baseline:
- **Unified `<head>` Architecture** (`themes/rjleather-theme/resources/views/layouts/app.blade.php`):
  - Brand pattern: `{Page Keyword} | RJ Motorbike`
  - Fallback cascades for meta descriptions and Open Graph data.
  - Twitter Card tags (`summary_large_image`) and `og:locale`.
  - Conditional crawler directives (`noindex, nofollow` on search, cart, checkout, account, and auth flows; `index, follow` on catalog and content).
  - Clean canonical URL handling that consolidates filter parameters while preserving page numbers.
- **Structured Data Service** (`app/Services/Seo/StructuredDataService.php`):
  - Sitewide `Organization` & `WebSite` with `SearchAction`.
  - Product-level `Product`, `Offer`, and `BreadcrumbList` schema.
  - Category-level `BreadcrumbList` and `ItemList` schema.
  - FAQ-level `FAQPage` schema.
- **Category Metadata System**:
  - Migration added `meta_title` and `meta_description` to the `categories` table.
  - Admin categories create/edit forms wired with SEO fields.
  - Artisan command generated metadata for 149/151 categories.
- **Database Concurrency**:
  - SQLite configured with WAL mode and busy timeout to eliminate lock contention during crawler indexing.

---

## 3. Remaining Implementation Specification

### Phase 4: Product Catalog Metadata & Media SEO (1,844 Products)

#### 4.1 Product Metadata Upgrade Command
Create an artisan command `app/Console/Commands/UpgradeProductSeoCommand.php` to analyze and upgrade all 1,844 products:
1. **Title Length & Uniqueness**:
   - Ensure title is between 40 and 60 characters.
   - For the 5 duplicate groups (e.g. `Andrea Dovizioso MotoGP Racing Jacket Ducati 2018`, `Black Biker Style Leather Jacket Men`):
     - Extract distinctive model identifier from slug (e.g. `BMLJ-AD2018B`, `UMLJ-1002-EN`, or leather finish `Cowhide`).
     - Format: `{Product Name} ({Model/Finish}) — Made to Measure | RJ Motorbike`.
   - For short titles (<30 chars):
     - Append intent modifiers: `{Product Name} — Custom Made to Measure Leather | RJ Motorbike`.
   - Guarantee 0 duplicate meta titles across the database.
2. **Meta Description Polish**:
   - For the 48 short descriptions (<70 chars) and 27 duplicate descriptions:
     - Generate complete, high-converting copy (145–158 characters):
       `"{Product Name} handcrafted from premium full-grain leather. Custom made to measure with reinforced seams & protective fit. Free worldwide shipping by RJ Motorbike."`
3. **Product-Specific Keyword Generator**:
   - Replace the static 4-token list (`{Category}, leather, handmade, RJ Leather`) with 6–8 product-specific tokens:
     - Exact product name.
     - Category + garment type (e.g., `men's biker jacket`, `women's racing suit`).
     - Material & build (e.g., `full-grain cowhide leather`, `made to measure motorcycle gear`).
     - Target geography terms (`leather jackets USA UK Australia`).
     - Brand token: `RJ Motorbike`.
4. **Category Coverage Completion**:
   - Fix remaining unmapped categories:
     - `for-women-movies-jackets` -> *"Women's Movie Leather Jackets | RJ Motorbike"*
     - `uncategorized` -> *"Specialty Leather Goods | RJ Motorbike"*

#### 4.2 Product Image Alt Tag Architecture
- In `app/Models/Product.php` and `themes/rjleather-theme/resources/views/pages/product.blade.php`:
  - Main hero image alt: `"{Product Title} — Custom Leather Motorcycle Gear | RJ Motorbike"`
  - Gallery thumbnails alt: `"{Product Title} — Angle {N} / Handmade Detail"`
  - Navigation promo image: Replace `alt=""` with `"Handmade Leather Motorcycle Jackets & Race Suits Collection"`.

---

### Phase 5: Homepage H1, Static Pages & Blog Subsystem

#### 5.1 Homepage H1 Optimization
- Update `themes/rjleather-theme/resources/views/pages/home.blade.php`:
  - Implement a dual-heading hero structure:
    - `<h1 class="rj-hero__title">Custom Leather Jackets & Motorcycle Gear</h1>`
    - `<p class="rj-hero__tagline">Crafted to last. <span>Made to be yours.</span></p>`
  - Ensure styles match the luxury typography while delivering the exact primary high-volume search term to search engine bots.

#### 5.2 Lightweight Database-Driven Blog Subsystem
- **Migration & Schema (`posts` table)**:
  - `id`: BigIncrements
  - `title`: String
  - `slug`: String, unique index
  - `excerpt`: Text, nullable
  - `content`: LongText
  - `featured_image`: String, nullable
  - `meta_title`: String, nullable
  - `meta_description`: String, nullable
  - `meta_keywords`: String, nullable
  - `is_published`: Boolean, default true
  - `published_at`: Timestamp
  - `timestamps`
- **Model `App\Models\Post`**:
  - Scopes: `scopePublished()`
  - Accessors: `reading_time` (words / 200 wpm)
- **Routes & Controllers**:
  - Public routes: `GET /blog` (`BlogController@index`), `GET /blog/{slug}` (`BlogController@show`)
  - Admin routes: `Admin\PostController` resource routes for post management.
- **Storefront Views**:
  - `themes/rjleather-theme/resources/views/pages/blog/index.blade.php` (Card grid, search/filter, pagination).
  - `themes/rjleather-theme/resources/views/pages/blog/show.blade.php` (Editorial layout, reading time, author, breadcrumbs, social share, contextual product CTAs).
- **Article Structured Data**:
  - Add `articleGraph(Post $post)` in `StructuredDataService`:
    - `@context`: `https://schema.org`
    - `@type`: `BlogPosting`
    - `headline`, `description`, `image`, `datePublished`, `dateModified`, `author` (RJ Motorbike Master Craftsmen), `publisher` (RJ Motorbike).
- **5 High-Ranking Seeded Articles**:
  1. *How to Measure for a Custom Made-to-Measure Leather Jacket (Step-by-Step Sizing Guide)*
  2. *Biker vs Bomber vs Cafe Racer: Which Motorcycle Leather Jacket Fits Your Style?*
  3. *Full-Grain vs Top-Grain Cowhide: The Rider's Guide to Leather Durability and Care*
  4. *MotoGP Race Suits Explained: One-Piece vs Two-Piece, Armor Levels and Track Standards*
  5. *Iconic Movie Leather Jackets: Top Gun, Fight Club & Wolverine — History & Heritage*
- **Navigation & Footer**:
  - Add "Guides" link into `app.blade.php` desktop and mobile menus and footer links.

---

### Phase 6: Sitemap, Performance & Comprehensive QA

#### 6.1 Sitemap Enhancement
- In `app/Services/SitemapService.php`:
  - For parent categories that have active child categories, index `route('category.show', $category->slug) . '?all=1'`. This eliminates 302 redirects in the sitemap while providing a 200 OK archive landing page.
  - Dynamically generate all URLs from `config('app.url')`.
  - Include published blog posts in the sitemap (`priority: 0.7`, `changefreq: monthly`).

#### 6.2 Caching & Concurrency
- Ensure public pages (`/`, `/about`, `/faq`, `/shop`, `/blog`) serve optimal caching headers while preserving dynamic cart state via the existing client-side drawer / fetch requests.

#### 6.3 Verification Suite
- Run automated feature tests.
- Validate JSON-LD schemas against Schema.org definitions.
- Verify 100% non-empty and unique meta titles across all 1,844 products and 151 categories.
- Confirm zero broken links or missing image alt attributes.

---

## 4. Implementation Phasing & Checkpoints
- **Step 1:** Implement Product SEO Upgrade command and run catalog cleanup.
- **Step 2:** Refine Product Image Alt tags and templates.
- **Step 3:** Implement Homepage H1 dual-heading layout.
- **Step 4:** Build Blog database migration, model, controller, views, and seed initial 5 guides.
- **Step 5:** Update SitemapService with parent `?all=1` and blog URLs.
- **Step 6:** Run full validation suite (JSON-LD, meta audit, PHPUnit tests).

