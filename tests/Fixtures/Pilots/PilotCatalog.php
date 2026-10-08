<?php

namespace Tests\Fixtures\Pilots;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Services\Plugins\PluginManager;
use App\Services\Themes\ThemeManager;
use App\Services\Themes\ThemeSettings;

/** Synthetic pilot profiles. Never invoked against an existing merchant database. */
final class PilotCatalog
{
    public static function configure(string $profile): array
    {
        if (! in_array($profile, ['stationery', 'beverage'], true) || app()->environment() !== 'testing') {
            throw new \RuntimeException('Synthetic pilots require the testing environment.');
        }
        $stationery = $profile === 'stationery';
        Setting::set('site_name', $stationery ? 'Paper Trail' : 'Tea Pilot');
        Setting::set('site_tagline', $stationery ? 'Tools for your next idea' : 'Freshly made');
        Setting::set('site_city', 'Sialkot');
        Setting::set('email_order_confirmation', true, 'email', 'boolean');
        Setting::set('ordering_enabled', true);
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        foreach (['cod', 'easypaisa', 'jazzcash', 'bank_transfer'] as $method) {
            Setting::set('payment_'.$method.'_enabled', in_array($method, ['cod', 'easypaisa'], true));
        }
        Setting::set('easypaisa_account', 'fixture-account-'.$profile);
        Setting::set('easypaisa_name', $stationery ? 'Paper Trail' : 'Tea Pilot');
        app(PluginManager::class)->change('mavyn.food-products', ! $stationery);
        app(PluginManager::class)->change('mavyn.store-availability', ! $stationery);
        $theme = $stationery ? 'mavyn.theme-default' : 'mavyn.theme-happy-tea';
        app(ThemeManager::class)->activate($theme);
        app(ThemeSettings::class)->save($theme, ['welcome_text' => $stationery ? 'Write your next chapter' : 'Enjoy a fresh cup', 'accent_color' => $stationery ? '#2458A6' : '#F88027']);
        $admin = Admin::create(['name' => 'Pilot owner', 'email' => $profile.'@example.test', 'password' => 'fixture-password-only', 'role' => 'super_admin', 'is_active' => true]);
        $category = Category::create(['name' => $stationery ? 'Notebooks' : 'Tea', 'slug' => $stationery ? 'notebooks' : 'tea', 'is_active' => true, 'show_on_homepage' => true]);
        $zone = DeliveryZone::create(['name' => $profile.' local zone', 'delivery_fee' => '6.35', 'is_active' => true]);
        Coupon::create(['code' => 'PILOT', 'type' => 'fixed', 'amount' => '5.25', 'usage_limit' => 100, 'per_user_limit' => 10, 'is_active' => true]);

        return compact('admin', 'category', 'zone');
    }

    public static function product(string $profile, int $category): array
    {
        $stationery = $profile === 'stationery';
        $data = ['title' => $stationery ? 'Everyday Journal' : 'Jasmine Tea', 'category_id' => $category, 'display_order' => 0, 'inventory_mode' => 'shared', 'stock_quantity' => 20, 'price' => '125.55', 'in_stock' => 1, 'is_active' => 1,
            'variants' => [['name' => $stationery ? 'A5' : 'Regular', 'price' => '125.55', 'is_default' => 1, 'is_active' => 1], ['name' => $stationery ? 'A4' : 'Large', 'price' => '180.25', 'is_active' => 1]],
            'option_groups' => [['name' => $stationery ? 'Paper color' : 'Temperature', 'selection_type' => 'single', 'min_selections' => 1, 'max_selections' => 1, 'is_active' => 1, 'values' => [['name' => $stationery ? 'Ivory' : 'Cold', 'price_adjustment' => '0.00', 'is_active' => 1], ['name' => $stationery ? 'White' : 'Hot', 'price_adjustment' => '0.00', 'is_active' => 1]]], ['name' => $stationery ? 'Gift packaging' : 'Extra topping', 'selection_type' => 'multiple', 'min_selections' => 0, 'max_selections' => 1, 'is_active' => 1, 'values' => [['name' => $stationery ? 'Gift wrap' : 'Pearls', 'price_adjustment' => '4.45', 'is_active' => 1]]]]];
        if (! $stationery) {
            $data['ingredients'] = 'Jasmine tea';
            $data['allergens'] = 'None listed';
            $data['variants'][0]['servings'] = 'One';
        }

        return $data;
    }
}
