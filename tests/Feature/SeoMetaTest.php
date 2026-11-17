<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SeoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_seo_meta_for_a_page(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->put(route('admin.seo.update'), [
            'pages' => [
                'shop' => [
                    'meta_title' => 'Shop Premium Teas — Happy Tea',
                    'meta_description' => 'Browse our full menu of teas and coffees.',
                    'meta_keywords' => 'shop tea, boba menu',
                    'og_image' => 'https://example.com/share.jpg',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_meta', [
            'page_key' => 'shop',
            'meta_title' => 'Shop Premium Teas — Happy Tea',
            'og_image' => 'https://example.com/share.jpg',
        ]);
    }

    public function test_seo_meta_renders_on_storefront_page(): void
    {
        // updateOrCreate: the default SEO seed rows already exist, the admin
        // dashboard overwrites the same row.
        SeoMeta::updateOrCreate(['page_key' => 'shop'], [
            'meta_title' => 'Shop Premium Teas — Happy Tea',
            'meta_description' => 'Browse our full menu of teas and coffees.',
            'meta_keywords' => 'shop tea, boba menu',
            'og_image' => 'https://example.com/share.jpg',
        ]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('<title>Shop Premium Teas — Happy Tea</title>', false)
            ->assertSee('content="Browse our full menu of teas and coffees."', false)
            ->assertSee('content="shop tea, boba menu"', false)
            ->assertSee('content="https://example.com/share.jpg"', false);
    }

    public function test_page_identity_cannot_be_overridden_by_unvalidated_nested_fields(): void
    {
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        SeoMeta::updateOrCreate(['page_key' => 'home'], ['meta_title' => 'Original home']);
        $this->actingAs($admin, 'admin')->putJson(route('admin.seo.update'), ['pages' => ['home' => ['page_key' => 'shop', 'meta_title' => 'Changed home']]])->assertUnprocessable()->assertJsonValidationErrors('pages.home');
        $this->assertDatabaseHas('seo_meta', ['page_key' => 'home', 'meta_title' => 'Original home']);
        // The tampered page_key must not have written a new row anywhere —
        // shop keeps whatever it had (the default seed) and gains nothing.
        $this->assertDatabaseMissing('seo_meta', ['page_key' => 'shop', 'meta_title' => 'Changed home']);
    }
}
