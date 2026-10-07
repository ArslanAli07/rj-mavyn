<?php

namespace Tests\Feature;

use App\Jobs\Media\CleanupMedia;
use App\Jobs\Media\ProcessMediaConversions;
use App\Models\Admin;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\MediaAsset;
use App\Models\MediaCleanupTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaLifecycleService;
use App\Services\Media\MediaPolicyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Tests\TestCase;

class MediaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private MediaLibraryService $library;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media_public');
        Storage::fake('media_private');
        config(['media-library.image_optimizers' => [], 'media-library.queue_conversions_after_database_commit' => false]);
        Bus::fake([ProcessMediaConversions::class, CleanupMedia::class]);
        $this->admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->library = app(MediaLibraryService::class);
    }

    private function upload(string $visibility = 'public'): MediaAsset
    {
        return $this->library->upload(UploadedFile::fake()->image('photo.jpg', 1200, 800), ['title' => 'Purchased bag', 'alt_text' => 'Original bag', 'visibility' => $visibility], $this->admin);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Bags']);
        $product = Product::create(['title' => 'Bag', 'category_id' => $category->id, 'price' => 500, 'is_active' => true, 'in_stock' => true]);
        $product->variants()->create(['name' => 'Default', 'weight' => 'Standard', 'price' => 500, 'is_default' => true, 'is_active' => true]);

        return $product;
    }

    public function test_direct_attachment_enforces_registered_target_mime_cardinality_and_ordering(): void
    {
        $one = $this->upload();
        $two = $this->upload();
        $product = $this->product();
        $first = $this->library->attach($one, $product, 'catalog.product.main', $this->admin);
        $this->library->attach($one, $product, 'catalog.product.main', $this->admin);
        $this->assertSame(0, $first->fresh()->sort_order);
        try {
            $this->library->attach($two, $product, 'catalog.product.main', $this->admin);
            $this->fail('Single-image slot accepted two assets.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('media', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        $this->library->attach($one, $product, 'store.logo', $this->admin);
    }

    public function test_direct_attachment_rejects_document_images_and_enforces_registered_size_limit(): void
    {
        $document = $this->library->upload(UploadedFile::fake()->create('guide.pdf', 5, 'application/pdf'), ['title' => 'Guide', 'visibility' => 'public'], $this->admin);
        $product = $this->product();
        try {
            $this->library->attach($document, $product, 'catalog.product.gallery', $this->admin);
            $this->fail('Document attached to image slot.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('media', $exception->errors());
        }
        $this->assertSame('not_applicable', $document->processing_status);
        $registry = app(MediaPolicyRegistry::class);
        $registry->registerSlot('sample.small-image', 'core.product', 1, maxBytes: 1);
        $image = $this->upload();
        $this->expectException(ValidationException::class);
        $this->library->syncImages($product, 'sample.small-image', [$image->id], $this->admin);
    }

    public function test_usage_metadata_survives_gallery_reordering(): void
    {
        $one = $this->upload();
        $two = $this->upload();
        $product = $this->product();
        $this->library->attach($one, $product, 'catalog.product.gallery', $this->admin, ['alt_text' => 'Side view']);
        $this->library->syncImages($product, 'catalog.product.gallery', [$two->id, $one->id], $this->admin);
        $this->assertSame('Side view', $product->gallery_images[1]['alt_text']);
    }

    public function test_profile_registry_rejects_duplicate_and_unbounded_profiles(): void
    {
        $registry = app(MediaPolicyRegistry::class);
        $this->assertCount(4, $registry->profiles());
        try {
            $registry->registerProfile('thumbnail', 100, 100);
            $this->fail('Duplicate profile accepted.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('duplicate', $exception->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $registry->registerProfile('sample.huge', 10000, 10000);
    }

    public function test_real_processing_produces_responsive_sources_dimensions_and_ready_status(): void
    {
        $asset = $this->upload();
        $pending = $this->library->present($asset, 'product-card');
        $this->assertSame('', $pending['srcset']);
        $this->assertFalse($pending['conversion_ready']);
        $this->assertSame(1200, $pending['width']);
        $job = Bus::dispatched(ProcessMediaConversions::class)->first();
        $job->handle(app(FileManipulator::class));
        $dto = $this->library->present($asset, 'product-card');
        $this->assertSame('ready', $asset->fresh()->processing_status);
        $this->assertTrue($dto['conversion_ready']);
        $this->assertStringContainsString('640w', $dto['srcset']);
        $media = $asset->getFirstMedia('original')->fresh();
        foreach ($media->responsiveImages('product-card')->getFilenames() as $file) {
            Storage::disk('media_public')->assertExists($media->id.'/responsive-images/'.$file);
        }
        $product = $this->product();
        $this->library->syncImages($product, 'catalog.product.main', [$asset->id], $this->admin);
        Auth::shouldUse('web');
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('activeSrcset')->assertSee('product-detail', false);
        $this->get(route('shop'))->assertOk()->assertSee('640w', false);
    }

    public function test_failed_processing_is_observable_retryable_and_stale_jobs_do_not_overwrite_state(): void
    {
        $asset = $this->upload();
        $media = $asset->getFirstMedia('original');
        $original = Storage::disk('media_public')->get($media->getPathRelativeToRoot());
        Storage::disk('media_public')->delete($media->getPathRelativeToRoot());
        $old = Bus::dispatched(ProcessMediaConversions::class)->first();
        try {
            $old->handle(app(FileManipulator::class));
            $this->fail('Missing original passed conversion.');
        } catch (RuntimeException $exception) {
            $this->assertSame('failed', $asset->fresh()->processing_status);
        }
        $this->actingAs($this->admin, 'admin')->get(route('admin.media.show', $asset))->assertOk()->assertSee('Image processing failed');
        Storage::disk('media_public')->put($media->getPathRelativeToRoot(), $original);
        $this->post(route('admin.media.retry', $asset))->assertSessionHasNoErrors();
        $this->assertSame(2, $asset->fresh()->processing_generation);
        $old->handle(app(FileManipulator::class));
        $this->assertSame('pending', $asset->fresh()->processing_status);
        $new = Bus::dispatched(ProcessMediaConversions::class)->last();
        $new->handle(app(FileManipulator::class));
        $old->failed(new RuntimeException('Stale failure'));
        $this->assertSame('ready', $asset->fresh()->processing_status);
        $this->assertNull($asset->fresh()->processing_error);
    }

    public function test_retry_guard_blocks_duplicate_queued_work_and_unauthorized_staff(): void
    {
        $asset = $this->upload();
        $this->actingAs($this->admin, 'admin')->post(route('admin.media.retry', $asset))->assertSessionHasErrors('media');
        $staff = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password', 'role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff, 'admin')->post(route('admin.media.retry', $asset))->assertForbidden();
    }

    public function test_private_responsive_files_and_conversions_require_authorized_delivery(): void
    {
        $asset = $this->upload('private');
        Bus::dispatched(ProcessMediaConversions::class)->first()->handle(app(FileManipulator::class));
        $media = $asset->getFirstMedia('original')->fresh();
        $files = $media->responsiveImages('product-card')->getFilenames();
        $this->assertNotEmpty($files);
        $this->assertSame([], Storage::disk('media_public')->allFiles());
        $url = route('admin.media.file', ['asset' => $asset->id, 'conversion' => 'product-card', 'responsive' => $files[0]]);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('admin.media.file', ['asset' => $asset->id, 'responsive' => '../photo.jpg']))->assertNotFound();
    }

    public function test_deletion_is_durable_idempotent_and_unselectable_before_cleanup(): void
    {
        $asset = $this->upload();
        $media = $asset->getFirstMedia('original');
        $this->library->delete($asset, $this->admin);
        $task = MediaCleanupTask::firstOrFail();
        $this->assertSame('deleting', $asset->fresh()->status);
        Storage::disk('media_public')->assertExists($media->getPathRelativeToRoot());
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.media.picker'))->assertJsonCount(0, 'data');
        $this->get(route('admin.media.file', $asset))->assertNotFound();
        $job = new CleanupMedia($task->id);
        $job->handle(app(MediaLifecycleService::class));
        $job->handle(app(MediaLifecycleService::class));
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        $this->assertSame([], Storage::disk('media_public')->allFiles());
    }

    public function test_failed_storage_cleanup_keeps_manifest_and_recovers_on_retry(): void
    {
        $asset = $this->upload();
        $this->library->delete($asset, $this->admin);
        $task = MediaCleanupTask::firstOrFail();
        $disk = Storage::disk('media_public');
        $broken = Mockery::mock($disk)->makePartial();
        $broken->shouldReceive('deleteDirectory')->once()->andThrow(new RuntimeException('Storage unavailable'));
        Storage::set('media_public', $broken);
        try {
            app(MediaLifecycleService::class)->cleanup($task->id);
            $this->fail('Broken storage succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('failed', $task->fresh()->status);
            $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'status' => 'deleting']);
            $this->assertNotEmpty($task->fresh()->paths);
        }
        Storage::set('media_public', $disk);
        app(MediaLifecycleService::class)->cleanup($task->id);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame(2, $task->fresh()->attempts);
    }

    public function test_rolled_back_delete_preserves_files_asset_and_never_dispatches_cleanup(): void
    {
        $asset = $this->upload();
        $media = $asset->getFirstMedia('original');
        DB::beginTransaction();
        $this->library->delete($asset, $this->admin);
        DB::rollBack();
        $this->assertSame('active', $asset->fresh()->status);
        $this->assertDatabaseCount('media_cleanup_tasks', 0);
        Storage::disk('media_public')->assertExists($media->getPathRelativeToRoot());
        Bus::assertNotDispatched(CleanupMedia::class);
    }

    public function test_deleted_products_and_orders_cannot_release_purchased_image_retention(): void
    {
        $asset = $this->upload();
        $product = $this->product();
        $this->library->setProductMainImage($product, $asset->id, $this->admin);
        $order = Order::create(['order_number' => 'TEST-1', 'customer_name' => 'Customer', 'customer_email' => 'customer@example.com',
            'customer_phone' => '03001234567', 'delivery_country' => 'Pakistan', 'delivery_city' => 'Sialkot', 'delivery_address' => 'Street',
            'subtotal' => 500, 'grand_total' => 500, 'payment_method' => 'cod']);
        $item = $order->items()->create(['product_id' => $product->id, 'product_title' => 'Bag', 'unit_price' => 500, 'quantity' => 1, 'total_price' => 500]);
        $this->library->retainOrderImage($item, $product);
        $this->library->retainOrderImage($item, $product);
        $this->assertSame(1, $asset->retentionHolds()->count());
        $asset->update(['alt_text' => 'Changed image metadata']);
        $product->delete();
        $this->assertSame('Original bag', $item->fresh()->media_snapshot['alt_text']);
        $this->assertSame($asset->getFirstMedia('original')->getUrl(), $item->fresh()->product_image_url);
        $order->delete();
        $this->assertNull($asset->retentionHolds()->first()->order_item_id);
        $this->actingAs($this->admin, 'admin')->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
        Storage::disk('media_public')->assertExists($asset->getFirstMedia('original')->getPathRelativeToRoot());
    }

    public function test_checkout_records_library_image_and_retention_hold_in_its_transaction(): void
    {
        $asset = $this->upload();
        $product = $this->product();
        $this->library->setProductMainImage($product, $asset->id, $this->admin);
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        Setting::set('email_order_confirmation', '0', 'email', 'boolean');
        $zone = DeliveryZone::create(['name' => 'Central', 'delivery_fee' => 0, 'is_active' => true]);
        Auth::shouldUse('web');
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->postJson(route('checkout.place-order'), $this->withHoneypot([
            'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '03001234567',
            'country' => 'Pakistan', 'city' => 'Sialkot', 'delivery_zone_id' => $zone->id, 'address_line' => 'Street', 'payment_method' => 'cod',
        ]))->assertOk();
        $item = OrderItem::firstOrFail();
        $this->assertSame($asset->id, $item->media_asset_id);
        $this->assertSame('Original bag', $item->media_snapshot['alt_text']);
        $this->assertDatabaseHas('media_retention_holds', ['media_asset_id' => $asset->id, 'order_item_id' => $item->id]);
    }

    public function test_reconciliation_respects_upload_grace_and_preserves_unknown_folders(): void
    {
        $stale = MediaAsset::create(['title' => 'Interrupted upload', 'visibility' => 'public', 'status' => 'uploading', 'created_at' => now()->subDays(2)]);
        // Timestamps are intentionally controlled because created_at is guarded.
        $stale->forceFill(['created_at' => now()->subDays(2)])->save();
        $recent = MediaAsset::create(['title' => 'In progress', 'visibility' => 'public', 'status' => 'uploading']);
        Storage::disk('media_public')->put('987654/unknown.jpg', 'orphan');
        $this->artisan('media:reconcile')->assertSuccessful();
        $this->assertSame('uploading', $stale->fresh()->status);
        $this->artisan('media:reconcile', ['--apply' => true])->assertSuccessful();
        $this->assertSame('deleting', $stale->fresh()->status);
        $this->assertSame('uploading', $recent->fresh()->status);
        Storage::disk('media_public')->assertExists('987654/unknown.jpg');
    }

    public function test_direct_model_deletion_cannot_bypass_lifecycle_or_remove_original_files(): void
    {
        $asset = $this->upload();
        $media = $asset->getFirstMedia('original');
        try {
            $asset->delete();
            $this->fail('Direct asset deletion allowed.');
        } catch (\LogicException $exception) {
            Storage::disk('media_public')->assertExists($media->getPathRelativeToRoot());
        }
        $this->expectException(\LogicException::class);
        $media->delete();
    }
}
