<?php

namespace Database\Seeders;

use App\Models\HomeSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HomeSlideSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        HomeSlide::truncate();
        Schema::enableForeignKeyConstraints();

        $slides = [
            [
                'image_path' => 'assets/sliders/coffee.jpg',
                'title' => 'Escape the ordinary,',
                'title_highlighted' => 'sip the extraordinary',
                'description' => 'We source unique, single-origin beans from around the world, roasted to perfection to bring out the richest flavors.',
                'button_text' => 'Explore Brews',
                'button_link' => '/#menu-coffee',
                'left_icon' => '☕',
                'right_badge_text' => '• 100% Vegan Freshly Roasted Coffee Beans • Organic Brew',
                'right_badge_emoji' => '☕',
                'display_order' => 1,
            ],
            [
                'image_path' => 'assets/sliders/ice-cream.jpg',
                'title' => 'Indulge in sweet,',
                'title_highlighted' => 'creamy perfection',
                'description' => 'Handcrafted in small batches using organic dairy and real, seasonal fruits for an unforgettable, creamy taste.',
                'button_text' => 'Explore Ice Cream',
                'button_link' => '/#menu-ice-cream',
                'left_icon' => '🍦',
                'right_badge_text' => '• 100% Organic Handcrafted Ice Cream • Fresh Local Milk',
                'right_badge_emoji' => '🍦',
                'display_order' => 2,
            ],
            [
                'image_path' => 'assets/sliders/juice.jpg',
                'title' => 'Fresh fruit teas,',
                'title_highlighted' => 'pressed daily',
                'description' => 'Vibrant fruit teas and cold-pressed juices made with real fruit — bright, refreshing, and never dull.',
                'button_text' => 'Explore Fruit Tea',
                'button_link' => '/#menu-fruit-tea',
                'left_icon' => '🍊',
                'right_badge_text' => '• Cold-Pressed Fresh Daily • Zero Added Sugar • Vegan',
                'right_badge_emoji' => '🍊',
                'display_order' => 3,
            ],
            [
                'image_path' => 'assets/sliders/milk-tea.jpg',
                'title' => 'Premium boba,',
                'title_highlighted' => 'crafted with care',
                'description' => 'Creamy milk teas with chewy pearls and house-made toppings — Taiwanese-inspired, locally loved.',
                'button_text' => 'Explore Milk Tea',
                'button_link' => '/#menu-milk-tea',
                'left_icon' => '🧋',
                'right_badge_text' => '• Premium Taiwanese Tea Leaves • Real Boba Pearls • Fresh',
                'right_badge_emoji' => '🧋',
                'display_order' => 4,
            ],
        ];

        foreach ($slides as $slide) {
            HomeSlide::create(array_merge($slide, [
                'is_active' => true,
            ]));
        }
    }
}
