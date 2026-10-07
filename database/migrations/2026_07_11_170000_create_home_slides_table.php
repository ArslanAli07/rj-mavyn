<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('title');
            $table->string('title_highlighted')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text')->default('Explore Collection');
            $table->string('button_link')->default('/shop');
            $table->string('left_icon')->default('🍰');
            $table->string('right_badge_text')->default('• 100% Organic Ingredients • Premium Taste');
            $table->string('right_badge_emoji')->default('🍰');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default slides
        \Illuminate\Support\Facades\DB::table('home_slides')->insert([
            [
                'image_path' => 'images/hero_cakes.jpg',
                'title' => 'Crafted with',
                'title_highlighted' => 'Pure Love',
                'description' => 'Exquisite custom cakes, velvety pastries & rich brownies — handcrafted daily with the finest European butter and delivered fresh across Sialkot.',
                'button_text' => 'Explore Collection',
                'button_link' => '/shop',
                'left_icon' => '🍰',
                'right_badge_text' => '• 100% Organic Ingredients • Premium Taste',
                'right_badge_emoji' => '🍰',
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'image_path' => 'images/hero_pastries.jpg',
                'title' => 'French Style',
                'title_highlighted' => 'Fresh Pastries',
                'description' => 'Indulge in our delicate croissants, cupcakes, and tarts, baked with pure butter, high-grade chocolate, and farm eggs.',
                'button_text' => 'Order Pastries',
                'button_link' => '/shop',
                'left_icon' => '🥐',
                'right_badge_text' => '• Freshly Baked Daily • French Recipe',
                'right_badge_emoji' => '🥐',
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'image_path' => 'images/hero_brownies.jpg',
                'title' => 'Indulgent Chocolate',
                'title_highlighted' => 'Fudgy Brownies',
                'description' => 'Try our signature Lotus Biscoff and chocolate fudge brownies, prepared fresh daily with imported cocoa and premium butter.',
                'button_text' => 'Order Brownies',
                'button_link' => '/shop',
                'left_icon' => '🍫',
                'right_badge_text' => '• Belgian Chocolate • Fudgy Texture',
                'right_badge_emoji' => '🍫',
                'display_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_slides');
    }
};
