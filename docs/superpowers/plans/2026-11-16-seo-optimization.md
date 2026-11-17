# SEO Optimization & Dominance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the remaining SEO implementation (Phases 4, 5, and 6) for RJ Motorbike, resolving all product metadata/image issues, delivering a high-impact homepage H1, launching a database-backed buyer guide blog subsystem with Article JSON-LD, optimizing sitemaps to eliminate 302 redirects, and conducting full verification.

**Architecture:** Laravel 11 MVC with SQLite WAL. Artisan commands for safe bulk catalog metadata upgrades, Eloquent models for content/blog, Blade components and themes for responsive UI, Spatie Sitemap generator for dynamic indexation, and StructuredDataService for Google Rich Results schemas.

**Tech Stack:** PHP 8.3, Laravel 11, SQLite, Spatie Sitemap, Schema.org JSON-LD, Blade, Tailwind/Custom CSS.

**Spec:** [docs/superpowers/specs/2026-11-16-seo-optimization-design.md](file:///home/arslan/Sites/mavyn/docs/superpowers/specs/2026-11-16-seo-optimization-design.md)

## Global Constraints
- Target brand name: `RJ Motorbike` (standardized across all titles, copy, and keywords).
- Target markets: USA, UK, and Australia (International English).
- Codebase rule: **Do NOT commit or push code until the user explicitly requests it.**
- Category/product invariants from AGENTS.md must be strictly preserved.

---

### Task 1: Product SEO Metadata Upgrade Command & Category Completion

**Files:**
- Create: `app/Console/Commands/UpgradeProductSeoCommand.php`
- Modify: `app/Console/Commands/GenerateCategoryMetasCommand.php`
- Test: `tests/Feature/ProductSeoUpgradeTest.php`

**Interfaces:**
- Consumes: `App\Models\Product`, `App\Models\Category`
- Produces: `UpgradeProductSeoCommand` (Artisan command `seo:upgrade-products`)

- [ ] **Step 1: Write the failing test for product metadata cleanup**

```php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeoUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fixes_short_titles_and_duplicate_titles_and_generates_rich_keywords(): void
    {
        $category = Category::factory()->create(['name' => 'Biker Jackets', 'slug' => 'biker-jackets']);
        
        $p1 = Product::factory()->create([
            'category_id' => $category->id,
            'title' => 'Short Jacket',
            'slug' => 'short-jacket-black-101',
            'meta_title' => 'Short Jacket',
            'meta_description' => 'Too short.',
            'meta_keywords' => 'Biker Jackets, leather, handmade, RJ Leather',
        ]);

        $p2 = Product::factory()->create([
            'category_id' => $category->id,
            'title' => 'Duplicate Jacket',
            'slug' => 'duplicate-jacket-brown-102',
            'meta_title' => 'Duplicate Jacket',
            'meta_description' => 'Another short one.',
            'meta_keywords' => 'Biker Jackets, leather, handmade, RJ Leather',
        ]);

        $p3 = Product::factory()->create([
            'category_id' => $category->id,
            'title' => 'Duplicate Jacket',
            'slug' => 'duplicate-jacket-vintage-103',
            'meta_title' => 'Duplicate Jacket',
            'meta_description' => 'Also short.',
            'meta_keywords' => 'Biker Jackets, leather, handmade, RJ Leather',
        ]);

        $this->artisan('seo:upgrade-products')
            ->assertSuccessful();

        $p1->refresh();
        $p2->refresh();
        $p3->refresh();

        $this->assertGreaterThanOrEqual(30, strlen($p1->meta_title));
        $this->assertNotEquals($p2->meta_title, $p3->meta_title);
        $this->assertGreaterThanOrEqual(120, strlen($p1->meta_description));
        $this->assertStringContainsString('RJ Motorbike', $p1->meta_description);
        $this->assertStringContainsString('RJ Motorbike', $p1->meta_keywords);
        $this->assertStringNotContainsString('RJ Leather', $p1->meta_keywords);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductSeoUpgradeTest`
Expected: Command `seo:upgrade-products` not found.

- [ ] **Step 3: Implement `GenerateCategoryMetasCommand` fixes and `UpgradeProductSeoCommand`**

In `app/Console/Commands/GenerateCategoryMetasCommand.php`, add explicit mappings for:
- `for-women-movies-jackets` => `Women's Movie Leather Jackets`
- `uncategorized` => `Specialty Leather Goods`

In `app/Console/Commands/UpgradeProductSeoCommand.php`:
Implement logic that processes all active products:
- Identify duplicate title groups by `title`. For each product in a group, parse unique model/color tokens from slug or ID and produce unique titles.
- For titles < 30 chars, append `— Custom Made to Measure Leather | RJ Motorbike` (staying <= 65 chars).
- For titles > 65 chars, shorten cleanly.
- For descriptions < 70 chars or duplicates, compose 145–158 char descriptions with benefit, leather grade, custom fit, and free shipping.
- Upgrade `meta_keywords` to 6–8 product-specific tokens containing product name, garment type, material, fit, and `RJ Motorbike`.
- Save without triggering redundant events/sitemaps.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductSeoUpgradeTest`
Expected: PASS

- [ ] **Step 5: Execute commands on live database**

Run:
```bash
php artisan seo:generate-category-metas
php artisan seo:upgrade-products
```
Verify via tinker that 0 duplicate titles exist, 0 descriptions are under 70 chars, and all 151 categories have meta titles.

---

### Task 2: Product Detail Media & Template SEO Enhancements

**Files:**
- Modify: `app/Models/Product.php`
- Modify: `themes/rjleather-theme/resources/views/pages/product.blade.php`
- Modify: `themes/rjleather-theme/resources/views/layouts/app.blade.php`
- Test: `tests/Feature/ProductMediaSeoTest.php`

**Interfaces:**
- Consumes: `Product` gallery and image accessors
- Produces: Enhanced `alt_text` attributes on all gallery and promotional images

- [ ] **Step 1: Write test for image alt attributes**

```php
namespace Tests\Feature;

use App\Models\Product;
use Tests\TestCase;

class ProductMediaSeoTest extends TestCase
{
    public function test_product_page_images_have_descriptive_alt_attributes(): void
    {
        $product = Product::active()->first();
        if (! $product) {
            $this->markTestSkipped('No active products found');
        }

        $response = $this->get(route('product.show', $product->slug));
        $response->assertOk();
        $response->assertDontSee('alt=""', false); // No empty alt tags on major content images
        $response->assertSee('data-gallery-main-image', false);
    }
}
```

- [ ] **Step 2: Run test to verify current state**

Run: `php artisan test --filter=ProductMediaSeoTest`
Expected: Might fail if empty `alt=""` is present in templates.

- [ ] **Step 3: Update `Product.php` and templates**

In `app/Models/Product.php`:
Enhance `getGalleryImagesAttribute`:
```php
public function getGalleryImagesAttribute(): array
{
    $library = $this->libraryImages('catalog.product.gallery', 'product-detail');

    return $library ?: $this->images->values()->map(fn ($image, $index) => [
        'url' => $image->image_url,
        'alt_text' => $this->title . ' — View ' . ($index + 1) . ' (Custom Leather Detail)',
    ])->all();
}
```

In `themes/rjleather-theme/resources/views/pages/product.blade.php`:
Ensure main image and gallery thumbnails use descriptive alt tags.
In `themes/rjleather-theme/resources/views/layouts/app.blade.php`:
Change `$navPromo` empty alt to `alt="Handmade Leather Motorcycle Collection"`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductMediaSeoTest`
Expected: PASS

---

### Task 3: Homepage H1 & Keyword Dual-Heading Layout

**Files:**
- Modify: `themes/rjleather-theme/resources/views/pages/home.blade.php`
- Test: `tests/Feature/HomepageSeoTest.php`

**Interfaces:**
- Consumes: Home page template and home slides
- Produces: Semantic H1 heading containing core target keywords with luxury sub-headline

- [ ] **Step 1: Write test for Homepage H1**

```php
namespace Tests\Feature;

use Tests\TestCase;

class HomepageSeoTest extends TestCase
{
    public function test_homepage_has_keyword_rich_h1_and_meta_description(): void
    {
        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee('<h1', false);
        $response->assertSee('Custom Leather Jackets &amp; Motorcycle Gear', false);
        $response->assertSee('Crafted to last.', false);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test --filter=HomepageSeoTest`
Expected: FAIL (current H1 is "Crafted to last.").

- [ ] **Step 3: Update `home.blade.php`**

Implement semantic dual-heading in `themes/rjleather-theme/resources/views/pages/home.blade.php`:
```blade
<div class="rj-hero__content">
    <h1 class="rj-hero__title">Custom Leather Jackets &amp; Motorcycle Gear</h1>
    <p class="rj-hero__tagline">{{ $heroTitle }} @if($heroHighlight)<span>{{ $heroHighlight }}</span>@endif</p>
    @if($heroText)<p class="rj-hero__text">{{ $heroText }}</p>@endif
    <div class="rj-hero__buttons">
...
```
Add matching typography styles in theme CSS or component classes so visual beauty is preserved.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=HomepageSeoTest`
Expected: PASS

---

### Task 4: Blog Subsystem Database, Model, and Structured Data

**Files:**
- Create: `database/migrations/2026_11_16_000003_create_posts_table.php`
- Create: `app/Models/Post.php`
- Modify: `app/Services/Seo/StructuredDataService.php`
- Test: `tests/Unit/PostModelTest.php`

**Interfaces:**
- Produces: `Post` model, `StructuredDataService::articleGraph(Post $post)`

- [ ] **Step 1: Write unit test for `Post` and `StructuredDataService`**

```php
namespace Tests\Unit;

use App\Models\Post;
use App\Services\Seo\StructuredDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_creation_and_article_graph(): void
    {
        $post = Post::create([
            'title' => 'How to Measure for a Custom Leather Jacket',
            'slug' => 'how-to-measure-custom-leather-jacket',
            'excerpt' => 'Step-by-step sizing guide for made-to-measure motorcycle jackets.',
            'content' => '<p>Detailed measuring guide instructions with chest, sleeve, waist...</p>',
            'meta_title' => 'How to Measure for a Custom Leather Jacket | Sizing Guide',
            'meta_description' => 'Get the perfect fit for your made-to-measure leather motorcycle jacket. Follow our master craftsman guide.',
            'meta_keywords' => 'leather jacket sizing, how to measure leather jacket, custom fit motorcycle jacket',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertEquals(1, $post->reading_time);
        $this->assertTrue(Post::published()->where('id', $post->id)->exists());

        $service = app(StructuredDataService::class);
        $graph = $service->articleGraph($post);

        $this->assertEquals('BlogPosting', $graph['@type']);
        $this->assertEquals($post->title, $graph['headline']);
        $this->assertEquals('RJ Motorbike', $graph['publisher']['name']);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test --filter=PostModelTest`
Expected: Migration/Model missing.

- [ ] **Step 3: Implement Migration, Model, and StructuredDataService method**

Create `database/migrations/2026_11_16_000003_create_posts_table.php` with:
- `id`, `title`, `slug` (unique), `excerpt`, `content`, `featured_image`, `meta_title`, `meta_description`, `meta_keywords`, `is_published`, `published_at`, `timestamps`.

Create `app/Models/Post.php` with:
- fillables, `scopePublished()`, `getReadingTimeAttribute()`, casts.

In `app/Services/Seo/StructuredDataService.php`:
Add `articleGraph(Post $post): array` returning valid Schema.org `BlogPosting` structure.

- [ ] **Step 4: Run migration and test**

Run:
```bash
php artisan migrate
php artisan test --filter=PostModelTest
```
Expected: PASS

---

### Task 5: Blog Controllers, Storefront Views, Seeded Content & Nav Links

**Files:**
- Create: `app/Http/Controllers/BlogController.php`
- Create: `app/Http/Controllers/Admin/PostController.php`
- Create: `database/seeders/BlogSeeder.php`
- Create: `themes/rjleather-theme/resources/views/pages/blog/index.blade.php`
- Create: `themes/rjleather-theme/resources/views/pages/blog/show.blade.php`
- Modify: `routes/web.php`
- Modify: `routes/admin.php`
- Modify: `themes/rjleather-theme/resources/views/layouts/app.blade.php`
- Test: `tests/Feature/BlogStorefrontTest.php`

**Interfaces:**
- Produces: `/blog` and `/blog/{slug}` storefront routes and admin management

- [ ] **Step 1: Write test for Blog storefront**

```php
namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_blog_index_and_post_detail_with_article_schema(): void
    {
        $post = Post::create([
            'title' => 'How to Measure for a Custom Leather Jacket',
            'slug' => 'how-to-measure-custom-leather-jacket',
            'excerpt' => 'Step-by-step sizing guide for made-to-measure motorcycle jackets.',
            'content' => '<p>Detailed measuring guide instructions with chest, sleeve, waist...</p>',
            'meta_title' => 'How to Measure for a Custom Leather Jacket | Sizing Guide',
            'meta_description' => 'Get the perfect fit for your made-to-measure leather motorcycle jacket.',
            'meta_keywords' => 'leather jacket sizing, how to measure leather jacket',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get(route('blog.index'));
        $response->assertOk();
        $response->assertSee($post->title);

        $detail = $this->get(route('blog.show', $post->slug));
        $detail->assertOk();
        $detail->assertSee($post->title);
        $detail->assertSee('BlogPosting', false);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test --filter=BlogStorefrontTest`
Expected: Routes not defined.

- [ ] **Step 3: Implement Controllers, Routes, Views, and Seed 5 Articles**

In `routes/web.php`:
```php
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');
```
In `routes/admin.php`:
Add resource routes for `PostController`.

Implement `BlogController.php`:
- `index`: Paginated published posts.
- `show`: Eager load post, abort 404 if not published, return view.

Implement Blade views `index.blade.php` and `show.blade.php` styled with luxury minimalism matching the theme.
Implement `database/seeders/BlogSeeder.php` with 5 keyword-rich articles:
1. *How to Measure for a Custom Made-to-Measure Leather Jacket (Step-by-Step Sizing Guide)*
2. *Biker vs Bomber vs Cafe Racer: Which Motorcycle Leather Jacket Fits Your Style?*
3. *Full-Grain vs Top-Grain Cowhide: The Rider's Guide to Leather Durability and Care*
4. *MotoGP Race Suits Explained: One-Piece vs Two-Piece, Armor Levels and Track Standards*
5. *Iconic Movie Leather Jackets: Top Gun, Fight Club & Wolverine — History & Heritage*

Run seeder: `php artisan db:seed --class=BlogSeeder`
Update `themes/rjleather-theme/resources/views/layouts/app.blade.php` to include "Guides" link in navigation and footer.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=BlogStorefrontTest`
Expected: PASS

---

### Task 6: Sitemap & Robots.txt Optimization

**Files:**
- Modify: `app/Services/SitemapService.php`
- Test: `tests/Feature/SitemapSeoTest.php`

**Interfaces:**
- Produces: Updated `public/sitemap.xml` with parent `?all=1` URLs and published blog posts.

- [ ] **Step 1: Write test for Sitemap updates**

```php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_parent_categories_with_all_param_and_blog_posts(): void
    {
        $parent = Category::factory()->create(['name' => 'Men', 'slug' => 'for-men', 'parent_id' => null]);
        $child = Category::factory()->create(['name' => 'Men Jackets', 'slug' => 'for-men-jackets', 'parent_id' => $parent->id]);

        $post = Post::create([
            'title' => 'Leather Care Guide',
            'slug' => 'leather-care-guide',
            'content' => 'Content...',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $service = app(SitemapService::class);
        $sitemap = $service->buildSitemap();
        $urls = collect($sitemap->getTags())->map(fn ($t) => $t->url)->all();

        $this->assertContains(route('category.show', $parent->slug) . '?all=1', $urls);
        $this->assertContains(route('category.show', $child->slug), $urls);
        $this->assertContains(route('blog.index'), $urls);
        $this->assertContains(route('blog.show', $post->slug), $urls);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test --filter=SitemapSeoTest`
Expected: `?all=1` or blog posts missing in sitemap tags.

- [ ] **Step 3: Update `SitemapService.php`**

In `app/Services/SitemapService.php`:
- In `$staticPages`, add `['route' => 'blog.index', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_WEEKLY]`.
- In category loop, if `$category->parent_id === null && $category->children()->active()->exists()`, append `?all=1` to URL.
- Add loop for `Post::published()->get()`:
  ```php
  foreach (Post::published()->get() as $post) {
      $sitemap->add(
          Url::create(route('blog.show', $post->slug))
              ->setLastModificationDate($post->updated_at)
              ->setPriority(0.7)
              ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
      );
  }
  ```
- Regenerate sitemap: `php artisan sitemap:generate`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SitemapSeoTest`
Expected: PASS

---

### Task 7: Comprehensive Validation & Quality Assurance

**Files:**
- Test: All feature and unit tests

- [ ] **Step 1: Run complete SEO sanity check via artisan/tinker**

Verify:
- 0 duplicate titles on products:
  `php artisan tinker --execute="echo \DB::table('products')->select('meta_title', \DB::raw('count(*) as c'))->groupBy('meta_title')->having('c', '>', 1)->count();"` => 0
- 0 descriptions < 70 chars:
  `php artisan tinker --execute="echo \App\Models\Product::whereRaw('LENGTH(meta_description) < 70')->count();"` => 0
- 151/151 categories have meta titles:
  `php artisan tinker --execute="echo \App\Models\Category::whereNull('meta_title')->count();"` => 0
- 5 published blog posts exist:
  `php artisan tinker --execute="echo \App\Models\Post::published()->count();"` => 5

- [ ] **Step 2: Run automated test suite**

Run:
```bash
php artisan test --filter="Seo|Product|Category|Sitemap|Blog|Theme"
```
Expected: All tests pass.

- [ ] **Step 3: Verify git status (REMINDER: Do NOT commit or push)**

Verify that all modifications and untracked files are clean and in working order.

