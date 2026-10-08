<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]), 'admin');
    }

    public function test_unknown_keys_reject_entire_update(): void
    {
        $this->putJson(route('admin.settings.update'), ['site_name' => 'Changed', 'smtp_password' => 'secret'])->assertUnprocessable()->assertJsonValidationErrors('smtp_password');
        $this->assertDatabaseMissing('settings', ['key' => 'site_name']);
        $this->assertDatabaseMissing('settings', ['key' => 'smtp_password']);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_invalid_values_do_not_partially_save(): void
    {
        $this->putJson(route('admin.settings.update'), ['site_name' => 'Changed', 'ordering_start_time' => '25:00', 'delivery_fee' => '-1', 'facebook_url' => 'javascript:alert(1)', 'email_order_update' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors(['ordering_start_time', 'delivery_fee', 'facebook_url', 'email_order_update']);
        $this->assertDatabaseMissing('settings', ['key' => 'site_name']);
        $this->assertDatabaseMissing('settings', ['key' => 'smtp_password']);
    }

    public function test_web_validation_shows_errors_and_keeps_entered_values(): void
    {
        $this->from(route('admin.settings.index'))->put(route('admin.settings.update'), ['site_name' => 'Entered store', 'ordering_start_time' => '25:00'])->assertSessionHasErrors('ordering_start_time');
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('role="alert"', false)->assertSee('value="Entered store"', false);
        $this->assertDatabaseMissing('settings', ['key' => 'site_name']);
    }

    public function test_schema_types_replace_legacy_type_and_preserve_omitted_values(): void
    {
        Setting::set('email_order_update', '1');
        Setting::set('site_name', 'Existing');
        $this->putJson(route('admin.settings.update'), ['email_order_update' => '0', 'delivery_fee' => '12.50'])->assertRedirect();
        $this->assertFalse(Setting::get('email_order_update'));
        $this->assertSame('boolean', Setting::where('key', 'email_order_update')->value('type'));
        $this->assertSame('Existing', Setting::get('site_name'));
        $this->assertSame('12.50', Setting::get('delivery_fee'));
    }
}
