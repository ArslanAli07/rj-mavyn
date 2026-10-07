<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Category::truncate();
        Schema::enableForeignKeyConstraints();

        $categories = [
            [
                'name' => 'Fruit Tea',
                'slug' => 'fruit-tea',
                'display_order' => 1,
                'show_on_homepage' => true,
                'image' => 'assets/sliders/juice.jpg',
                'description' => 'Bright fruit teas & refreshers — cold-pressed juices, citrus teas, and seasonal fruit blends.',
            ],
            [
                'name' => 'Milk Tea',
                'slug' => 'milk-tea',
                'display_order' => 2,
                'show_on_homepage' => true,
                'image' => 'assets/sliders/milk-tea.jpg',
                'description' => 'Premium boba & bubble tea — creamy milk teas with real pearls and signature toppings.',
            ],
            [
                'name' => 'Ice Cream',
                'slug' => 'ice-cream',
                'display_order' => 3,
                'show_on_homepage' => true,
                'image' => 'assets/sliders/ice-cream.jpg',
                'description' => 'Handcrafted organic sundaes, milkshakes, and scoops made with local dairy.',
            ],
            [
                'name' => 'Coffee',
                'slug' => 'coffee',
                'display_order' => 4,
                'show_on_homepage' => true,
                'image' => 'assets/sliders/coffee.jpg',
                'description' => 'Specialty espresso & cold brew — freshly roasted beans pulled to perfection.',
            ],
        ];

        foreach ($categories as $cat) {
            Category::create(array_merge($cat, [
                'is_active' => true,
            ]));
        }
    }
}
