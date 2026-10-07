<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Testimonial::truncate();
        Schema::enableForeignKeyConstraints();

        $testimonials = [
            ['customer_name' => 'Ayesha Khan', 'customer_location' => 'Sialkot', 'rating' => 5, 'review' => 'The brown sugar boba milk tea is unreal — creamy, not too sweet, and the pearls are perfectly chewy. Happy Tea is my new after-class ritual.'],
            ['customer_name' => 'Bilal Akhtar', 'customer_location' => 'Lahore', 'rating' => 5, 'review' => 'Came for the latte, stayed for the peachy cream pudding tea. Fresh ingredients and consistently great drinks every visit.'],
            ['customer_name' => 'Sara Mahmood', 'customer_location' => 'Sialkot', 'rating' => 5, 'review' => 'The Oreo sundae and fruit teas hit different. Delivery was on time and everything arrived cold and perfect.'],
            ['customer_name' => 'Usman Farooq', 'customer_location' => 'Gujrat', 'rating' => 4, 'review' => 'Great spot for office treats. We ordered a milk tea round for the team and everyone loved it. Will order again.'],
            ['customer_name' => 'Hina Raza', 'customer_location' => 'Sialkot', 'rating' => 5, 'review' => 'Happy Tea made our date night extra special. Beautiful drinks, friendly service, and the jasmine latte is a must-try.'],
            ['customer_name' => 'Kamran Sheikh', 'customer_location' => 'Daska', 'rating' => 5, 'review' => 'Best boba in town. Super Orange Juice and cheese grape boom are my go-tos — fresh, vibrant, and worth every rupee.'],
        ];

        foreach ($testimonials as $index => $t) {
            Testimonial::create(array_merge($t, [
                'is_approved' => true,
                'is_featured' => true,
                'display_order' => $index + 1,
            ]));
        }
    }
}
