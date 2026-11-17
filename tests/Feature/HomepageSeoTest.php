<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomepageSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_keyword_rich_h1_and_meta_description(): void
    {
        DB::table('theme_selection')->where('id', 1)->update(['active_theme' => 'rjleather.theme-storefront']);

        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee('<h1', false);
        $response->assertSee('Custom Leather Jackets &amp; Motorcycle Gear', false);
        $response->assertSee('Crafted to last.', false);
    }
}

