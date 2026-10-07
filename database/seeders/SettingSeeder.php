<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $gallery = [
            ['src' => 'assets/uploads/gallery/1-coffee.jpg', 'alt' => 'Fresh pour coffee'],
            ['src' => 'assets/uploads/gallery/2-ice-cream.jpg', 'alt' => 'Handcrafted gelato'],
            ['src' => 'assets/uploads/gallery/3-juice.jpg', 'alt' => 'Cold-pressed juice'],
            ['src' => 'assets/uploads/gallery/4-milk-tea.jpg', 'alt' => 'Classic boba milk tea'],
            ['src' => 'assets/uploads/gallery/5-latte.jpg', 'alt' => 'Silky latte art'],
            ['src' => 'assets/uploads/gallery/6-oreo sundae.jpg', 'alt' => 'Oreo sundae'],
            ['src' => 'assets/uploads/gallery/7-brown sugar boba milk tea.jpg', 'alt' => 'Brown sugar boba'],
            ['src' => 'assets/uploads/gallery/8-super orange juice.jpg', 'alt' => 'Super orange juice'],
            ['src' => 'assets/uploads/gallery/9-pudding milk tea.jpg', 'alt' => 'Pudding milk tea'],
            ['src' => 'assets/uploads/gallery/10-peachy cream pudding tea.png', 'alt' => 'Peachy cream pudding tea'],
        ];

        $settings = [
            ['key' => 'site_name', 'value' => 'Happy Tea', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_tagline', 'value' => 'Crafted with love, served with soul', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_email', 'value' => 'hello@happyteaskt.com', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_phone', 'value' => '+92-52-1234567', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_whatsapp', 'value' => '+923001234567', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_address', 'value' => 'Main Boulevard, Model Town, Sialkot, Pakistan', 'group' => 'general', 'type' => 'text'],
            ['key' => 'facebook_url', 'value' => 'https://facebook.com/happyteaskt', 'group' => 'general', 'type' => 'text'],
            ['key' => 'instagram_url', 'value' => 'https://instagram.com/happyteaskt', 'group' => 'general', 'type' => 'text'],
            ['key' => 'tiktok_url', 'value' => 'https://tiktok.com/@happyteaskt', 'group' => 'general', 'type' => 'text'],

            ['key' => 'meta_title', 'value' => 'Happy Tea — Premium Beverages in Sialkot', 'group' => 'seo', 'type' => 'text'],
            ['key' => 'meta_description', 'value' => 'Explore handcrafted coffees, fruit teas, ice cream, and premium boba tea at Happy Tea in Sialkot. Fresh daily, delivered with care.', 'group' => 'seo', 'type' => 'text'],

            ['key' => 'delivery_fee', 'value' => '200', 'group' => 'delivery', 'type' => 'text'],
            ['key' => 'free_delivery_above', 'value' => '2000', 'group' => 'delivery', 'type' => 'text'],
            ['key' => 'express_delivery_fee', 'value' => '400', 'group' => 'delivery', 'type' => 'text'],
            ['key' => 'same_day_cutoff', 'value' => '14:00', 'group' => 'delivery', 'type' => 'text'],

            ['key' => 'ordering_enabled', 'value' => '0', 'group' => 'ordering', 'type' => 'boolean'],
            ['key' => 'ordering_start_time', 'value' => '09:00', 'group' => 'ordering', 'type' => 'text'],
            ['key' => 'ordering_end_time', 'value' => '21:00', 'group' => 'ordering', 'type' => 'text'],

            ['key' => 'payment_cod_enabled', 'value' => '1', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment_easypaisa_enabled', 'value' => '1', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment_jazzcash_enabled', 'value' => '1', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment_bank_transfer_enabled', 'value' => '1', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'easypaisa_account', 'value' => '0300-1234567', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'easypaisa_name', 'value' => 'Happy Tea', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'jazzcash_account', 'value' => '0301-7654321', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'jazzcash_name', 'value' => 'Happy Tea', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'bank_name', 'value' => 'Meezan Bank', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'bank_account', 'value' => '0123456789012345', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'bank_iban', 'value' => 'PK12MEZN0001234567890123', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'bank_title', 'value' => 'Happy Tea', 'group' => 'payment', 'type' => 'text'],

            ['key' => 'email_order_confirmation', 'value' => '1', 'group' => 'email', 'type' => 'boolean'],
            ['key' => 'email_account_signup', 'value' => '1', 'group' => 'email', 'type' => 'boolean'],
            ['key' => 'email_forgot_password', 'value' => '1', 'group' => 'email', 'type' => 'boolean'],
            ['key' => 'email_order_update', 'value' => '1', 'group' => 'email', 'type' => 'boolean'],

            ['key' => 'homepage_gallery', 'value' => json_encode($gallery), 'group' => 'homepage', 'type' => 'json'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
