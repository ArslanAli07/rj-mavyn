<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RemovedAdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_modules_have_no_registered_admin_routes(): void
    {
        foreach (['home-slides', 'testimonials', 'faqs'] as $module) {
            foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'bulk', 'approve'] as $action) {
                $this->assertFalse(Route::has('admin.'.$module.'.'.$action));
            }
        }
    }

    public function test_removed_admin_endpoints_return_not_found_for_guests_and_super_admins(): void
    {
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $endpoints = [['GET', ''], ['GET', '/create'], ['POST', ''], ['GET', '/1'], ['GET', '/1/edit'],
            ['PUT', '/1'], ['PATCH', '/1'], ['DELETE', '/1'], ['POST', '/bulk']];
        foreach ([false, true] as $authenticated) {
            if ($authenticated) {
                $this->actingAs($admin, 'admin');
            }
            foreach (['home-slides', 'testimonials', 'faqs'] as $module) {
                foreach ($endpoints as [$method, $suffix]) {
                    $this->call($method, '/admin/'.$module.$suffix)->assertNotFound();
                }
            }
            $this->patch('/admin/testimonials/1/approve')->assertNotFound();
        }
    }
}
