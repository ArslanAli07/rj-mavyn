<?php

namespace Tests\Feature;

use App\Jobs\Media\ProcessMediaConversions as PerformConversionsJob;
use App\Models\Admin;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media_public');
        Storage::fake('media_private');
        config(['media-library.queue_conversions_after_database_commit' => false]);
        Bus::fake([PerformConversionsJob::class]);
        $this->admin = Admin::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
    }

    private function upload(string $visibility = 'public'): MediaAsset
    {
        return app(MediaLibraryService::class)->upload(
            UploadedFile::fake()->image('photo.jpg', 1200, 800),
            ['title' => 'Travel collection', 'alt_text' => 'Travel bag', 'visibility' => $visibility],
            $this->admin,
        );
    }

    private function productPayload(Category $category, array $extra = []): array
    {
        return $extra + [
            'title' => 'Library product', 'category_id' => $category->id, 'display_order' => 0,
            'stock_quantity' => 10, 'is_active' => 1, 'in_stock' => 1,
            'variants' => [['name' => 'Default', 'price' => 500, 'is_default' => 1]],
        ];
    }

    public function test_product_forms_save_reuse_replace_and_clear_main_image_without_deleting_files(): void
    {
        $asset = $this->upload();
        $replacement = $this->upload();
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags']);
        $this->actingAs($this->admin, 'admin');
        $this->get(route('admin.products.create'))->assertOk()->assertSee('main_image_asset_id');
        foreach (['First bag', 'Second bag'] as $title) {
            $this->post(route('admin.products.store'), $this->productPayload($category, [
                'title' => $title, 'main_image_asset_id' => $asset->id,
            ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index'));
        }
        $one = Product::where('title', 'First bag')->firstOrFail();
        $two = Product::where('title', 'Second bag')->firstOrFail();
        $one->update(['main_image' => 'assets/product/latte.jpg']);
        $this->assertDatabaseCount('media', 2);
        $this->assertSame(2, $asset->usages()->count());
        $url = $asset->getFirstMedia('original')->getUrl();
        $this->assertSame($url, $one->main_image_url);
        Auth::shouldUse('web');
        $this->get(route('product.show', $one->slug))->assertOk()->assertSee($url, false);
        $this->get(route('shop'))->assertOk()->assertSee($url, false);
        $this->actingAs($this->admin, 'admin');
        $this->get(route('admin.products.edit', $one))->assertOk()->assertSee('main_image_asset_id');
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
        $this->put(route('admin.products.update', $one), $this->productPayload($category, [
            'title' => $one->title, 'main_image_asset_id' => $replacement->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $one->mainImageUsages()->count());
        $this->assertSame($replacement->id, $one->main_image_asset_id);
        $this->assertSame($url, $two->main_image_url);
        $this->put(route('admin.products.update', $one), $this->productPayload($category, [
            'title' => $one->title, 'main_image_asset_id' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(asset('assets/product/latte.jpg'), $one->fresh()->main_image_url);
        $this->assertDatabaseCount('media_assets', 2);
        Storage::disk('media_public')->assertExists($asset->getFirstMedia('original')->getPathRelativeToRoot());
        $this->delete(route('admin.products.destroy', $two))->assertRedirect();
        $this->assertSame(0, $asset->usages()->count());
        $this->assertDatabaseCount('media_assets', 2);
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_product_main_image_rejects_private_documents_missing_assets_and_conflicting_upload(): void
    {
        $private = $this->upload('private');
        $public = $this->upload();
        $document = app(MediaLibraryService::class)->upload(
            UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
            ['title' => 'Guide', 'visibility' => 'public'], $this->admin,
        );
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags']);
        $this->actingAs($this->admin, 'admin');
        foreach ([$private->id, $document->id, 99999] as $id) {
            $this->post(route('admin.products.store'), $this->productPayload($category, [
                'main_image_asset_id' => $id,
            ]))->assertSessionHasErrors('main_image_asset_id');
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('media_usages', 0);
        $this->getJson(route('admin.media.picker', ['type' => 'image']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $public->id);
        $this->post(route('admin.products.store'), $this->productPayload($category, [
            'main_image_asset_id' => $public->id, 'main_image' => UploadedFile::fake()->image('legacy.jpg'),
        ]))->assertSessionHasErrors('main_image');
    }

    public function test_admin_provision_requires_unique_email_and_strong_password(): void
    {
        $this->artisan('admin:provision', ['email' => 'secure@example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-strong-password-123')
            ->expectsOutput('Admin created.')->assertSuccessful();
        $admin = Admin::where('email', 'secure@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-password-123', $admin->password));
        $this->artisan('admin:provision', ['email' => 'weak@example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')->assertFailed();
        $this->assertDatabaseMissing('admins', ['email' => 'weak@example.com']);
    }

    public function test_upload_produces_one_polymorphic_original_and_queues_conversions(): void
    {
        $asset = $this->upload();
        $media = $asset->getFirstMedia('original');
        $this->assertSame('core.media-asset', $media->model_type);
        $this->assertSame('media_public', $media->disk);
        $this->assertSame('media_public', $media->conversions_disk);
        Storage::disk('media_public')->assertExists($media->getPathRelativeToRoot());
        Bus::assertDispatched(PerformConversionsJob::class);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_library_upload_details_edit_and_public_copy_url(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
            'title' => 'Catalog image', 'visibility' => 'public', 'alt_text' => 'Bag',
        ])->assertRedirect();
        $asset = MediaAsset::firstOrFail();
        $this->get(route('admin.media.index'))->assertOk()->assertSee('Catalog image');
        $this->get(route('admin.media.show', $asset))->assertOk()->assertSee('Copy URL');
        $this->put(route('admin.media.update', $asset), [
            'title' => 'Updated title', 'alt_text' => 'Bag on a table', 'visibility' => 'private',
        ])->assertRedirect();
        $this->assertSame('Updated title', $asset->fresh()->title);
        $this->assertSame('public', $asset->fresh()->visibility);
    }

    public function test_picker_excludes_private_files_and_public_presentation_rejects_them(): void
    {
        $public = $this->upload();
        $private = $this->upload('private');
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.media.picker'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $public->id);
        $this->get(route('admin.media.show', $private))->assertOk()->assertDontSee('Copy URL');
        $media = $private->getFirstMedia('original');
        $this->assertSame('media_private', $media->disk);
        $this->assertSame('media_private', $media->conversions_disk);
        $this->get(route('admin.media.file', $private))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_guest_customer_and_non_owner_staff_cannot_manage_or_download_media(): void
    {
        $asset = $this->upload('private');
        $this->get(route('admin.media.file', $asset))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.media.file', $asset))
            ->assertRedirect(route('admin.login'));
        $staff = Admin::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $this->actingAs($staff, 'admin')->get(route('admin.media.file', $asset))->assertForbidden();
        $this->getJson(route('admin.media.picker'))->assertForbidden();
        $this->delete(route('admin.media.destroy', $asset))->assertForbidden();
    }

    public function test_same_asset_can_be_reused_across_polymorphic_targets_without_copying(): void
    {
        $asset = $this->upload();
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags']);
        $product = Product::create([
            'category_id' => $category->id, 'title' => 'Bag', 'slug' => 'bag', 'price' => 500,
        ]);
        $service = app(MediaLibraryService::class);
        $one = $service->attach($asset, $product, 'catalog.product.gallery', $this->admin);
        $two = $service->attach($asset, $category, 'catalog.category.hero', $this->admin);
        $service->attach($asset, $product, 'catalog.product.gallery', $this->admin);
        $this->assertSame('core.product', $one->mediable_type);
        $this->assertSame('core.category', $two->mediable_type);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('media_usages', 2);
        $service->detach($one, $this->admin);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_usages', 1);
    }

    public function test_in_use_assets_cannot_be_deleted_but_detached_assets_can(): void
    {
        $asset = $this->upload();
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags']);
        $service = app(MediaLibraryService::class);
        $usage = $service->attach($asset, $category, 'catalog.category.hero', $this->admin);
        $this->actingAs($this->admin, 'admin')->delete(route('admin.media.destroy', $asset))
            ->assertSessionHasErrors('media');
        $this->assertDatabaseCount('media_assets', 1);
        $service->detach($usage, $this->admin);
        $this->delete(route('admin.media.destroy', $asset))->assertRedirect(route('admin.media.index'));
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('media_public')->allFiles());
    }

    public function test_private_media_cannot_be_attached_to_public_content(): void
    {
        $asset = $this->upload('private');
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags']);
        $this->expectException(ValidationException::class);
        app(MediaLibraryService::class)->attach($asset, $category, 'catalog.category.hero', $this->admin);
    }

    public function test_unsupported_uploads_are_rejected_without_creating_asset_rows(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->create('script.html', 1, 'text/html'),
            'title' => 'Unsafe', 'visibility' => 'public',
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_pending_conversion_has_original_fallback(): void
    {
        $asset = $this->upload();
        $dto = app(MediaLibraryService::class)->present($asset, 'thumbnail');
        $this->assertFalse($dto['conversion_ready']);
        $this->assertSame($asset->getFirstMedia('original')->getUrl(), $dto['url']);
    }

    public function test_picker_component_renders_the_shared_picker_endpoint(): void
    {
        $html = Blade::render('<x-admin.media-picker name="hero_asset_id" />');
        $this->assertStringContainsString('hero_asset_id', $html);
        $this->assertStringContainsString('/admin/media/picker', str_replace('\\/', '/', $html));
    }

    public function test_real_conversion_job_creates_resized_webp_derivatives(): void
    {
        config(['media-library.image_optimizers' => []]);
        $asset = $this->upload();
        $job = Bus::dispatched(PerformConversionsJob::class)->first();
        $job->handle(app(FileManipulator::class));
        $media = $asset->getFirstMedia('original')->fresh();
        $this->assertTrue($media->hasGeneratedConversion('thumbnail'));
        $this->assertTrue($media->hasGeneratedConversion('product-card'));
        $dimensions = getimagesize($media->getPath('thumbnail'));
        $this->assertLessThanOrEqual(320, $dimensions[0]);
        $this->assertLessThanOrEqual(320, $dimensions[1]);
        $this->assertSame('image/webp', $dimensions['mime']);
        Storage::disk('media_public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_private_conversion_outputs_never_go_to_public_disk(): void
    {
        config(['media-library.image_optimizers' => []]);
        $asset = $this->upload('private');
        Bus::dispatched(PerformConversionsJob::class)->first()
            ->handle(app(FileManipulator::class));
        $media = $asset->getFirstMedia('original')->fresh();
        $this->assertTrue($media->hasGeneratedConversion('thumbnail'));
        Storage::disk('media_private')->assertExists($media->getPathRelativeToRoot('thumbnail'));
        $this->assertSame([], Storage::disk('media_public')->allFiles());
    }
}
