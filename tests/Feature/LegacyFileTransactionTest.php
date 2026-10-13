<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LegacyFileTransactionTest extends TestCase
{
    // Real commits exercise cleanup after the outer admin/audit transaction.
    use DatabaseMigrations;

    public static function deletionCases(): array
    {
        return [['category'], ['gallery'], ['receipt']];
    }

    private function fixture(string $kind): array
    {
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
        Storage::fake('public');
        Storage::fake('local');
        $disk = $kind === 'receipt' ? 'local' : 'public';
        $path = $kind.'/original.jpg';
        Storage::disk($disk)->put($path, 'original bytes');

        if ($kind === 'category') {
            $model = Category::create(['name' => 'Category', 'image' => $path]);
            $url = route('admin.categories.destroy', $model);
        } elseif ($kind === 'slide') {
            $model = HomeSlide::create(['image_path' => $path, 'display_order' => 0]);
            $url = '/admin/home-slides/'.$model->id;
        } elseif ($kind === 'gallery') {
            $category = Category::create(['name' => 'Category']);
            $product = Product::create(['category_id' => $category->id, 'title' => 'Product', 'price' => 100]);
            $model = $product->images()->create(['image' => $path, 'sort_order' => 0]);
            $url = route('admin.products.images.delete', [$product, $model]);
        } else {
            $model = Order::create(['order_number' => 'FILE-TEST', 'customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '123', 'delivery_country' => 'Pakistan', 'delivery_city' => 'City', 'delivery_address' => 'Street', 'subtotal' => 100, 'grand_total' => 100, 'payment_method' => 'cod', 'payment_receipt' => $path, 'payment_receipt_disk' => $disk]);
            $url = route('admin.orders.bulk');
        }

        return [$model, $url, $disk, $path];
    }

    private function remove(string $kind, $model, string $url): void
    {
        $response = $kind === 'receipt'
            ? $this->post($url, ['bulk_action' => 'delete', 'ids' => [$model->id]])
            : $this->delete($url);
        $response->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_removed_slide_routes_preserve_legacy_files_and_records(): void
    {
        [$model, $url, $disk, $path] = $this->fixture('slide');
        $this->delete($url)->assertNotFound();
        $this->post('/admin/home-slides/bulk', ['bulk_action' => 'delete', 'ids' => [$model->id]])->assertNotFound();
        $this->assertNotNull($model->fresh());
        Storage::disk($disk)->assertExists($path);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    #[DataProvider('deletionCases')]
    public function test_failed_audit_preserves_database_reference_and_file(string $kind): void
    {
        [$model, $url, $disk, $path] = $this->fixture($kind);
        $this->withoutExceptionHandling();
        AdminAuditLog::creating(function ($log) {
            if ($log->entity_type === 'admin_operations') {
                throw new RuntimeException('Injected audit failure');
            }
        });
        try {
            $this->remove($kind, $model, $url);
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected audit failure', $exception->getMessage());
        }
        $this->assertNotNull($model->fresh());
        Storage::disk($disk)->assertExists($path);
    }

    #[DataProvider('deletionCases')]
    public function test_successful_commit_removes_file_and_reference(string $kind): void
    {
        [$model, $url, $disk, $path] = $this->fixture($kind);
        $this->remove($kind, $model, $url);
        $this->assertNull($model->fresh());
        Storage::disk($disk)->assertMissing($path);
    }
}
