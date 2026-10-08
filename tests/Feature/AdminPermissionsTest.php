<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminPermissions;
use App\Services\InventoryService;
use App\Services\OrderStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = [], string $role = 'custom'): Admin
    {
        return Admin::create(['name' => 'Staff', 'email' => uniqid().'@example.com', 'password' => 'a-long-test-password', 'role' => $role, 'is_active' => true, 'permissions' => $permissions]);
    }

    private function order(): Order
    {
        return Order::create(['order_number' => 'TEST-'.uniqid(), 'customer_name' => 'Customer', 'customer_email' => 'customer@example.com',
            'customer_phone' => '123', 'delivery_country' => 'Pakistan', 'delivery_city' => 'City', 'delivery_address' => 'Street',
            'subtotal' => 100, 'grand_total' => 100, 'payment_method' => 'cod', 'status' => 'pending', 'payment_status' => 'pending']);
    }

    private function staffData(Admin $staff, array $overrides = []): array
    {
        return array_replace(['name' => $staff->name, 'email' => $staff->email, 'role' => $staff->role,
            'permissions' => $staff->permissions ?? [], 'is_active' => true], $overrides);
    }

    public function test_customer_unknown_role_and_inactive_admin_cannot_access_protected_data(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->getJson(route('admin.orders.index'))->assertUnauthorized();
        $staff = $this->admin([], 'legacy_staff');
        $this->actingAs($staff, 'admin')->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.workspace'))->assertOk()->assertDontSee(route('admin.orders.poll'));
        $staff->update(['is_active' => false]);
        $this->get(route('admin.workspace'))->assertRedirect(route('admin.login'));
    }

    public function test_read_permission_does_not_allow_single_or_bulk_mutations_and_navigation_is_filtered(): void
    {
        $category = Category::create(['name' => 'Original']);
        $this->actingAs($this->admin(['categories.view']), 'admin');
        $this->get(route('admin.categories.index'))->assertOk()->assertDontSee(route('admin.settings.index'))->assertDontSee(route('admin.orders.poll'));
        $this->postJson(route('admin.categories.bulk'), ['ids' => [$category->id], 'bulk_action' => 'delete'])->assertForbidden();
        $this->deleteJson(route('admin.categories.destroy', $category))->assertForbidden();
        $this->putJson(route('admin.categories.update', $category), ['name' => 'Changed'])->assertForbidden();
        $this->assertSame('Original', $category->fresh()->name);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_bulk_writes_record_before_after_for_each_entity_and_delete_requires_separate_grant(): void
    {
        $a = Category::create(['name' => 'A', 'is_active' => true]);
        $b = Category::create(['name' => 'B', 'is_active' => true]);
        $actor = $this->admin(['categories.manage']);
        $this->actingAs($actor, 'admin')->post(route('admin.categories.bulk'), ['ids' => [$a->id, $b->id], 'bulk_action' => 'deactivate'])->assertRedirect();
        $logs = AdminAuditLog::where('entity_type', 'categories')->get();
        $this->assertCount(2, $logs);
        $this->assertSame($actor->id, $logs[0]->actor_id);
        $this->assertEquals(1, $logs[0]->changes['is_active']['before']);
        $this->assertEquals(0, $logs[0]->changes['is_active']['after']);
        $this->postJson(route('admin.categories.bulk'), ['ids' => [$a->id], 'bulk_action' => 'delete'])->assertForbidden();
        $actor->update(['permissions' => ['categories.delete']]);
        $this->post(route('admin.categories.bulk'), ['ids' => [$a->id], 'bulk_action' => 'delete'])->assertRedirect();
        $this->assertDatabaseHas('admin_audit_logs', ['entity_type' => 'categories', 'entity_id' => (string) $a->id, 'action' => 'deleted']);
    }

    public function test_order_payment_changes_require_payment_permission_in_single_bulk_and_locked_service(): void
    {
        $order = $this->order();
        $actor = $this->admin(['orders.view', 'orders.manage']);
        $this->actingAs($actor, 'admin')->patchJson(route('admin.orders.status', $order), ['status' => 'confirmed', 'payment_status' => 'paid'])->assertForbidden();
        $this->postJson(route('admin.orders.bulk'), ['ids' => [$order->id], 'bulk_action' => 'set_payment', 'payment_status' => 'paid'])->assertForbidden();
        $this->patch(route('admin.orders.status', $order), ['status' => 'confirmed', 'payment_status' => 'pending'])->assertRedirect();
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->get(route('admin.orders.receipt', $order))->assertForbidden();
        $actor->update(['permissions' => ['orders.view', 'orders.manage', 'orders.payment']]);
        $this->patch(route('admin.orders.status', $order), ['status' => 'confirmed', 'payment_status' => 'paid', 'admin_notes' => 'Private note'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $log = AdminAuditLog::where('entity_type', 'orders')->latest('id')->firstOrFail();
        $this->assertSame('pending', $log->changes['payment_status']['before']);
        $this->assertSame('paid', $log->changes['payment_status']['after']);
        $this->assertSame('[redacted]', $log->changes['admin_notes']['after']);
        $this->expectException(HttpException::class);
        app(OrderStateService::class)->updateMany([$order->id], ['payment_status' => 'refunded'], $this->admin(['orders.manage']));
    }

    public function test_private_media_is_not_exposed_to_public_media_viewers(): void
    {
        $asset = MediaAsset::create(['title' => 'Private document', 'visibility' => 'private', 'status' => 'active']);
        $this->actingAs($this->admin(['media.view']), 'admin');
        $this->get(route('admin.media.index'))->assertOk()->assertDontSee('Private document');
        $this->getJson(route('admin.media.show', $asset))->assertForbidden();
        $this->getJson(route('admin.media.file', $asset))->assertForbidden();
        $this->getJson(route('admin.media.picker'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_management_cannot_escalate_privileges_or_remove_last_owner(): void
    {
        $owner = $this->admin([], 'super_admin');
        $staff = $this->admin(['categories.view']);
        $this->actingAs($staff, 'admin')->putJson(route('admin.staff.update', $staff), $this->staffData($staff, ['role' => 'super_admin']))->assertForbidden();
        $this->actingAs($owner, 'admin')->putJson(route('admin.staff.update', $owner), $this->staffData($owner, ['is_active' => false]))->assertUnprocessable();
        $manager = $this->admin(['staff.manage', 'categories.view']);
        $this->actingAs($manager, 'admin')->putJson(route('admin.staff.update', $staff), $this->staffData($staff, ['permissions' => ['orders.payment']]))->assertForbidden();
        $this->putJson(route('admin.staff.update', $owner), $this->staffData($owner))->assertForbidden();
        $this->putJson(route('admin.staff.update', $staff), $this->staffData($staff))->assertRedirect();
    }

    public function test_staff_creation_and_password_change_are_audited_without_secrets(): void
    {
        $owner = $this->admin([], 'super_admin');
        $this->actingAs($owner, 'admin')->post(route('admin.staff.store'), ['name' => 'New Staff', 'email' => 'new@example.com', 'password' => 'secret-long-password', 'role' => 'catalog', 'is_active' => true, 'permissions' => []])->assertRedirect();
        $staff = Admin::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($staff->hasPermission('products.manage'));
        $this->assertFalse($staff->hasPermission('orders.payment'));
        $log = AdminAuditLog::where('entity_type', 'admins')->sole();
        $this->assertSame('[redacted]', $log->changes['password']['after']);
        $this->assertStringNotContainsString('secret-long-password', json_encode($log->changes));
        $this->assertArrayNotHasKey('email', $log->changes);
        $this->get(route('admin.staff.index'))->assertOk();
        $this->get(route('admin.audit.index'))->assertOk()->assertSee('New Staff');
    }

    public function test_audit_failure_rolls_back_sensitive_database_change(): void
    {
        $category = Category::create(['name' => 'A', 'is_active' => true]);
        $this->actingAs($this->admin(['categories.manage']), 'admin');
        AdminAuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('admin.categories.bulk'), ['ids' => [$category->id], 'bulk_action' => 'deactivate']);
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertTrue($category->fresh()->is_active);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_grant_revocation_takes_effect_on_next_request_and_unknown_grants_are_rejected(): void
    {
        $owner = $this->admin([], 'super_admin');
        $staff = $this->admin(['orders.view']);
        $this->actingAs($staff, 'admin')->getJson(route('admin.orders.poll'))->assertOk();
        Admin::whereKey($staff->id)->update(['permissions' => []]);
        $this->getJson(route('admin.orders.poll'))->assertForbidden();
        $this->actingAs($owner, 'admin')->putJson(route('admin.staff.update', $staff), $this->staffData($staff, ['permissions' => ['made-up.permission']]))->assertUnprocessable();
    }

    public function test_settings_values_are_redacted_and_successful_operations_are_logged(): void
    {
        $this->actingAs($this->admin(['settings.manage']), 'admin')->put(route('admin.settings.update'), ['site_tagline' => 'private-value'])->assertRedirect();
        $log = AdminAuditLog::where('entity_type', 'settings')->sole();
        $this->assertSame('[redacted]', $log->changes['value']['after']);
        $this->assertStringNotContainsString('private-value', json_encode($log->changes));
        $this->assertDatabaseHas('admin_audit_logs', ['entity_type' => 'admin_operations', 'route' => 'admin.settings.update', 'action' => 'executed']);
        $this->actingAs($this->admin(['settings.view']), 'admin')->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_stock_adjustments_require_separate_inventory_permission(): void
    {
        $product = Product::create(['category_id' => Category::create(['name' => 'Stock'])->id, 'title' => 'Stock', 'price' => 100, 'inventory_mode' => 'untracked', 'stock_quantity' => 0]);
        $actor = $this->admin(['products.manage']);
        try {
            app(InventoryService::class)->configureProduct($product, 'shared', 5, 0, $actor);
            $this->fail('Expected inventory permission rejection');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $actor->update(['permissions' => ['inventory.manage']]);
        app(InventoryService::class)->configureProduct($product, 'shared', 5, 0, $actor);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_export_is_separately_authorized_and_audited(): void
    {
        $actor = $this->admin(['newsletter.view']);
        $this->actingAs($actor, 'admin')->get(route('admin.newsletter.export'))->assertForbidden();
        $actor->update(['permissions' => ['newsletter.view', 'newsletter.export']]);
        $this->get(route('admin.newsletter.export'))->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $actor->id, 'action' => 'accessed', 'entity_type' => 'newsletter_subscribers']);
    }

    public function test_web_validation_redirect_does_not_record_a_successful_operation(): void
    {
        $this->actingAs($this->admin(['categories.manage']), 'admin')->post(route('admin.categories.store'), [])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_audit_history_cannot_be_edited_through_models(): void
    {
        $this->actingAs($this->admin(['categories.manage']), 'admin')->post(route('admin.categories.store'), ['name' => 'Audited'])->assertRedirect();
        $log = AdminAuditLog::firstOrFail();
        $this->expectException(\LogicException::class);
        $log->update(['actor_name' => 'Replacement actor']);
    }

    public function test_route_permissions_are_mapped_and_unknown_routes_fail_closed(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (in_array('admin.permissions', $route->middleware(), true)) {
                $this->assertNotNull(AdminPermissions::required($route->getName()), $route->getName());
            }
        }
        $this->assertNull(AdminPermissions::required('admin.orders.unmapped'));
    }
}
