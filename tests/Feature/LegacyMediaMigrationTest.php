<?php

namespace Tests\Feature;

use App\Jobs\Media\CleanupMedia;
use App\Jobs\Media\ProcessMediaConversions;
use App\Models\Admin;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\LegacyMediaImport;
use App\Models\MediaAsset;
use App\Models\MediaRetentionHold;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Media\ContentMediaService;
use App\Services\Media\LegacyMediaImporter;
use App\Services\Media\LegacyMediaMigrationService;
use App\Services\Media\LegacyMediaSource;
use App\Services\Media\LegacyReceiptMigrator;
use App\Services\Media\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LegacyMediaMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['public', 'local', 'legacy_assets', 'media_public', 'media_private'] as $disk) {
            Storage::fake($disk);
        }
        config(['media-library.image_optimizers' => [], 'media-library.queue_conversions_after_database_commit' => false]);
        Bus::fake([ProcessMediaConversions::class, CleanupMedia::class]);
        // The old schema migration inserts three default slides; fixtures control their own sources.
        HomeSlide::query()->delete();
    }

    private function image(string $path, string $disk = 'public', ?string $bytes = null): string
    {
        $bytes ??= $this->imageBytes(600, 400);
        Storage::disk($disk)->put($path, $bytes);

        return $bytes;
    }

    private function imageBytes(int $width, int $height): string
    {
        $upload = UploadedFile::fake()->image('photo.jpg', $width, $height);

        return file_get_contents($upload->getRealPath());
    }

    private function product(string $path = 'products/main.jpg'): Product
    {
        $category = Category::create(['name' => 'Catalog']);

        return Product::create(['title' => 'Original product', 'category_id' => $category->id, 'price' => 500, 'main_image' => $path]);
    }

    private function order(?string $receipt = null): Order
    {
        return Order::create(['order_number' => 'LEGACY-'.Order::count(), 'customer_name' => 'Customer',
            'customer_email' => 'customer@example.com', 'customer_phone' => '03001234567', 'delivery_city' => 'Sialkot',
            'delivery_address' => 'Street', 'subtotal' => 500, 'grand_total' => 500, 'payment_method' => 'cod',
            'payment_receipt' => $receipt, 'payment_receipt_disk' => 'public']);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true]);
    }

    public function test_report_only_inventories_without_mutating_files_or_references(): void
    {
        $this->image('products/main.jpg');
        $this->image('receipts/one.jpg');
        $product = $this->product();
        $order = $this->order('receipts/one.jpg');
        $this->artisan('media:migrate-legacy')->assertSuccessful();
        $this->assertDatabaseCount('legacy_media_imports', 0);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame('products/main.jpg', $product->fresh()->main_image);
        $this->assertSame('public', $order->fresh()->payment_receipt_disk);
        Storage::disk('public')->assertExists('receipts/one.jpg');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_import_is_idempotent_and_deduplicates_compatible_public_images_with_ordered_usages(): void
    {
        $bytes = $this->image('products/main.jpg');
        $this->image('products/shared.jpg', bytes: $bytes);
        $this->image('products/second.jpg', bytes: $this->imageBytes(700, 300));
        $product = $this->product();
        $product->images()->create(['image' => 'products/second.jpg', 'sort_order' => 20]);
        $product->images()->create(['image' => 'products/shared.jpg', 'sort_order' => 10]);
        $product->category->update(['image' => 'products/shared.jpg']);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $product->refresh();
        $this->assertNull($product->main_image);
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseCount('legacy_media_imports', 3);
        $this->assertSame($product->main_image_asset_id, $product->category->image_asset_id);
        $this->assertSame($product->main_image_asset_id, $product->gallery_asset_ids[0]);
        $this->assertCount(2, $product->gallery_asset_ids);
        $this->assertSame(0, $product->images()->count());
        $this->assertSame('Original product', $product->gallery_images[0]['alt_text']);
        Storage::disk('public')->assertExists('products/main.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseCount('media_usages', 4);
        $this->assertDatabaseCount('legacy_media_imports', 3);
    }

    public function test_document_root_slides_branding_and_gallery_metadata_are_migrated(): void
    {
        $this->image('images/hero.jpg', 'legacy_assets');
        $this->image('images/gallery.jpg', 'legacy_assets', $this->imageBytes(300, 200));
        $slide = HomeSlide::create(['image_path' => 'images/hero.jpg', 'mobile_image_path' => 'images/hero.jpg', 'title' => 'Banner']);
        Setting::set('site_logo', 'images/hero.jpg');
        Setting::set('site_favicon', 'images/hero.jpg');
        Setting::set('homepage_gallery', [['src' => 'images/gallery.jpg', 'alt' => 'First view'], ['src' => 'images/hero.jpg', 'alt' => 'Second view']], 'content', 'json');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertNull($slide->fresh()->image_path);
        $this->assertSame($slide->fresh()->image_asset_id, $slide->fresh()->mobile_image_asset_id);
        $this->assertNotNull(app(ContentMediaService::class)->branding('site_logo'));
        $gallery = app(ContentMediaService::class)->gallery();
        $this->assertSame(['First view', 'Second view'], array_column($gallery, 'alt'));
        $this->assertSame([], Setting::get('homepage_gallery'));
        Storage::disk('legacy_assets')->assertExists('images/gallery.jpg');
    }

    public function test_historical_path_survives_product_changes_and_deletion_with_independent_retention(): void
    {
        $this->image('products/main.jpg');
        $oldBytes = $this->imageBytes(320, 200);
        $this->image('products/old.jpg', bytes: $oldBytes);
        $product = $this->product();
        $order = $this->order();
        $item = $order->items()->create(['product_id' => $product->id, 'product_title' => 'Purchased title',
            'product_image' => 'products/old.jpg', 'unit_price' => 500, 'quantity' => 1, 'total_price' => 500]);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $item->refresh();
        $this->assertNotSame($product->fresh()->main_image_asset_id, $item->media_asset_id);
        $this->assertSame('products/old.jpg', $item->product_image);
        $this->assertSame('Purchased title', $item->media_snapshot['title']);
        $asset = MediaAsset::findOrFail($item->media_asset_id);
        $this->assertSame(hash('sha256', $oldBytes), hash_file('sha256', $asset->getFirstMedia('original')->getPath()));
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('media_retention_holds', 1);
        $product->delete();
        $order->delete();
        $this->assertNull(MediaRetentionHold::firstOrFail()->order_item_id);
        $this->actingAs($this->admin(), 'admin')->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
    }

    public function test_missing_gallery_member_keeps_the_entire_legacy_gallery_visible_and_other_targets_continue(): void
    {
        $this->image('products/main.jpg');
        $product = $this->product();
        $product->images()->create(['image' => 'products/main.jpg', 'sort_order' => 0]);
        $product->images()->create(['image' => 'products/missing.jpg', 'sort_order' => 1]);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        $this->assertNotNull($product->fresh()->main_image_asset_id);
        $this->assertSame([], $product->fresh()->gallery_asset_ids);
        $this->assertSame(2, $product->images()->count());
        $this->assertCount(2, $product->fresh()->gallery_images);
        $this->image('products/missing.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertSame(0, $product->images()->count());
    }

    public function test_existing_library_selection_and_legacy_fallback_are_preserved(): void
    {
        $product = $this->product('products/missing.jpg');
        $admin = $this->admin();
        $library = app(MediaLibraryService::class);
        $asset = $library->upload(UploadedFile::fake()->image('chosen.jpg'), ['title' => 'Chosen', 'visibility' => 'public'], $admin);
        $library->setProductMainImage($product, $asset->id, $admin);
        $messages = [];
        $counts = app(LegacyMediaMigrationService::class)->run(true, function ($message) use (&$messages) {
            $messages[] = $message;
        });
        $this->assertSame(0, $counts['errors'], implode("\n", $messages));
        $this->assertSame($asset->id, $product->fresh()->main_image_asset_id);
        $this->assertSame('products/missing.jpg', $product->fresh()->main_image);
        $this->assertDatabaseCount('legacy_media_imports', 0);
    }

    public function test_changed_source_checksum_is_reported_without_creating_a_new_asset_or_rewriting_history(): void
    {
        $this->image('products/main.jpg');
        $product = $this->product();
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $id = $product->fresh()->main_image_asset_id;
        $this->image('products/main.jpg', bytes: $this->imageBytes(120, 80));
        $this->artisan('media:migrate-legacy')->assertFailed();
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        $this->assertSame($id, $product->fresh()->main_image_asset_id);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_shared_receipts_move_privately_and_do_not_merge_with_identical_public_image_bytes(): void
    {
        $bytes = $this->image('products/main.jpg');
        $this->image('receipts/shared.jpg', bytes: $bytes);
        $this->product();
        $one = $this->order('receipts/shared.jpg');
        $two = $this->order('receipts/shared.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $one->refresh();
        $two->refresh();
        $this->assertSame('local', $one->payment_receipt_disk);
        $this->assertSame($one->payment_receipt, $two->payment_receipt);
        $this->assertSame($bytes, Storage::disk('local')->get($one->payment_receipt));
        Storage::disk('public')->assertMissing('receipts/shared.jpg');
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertNull(LegacyMediaImport::where('visibility', 'private')->firstOrFail()->media_asset_id);
        $this->get(route('admin.orders.receipt', $one))->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin(), 'admin')->get(route('admin.orders.receipt', $one))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('legacy_media_imports', 2);
    }

    public function test_failed_public_receipt_cleanup_is_resumable_after_database_references_change(): void
    {
        $bytes = $this->image('receipts/one.jpg');
        $order = $this->order('receipts/one.jpg');
        $disk = Storage::disk('public');
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('delete')->once()->with('receipts/one.jpg')->andReturn(false);
        Storage::set('public', $mock);
        try {
            app(LegacyReceiptMigrator::class)->migrate(['disk' => 'public', 'path' => 'receipts/one.jpg']);
            $this->fail('Expected public cleanup failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cleanup failed', $exception->getMessage());
        }
        Storage::set('public', $disk);
        $this->assertSame('local', $order->fresh()->payment_receipt_disk);
        $this->assertSame('copied', LegacyMediaImport::firstOrFail()->status);
        $this->assertSame($bytes, Storage::disk('local')->get($order->fresh()->payment_receipt));
        Storage::disk('public')->assertExists('receipts/one.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('receipts/one.jpg');
        $this->assertSame('completed', LegacyMediaImport::firstOrFail()->status);
    }

    public function test_conflicting_private_destination_is_never_overwritten_and_public_receipt_remains(): void
    {
        $bytes = $this->image('receipts/one.jpg');
        $order = $this->order('receipts/one.jpg');
        $source = ['disk' => 'public', 'path' => 'receipts/one.jpg'];
        $key = app(LegacyMediaSource::class)->key($source, 'private');
        LegacyMediaImport::create(['source_key' => $key, 'source_disk' => 'public', 'source_path' => $source['path'],
            'checksum' => hash('sha256', $bytes), 'visibility' => 'private', 'destination_disk' => 'local',
            'destination_path' => 'receipts/legacy/'.$key.'.jpg']);
        Storage::disk('local')->put('receipts/legacy/'.$key.'.jpg', 'different bytes');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        $this->assertSame('public', $order->fresh()->payment_receipt_disk);
        Storage::disk('public')->assertExists('receipts/one.jpg');
        $this->assertSame('different bytes', Storage::disk('local')->get('receipts/legacy/'.$key.'.jpg'));
    }

    public function test_interrupted_image_copy_reuses_journal_asset_and_is_not_collected_by_lifecycle_reconciliation(): void
    {
        $bytes = $this->image('products/main.jpg');
        $source = ['disk' => 'public', 'path' => 'products/main.jpg'];
        $asset = app(LegacyMediaImporter::class)->image($source, 'Imported');
        $journal = LegacyMediaImport::firstOrFail();
        $journal->update(['status' => 'pending']);
        $asset->forceFill(['status' => 'uploading', 'created_at' => now()->subDays(2)])->save();
        Storage::disk('media_public')->put($journal->destination_path, 'partial copy');
        $this->artisan('media:reconcile', ['--apply' => true])->assertSuccessful();
        $this->assertSame('uploading', $asset->fresh()->status);
        $product = $this->product();
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertSame($asset->id, $product->fresh()->main_image_asset_id);
        $this->assertSame($bytes, Storage::disk('media_public')->get($journal->destination_path));
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_unsafe_paths_symlinks_and_private_receipt_content_are_rejected(): void
    {
        $source = app(LegacyMediaSource::class);
        foreach (['../private/secret.jpg', '/tmp/secret.jpg', 'https://example.com/file.jpg', 'products/../file.jpg', 'media/1/original.jpg'] as $path) {
            try {
                $source->inspect(['disk' => 'public', 'path' => $path]);
                $this->fail('Unsafe source accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('unsafe', $exception->getMessage());
            }
        }
        $this->image('products/main.jpg');
        symlink(Storage::disk('public')->path('products/main.jpg'), Storage::disk('public')->path('products/link.jpg'));
        try {
            $source->inspect(['disk' => 'public', 'path' => 'products/link.jpg']);
            $this->fail('Symlink accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Symlink', $exception->getMessage());
        }
        $this->image('receipts/one.jpg');
        $product = $this->product('receipts/one.jpg');
        $order = $this->order('receipts/one.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        $this->assertNull($product->fresh()->main_image_asset_id);
        $this->assertSame('public', $order->fresh()->payment_receipt_disk);
        Storage::disk('public')->assertExists('receipts/one.jpg');
    }

    public function test_unknown_files_are_reported_and_preserved_and_concurrent_migration_is_refused(): void
    {
        $this->image('receipts/unreferenced.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->expectsOutputToContain('Unreferenced public file: receipts/unreferenced.jpg')->assertSuccessful();
        Storage::disk('public')->assertExists('receipts/unreferenced.jpg');
        $lock = Cache::lock('media-legacy-migration', 86400);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_receipt_staging_recovers_a_partial_copy_and_finishes_after_an_interrupted_unlink(): void
    {
        $bytes = $this->image('receipts/one.jpg');
        $order = $this->order('receipts/one.jpg');
        $source = ['disk' => 'public', 'path' => 'receipts/one.jpg'];
        $key = app(LegacyMediaSource::class)->key($source, 'private');
        $destination = 'receipts/legacy/'.$key.'.jpg';
        $journal = LegacyMediaImport::create(['source_key' => $key, 'source_disk' => 'public', 'source_path' => $source['path'],
            'checksum' => hash('sha256', $bytes), 'visibility' => 'private', 'destination_disk' => 'local', 'destination_path' => $destination]);
        Storage::disk('local')->put($destination.'.copy', 'partial');
        $this->artisan('media:migrate-legacy')->assertSuccessful();
        Storage::disk('local')->assertMissing($destination);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertSame($bytes, Storage::disk('local')->get($destination));
        $this->assertSame('local', $order->fresh()->payment_receipt_disk);
        $journal->update(['status' => 'copied']);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertSame('completed', $journal->fresh()->status);
    }

    public function test_private_receipt_storage_cannot_be_mapped_into_public_storage(): void
    {
        $this->image('receipts/one.jpg');
        $order = $this->order('receipts/one.jpg');
        $local = Storage::disk('local');
        Storage::set('local', Storage::disk('public'));
        try {
            $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
            $this->assertSame('public', $order->fresh()->payment_receipt_disk);
            Storage::disk('public')->assertExists('receipts/one.jpg');
            $this->assertDatabaseCount('legacy_media_imports', 0);
        } finally {
            Storage::set('local', $local);
        }
    }

    public function test_pdf_receipts_and_shared_migrated_files_are_retained_after_order_deletion(): void
    {
        Storage::disk('public')->put('receipts/document.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $one = $this->order('receipts/document.pdf');
        $two = $this->order('receipts/document.pdf');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $path = $one->fresh()->payment_receipt;
        $this->actingAs($this->admin(), 'admin')->post(route('admin.orders.bulk'), ['bulk_action' => 'delete', 'ids' => [$one->id]])->assertRedirect();
        Storage::disk('local')->assertExists($path);
        $this->get(route('admin.orders.receipt', $two))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('admin.orders.bulk'), ['bulk_action' => 'delete', 'ids' => [$two->id]])->assertRedirect();
        Storage::disk('local')->assertExists($path);
        $this->artisan('media:migrate-legacy')->assertSuccessful();
    }

    public function test_existing_order_media_repairs_missing_retention_without_replacing_the_snapshot(): void
    {
        $admin = $this->admin();
        $asset = app(MediaLibraryService::class)->upload(UploadedFile::fake()->image('selected.jpg'), ['title' => 'Current title', 'visibility' => 'public'], $admin);
        $item = $this->order()->items()->create(['product_title' => 'Purchased title', 'media_asset_id' => $asset->id,
            'media_snapshot' => ['title' => 'Saved title', 'alt_text' => 'Saved alt'], 'unit_price' => 500, 'quantity' => 1, 'total_price' => 500]);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertSame(['title' => 'Saved title', 'alt_text' => 'Saved alt'], $item->fresh()->media_snapshot);
        $this->assertSame($item->media_snapshot, MediaRetentionHold::firstOrFail()->snapshot);
        $this->assertSame($asset->id, MediaRetentionHold::firstOrFail()->media_asset_id);
    }

    public function test_malformed_gallery_settings_and_missing_private_receipts_are_reported(): void
    {
        Setting::set('homepage_gallery', [['src' => ['bad'], 'alt' => 'Image']], 'content', 'json');
        $order = $this->order('receipts/missing.jpg');
        $order->update(['payment_receipt_disk' => 'local']);
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([['src' => ['bad'], 'alt' => 'Image']], Setting::get('homepage_gallery'));
        $this->assertSame('receipts/missing.jpg', $order->fresh()->payment_receipt);
    }

    public function test_a_previously_imported_public_source_cannot_later_be_migrated_as_a_receipt(): void
    {
        $this->image('products/main.jpg');
        $this->product();
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $order = $this->order('products/main.jpg');
        $this->artisan('media:migrate-legacy', ['--apply' => true])->assertFailed();
        $this->assertSame('public', $order->fresh()->payment_receipt_disk);
        Storage::disk('public')->assertExists('products/main.jpg');
        $this->assertSame(0, LegacyMediaImport::where('visibility', 'private')->count());
    }
}
