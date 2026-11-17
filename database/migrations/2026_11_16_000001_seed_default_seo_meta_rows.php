<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the per-page SEO rows the storefront layout reads before any page
 * fallback (home, shop and the static pages). The admin SEO dashboard
 * (admin/seo) edits these same rows, so merchants can retune the copy
 * without touching templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        $brand = 'RJ Motorbike';

        $pages = [
            'home' => [
                'meta_title' => 'Custom Leather Jackets, Race Suits & Motorcycle Gear | '.$brand,
                'meta_description' => 'Handmade leather jackets, MotoGP race suits and motorcycle gear, cut to your measurements from full-grain hides. Made to order with free worldwide shipping.',
                'meta_keywords' => 'custom leather jacket, leather motorcycle jacket, motoGP leather suit, made to measure leather, leather race suit',
            ],
            'shop' => [
                'meta_title' => 'Shop All Leather Jackets & Motorcycle Gear | '.$brand,
                'meta_description' => 'Browse the full collection: biker and bomber jackets, MotoGP race suits, gloves, chaps, boots and movie leather jackets — all handmade and made to measure.',
                'meta_keywords' => 'leather jackets, motorcycle gear, race suits, shop leather collection',
            ],
            'about' => [
                'meta_title' => 'Our Story | '.$brand.' — Handmade Leather Workshop',
                'meta_description' => 'Inside the '.$brand.' workshop: hand-cut full-grain hides, made-to-measure leather jackets and race suits, and a finish that improves with every mile.',
                'meta_keywords' => 'leather workshop, handmade leather, made to measure leather jacket',
            ],
            'contact' => [
                'meta_title' => 'Contact | '.$brand.' — Custom Orders & Customer Support',
                'meta_description' => 'Questions about a made-to-measure leather jacket, an existing order or delivery times? Reach the '.$brand.' workshop by email, phone or WhatsApp.',
                'meta_keywords' => 'contact leather store, custom leather order support',
            ],
            'faq' => [
                'meta_title' => 'FAQ | '.$brand.' — Sizing, Delivery & Returns',
                'meta_description' => 'Answers on made-to-measure sizing, leather care, delivery times, returns and payment options for custom leather jackets and motorcycle gear.',
                'meta_keywords' => 'leather jacket size guide, leather care, delivery, returns',
            ],
            'gallery' => [
                'meta_title' => 'Gallery | '.$brand.' — Workshop & Craft Photography',
                'meta_description' => 'Behind the seams: photos from the '.$brand.' workshop — hides, hand stitching, bespoke fittings and finished made-to-measure leather pieces.',
                'meta_keywords' => 'leather workshop gallery, leather craft photos',
            ],
            'privacy' => [
                'meta_title' => 'Privacy Policy | '.$brand,
                'meta_description' => 'How '.$brand.' collects, uses and protects your personal information, including order data, analytics and marketing consent.',
                'meta_keywords' => 'privacy policy',
            ],
            'terms' => [
                'meta_title' => 'Terms & Conditions | '.$brand,
                'meta_description' => 'The terms that govern orders with '.$brand.': made-to-measure production, pricing, payment, delivery and liability.',
                'meta_keywords' => 'terms and conditions',
            ],
            'returns' => [
                'meta_title' => 'Returns & Refund Policy | '.$brand,
                'meta_description' => 'Exchanges and refunds for made-to-measure leather jackets, race suits and gear: eligibility, timelines and how to start a return.',
                'meta_keywords' => 'refund policy, leather jacket returns, exchanges',
            ],
            'shipping' => [
                'meta_title' => 'Shipping & Delivery Policy | '.$brand,
                'meta_description' => 'Free worldwide shipping on qualifying orders: production times, tracked delivery estimates and customs information for '.$brand.' leather goods.',
                'meta_keywords' => 'shipping policy, free worldwide shipping, delivery times',
            ],
        ];

        foreach ($pages as $pageKey => $meta) {
            DB::table('seo_meta')->updateOrInsert(
                ['page_key' => $pageKey],
                $meta + ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('seo_meta')->whereIn('page_key', [
            'home', 'shop', 'about', 'contact', 'faq', 'gallery', 'privacy', 'terms', 'returns', 'shipping',
        ])->delete();
    }
};
