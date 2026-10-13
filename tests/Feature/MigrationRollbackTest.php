<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_lifecycle_indexes_are_removed_before_their_columns(): void
    {
        $migration = require database_path('migrations/2026_10_08_000004_add_media_lifecycle_and_retention.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('media_assets', 'status'));
        $this->assertFalse(Schema::hasColumn('order_items', 'media_asset_id'));
        $migration->up();
        $this->assertTrue(Schema::hasIndex('media_assets', ['status']));
        $this->assertTrue(Schema::hasIndex('media_assets', ['processing_status']));
    }

    public function test_tracking_token_unique_index_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_07_11_162100_add_tracking_token_to_orders_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'tracking_token'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'tracking_token'));
        $this->assertTrue(Schema::hasIndex('orders', ['tracking_token'], 'unique'));
    }

    public function test_nullable_slide_fields_are_backfilled_before_restoring_required_columns(): void
    {
        $id = DB::table('home_slides')->insertGetId(['image_path' => 'images/test.jpg', 'title' => null, 'button_text' => null, 'button_link' => null, 'left_icon' => null, 'right_badge_text' => null, 'right_badge_emoji' => null]);
        $migration = require database_path('migrations/2026_07_20_000000_make_home_slide_content_fields_nullable.php');
        $migration->down();
        $row = DB::table('home_slides')->find($id);
        $this->assertSame('', $row->title);
        $this->assertSame('Explore Collection', $row->button_text);
        $this->assertSame('/shop', $row->button_link);
        $migration->up();
        DB::table('home_slides')->where('id', $id)->update(['title' => null]);
        $this->assertNull(DB::table('home_slides')->find($id)->title);
    }
}
