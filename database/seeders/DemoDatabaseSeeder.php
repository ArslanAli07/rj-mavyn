<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Explicit legacy Happy Tea fixtures, never part of migrate --seed or default db:seed. */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo fixtures are restricted to local disposable databases.');
        }
        foreach (['products', 'product_variants', 'categories', 'occasions', 'delivery_zones', 'home_slides', 'testimonials', 'faqs', 'orders', 'order_items', 'users', 'coupons', 'coupon_usages', 'cart_items', 'addresses', 'wishlists', 'newsletter_subscribers', 'contact_messages', 'seo_meta', 'media_assets', 'media', 'notification_outbox'] as $table) {
            if (DB::table($table)->exists()) {
                throw new LogicException('Demo fixtures require an empty store database. Existing content was preserved.');
            }
        }
        $this->call([
            DatabaseSeeder::class, DeliveryZoneSeeder::class, CategorySeeder::class,
            OccasionSeeder::class, ProductSeeder::class, HomeSlideSeeder::class,
            TestimonialSeeder::class, FaqSeeder::class, SettingSeeder::class,
        ]);
    }
}
