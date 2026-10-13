<?php

namespace Tests\Feature;

use App\Jobs\Media\ProcessMediaConversions as PerformConversionsJob;
use App\Mail\WelcomeMail;
use App\Models\Admin;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Media\ContentMediaService;
use App\Services\Media\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaContentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private MediaLibraryService $library;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media_public');
        Storage::fake('media_private');
        Bus::fake([PerformConversionsJob::class]);
        $this->admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->library = app(MediaLibraryService::class);
    }

    private function image(string $visibility = 'public'): MediaAsset
    {
        return $this->library->upload(UploadedFile::fake()->image('image.jpg'), ['title' => 'Catalog image', 'alt_text' => "Bag's front", 'visibility' => $visibility], $this->admin);
    }

    private function payload(Category $category, array $extra = []): array
    {
        return $extra + ['title' => 'Bag', 'category_id' => $category->id, 'display_order' => 0, 'stock_quantity' => 10,
            'is_active' => 1, 'variants' => [['name' => 'Default', 'price' => 500, 'is_default' => 1]]];
    }

    public function test_ordered_product_gallery_saves_reorders_clears_and_restores_legacy_images(): void
    {
        $one = $this->image();
        $two = $this->image();
        $category = Category::create(['name' => 'Bags']);
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), $this->payload($category, [
            'gallery_asset_ids' => [$two->id, $one->id], 'gallery_asset_ids_present' => 1,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $product->images()->create(['image' => 'legacy.jpg', 'sort_order' => 0]);
        $this->assertSame([$two->id, $one->id], $product->gallery_asset_ids);
        $this->assertSame([$two->id, $one->id], array_column($product->gallery_images, 'id'));
        Auth::shouldUse('web');
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee($two->getFirstMedia('original')->getUrl(), false)->assertSee('Bag&#039;s front', false);
        $this->actingAs($this->admin, 'admin')->put(route('admin.products.update', $product), $this->payload($category, [
            'gallery_asset_ids' => [$one->id, $two->id], 'gallery_asset_ids_present' => 1,
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$one->id, $two->id], $product->gallery_asset_ids);
        $this->put(route('admin.products.update', $product), $this->payload($category, ['gallery_asset_ids_present' => 1]))->assertSessionHasNoErrors();
        $this->assertSame([], $product->gallery_asset_ids);
        $this->assertSame(asset('storage/legacy.jpg'), $product->gallery_images[0]['url']);
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseCount('media', 2);
    }

    public function test_category_picker_replaces_detaches_and_preserves_shared_image(): void
    {
        $image = $this->image();
        $other = $this->image();
        $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), [
            'name' => 'Bags', 'image_asset_id' => $image->id, 'is_active' => 1, 'show_on_homepage' => 1,
        ])->assertSessionHasNoErrors();
        $category = Category::firstOrFail();
        $this->assertSame($image->getFirstMedia('original')->getUrl(), $category->image_url);
        $category->update(['image' => 'assets/legacy.jpg']);
        $this->put(route('admin.categories.update', $category), ['name' => 'Bags', 'image_asset_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $category->image_asset_id);
        $this->put(route('admin.categories.update', $category), ['name' => 'Bags', 'image_asset_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame(asset('assets/legacy.jpg'), $category->fresh()->image_url);
        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_slide_desktop_and_mobile_pick_from_library_and_have_safe_fallbacks(): void
    {
        $desktop = $this->image();
        $mobile = $this->image();
        $slide = HomeSlide::create(['display_order' => 0]);
        $this->library->syncImages($slide, 'content.hero.desktop', [$desktop->id], $this->admin);
        $this->library->syncImages($slide, 'content.hero.mobile', [$mobile->id], $this->admin);
        $this->assertNull($slide->image_path);
        $this->assertSame($desktop->getFirstMedia('original')->getUrl(), $slide->image_url);
        $this->assertSame($mobile->getFirstMedia('original')->getUrl(), $slide->mobile_image_url);
        Auth::shouldUse('web');
        $this->get(route('home'))->assertOk()->assertSee($mobile->getFirstMedia('original')->getUrl(), false)->assertSee($desktop->getFirstMedia('original')->getUrl(), false);
        $this->library->syncImages($slide, 'content.hero.mobile', [], $this->admin);
        $this->assertSame($desktop->getFirstMedia('original')->getUrl(), $slide->fresh()->mobile_image_url);
        $this->assertSame($desktop->id, $slide->fresh()->image_asset_id);
    }

    public function test_branding_and_site_gallery_use_tracked_setting_records_and_render_on_storefront(): void
    {
        $one = $this->image();
        $two = $this->image();
        Setting::set('homepage_gallery', [['src' => 'assets/legacy.jpg', 'alt' => 'Legacy photo']], 'homepage', 'json');
        Cache::put('unrelated', 'keep');
        $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
            'site_logo_asset_id' => $one->id, 'site_favicon_asset_id' => $two->id,
            'homepage_gallery_asset_ids' => [$two->id, $one->id], 'homepage_gallery_asset_ids_present' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('settings', ['key' => 'site_logo_asset_id']);
        $this->assertDatabaseHas('media_usages', ['mediable_type' => 'core.setting', 'slot' => 'store.logo', 'media_asset_id' => $one->id]);
        $this->assertSame('keep', Cache::get('unrelated'));
        $content = app(ContentMediaService::class);
        $this->assertSame($one->getFirstMedia('original')->getUrl(), $content->branding('site_logo')['url']);
        $this->assertSame([$two->getFirstMedia('original')->getUrl(), $one->getFirstMedia('original')->getUrl()], array_column($content->gallery(), 'src'));
        $emailHtml = (new WelcomeMail(User::factory()->create()))->render();
        $this->assertStringContainsString($one->getFirstMedia('original')->getUrl(), $emailHtml);
        Auth::shouldUse('web');
        $this->get(route('home'))->assertOk()->assertSee($one->getFirstMedia('original')->getUrl(), false)->assertSee($two->getFirstMedia('original')->getUrl(), false);
        $this->get(route('gallery'))->assertOk()->assertSee($two->getFirstMedia('original')->getUrl(), false);
        $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
            'site_logo_asset_id' => '', 'site_favicon_asset_id' => '', 'homepage_gallery_asset_ids_present' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertNull($content->branding('site_logo'));
        $this->assertSame(asset('assets/legacy.jpg'), $content->gallery()[0]['src']);
        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_invalid_media_is_rejected_atomically_in_all_content_forms(): void
    {
        $private = $this->image('private');
        $document = $this->library->upload(UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'), ['title' => 'Guide', 'visibility' => 'public'], $this->admin);
        $category = Category::create(['name' => 'Bags']);
        $slide = HomeSlide::firstOrFail();
        $this->actingAs($this->admin, 'admin');
        foreach ([$private->id, $document->id, 9999] as $id) {
            $this->post(route('admin.categories.store'), ['name' => 'Rejected', 'image_asset_id' => $id])->assertSessionHasErrors('image_asset_id');
            foreach (['image_asset_id' => 'content.hero.desktop', 'mobile_image_asset_id' => 'content.hero.mobile'] as $field => $slot) {
                try {
                    $this->library->syncImages($slide, $slot, [$id], $this->admin, $field);
                    $this->fail('Invalid hero media should be rejected.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey($field, $exception->errors());
                }
            }
            $this->post(route('admin.products.store'), $this->payload($category, ['gallery_asset_ids' => [$id]]))->assertSessionHasErrors('gallery_asset_ids.0');
            $this->put(route('admin.settings.update'), ['site_name' => 'Rejected', 'site_logo_asset_id' => $id])->assertSessionHasErrors('site_logo_asset_id');
            $this->put(route('admin.settings.update'), ['homepage_gallery_asset_ids' => [$id]])->assertSessionHasErrors('homepage_gallery_asset_ids.0');
        }
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseMissing('settings', ['key' => 'site_name']);
        $this->assertDatabaseCount('media_usages', 0);
    }

    public function test_duplicate_and_excessive_gallery_selections_are_rejected_and_partial_updates_preserve_references(): void
    {
        $image = $this->image();
        $category = Category::create(['name' => 'Bags']);
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), $this->payload($category, ['gallery_asset_ids' => [$image->id]]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->put(route('admin.products.update', $product), $this->payload($category))->assertSessionHasNoErrors();
        $this->assertSame([$image->id], $product->gallery_asset_ids);
        $this->put(route('admin.products.update', $product), $this->payload($category, ['gallery_asset_ids' => [$image->id, $image->id]]))->assertSessionHasErrors('gallery_asset_ids.0');
        $this->put(route('admin.products.update', $product), $this->payload($category, ['gallery_asset_ids' => array_fill(0, 31, $image->id)]))->assertSessionHasErrors('gallery_asset_ids');
        $this->assertSame([$image->id], $product->gallery_asset_ids);
    }

    public function test_content_deletion_detaches_all_slots_without_destroying_shared_originals(): void
    {
        $image = $this->image();
        $category = Category::create(['name' => 'Bags']);
        $product = Product::create(['title' => 'Bag', 'category_id' => $category->id, 'price' => 500]);
        $slide = HomeSlide::create(['display_order' => 0]);
        $this->library->syncImages($product, 'catalog.product.gallery', [$image->id], $this->admin);
        $this->library->syncImages($category, 'catalog.category.hero', [$image->id], $this->admin);
        $this->library->syncImages($slide, 'content.hero.desktop', [$image->id], $this->admin);
        $product->delete();
        $this->assertSame(2, $image->usages()->count());
        $this->actingAs($this->admin, 'admin');
        $slide->delete();
        $this->assertSame(1, $image->usages()->count());
        $this->delete(route('admin.media.destroy', $image))->assertSessionHasErrors('media');
        $this->delete(route('admin.categories.destroy', $category))->assertRedirect();
        $this->assertSame(0, $image->usages()->count());
        Storage::disk('media_public')->assertExists($image->getFirstMedia('original')->getPathRelativeToRoot());
    }

    public function test_admin_picker_visibility_uses_admin_guard_when_customer_guard_is_default(): void
    {
        $category = Category::create(['name' => 'Bags']);
        $product = Product::create(['title' => 'Bag', 'category_id' => $category->id, 'price' => 500]);
        $this->actingAs($this->admin, 'admin');
        Auth::shouldUse('web');
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('gallery_asset_ids');
        $this->get(route('admin.categories.edit', $category))->assertOk()->assertSee('image_asset_id');
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('homepage_gallery_asset_ids');
        $staff = Admin::create(['name' => 'Staff', 'email' => 'restricted@example.com', 'password' => 'password', 'role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff, 'admin');
        Auth::shouldUse('web');
        $this->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_staff_cannot_save_media_settings_or_bypass_typed_image_slots(): void
    {
        $image = $this->image();
        $staff = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password', 'role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff, 'admin')->put(route('admin.settings.update'), ['site_name' => 'Rejected', 'site_logo_asset_id' => $image->id])->assertForbidden();
        $this->assertDatabaseMissing('settings', ['key' => 'site_name']);
        $this->assertDatabaseCount('media_usages', 0);
        $setting = Setting::create(['key' => 'payment_account', 'value' => 'example']);
        $this->expectException(ValidationException::class);
        $this->library->syncImages($setting, 'store.logo', [$image->id], $this->admin);
    }
}
